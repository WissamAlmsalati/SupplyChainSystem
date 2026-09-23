<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\AppUser;
use App\Models\DeliveryZone;
use App\Models\Image;
use App\Models\PremiumFeature;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// A cafe photographs its own door so the driver finds it. Pictures are always a
// list, whatever carries them, and a record with none still answers with the
// default artwork rather than an empty array.
class AddressImagesTest extends TestCase
{
    use RefreshDatabase;

    private AppUser $cafe;

    private Address $address;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        DeliveryZone::create(['hex_id' => '84384b3ffffffff', 'name' => 'طرابلس', 'delivery_price' => 7, 'is_active' => true]);
        PremiumFeature::updateOrCreate(['code' => 'customer_branches'], ['name' => 'b', 'is_active' => true]);
        $this->cafe = AppUser::factory()->customer()->create();
        $this->address = Address::create(['user_id' => $this->cafe->id, 'name' => 'الفرع الرئيسي', 'latitude' => 32.88, 'longitude' => 13.19]);
    }

    private function as(AppUser $user): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken];
    }

    public function test_an_address_with_no_pictures_answers_the_default_artwork_as_a_list(): void
    {
        $images = $this->getJson("/api/v1/customer/addresses/{$this->address->id}", $this->as($this->cafe))
            ->assertOk()->json('images');

        $this->assertCount(1, $images);
        $this->assertNull($images[0]['id']);
        $this->assertSame('svg', $images[0]['type']);
        $this->assertTrue($images[0]['is_primary']);
        $this->assertStringContainsString('/api/v1/placeholder/', $images[0]['url']);
    }

    public function test_a_cafe_uploads_several_pictures_and_they_come_back_ordered(): void
    {
        foreach (['front.jpg', 'door.png', 'sign.jpg'] as $i => $name) {
            $this->postJson("/api/v1/customer/addresses/{$this->address->id}/images",
                ['image' => UploadedFile::fake()->image($name)], $this->as($this->cafe))->assertCreated();
        }

        $images = $this->getJson("/api/v1/customer/addresses/{$this->address->id}", $this->as($this->cafe))
            ->assertOk()->json('images');

        $this->assertCount(3, $images);
        // The first upload stands in as the primary until somebody says otherwise.
        $this->assertTrue($images[0]['is_primary']);
        $this->assertSame([0, 1, 2], array_column($images, 'sort_order'));
        $this->assertSame(['jpg', 'png', 'jpg'], array_column($images, 'type'));
        $this->assertFalse($images[1]['is_primary']);
        foreach ($images as $image) {
            $this->assertNotNull($image['id']);
            Storage::disk('public')->assertExists(str_replace('/storage/', '', $image['url']));
        }
    }

    public function test_marking_a_picture_primary_demotes_the_one_before_it_and_moves_it_first(): void
    {
        foreach (['a.jpg', 'b.jpg'] as $name) {
            $this->postJson("/api/v1/customer/addresses/{$this->address->id}/images",
                ['image' => UploadedFile::fake()->image($name)], $this->as($this->cafe))->assertCreated();
        }
        $second = Image::orderByDesc('id')->first();

        $this->patchJson("/api/v1/customer/addresses/{$this->address->id}/images/{$second->id}",
            ['is_primary' => true], $this->as($this->cafe))->assertOk();

        $images = $this->getJson("/api/v1/customer/addresses/{$this->address->id}", $this->as($this->cafe))->json('images');
        $this->assertSame($second->id, $images[0]['id']);
        $this->assertTrue($images[0]['is_primary']);
        $this->assertFalse($images[1]['is_primary']);
    }

    public function test_deleting_a_picture_removes_the_row_and_the_file(): void
    {
        $id = $this->postJson("/api/v1/customer/addresses/{$this->address->id}/images",
            ['image' => UploadedFile::fake()->image('front.jpg')], $this->as($this->cafe))->assertCreated()->json('data.id');
        $path = Image::findOrFail($id)->path;

        $this->deleteJson("/api/v1/customer/addresses/{$this->address->id}/images/{$id}", [], $this->as($this->cafe))->assertOk();

        $this->assertDatabaseMissing('images', ['id' => $id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_a_cafe_cannot_touch_another_cafes_pictures(): void
    {
        $other = AppUser::factory()->customer()->create();
        $id = $this->postJson("/api/v1/customer/addresses/{$this->address->id}/images",
            ['image' => UploadedFile::fake()->image('front.jpg')], $this->as($this->cafe))->assertCreated()->json('data.id');

        $this->postJson("/api/v1/customer/addresses/{$this->address->id}/images",
            ['image' => UploadedFile::fake()->image('x.jpg')], $this->as($other))->assertNotFound();
        $this->deleteJson("/api/v1/customer/addresses/{$this->address->id}/images/{$id}", [], $this->as($other))->assertNotFound();
        $this->assertDatabaseHas('images', ['id' => $id]);
    }

    public function test_the_office_manages_pictures_of_any_address(): void
    {
        $office = AppUser::factory()->create(['user_type_id' => UserType::where('name', 'super_admin')->value('id')]);

        $id = $this->postJson("/api/v1/addresses/{$this->address->id}/images",
            ['image' => UploadedFile::fake()->image('front.jpg')], $this->as($office))->assertCreated()->json('data.id');

        $this->assertDatabaseHas('images', ['id' => $id, 'imageable_type' => Address::class, 'imageable_id' => $this->address->id]);
        $this->deleteJson("/api/v1/addresses/{$this->address->id}/images/{$id}", [], $this->as($office))->assertOk();
    }

    public function test_only_pictures_are_accepted(): void
    {
        $this->postJson("/api/v1/customer/addresses/{$this->address->id}/images",
            ['image' => UploadedFile::fake()->create('notes.pdf', 40, 'application/pdf')], $this->as($this->cafe))
            ->assertUnprocessable()->assertJsonValidationErrors('image');
    }
}

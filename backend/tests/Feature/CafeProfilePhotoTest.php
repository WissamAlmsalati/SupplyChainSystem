<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Address;
use App\Models\AppUser;
use App\Models\DeliveryZone;
use App\Models\Image;
use App\Models\PremiumFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// The cafe itself has a picture, the same way its branches do. The profile is
// the account's own summary, so it names its addresses rather than carrying
// them whole — the addresses endpoint is where an address is read.
class CafeProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    private AppUser $cafe;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        DeliveryZone::create(['hex_id' => '84384b3ffffffff', 'name' => 'طرابلس', 'delivery_price' => 7, 'is_active' => true]);
        PremiumFeature::updateOrCreate(['code' => 'customer_branches'], ['name' => 'b', 'is_active' => true]);
        $this->cafe = AppUser::factory()->customer()->create();
        $this->cafe->customerProfile()->updateOrCreate([], ['business_name' => 'مقهى الفجر']);
    }

    private function as(AppUser $user): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken];
    }

    public function test_a_cafe_with_no_picture_gets_the_default_artwork_as_a_list(): void
    {
        $images = $this->getJson('/api/v1/customer/profile', $this->as($this->cafe))
            ->assertOk()->json('user.images');

        $this->assertCount(1, $images);
        $this->assertNull($images[0]['id']);
        $this->assertTrue($images[0]['is_primary']);
        $this->assertStringContainsString('/api/v1/placeholder/', $images[0]['url']);
    }

    public function test_a_cafe_uploads_its_own_pictures(): void
    {
        foreach (['shop.jpg', 'counter.png'] as $name) {
            $this->postJson('/api/v1/customer/profile/images',
                ['image' => UploadedFile::fake()->image($name)], $this->as($this->cafe))->assertCreated();
        }

        $images = $this->getJson('/api/v1/customer/profile', $this->as($this->cafe))->json('user.images');

        $this->assertCount(2, $images);
        $this->assertTrue($images[0]['is_primary']);
        $this->assertSame(['jpg', 'png'], array_column($images, 'type'));
    }

    public function test_a_cafe_deletes_its_picture_and_the_file_goes_with_it(): void
    {
        $id = $this->postJson('/api/v1/customer/profile/images',
            ['image' => UploadedFile::fake()->image('shop.jpg')], $this->as($this->cafe))->assertCreated()->json('data.id');
        $path = Image::findOrFail($id)->path;

        $this->deleteJson("/api/v1/customer/profile/images/{$id}", [], $this->as($this->cafe))->assertOk();

        $this->assertDatabaseMissing('images', ['id' => $id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_a_cafe_cannot_delete_another_cafes_picture(): void
    {
        $other = AppUser::factory()->customer()->create();
        $other->customerProfile()->updateOrCreate([], ['business_name' => 'مقهى آخر']);
        $id = $this->postJson('/api/v1/customer/profile/images',
            ['image' => UploadedFile::fake()->image('shop.jpg')], $this->as($this->cafe))->assertCreated()->json('data.id');

        $this->deleteJson("/api/v1/customer/profile/images/{$id}", [], $this->as($other))->assertNotFound();
        $this->assertDatabaseHas('images', ['id' => $id]);
    }

    public function test_a_cafe_edits_its_name_email_and_cafe_name_but_never_its_phone(): void
    {
        $before = $this->cafe->mobile_number;

        $this->patchJson('/api/v1/customer/profile', [
            'name' => 'وسام', 'email' => 'wissam@example.com', 'business_name' => 'مقهى النخيل',
        ], $this->as($this->cafe))->assertOk();

        $this->cafe->refresh();
        $this->assertSame('وسام', $this->cafe->name);
        $this->assertSame('wissam@example.com', $this->cafe->email);
        $this->assertSame('مقهى النخيل', $this->cafe->customerProfile->business_name);

        // Sending the phone is refused outright rather than quietly dropped:
        // a client that thinks it changed the number would be lied to.
        $this->patchJson('/api/v1/customer/profile', ['mobile_number' => '0910009999'], $this->as($this->cafe))
            ->assertUnprocessable()->assertJsonValidationErrors('mobile_number');
        $this->assertSame($before, $this->cafe->refresh()->mobile_number);
    }

    public function test_renaming_the_cafe_is_written_to_the_dashboard_log_with_the_old_name(): void
    {
        $this->patchJson('/api/v1/customer/profile', ['business_name' => 'مقهى النخيل'], $this->as($this->cafe))->assertOk();

        $log = ActivityLog::where('entity_type', 'CustomerProfile')->where('action', 'updated')->latest('id')->firstOrFail();

        $this->assertSame($this->cafe->id, $log->user_id);
        $this->assertSame('مقهى النخيل', $log->metadata['changes']['business_name']);
        $this->assertSame('مقهى الفجر', $log->metadata['before']['business_name']);
    }

    public function test_the_profile_names_its_addresses_rather_than_carrying_them_whole(): void
    {
        $first = Address::create(['user_id' => $this->cafe->id, 'name' => 'الفرع الرئيسي', 'latitude' => 32.88, 'longitude' => 13.19]);
        $second = Address::create(['user_id' => $this->cafe->id, 'name' => 'فرع ثان', 'latitude' => 32.88, 'longitude' => 13.19]);

        $body = $this->getJson('/api/v1/customer/profile', $this->as($this->cafe))->assertOk()->json();

        $this->assertSame([$first->id, $second->id], $body['addresses']);
        $this->assertSame(2, $body['user']['addresses_count']);
        $this->assertTrue($body['user']['has_addresses']);
    }
}

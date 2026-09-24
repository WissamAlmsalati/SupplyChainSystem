<?php

namespace Tests\Feature;

use App\Models\AppUser;
use App\Models\Promo;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// A banner points either out of the platform or into it, never by a string the
// client has to take apart: `link` is an external URL, and a destination inside
// the apps is named by deeplink_entity with the id it needs.
class PromoDeeplinkTest extends TestCase
{
    use RefreshDatabase;

    private function as(AppUser $user): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken];
    }

    private function office(): AppUser
    {
        return AppUser::factory()->create(['user_type_id' => UserType::where('name', 'super_admin')->value('id')]);
    }

    public function test_the_app_is_told_the_destination_rather_than_being_made_to_parse_a_path(): void
    {
        Promo::create(['description' => 'منتج', 'deeplink_entity' => 'product', 'deeplink_entity_id' => 123, 'is_active' => true]);
        Promo::create(['description' => 'قائمة', 'deeplink_entity' => 'products', 'is_active' => true]);
        Promo::create(['description' => 'تصنيف', 'deeplink_entity' => 'category', 'deeplink_entity_id' => 7, 'is_active' => true]);
        Promo::create(['description' => 'خارجي', 'link' => 'https://facebook.com/alsahel', 'is_active' => true]);
        Promo::create(['description' => 'صورة فقط', 'is_active' => true]);

        $rows = collect($this->getJson('/api/v1/customer/promos', $this->as(AppUser::factory()->customer()->create()))
            ->assertOk()->json('data'))->keyBy('description');

        $this->assertSame('product', $rows['منتج']['deeplink_entity']);
        $this->assertSame(123, $rows['منتج']['deeplink_entity_id']);
        $this->assertNull($rows['منتج']['link']);

        $this->assertSame('products', $rows['قائمة']['deeplink_entity']);
        $this->assertNull($rows['قائمة']['deeplink_entity_id']);

        $this->assertSame('category', $rows['تصنيف']['deeplink_entity']);
        $this->assertSame(7, $rows['تصنيف']['deeplink_entity_id']);

        // Out of the platform: a full URL, and no destination inside the app.
        $this->assertSame('https://facebook.com/alsahel', $rows['خارجي']['link']);
        $this->assertNull($rows['خارجي']['deeplink_entity']);

        $this->assertNull($rows['صورة فقط']['link']);
        $this->assertNull($rows['صورة فقط']['deeplink_entity']);

        // One picture field, holding the URL to draw — not the storage path.
        $this->assertArrayNotHasKey('image_url', $rows['منتج']);
        $this->assertSame('/api/v1/placeholder/promo.svg', $rows['منتج']['image']);

        // Every banner carries the same keys in the same order, whatever it
        // points at, so a client never has to check whether one is there.
        $expected = ['id', 'image', 'image_type', 'image_is_placeholder', 'description', 'show_description', 'is_active', 'link', 'deeplink_entity', 'deeplink_entity_id'];
        foreach ($rows as $row) {
            $this->assertSame($expected, array_keys($row));
        }
    }

    public function test_the_office_may_only_name_a_destination_the_apps_know(): void
    {
        $office = $this->office();

        $this->postJson('/api/v1/promos', ['description' => 'x', 'deeplink_entity' => 'wishlist'], $this->as($office))
            ->assertUnprocessable()->assertJsonValidationErrors('deeplink_entity');

        $this->postJson('/api/v1/promos', ['description' => 'x', 'deeplink_entity' => 'category'], $this->as($office))
            ->assertUnprocessable()->assertJsonValidationErrors('deeplink_entity_id');

        // A list needs no id, so sending one is a mistake worth reporting.
        $this->postJson('/api/v1/promos', ['description' => 'x', 'deeplink_entity' => 'cart', 'deeplink_entity_id' => 5], $this->as($office))
            ->assertUnprocessable()->assertJsonValidationErrors('deeplink_entity_id');

        $this->postJson('/api/v1/promos', ['description' => 'x', 'deeplink_entity' => 'category', 'deeplink_entity_id' => 7], $this->as($office))
            ->assertCreated();
    }

    public function test_an_external_link_must_be_a_url_and_cannot_share_a_banner_with_a_destination(): void
    {
        $office = $this->office();

        $this->postJson('/api/v1/promos', ['description' => 'x', 'link' => '/products/12'], $this->as($office))
            ->assertUnprocessable()->assertJsonValidationErrors('link');

        $this->postJson('/api/v1/promos', ['description' => 'x', 'link' => 'https://facebook.com/a', 'deeplink_entity' => 'cart'], $this->as($office))
            ->assertUnprocessable()->assertJsonValidationErrors('link');

        $this->postJson('/api/v1/promos', ['description' => 'x', 'link' => 'https://facebook.com/a'], $this->as($office))->assertCreated();
    }
}

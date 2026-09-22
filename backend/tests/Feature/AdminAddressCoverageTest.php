<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\AppUser;
use App\Models\DeliveryZone;
use App\Models\PremiumFeature;
use App\Models\UserType;
use App\Services\AddressZoneResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// The office is told what a cafe is told: an address no zone reaches is saved,
// and says so, rather than reporting a plain success for a place nobody can
// deliver to. A zone the office set by hand is the way to serve such a place.
class AdminAddressCoverageTest extends TestCase
{
    use RefreshDatabase;

    // 32.88, 13.19 (Tripoli) falls in this res-4 cell. 25.0, 20.0 is desert.
    private const TRIPOLI = '84384b3ffffffff';

    private DeliveryZone $zone;

    private AppUser $office;

    private AppUser $cafe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->zone = DeliveryZone::create(['hex_id' => self::TRIPOLI, 'name' => 'طرابلس', 'delivery_price' => 7, 'is_active' => true]);
        PremiumFeature::updateOrCreate(['code' => 'customer_branches'], ['name' => 'branches', 'is_active' => true]);
        $this->office = AppUser::factory()->create(['user_type_id' => UserType::where('name', 'super_admin')->value('id')]);
        $this->cafe = AppUser::factory()->customer()->create();
    }

    private function headers(): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$this->office->createToken('t')->plainTextToken];
    }

    public function test_an_address_the_office_saves_outside_every_zone_is_flagged_with_a_dialog(): void
    {
        $res = $this->postJson('/api/v1/addresses', [
            'user_id' => $this->cafe->id, 'name' => 'فرع الجنوب', 'latitude' => 25.0, 'longitude' => 20.0,
        ], $this->headers())
            ->assertCreated()
            ->assertJsonPath('is_deliverable', false)
            ->assertJsonPath('title', AddressZoneResolver::OUTSIDE_COVERAGE_TITLE)
            ->assertJsonPath('message', AddressZoneResolver::OUTSIDE_COVERAGE_MESSAGE);

        $this->assertNull(Address::findOrFail($res->json('data.id'))->delivery_zone_id);
    }

    public function test_an_address_inside_a_zone_is_the_ordinary_201(): void
    {
        $res = $this->postJson('/api/v1/addresses', [
            'user_id' => $this->cafe->id, 'name' => 'فرع طرابلس', 'latitude' => 32.88, 'longitude' => 13.19,
        ], $this->headers())->assertCreated();

        $this->assertSame($this->zone->id, Address::findOrFail($res->json('id'))->delivery_zone_id);
    }

    public function test_a_zone_the_office_sets_by_hand_serves_a_point_no_zone_covers(): void
    {
        $res = $this->postJson('/api/v1/addresses', [
            'user_id' => $this->cafe->id, 'name' => 'فرع الجنوب', 'latitude' => 25.0, 'longitude' => 20.0,
            'delivery_zone_id' => $this->zone->id,
        ], $this->headers())->assertCreated();

        $this->assertSame($this->zone->id, Address::findOrFail($res->json('id'))->delivery_zone_id);
    }

    public function test_moving_the_pin_out_of_coverage_flags_it_but_an_edit_that_leaves_it_alone_does_not(): void
    {
        $id = $this->postJson('/api/v1/addresses', [
            'user_id' => $this->cafe->id, 'name' => 'فرع طرابلس', 'latitude' => 32.88, 'longitude' => 13.19,
        ], $this->headers())->assertCreated()->json('id');

        // Renaming says nothing about coverage, because coverage was not looked up.
        $this->patchJson("/api/v1/addresses/{$id}", ['name' => 'الفرع الرئيسي'], $this->headers())->assertOk();

        $this->patchJson("/api/v1/addresses/{$id}", ['latitude' => 25.0, 'longitude' => 20.0], $this->headers())
            ->assertOk()
            ->assertJsonPath('is_deliverable', false);

        $this->patchJson("/api/v1/addresses/{$id}", ['latitude' => 32.88, 'longitude' => 13.19], $this->headers())->assertOk();
    }
}

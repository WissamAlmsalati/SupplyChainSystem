<?php

namespace Tests\Feature;

use App\Models\AppUser;
use App\Models\PremiumFeature;
use App\Models\Warehouse;
use App\Services\H3Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseHexTest extends TestCase
{
    use RefreshDatabase;

    protected function adminToken(): string
    {
        $admin = AppUser::factory()->create([
            'user_type_id' => \App\Models\UserType::firstOrCreate(['name' => 'admin'])->id,
            'is_active' => true,
        ]);

        return $admin->createToken('test')->plainTextToken;
    }

    public function test_warehouse_hex_is_computed_from_lat_lng_resolution(): void
    {
        $token = $this->adminToken();
        PremiumFeature::create(['code' => 'add_inventory', 'name' => 'إضافة مخزون', 'is_active' => true]);
        $res = $this->postJson('/api/v1/warehouses', [
            'name' => 'مستودع طرابلس',
            'city' => 'طرابلس',
            'latitude' => 27.0,
            'longitude' => 17.0,
            'resolution' => 7,
        ], ['Authorization' => "Bearer $token"]);

        $res->assertCreated();
        $this->assertNotEmpty($res->json('hex_id'));
    }

    public function test_expand_hex_creates_child_delivery_zones(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'مستودع طرابلس',
            'city' => 'طرابلس',
            'hex_id' => H3Service::latLngToCell(27.0, 17.0, 7),
            'resolution' => 7,
        ]);

        $token = $this->adminToken();
        $res = $this->postJson("/api/v1/warehouses/{$warehouse->id}/expand-hex", [
            'child_resolution' => 8,
            'default_price' => 5,
        ], ['Authorization' => "Bearer $token"]);

        $res->assertCreated();
        $this->assertCount(7, $res->json('data'));
        $this->assertDatabaseHas('delivery_zones', [
            'warehouse_id' => $warehouse->id,
            'delivery_price' => 5,
        ]);
    }
}

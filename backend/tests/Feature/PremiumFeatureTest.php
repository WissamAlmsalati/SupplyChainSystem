<?php

namespace Tests\Feature;

use App\Models\AppUser;
use App\Models\PremiumFeature;
use App\Models\ProductVariant;
use App\Models\UserType;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PremiumFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function adminToken(): string
    {
        $adminType = UserType::firstOrCreate(['name' => 'admin']);

        $admin = AppUser::factory()->create([
            'user_type_id' => $adminType->id,
            'is_active' => true,
        ]);

        return $admin->createToken('test')->plainTextToken;
    }

    // The admin screen toggles features, so the list includes inactive ones.
    public function test_lists_all_premium_features(): void
    {
        PremiumFeature::create(['code' => 'add_inventory', 'name' => 'إضافة مخزون', 'is_active' => true]);
        PremiumFeature::create(['code' => 'add_role', 'name' => 'إضافة دور', 'is_active' => false]);

        $token = $this->adminToken();
        $res = $this->getJson('/api/v1/premium-features', ['Authorization' => "Bearer $token"]);

        $res->assertOk();
        $this->assertEqualsCanonicalizing(['add_inventory', 'add_role'], array_column($res->json(), 'code'));
    }

    // add_inventory gates creating warehouses (see WarehouseController::store).
    public function test_cannot_create_warehouse_when_feature_inactive(): void
    {
        PremiumFeature::create(['code' => 'add_inventory', 'name' => 'إضافة مخزون', 'is_active' => false]);

        $token = $this->adminToken();
        $res = $this->postJson('/api/v1/warehouses', [
            'name' => 'مستودع جديد',
        ], ['Authorization' => "Bearer $token"]);

        $res->assertForbidden();
        $this->assertDatabaseCount('warehouses', 0);
    }

    public function test_can_add_inventory_stock(): void
    {
        $warehouse = Warehouse::factory()->create();
        $variant = ProductVariant::factory()->create();

        $token = $this->adminToken();
        $res = $this->postJson('/api/v1/inventory', [
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $variant->id,
            'quantity' => 10,
        ], ['Authorization' => "Bearer $token"]);

        $res->assertCreated();
        $this->assertDatabaseHas('stock_movements', [
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $variant->id,
            'quantity_change' => 10,
            'type' => 'adjustment',
        ]);
    }

    public function test_cannot_create_role_when_feature_inactive(): void
    {
        PremiumFeature::create(['code' => 'add_role', 'name' => 'إضافة دور', 'is_active' => false]);

        $token = $this->adminToken();
        $res = $this->postJson('/api/v1/user-types', [
            'name' => 'New Role',
            'permission_ids' => [],
        ], ['Authorization' => "Bearer $token"]);

        $res->assertForbidden();
        $res->assertJsonPath('message', 'هذه الميزة غير متوفرة في خطتك');
    }

    public function test_can_create_role_when_feature_active(): void
    {
        PremiumFeature::create(['code' => 'add_role', 'name' => 'إضافة دور', 'is_active' => true]);

        $token = $this->adminToken();
        $res = $this->postJson('/api/v1/user-types', [
            'name' => 'New Role',
            'permission_ids' => [],
        ], ['Authorization' => "Bearer $token"]);

        $res->assertCreated();
    }
}

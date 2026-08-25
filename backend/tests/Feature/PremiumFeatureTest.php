<?php

namespace Tests\Feature;

use App\Models\AppUser;
use App\Models\PremiumFeature;
use App\Models\UserType;
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

    public function test_lists_only_active_premium_features(): void
    {
        PremiumFeature::create(['code' => 'add_inventory', 'name' => 'إضافة مخزون', 'is_active' => true]);
        PremiumFeature::create(['code' => 'add_role', 'name' => 'إضافة دور', 'is_active' => false]);

        $token = $this->adminToken();
        $res = $this->getJson('/api/v1/premium-features', ['Authorization' => "Bearer $token"]);

        $res->assertOk();
        $res->assertJson(['add_inventory']);
        $res->assertJsonMissing(['add_role']);
    }

    public function test_cannot_create_inventory_when_feature_inactive(): void
    {
        PremiumFeature::create(['code' => 'add_inventory', 'name' => 'إضافة مخزون', 'is_active' => false]);

        $warehouse = \App\Models\Warehouse::factory()->create();
        $variant = \App\Models\ProductVariant::factory()->create();

        $token = $this->adminToken();
        $res = $this->postJson('/api/v1/inventory', [
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $variant->id,
            'quantity' => 10,
        ], ['Authorization' => "Bearer $token"]);

        $res->assertForbidden();
        $res->assertJsonPath('message', 'هذه الميزة غير متوفرة في خطتك');
    }

    public function test_can_create_inventory_when_feature_active(): void
    {
        PremiumFeature::create(['code' => 'add_inventory', 'name' => 'إضافة مخزون', 'is_active' => true]);

        $warehouse = \App\Models\Warehouse::factory()->create();
        $variant = \App\Models\ProductVariant::factory()->create();

        $token = $this->adminToken();
        $res = $this->postJson('/api/v1/inventory', [
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $variant->id,
            'quantity' => 10,
        ], ['Authorization' => "Bearer $token"]);

        $res->assertCreated();
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

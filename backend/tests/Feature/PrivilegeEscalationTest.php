<?php

namespace Tests\Feature;

use App\Models\AppUser;
use App\Models\Permission;
use App\Models\PremiumFeature;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Holding USERS_EDIT or USER_TYPES_EDIT must not be a way to become the super admin.
class PrivilegeEscalationTest extends TestCase
{
    use RefreshDatabase;

    private AppUser $clerk;

    private AppUser $super;

    protected function setUp(): void
    {
        parent::setUp();
        $role = UserType::firstOrCreate(['name' => 'office_clerk']);
        $role->permissions()->sync(Permission::whereIn('code', ['USERS_VIEW', 'USERS_CREATE', 'USERS_EDIT', 'USERS_DELETE', 'USER_TYPES_VIEW', 'USER_TYPES_CREATE', 'USER_TYPES_EDIT', 'USER_TYPES_DELETE'])->pluck('id'));
        $this->clerk = AppUser::factory()->create(['user_type_id' => $role->id]);
        $this->super = AppUser::factory()->create(['user_type_id' => UserType::where('name', 'super_admin')->value('id')]);
        PremiumFeature::updateOrCreate(['code' => 'add_role'], ['name' => 'add role', 'is_active' => true]);
    }

    private function as(AppUser $user): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken];
    }

    public function test_only_a_super_admin_manages_super_admins(): void
    {
        $superType = UserType::where('name', 'super_admin')->value('id');
        $new = ['name' => 'x', 'email' => 'x@example.com', 'mobile_number' => '0915550000', 'password' => 'secret-123', 'user_type_id' => $superType, 'is_active' => true];

        $this->postJson('/api/v1/users', $new, $this->as($this->clerk))->assertForbidden();
        $this->patchJson("/api/v1/users/{$this->clerk->id}", ['name' => $this->clerk->name, 'user_type_id' => $superType], $this->as($this->clerk))->assertForbidden();
        $this->patchJson("/api/v1/users/{$this->super->id}", ['name' => 'x', 'user_type_id' => $superType, 'password' => 'hijacked-123'], $this->as($this->clerk))->assertForbidden();
        $this->deleteJson("/api/v1/users/{$this->super->id}", [], $this->as($this->clerk))->assertForbidden();

        $this->postJson('/api/v1/users', $new, $this->as($this->super))->assertCreated();
    }

    public function test_nobody_removes_themselves_or_the_last_super_admin(): void
    {
        $this->deleteJson("/api/v1/users/{$this->super->id}", [], $this->as($this->super))->assertUnprocessable();
        $this->patchJson("/api/v1/users/{$this->super->id}", ['name' => 'x', 'user_type_id' => $this->super->user_type_id, 'is_active' => false], $this->as($this->super))
            ->assertUnprocessable()->assertJsonPath('message', 'لا يمكنك تعطيل حسابك بنفسك');

        $second = AppUser::factory()->create(['user_type_id' => $this->super->user_type_id]);
        $second->update(['is_active' => false]);
        $this->deleteJson("/api/v1/users/{$this->super->id}", [], $this->as(AppUser::factory()->create(['user_type_id' => $this->super->user_type_id, 'is_active' => true])))->assertNoContent();
    }

    public function test_roles_cannot_be_used_to_gain_privileges(): void
    {
        $headers = fn () => $this->as($this->clerk);
        $admin = UserType::where('name', 'admin')->first();
        $foreignCode = Permission::where('code', 'WALLETS_EDIT')->value('id');
        $ownCode = Permission::where('code', 'USERS_VIEW')->value('id');

        // Built-in roles, the clerk's own role, and codes the clerk does not hold.
        $this->patchJson("/api/v1/user-types/{$admin->id}", ['name' => 'admin', 'permission_ids' => []], $headers())->assertForbidden();
        $this->patchJson("/api/v1/user-types/{$this->clerk->user_type_id}", ['name' => 'office_clerk', 'permission_ids' => [$ownCode]], $headers())->assertForbidden();
        $this->postJson('/api/v1/user-types', ['name' => 'helper', 'permission_ids' => [$foreignCode]], $headers())->assertForbidden();
        $this->postJson('/api/v1/user-types', ['name' => 'helper', 'permission_ids' => [$ownCode]], $headers())->assertCreated();

        // Nobody renames or deletes what the code refers to by name.
        $this->patchJson("/api/v1/user-types/{$admin->id}", ['name' => 'boss'], $this->as($this->super))->assertUnprocessable();
        $this->deleteJson("/api/v1/user-types/{$admin->id}", [], $this->as($this->super))->assertUnprocessable();
        $this->deleteJson("/api/v1/user-types/{$this->clerk->user_type_id}", [], $this->as($this->super))->assertUnprocessable();
    }
}

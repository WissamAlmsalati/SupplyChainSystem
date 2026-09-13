<?php

namespace Tests\Feature;

use App\Models\AppUser;
use App\Models\Permission;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelegateEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected AppUser $adminUser;
    protected UserType $delegateType;

    protected function setUp(): void
    {
        parent::setUp();

        $adminType = UserType::create(['name' => 'admin']);
        $this->delegateType = UserType::create(['name' => 'delegate']);
        UserType::create(['name' => 'super_admin']);
        UserType::create(['name' => 'cafe']);

        $codes = ['DELEGATES_VIEW', 'DELEGATES_CREATE', 'DELEGATES_EDIT', 'DELEGATES_DELETE'];
        $permissions = collect($codes)->map(fn ($code) => Permission::create(['code' => $code]));
        $adminType->permissions()->sync($permissions->pluck('id'));

        $this->adminUser = AppUser::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'mobile_number' => '0922222222',
            'password' => bcrypt('password'),
            'user_type_id' => $adminType->id,
            'is_active' => true,
        ]);
    }

    protected function token(): string
    {
        $res = $this->postJson('/api/v1/login', [
            'email' => 'admin@test.com',
            'password' => 'password',
        ]);

        $res->assertOk();

        return $res->json('token');
    }

    public function test_admin_can_list_delegates(): void
    {
        AppUser::create([
            'name' => 'Delegate One',
            'email' => 'delegate1@test.com',
            'mobile_number' => '0933333333',
            'password' => bcrypt('password'),
            'user_type_id' => $this->delegateType->id,
            'is_active' => true,
        ]);

        $token = $this->token();
        $res = $this->getJson('/api/v1/delegates', ['Authorization' => "Bearer $token"]);

        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
    }

    public function test_admin_can_create_delegate(): void
    {
        $token = $this->token();
        $res = $this->postJson('/api/v1/delegates', [
            'name' => 'New Delegate',
            'email' => 'new.delegate@test.com',
            'mobile_number' => '0944444444',
            'password' => 'password',
            'is_active' => true,
        ], ['Authorization' => "Bearer $token"]);

        $res->assertCreated();
        $this->assertDatabaseHas('users', [
            'email' => 'new.delegate@test.com',
            'user_type_id' => $this->delegateType->id,
        ]);
        $this->assertDatabaseHas('delegate_profiles', ['user_id' => $res->json('data.id')]);
    }

    public function test_admin_can_update_delegate(): void
    {
        $delegate = AppUser::create([
            'name' => 'Delegate Old',
            'email' => 'delegate.old@test.com',
            'password' => bcrypt('password'),
            'user_type_id' => $this->delegateType->id,
            'is_active' => true,
        ]);

        $token = $this->token();
        $res = $this->putJson('/api/v1/delegates/' . $delegate->id, [
            'name' => 'Delegate Updated',
            'email' => 'delegate.updated@test.com',
            'is_active' => false,
        ], ['Authorization' => "Bearer $token"]);

        $res->assertOk();
        $this->assertDatabaseHas('users', [
            'id' => $delegate->id,
            'name' => 'Delegate Updated',
            'is_active' => false,
        ]);
    }

    public function test_admin_can_delete_delegate(): void
    {
        $delegate = AppUser::create([
            'name' => 'Delegate To Delete',
            'email' => 'delegate.delete@test.com',
            'password' => bcrypt('password'),
            'user_type_id' => $this->delegateType->id,
            'is_active' => true,
        ]);

        $token = $this->token();
        $res = $this->deleteJson('/api/v1/delegates/' . $delegate->id, [], ['Authorization' => "Bearer $token"]);

        $res->assertNoContent();
        $this->assertSoftDeleted('users', ['id' => $delegate->id]);
    }

    public function test_admin_can_toggle_delegate_active_status(): void
    {
        $delegate = AppUser::create([
            'name' => 'Delegate Toggle',
            'email' => 'delegate.toggle@test.com',
            'password' => bcrypt('password'),
            'user_type_id' => $this->delegateType->id,
            'is_active' => true,
        ]);

        $token = $this->token();
        $res = $this->postJson('/api/v1/delegates/' . $delegate->id . '/toggle-active', [], ['Authorization' => "Bearer $token"]);

        $res->assertOk();
        $this->assertDatabaseHas('users', [
            'id' => $delegate->id,
            'is_active' => false,
        ]);
    }
}

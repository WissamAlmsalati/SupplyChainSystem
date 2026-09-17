<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Models\AppUser;
use App\Models\Permission;
use App\Models\UserType;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

// CheckPermission is fail-closed, so every guarded route must resolve to a
// code that PermissionSeeder creates. Add the route's module to the seeder
// (or an entry to CheckPermission::ROUTE_MAP) when this test names a route.
class PermissionCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_guarded_route_maps_to_a_seeded_permission(): void
    {
        $this->seed(PermissionSeeder::class);
        $seeded = Permission::pluck('code');
        $missing = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if (! $name || ! str_starts_with($route->uri(), 'api/v1') || ! in_array('permission', $route->gatherMiddleware(), true)) {
                continue;
            }
            $code = CheckPermission::codeForRoute($name);
            if ($code !== null && ! $seeded->contains($code)) {
                $missing[$name] = $code;
            }
        }

        $this->assertSame([], $missing, 'Guarded routes without a seeded permission code');
    }

    private function clerk(): array
    {
        $type = UserType::firstOrCreate(['name' => 'clerk']);
        $user = AppUser::factory()->create(['user_type_id' => $type->id]);

        return [$type, ['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken]];
    }

    public function test_a_role_is_refused_until_it_holds_the_code(): void
    {
        [$type, $headers] = $this->clerk();

        $this->getJson('/api/v1/carts', $headers)->assertForbidden();

        $type->permissions()->attach(Permission::firstOrCreate(['code' => 'CARTS_VIEW']));
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/carts', $headers)->assertOk();
    }

    public function test_custom_actions_resolve_to_their_own_codes(): void
    {
        $this->assertSame('ORDER_ASSIGN', CheckPermission::codeForRoute('orders.assign-delegate'));
        $this->assertSame('DASHBOARD_VIEW', CheckPermission::codeForRoute('dashboard.monthly'));
        $this->assertSame('WAREHOUSES_EDIT', CheckPermission::codeForRoute('warehouses.expand-hex'));
        $this->assertSame('CUSTOMER_BRANCHES_VIEW', CheckPermission::codeForRoute('addresses.index'));
        // An action nobody mapped still produces a code, which nobody seeded: refused.
        $this->assertSame('ORDERS_EXPORT', CheckPermission::codeForRoute('orders.export'));
    }

    public function test_user_owned_and_app_routes_need_no_module_permission(): void
    {
        [, $headers] = $this->clerk();

        $this->getJson('/api/v1/notifications', $headers)->assertOk();
        $this->assertNull(CheckPermission::codeForRoute('customer.orders.index'));
        $this->assertNull(CheckPermission::codeForRoute('delegate.location'));
        $this->assertNull(CheckPermission::codeForRoute('notifications.mark-all-read'));
    }
}

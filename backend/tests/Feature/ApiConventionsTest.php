<?php

namespace Tests\Feature;

use App\Models\AppUser;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

// Partial updates are PATCH, and PUT keeps working for clients already out there.
class ApiConventionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_update_route_accepts_patch(): void
    {
        $putOnly = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1')) {
                continue;
            }
            $methods = $route->methods();
            if (in_array('PUT', $methods, true) && ! in_array('PATCH', $methods, true)) {
                $putOnly[] = $route->uri();
            }
        }

        $this->assertSame([], $putOnly, 'These routes take PUT but not PATCH');
    }

    public function test_both_methods_reach_the_same_action(): void
    {
        $user = AppUser::factory()->admin()->create();
        $headers = fn () => ['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken];

        $first = Notification::create(['user_id' => $user->id, 'title' => 'أ', 'type' => 'info']);
        $second = Notification::create(['user_id' => $user->id, 'title' => 'ب', 'type' => 'info']);

        $this->patchJson("/api/v1/notifications/{$first->id}/read", [], $headers())->assertOk();
        $this->app['auth']->forgetGuards();
        $this->putJson("/api/v1/notifications/{$second->id}/read", [], $headers())->assertOk();

        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNotNull($second->fresh()->read_at);
    }
}

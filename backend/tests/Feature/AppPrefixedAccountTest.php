<?php

namespace Tests\Feature;

use App\Models\AppUser;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Each app reaches "who am I" and its notifications under its own prefix.
class AppPrefixedAccountTest extends TestCase
{
    use RefreshDatabase;

    private function headers(AppUser $user): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken];
    }

    public function test_each_app_has_me_and_logout_under_its_prefix(): void
    {
        foreach (['customer' => AppUser::factory()->customer()->create(), 'delegate' => AppUser::factory()->delegate()->create()] as $prefix => $user) {
            $headers = $this->headers($user);

            $this->getJson("/api/v1/{$prefix}/me", $headers)->assertOk()->assertJsonPath('id', $user->id);
            $this->postJson("/api/v1/{$prefix}/logout", [], $headers)->assertOk();

            $this->app['auth']->forgetGuards();
            $this->getJson("/api/v1/{$prefix}/me", $headers)->assertUnauthorized();
        }
    }

    public function test_notifications_under_the_prefix_are_the_users_own(): void
    {
        $customer = AppUser::factory()->customer()->create();
        $other = AppUser::factory()->customer()->create();
        Notification::sendTo([$customer->id], 'طلبك في الطريق', 'المندوب خرج بطلبك', '/orders/1', 'order');
        Notification::sendTo([$other->id], 'ليس لك');
        $mine = Notification::where('user_id', $customer->id)->first();
        $theirs = Notification::where('user_id', $other->id)->first();
        $headers = $this->headers($customer);

        $this->getJson('/api/v1/customer/notifications', $headers)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'طلبك في الطريق');
        $this->getJson('/api/v1/customer/notifications/unread-count', $headers)->assertOk()->assertJsonPath('count', 1);
        $this->getJson("/api/v1/customer/notifications/{$theirs->id}", $headers)->assertForbidden();
        $this->patchJson("/api/v1/customer/notifications/{$theirs->id}/read", [], $headers)->assertForbidden();
        $this->deleteJson("/api/v1/customer/notifications/{$theirs->id}", [], $headers)->assertForbidden();

        $this->patchJson("/api/v1/customer/notifications/{$mine->id}/read", [], $headers)->assertOk();
        $this->getJson('/api/v1/customer/notifications/unread-count', $headers)->assertJsonPath('count', 0);
        $this->patchJson('/api/v1/customer/notifications/mark-all-read', [], $headers)->assertOk();
        $this->deleteJson("/api/v1/customer/notifications/{$mine->id}", [], $headers)->assertNoContent();
    }

    public function test_the_flat_paths_still_answer_for_clients_already_deployed(): void
    {
        $headers = $this->headers(AppUser::factory()->customer()->create());

        $this->getJson('/api/v1/me', $headers)->assertOk();
        $this->getJson('/api/v1/notifications', $headers)->assertOk();
    }
}

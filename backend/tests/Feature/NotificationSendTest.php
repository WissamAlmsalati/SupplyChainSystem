<?php

namespace Tests\Feature;

use App\Models\AppUser;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationSendTest extends TestCase
{
    use RefreshDatabase;

    private function as(AppUser $user): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken];
    }

    public function test_admin_sends_an_announcement_to_every_active_customer(): void
    {
        $admin = AppUser::factory()->admin()->create();
        $customers = AppUser::factory()->customer()->count(3)->create();
        AppUser::factory()->customer()->create(['is_active' => false]);
        AppUser::factory()->delegate()->create();

        $this->postJson('/api/v1/notifications/send', [
            'audience' => 'customers', 'title' => 'عرض نهاية الأسبوع', 'message' => 'خصم 10% على البن', 'link' => '/products',
        ], $this->as($admin))->assertCreated()->assertJsonPath('data.sent', 3);

        $this->assertSame(3, Notification::where('type', 'announcement')->count());
        $this->assertDatabaseHas('notifications', ['user_id' => $customers[0]->id, 'title' => 'عرض نهاية الأسبوع', 'link' => '/products']);

        $this->getJson('/api/v1/notifications', $this->as($customers[1]))->assertOk()->assertJsonPath('data.0.title', 'عرض نهاية الأسبوع');

        $this->getJson('/api/v1/notifications/sent', $this->as($admin))->assertOk()
            ->assertJsonPath('data.0.recipients', 3)->assertJsonPath('data.0.read', 0);
    }

    public function test_specific_users_and_validation(): void
    {
        $admin = AppUser::factory()->admin()->create();
        $a = AppUser::factory()->customer()->create();
        $b = AppUser::factory()->delegate()->create();

        $this->postJson('/api/v1/notifications/send', ['audience' => 'users', 'user_ids' => [$a->id, $b->id, $a->id], 'title' => 'تنبيه'], $this->as($admin))
            ->assertCreated()->assertJsonPath('data.sent', 2);
        $this->postJson('/api/v1/notifications/send', ['audience' => 'users', 'title' => 'تنبيه'], $this->as($admin))->assertUnprocessable();
        $this->postJson('/api/v1/notifications/send', ['audience' => 'everyone', 'title' => 'تنبيه'], $this->as($admin))->assertUnprocessable();
        $this->postJson('/api/v1/notifications/send', ['audience' => 'delegates', 'title' => ''], $this->as($admin))->assertUnprocessable();
    }

    public function test_sending_needs_its_own_permission_and_customers_cannot_send(): void
    {
        $customer = AppUser::factory()->customer()->create();
        $this->postJson('/api/v1/notifications/send', ['audience' => 'all', 'title' => 'x'], $this->as($customer))->assertForbidden();

        $type = UserType::create(['name' => 'clerk']);
        $clerk = AppUser::factory()->create(['user_type_id' => $type->id]);
        $this->postJson('/api/v1/notifications/send', ['audience' => 'all', 'title' => 'x'], $this->as($clerk))->assertForbidden();
        $this->getJson('/api/v1/notifications', $this->as($clerk))->assertOk();

        $type->permissions()->attach(Permission::firstOrCreate(['code' => 'NOTIFICATIONS_SEND']));
        $this->postJson('/api/v1/notifications/send', ['audience' => 'all', 'title' => 'x'], $this->as($clerk))->assertCreated();
    }
}

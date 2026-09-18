<?php

namespace Tests\Feature;

use App\Models\AppUser;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// A phone number is unique within a user type, so each app signs in at its own
// route and the route decides which type to look in.
class ScopedLoginTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '0910000777';

    private function customer(): AppUser
    {
        return AppUser::factory()->customer()->create(['mobile_number' => self::PHONE, 'password' => Hash::make('customer-pass')]);
    }

    private function delegate(): AppUser
    {
        return AppUser::factory()->delegate()->create(['mobile_number' => self::PHONE, 'password' => Hash::make('delegate-pass')]);
    }

    public function test_the_same_number_can_be_a_customer_and_a_delegate(): void
    {
        $customer = $this->customer();
        $delegate = $this->delegate();

        $this->assertNotSame($customer->id, $delegate->id);
        $this->assertSame(2, AppUser::where('mobile_number', self::PHONE)->count());
    }

    public function test_each_door_finds_its_own_account(): void
    {
        $customer = $this->customer();
        $delegate = $this->delegate();

        $this->postJson('/api/v1/customer/login', ['phone_number' => self::PHONE, 'password' => 'customer-pass'])
            ->assertOk()->assertJsonStructure(['token']);
        $this->assertSame($customer->id, $customer->fresh()->tokens()->count() ? $customer->id : null);

        $this->postJson('/api/v1/delegate/login', ['phone_number' => self::PHONE, 'password' => 'delegate-pass'])
            ->assertOk()->assertJsonStructure(['token']);
        $this->assertSame(1, $delegate->fresh()->tokens()->count());
    }

    public function test_a_delegate_cannot_sign_in_to_the_customer_app(): void
    {
        $this->delegate();

        // Right credentials, wrong door: refused, and the wording gives nothing away.
        $this->postJson('/api/v1/customer/login', ['phone_number' => self::PHONE, 'password' => 'delegate-pass'])
            ->assertUnauthorized()->assertJsonPath('message', 'بيانات الدخول غير صحيحة');

        $this->postJson('/api/v1/delegate/login', ['phone_number' => self::PHONE, 'password' => 'delegate-pass'])->assertOk();
    }

    public function test_a_customer_cannot_sign_in_to_the_delegate_app(): void
    {
        $this->customer();

        $this->postJson('/api/v1/delegate/login', ['phone_number' => self::PHONE, 'password' => 'customer-pass'])
            ->assertUnauthorized();
    }

    public function test_the_wrong_password_is_refused_at_the_right_door(): void
    {
        $this->customer();

        $this->postJson('/api/v1/customer/login', ['phone_number' => self::PHONE, 'password' => 'delegate-pass'])
            ->assertUnauthorized();
    }

    public function test_staff_sign_in_at_their_own_door_with_email(): void
    {
        AppUser::factory()->admin()->create(['email' => 'boss@example.com', 'password' => Hash::make('secret123')]);

        $this->postJson('/api/v1/admin/login', ['email' => 'boss@example.com', 'password' => 'secret123'])
            ->assertOk()->assertJsonStructure(['token', 'permissions']);

        // The unscoped route still works for clients not yet moved over.
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/login', ['email' => 'boss@example.com', 'password' => 'secret123'])->assertOk();
    }

    public function test_a_customer_cannot_sign_in_at_the_admin_door(): void
    {
        $this->customer();
        AppUser::factory()->admin()->create(['email' => 'boss@example.com', 'password' => Hash::make('secret123')]);

        $this->postJson('/api/v1/admin/login', ['phone_number' => self::PHONE, 'password' => 'customer-pass'])
            ->assertUnauthorized();
    }

    public function test_a_number_cannot_repeat_within_one_type(): void
    {
        $this->customer();

        $this->expectException(UniqueConstraintViolationException::class);
        AppUser::factory()->customer()->create(['mobile_number' => self::PHONE]);
    }
}

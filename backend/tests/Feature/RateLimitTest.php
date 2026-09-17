<?php

namespace Tests\Feature;

use App\Models\AppUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_guessing_on_one_account_is_throttled(): void
    {
        AppUser::factory()->admin()->create(['email' => 'target@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->assertNotSame(429, $this->postJson('/api/v1/login', ['email' => 'target@example.com', 'password' => "wrong{$i}"])->status());
        }

        $this->postJson('/api/v1/login', ['email' => 'target@example.com', 'password' => 'wrong'])
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJson(['success' => false, 'message' => 'محاولات كثيرة، حاول مرة أخرى بعد قليل']);
    }

    public function test_otp_verification_is_throttled_per_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->assertNotSame(429, $this->postJson('/api/v1/customer/verify-otp', ['token' => "t{$i}", 'otp' => '000000'])->status());
        }

        $this->postJson('/api/v1/customer/verify-otp', ['token' => 't9', 'otp' => '000000'])->assertStatus(429);
    }
}

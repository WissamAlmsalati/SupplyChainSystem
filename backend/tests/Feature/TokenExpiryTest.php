<?php

namespace Tests\Feature;

use App\Models\AppUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_tokens_expire(): void
    {
        $minutes = (int) config('sanctum.expiration');
        $this->assertGreaterThan(0, $minutes, 'Bearer tokens must not live forever');

        $headers = ['Authorization' => 'Bearer '.AppUser::factory()->admin()->create()->createToken('t')->plainTextToken];

        $this->getJson('/api/v1/me', $headers)->assertOk();

        $this->travel($minutes + 1)->minutes();
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/me', $headers)->assertUnauthorized();
    }
}

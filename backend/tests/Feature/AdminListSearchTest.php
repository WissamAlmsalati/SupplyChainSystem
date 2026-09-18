<?php

namespace Tests\Feature;

use App\Models\AppUser;
use App\Models\Wallet;
use App\Models\WalletTopup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Admin list searches fold Arabic spelling, and the top-up queue reports how
// many rows sit under every status so a rejected request is never hidden by
// the default filter.
class AdminListSearchTest extends TestCase
{
    use RefreshDatabase;

    private AppUser $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = AppUser::factory()->admin()->create();
    }

    private function headers(): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$this->admin->createToken('t')->plainTextToken];
    }

    public function test_custody_search_matches_either_arabic_spelling(): void
    {
        AppUser::factory()->delegate()->create(['name' => 'مندوب مصراتة', 'mobile_number' => '0920000001']);
        AppUser::factory()->delegate()->create(['name' => 'مندوب بنغازي', 'mobile_number' => '0920000002']);

        foreach (['مصراتة', 'مصراته', 'مصراتة  '] as $term) {
            $this->getJson('/api/v1/custody?search='.urlencode($term), $this->headers())
                ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'مندوب مصراتة');
        }

        // ى for ي, and the phone still matches on its own.
        $this->getJson('/api/v1/custody?search='.urlencode('بنغازى'), $this->headers())->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/custody?search=0920000002', $this->headers())->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/custody?search='.urlencode('طرابلس'), $this->headers())->assertOk()->assertJsonPath('meta.total', 0);
    }

    private function topup(AppUser $user, string $status, float $amount = 50): WalletTopup
    {
        $wallet = Wallet::firstOrCreate(['user_id' => $user->id]);

        return WalletTopup::forceCreate([
            'wallet_id' => $wallet->id, 'user_id' => $user->id, 'amount' => $amount,
            'method' => 'bank_transfer', 'status' => $status, 'reference_number' => 'TRX-'.$status,
        ]);
    }

    public function test_top_up_counts_cover_every_status_whatever_is_filtered(): void
    {
        $customer = AppUser::factory()->customer()->create(['name' => 'مقهى الزاوية']);
        $this->topup($customer, 'approved');
        $this->topup($customer, 'approved');
        $this->topup($customer, 'rejected');
        $this->topup($customer, 'cancelled');

        $expected = ['pending' => 0, 'approved' => 2, 'rejected' => 1, 'cancelled' => 1, 'failed' => 0, 'all' => 4];

        // The queue is empty, but the counts still say a rejected request exists.
        $this->getJson('/api/v1/wallet-topups?status=pending', $this->headers())
            ->assertOk()->assertJsonPath('meta.total', 0)->assertJsonPath('meta.status_counts', $expected);

        $this->getJson('/api/v1/wallet-topups?status=rejected', $this->headers())
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('meta.status_counts', $expected)
            ->assertJsonPath('data.0.status', 'rejected');

        $this->getJson('/api/v1/wallet-topups', $this->headers())->assertOk()->assertJsonPath('meta.total', 4);
    }

    public function test_top_up_counts_follow_the_other_filters_and_arabic_search(): void
    {
        $zawia = AppUser::factory()->customer()->create(['name' => 'مقهى الزاوية']);
        $sabha = AppUser::factory()->customer()->create(['name' => 'مقهى سبها']);
        $this->topup($zawia, 'approved');
        $this->topup($zawia, 'rejected');
        $this->topup($sabha, 'approved');

        $this->getJson('/api/v1/wallet-topups?search='.urlencode('الزاويه'), $this->headers())
            ->assertOk()
            ->assertJsonPath('meta.status_counts.all', 2)
            ->assertJsonPath('meta.status_counts.rejected', 1)
            ->assertJsonPath('meta.status_counts.approved', 1);
    }
}

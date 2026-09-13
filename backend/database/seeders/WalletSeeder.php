<?php

namespace Database\Seeders;

use App\Enums\TopupMethod;
use App\Enums\UserRole;
use App\Models\AppUser;
use App\Services\WalletService;
use Illuminate\Database\Seeder;

// Opening balances through approved top-ups, plus one pending request per method for review screens.
class WalletSeeder extends Seeder
{
    public function run(): void
    {
        $wallets = app(WalletService::class);
        $admin = AppUser::whereHas('userType', fn ($q) => $q->where('name', UserRole::Admin->value))->first();
        $delegate = AppUser::whereHas('userType', fn ($q) => $q->where('name', UserRole::Delegate->value))->first();
        $customers = AppUser::whereHas('userType', fn ($q) => $q->where('name', UserRole::Customer->value))->get();

        foreach ($customers as $i => $customer) {
            $topup = $wallets->requestTopup($customer, fake()->randomElement([500, 800, 1200]), TopupMethod::BankTransfer, [
                'reference_number' => 'TRX-' . fake()->numerify('######'),
            ]);
            $wallets->approveTopup($topup, $admin);

            if ($delegate && $i % 2 === 0) {
                $wallets->collectCash($delegate, $customer, fake()->randomElement([100, 150, 200]), null, 'تحصيل عند التوصيل');
            }
        }

        if ($customers->count() >= 2) {
            $wallets->requestTopup($customers[0], 250, TopupMethod::BankTransfer, ['reference_number' => 'TRX-' . fake()->numerify('######'), 'note' => 'تحويل من مصرف الجمهورية']);
            $wallets->requestTopup($customers[1], 100, TopupMethod::BankTransfer, ['reference_number' => 'TRX-' . fake()->numerify('######')]);
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PaymentGateway;

class PaymentGatewaysSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PaymentGateway::updateOrCreate(
            ['code' => 'kashier'],
            [
                'name' => 'كاشير (Kashier)',
                'type' => 'cards,wallets',
                'is_active' => false,
                'is_configured' => false,
                'credentials' => null,
            ]
        );

        PaymentGateway::updateOrCreate(
            ['code' => 'paymob'],
            [
                'name' => 'باي موب (Paymob)',
                'type' => 'cards,wallets',
                'is_active' => false,
                'is_configured' => false,
                'credentials' => null,
            ]
        );
    }
}

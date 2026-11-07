<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BusinessBankAccountFactory extends Factory
{
    public function definition(): array
    {
        return ['bank_name' => 'Banco de pruebas ATD', 'account_type' => 'savings', 'account_holder' => 'Empresa ficticia ATD',
            'account_number' => '000123456789', 'currency' => 'DOP', 'is_active' => true, 'sort_order' => 0];
    }
}

<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Support\ServiceCatalog;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ServiceCatalog::all() as $index => $service) {
            Service::query()->updateOrCreate(['code' => $service['code']], $service + ['sort_order' => $index, 'is_active' => true]);
        }
        Service::whereIn('code', ServiceCatalog::retiredCodes())->update(['is_active' => false]);
    }
}

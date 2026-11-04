<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Accounting\AccountMappingKey;
use App\Enums\Payments\AppointmentFeeType;
use App\Enums\Payments\ServiceCategory;
use App\Interfaces\Accounting\AccountServiceInterface;
use App\Models\Payments\BillableService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;

/**
 * The price-list items of the appointment fees (config payments.fees.services), posted to Fees revenue.
 * Idempotent: an item the clinic already changed (price, name, archived) is left as it is.
 */
final class FeeServicesSeeder extends Seeder
{
    public function run(AccountServiceInterface $accounts): void
    {
        $revenue = $accounts->resolve(AccountMappingKey::FeeRevenue)->id;
        foreach (AppointmentFeeType::cases() as $type) {
            /** @var array{code: string, name: string, price: string} $service */
            $service = Config::array('payments.fees.services.'.$type->value);
            BillableService::query()->withDeleted()->firstOrCreate(['code' => $service['code']], [
                'name' => $service['name'],
                'category' => ServiceCategory::Other,
                'price' => $service['price'],
                'tax_rate' => '0',
                'revenue_account_id' => $revenue,
            ]);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Currency;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Models\User;
use App\Space\InstallUtils;
use Illuminate\Database\Seeder;
use Silber\Bouncer\BouncerFacade;

class AlbertineDentalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Sets up Albertine Dental Surgery with:
     * - User: Abel Wembabazi (admin access)
     * - Company: Albertine Dental Surgery
     * - Address: Plot No. 4, Lusaka Middle Cell, Bishop Rwakaikara Road, Hoima
     * - Phone: 0769969282
     * - Country: Uganda
     * - Currency: Uganda Shillings (UGX)
     */
    public function run(): void
    {
        // Get or create UGX currency
        $currency = Currency::where('code', 'UGX')->first();
        if (!$currency) {
            $currency = Currency::create([
                'name' => 'Ugandan Shilling',
                'code' => 'UGX',
                'symbol' => 'UGX ',
                'precision' => '0',
                'thousand_separator' => ',',
                'decimal_separator' => '.',
            ]);
        }

        // Update or create user
        $user = User::first();
        $user->update([
            'email' => 'abel@albertinedental.com',
            'name' => 'Abel Wembabazi',
            'role' => 'super admin',
            'phone' => '0769969282',
        ]);

        // Set user settings
        $user->setSettings([
            'language' => 'en',
            'timezone' => 'Africa/Kampala',
            'date_format' => 'DD-MM-YYYY',
            'currency_id' => $currency->id,
        ]);

        // Update or create company
        $company = Company::first();
        $company->update([
            'name' => 'Albertine Dental Surgery',
            'slug' => 'albertine-dental-surgery',
        ]);

        // Update company address
        $address = $company->address;
        if ($address) {
            $address->update([
                'address_street_1' => 'Plot No. 4, Lusaka Middle Cell',
                'address_street_2' => 'Bishop Rwakaikara Road',
                'city' => 'Hoima',
                'country_id' => 227, // Uganda
                'phone' => '0769969282',
            ]);
        } else {
            Address::create([
                'address_street_1' => 'Plot No. 4, Lusaka Middle Cell',
                'address_street_2' => 'Bishop Rwakaikara Road',
                'city' => 'Hoima',
                'country_id' => 227, // Uganda
                'phone' => '0769969282',
                'company_id' => $company->id,
            ]);
        }

        // Set company settings
        CompanySetting::setSettings([
            'currency' => $currency->id,
            'date_format' => 'DD-MM-YYYY',
            'language' => 'en',
            'timezone' => 'Africa/Kampala',
            'fiscal_year' => 'calendar_year',
            'tax_per_item' => false,
            'discount_per_item' => false,
            'invoice_prefix' => 'ADS-INV-',
            'estimate_prefix' => 'ADS-QT-',
            'payment_prefix' => 'ADS-PAY-',
        ], $company->id);

        // Create dental payment methods
        $paymentMethods = [
            [
                'name' => 'Cash',
                'driver' => 'cash',
            ],
            [
                'name' => 'MTN MoMo 0769969282',
                'driver' => 'mobile_money',
            ],
            [
                'name' => 'Bank Transfer',
                'driver' => 'bank_transfer',
            ],
        ];

        foreach ($paymentMethods as $method) {
            PaymentMethod::firstOrCreate(
                [
                    'name' => $method['name'],
                    'company_id' => $company->id,
                ],
                [
                    'name' => $method['name'],
                    'driver' => $method['driver'],
                    'company_id' => $company->id,
                    'type' => PaymentMethod::TYPE_GENERAL,
                    'active' => true,
                ]
            );
        }

        // Ensure profile is complete
        Setting::setSetting('profile_complete', 'COMPLETED');

        // Create installation marker if not exists
        InstallUtils::createDbMarker();

        $this->command->info('Albertine Dental Surgery has been set up successfully!');
        $this->command->info('User: Abel Wembabazi (abel@albertinedental.com)');
        $this->command->info('Company: Albertine Dental Surgery');
    }
}

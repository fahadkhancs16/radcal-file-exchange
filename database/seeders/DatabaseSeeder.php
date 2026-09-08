<?php

namespace Database\Seeders;

use App\Models\Exchange;
use App\Models\ExchangeFile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@radcal.com'],
            [
                'name' => 'Radcal Admin',
                'password' => Hash::make('password'),
                'is_admin' => true,
            ],
        );

        if (! app()->environment('local')) {
            return;
        }

        // A healthy Radcal-initiated exchange with files on both sides.
        $active = Exchange::factory()
            ->password('demo-pass')
            ->for($admin, 'creator')
            ->create(['code' => 'ABC123', 'customer_name' => 'Jane Ruiz', 'company' => 'Northlight Imaging']);
        ExchangeFile::factory()->for($active)->fromRadcal()->named('calibration-report.pdf')->create();
        ExchangeFile::factory()->for($active)->fromRadcal()->named('raw-traces.zip')->create();
        ExchangeFile::factory()->for($active)->named('site-photos.zip')->create();

        // Customer-initiated transfer.
        $sendIn = Exchange::factory()->customerInitiated()->password('demo-pass')->create([
            'code' => 'SENDIN01', 'customer_name' => 'Marco Feld', 'company' => 'Feld Medical Physics',
            'description' => 'Dose-rate discrepancy on our 9010 — traces attached.',
        ]);
        ExchangeFile::factory()->for($sendIn)->count(2)->create();

        Exchange::factory()->expiringSoon()->create(['code' => 'EXPSOON1', 'customer_name' => 'Priya Anand']);
        Exchange::factory()->expired()->create(['code' => 'GONESOON', 'customer_name' => 'Old Job']);
        Exchange::factory()->disabled()->create(['code' => 'HALTED01', 'customer_name' => 'Suspended Co']);
        Exchange::factory()->purged()->create(['code' => 'PURGED01', 'customer_name' => 'Deleted Co']);
    }
}

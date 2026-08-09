<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    private const SETTINGS = [
        ['key' => 'app.name', 'value' => 'PPOB App', 'group' => 'umum', 'type' => 'string', 'label' => 'Nama Aplikasi'],
        ['key' => 'app.support_email', 'value' => 'support@ppob.test', 'group' => 'umum', 'type' => 'string', 'label' => 'Email Dukungan'],
        ['key' => 'app.support_whatsapp', 'value' => '6281234567890', 'group' => 'umum', 'type' => 'string', 'label' => 'WhatsApp Dukungan'],
        ['key' => 'app.maintenance', 'value' => '0', 'group' => 'umum', 'type' => 'bool', 'label' => 'Mode Pemeliharaan'],
        ['key' => 'app.min_version_android', 'value' => '1.0.0', 'group' => 'umum', 'type' => 'string', 'label' => 'Versi Android Minimum'],
        ['key' => 'app.min_version_ios', 'value' => '1.0.0', 'group' => 'umum', 'type' => 'string', 'label' => 'Versi iOS Minimum'],

        ['key' => 'transaction.enabled', 'value' => '1', 'group' => 'transaksi', 'type' => 'bool', 'label' => 'Izinkan Transaksi'],
        ['key' => 'transaction.open_hour', 'value' => '00:00', 'group' => 'transaksi', 'type' => 'string', 'label' => 'Jam Buka Layanan'],
        ['key' => 'transaction.close_hour', 'value' => '23:59', 'group' => 'transaksi', 'type' => 'string', 'label' => 'Jam Tutup Layanan'],

        ['key' => 'deposit.auto_approve', 'value' => '0', 'group' => 'deposit', 'type' => 'bool', 'label' => 'Setujui Deposit Otomatis'],
        ['key' => 'deposit.min_amount', 'value' => '10000', 'group' => 'deposit', 'type' => 'int', 'label' => 'Deposit Minimum'],

        ['key' => 'referral.bonus_amount', 'value' => '5000', 'group' => 'referral', 'type' => 'int', 'label' => 'Bonus Referral'],
        ['key' => 'referral.enabled', 'value' => '1', 'group' => 'referral', 'type' => 'bool', 'label' => 'Aktifkan Program Referral'],
    ];

    public function run(): void
    {
        foreach (self::SETTINGS as $setting) {
            Setting::firstOrCreate(['key' => $setting['key']], $setting);
        }
    }
}

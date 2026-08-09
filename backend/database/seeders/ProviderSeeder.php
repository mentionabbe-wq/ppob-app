<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Provider;
use Illuminate\Database\Seeder;

class ProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            [
                'name' => 'Digiflazz',
                'code' => 'digiflazz',
                'base_url' => config('ppob.providers.digiflazz.base_url'),
                'priority' => 1,
                'is_active' => (bool) config('ppob.providers.digiflazz.enabled'),
                'credentials' => [
                    'username' => config('ppob.providers.digiflazz.username'),
                    'api_key' => config('ppob.providers.digiflazz.api_key'),
                    'webhook_secret' => config('ppob.providers.digiflazz.webhook_secret'),
                ],
            ],
            [
                'name' => 'VIP Reseller',
                'code' => 'vipreseller',
                'base_url' => config('ppob.providers.vipreseller.base_url'),
                'priority' => 2,
                'is_active' => (bool) config('ppob.providers.vipreseller.enabled'),
                'credentials' => [
                    'api_id' => config('ppob.providers.vipreseller.api_id'),
                    'api_key' => config('ppob.providers.vipreseller.api_key'),
                ],
            ],
        ];

        foreach ($providers as $data) {
            $credentials = array_filter($data['credentials'] ?? []);
            unset($data['credentials']);

            $provider = Provider::firstOrNew(['code' => $data['code']]);
            $provider->fill($data);

            // Jangan timpa kredensial yang sudah tersimpan di panel admin
            // dengan nilai .env yang mungkin kosong.
            if ($credentials !== [] && blank($provider->credentials_encrypted)) {
                $provider->setCredentials($credentials);
            }

            $provider->save();
        }
    }
}

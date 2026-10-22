<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Setting::query()
            ->whereIn('key', ['linkedin', 'instagram', 'facebook'])
            ->delete();

        $settings = [
            ['key' => 'instagram_url', 'label' => 'Instagram'],
            ['key' => 'tiktok_url', 'label' => 'TikTok'],
            ['key' => 'facebook_url', 'label' => 'Facebook'],
            ['key' => 'youtube_url', 'label' => 'YouTube'],
            ['key' => 'x_url', 'label' => 'X'],
        ];

        foreach ($settings as $setting) {
            Setting::query()->updateOrCreate(
                ['key' => $setting['key']],
                [
                    'label' => $setting['label'],
                    'value' => '',
                    'group' => 'Redes',
                    'type' => 'text',
                ],
            );
        }
    }

    public function down(): void
    {
        Setting::query()
            ->whereIn('key', ['instagram_url', 'tiktok_url', 'facebook_url', 'youtube_url', 'x_url'])
            ->delete();
    }
};

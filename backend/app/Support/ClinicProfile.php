<?php

namespace App\Support;

use App\Models\WebsiteSetting;
use Throwable;

/**
 * Resolves the clinic identity shown in transactional email footers.
 *
 * Contact details live in the public website settings so the address and phone
 * number only have to be maintained in one place. Email rendering must still
 * work when the database is unavailable (for example during a deploy), so every
 * lookup falls back to configuration instead of failing the message.
 */
class ClinicProfile
{
    /**
     * @return array{name: string, email: string, phone: ?string, address: ?string, website: string}
     */
    public static function contact(): array
    {
        $settings = null;

        try {
            $settings = WebsiteSetting::query()->first();
        } catch (Throwable) {
            // Fall back to configuration when settings cannot be read.
        }

        return [
            'name' => (string) config('app.name', 'RoyalCare Clinic'),
            'email' => (string) ($settings?->email ?: config('mail.from.address')),
            'phone' => $settings?->primary_phone ?: ($settings?->secondary_phone ?: null),
            'address' => $settings?->address ?: ($settings?->address_km ?: null),
            'website' => rtrim((string) config('services.frontend.url', config('app.url')), '/'),
        ];
    }
}

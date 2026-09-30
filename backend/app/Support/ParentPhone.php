<?php

namespace App\Support;

class ParentPhone
{
    /** Teacher-facing rendering of a parent's phone; ZALO_PARENT_PHONE_DISPLAY=masked keeps only the last 3 digits. */
    public static function display(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        if (config('services.zalo.parent_phone_display') !== 'masked') {
            return $phone;
        }

        $len = mb_strlen($phone);

        return $len <= 3 ? $phone : str_repeat('•', $len - 3).mb_substr($phone, -3);
    }
}

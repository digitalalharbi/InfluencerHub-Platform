<?php

namespace App\Domain\Requests\Enums;

use Illuminate\Support\Facades\Lang;

enum ServiceRequestType: string
{
    case Campaign = 'campaign';
    case Content = 'content';
    case Report = 'report';
    case Consultation = 'consultation';
    case Other = 'other';

    public static function values(): array
    {
        return array_map(fn ($c) => $c->value, self::cases());
    }

    public static function labels(): array
    {
        $ar = ['campaign' => 'حملة', 'content' => 'محتوى', 'report' => 'تقرير', 'consultation' => 'استشارة', 'other' => 'أخرى'];
        if (app()->getLocale() === 'ar') {
            return $ar;
        }
        $out = [];
        foreach ($ar as $k => $v) {
            $out[$k] = Lang::has("service_requests.type_{$k}") ? trans("service_requests.type_{$k}") : $v;
        }

        return $out;
    }
}

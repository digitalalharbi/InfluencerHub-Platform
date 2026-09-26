<?php

namespace App\Domain\Requests\Enums;

use Illuminate\Support\Facades\Lang;

enum ServiceRequestPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public static function values(): array
    {
        return array_map(fn ($c) => $c->value, self::cases());
    }

    public static function labels(): array
    {
        $ar = ['low' => 'منخفضة', 'normal' => 'عادية', 'high' => 'عالية', 'urgent' => 'عاجلة'];
        if (app()->getLocale() === 'ar') {
            return $ar;
        }
        $out = [];
        foreach ($ar as $k => $v) {
            $out[$k] = Lang::has("service_requests.prio_{$k}") ? trans("service_requests.prio_{$k}") : $v;
        }

        return $out;
    }

    /** ساعات SLA لكل أولوية. */
    public static function slaHours(string $priority): int
    {
        return ['urgent' => 4, 'high' => 24, 'normal' => 72, 'low' => 168][$priority] ?? 72;
    }
}

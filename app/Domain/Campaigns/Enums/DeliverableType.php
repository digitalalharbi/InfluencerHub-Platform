<?php

namespace App\Domain\Campaigns\Enums;

use Illuminate\Support\Facades\Lang;

enum DeliverableType: string
{
    case Post = 'post';
    case Story = 'story';
    case Reel = 'reel';
    case Video = 'video';
    case Ugc = 'ugc';

    public static function values(): array
    {
        return array_map(fn ($c) => $c->value, self::cases());
    }

    // التسميات لغة-واعية مع رجوع عربيّ مبكّر: القيم (المفاتيح) لا تتغيّر فتبقى قواعد التحقّق in: سليمة.
    public static function labels(): array
    {
        $ar = ['post' => 'منشور', 'story' => 'ستوري', 'reel' => 'ريل', 'video' => 'فيديو', 'ugc' => 'UGC'];
        if (app()->getLocale() === 'ar') {
            return $ar;
        }
        $out = [];
        foreach ($ar as $k => $v) {
            $out[$k] = Lang::has("campaigns.dtype_{$k}") ? trans("campaigns.dtype_{$k}") : $v;
        }

        return $out;
    }
}

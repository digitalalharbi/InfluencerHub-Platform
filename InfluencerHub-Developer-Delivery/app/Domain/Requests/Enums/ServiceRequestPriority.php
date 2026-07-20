<?php
namespace App\Domain\Requests\Enums;
enum ServiceRequestPriority: string {
    case Low = 'low'; case Normal = 'normal'; case High = 'high'; case Urgent = 'urgent';
    public static function values(): array { return array_map(fn($c)=>$c->value, self::cases()); }
    public static function labels(): array { return ['low'=>'منخفضة','normal'=>'عادية','high'=>'عالية','urgent'=>'عاجلة']; }
    /** ساعات SLA لكل أولوية. */
    public static function slaHours(string $priority): int {
        return ['urgent'=>4,'high'=>24,'normal'=>72,'low'=>168][$priority] ?? 72;
    }
}

<?php
namespace App\Domain\Requests\Enums;
enum ServiceRequestType: string {
    case Campaign = 'campaign'; case Content = 'content'; case Report = 'report';
    case Consultation = 'consultation'; case Other = 'other';
    public static function values(): array { return array_map(fn($c)=>$c->value, self::cases()); }
    public static function labels(): array {
        return ['campaign'=>'حملة','content'=>'محتوى','report'=>'تقرير','consultation'=>'استشارة','other'=>'أخرى'];
    }
}

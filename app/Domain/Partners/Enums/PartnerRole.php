<?php
namespace App\Domain\Partners\Enums;
enum PartnerRole: string {
    case PartnerAdmin = 'partner_admin';
    case PartnerMember = 'partner_member';
    public static function values(): array { return array_map(fn($c)=>$c->value, self::cases()); }
}

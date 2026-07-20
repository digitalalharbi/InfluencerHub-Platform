<?php
namespace App\Domain\Creators\Enums;
enum CreatorType: string {
    case Influencer = 'influencer';
    case UgcCreator = 'ugc_creator';
    case Both = 'both';
    public static function values(): array { return array_map(fn ($c) => $c->value, self::cases()); }
    public function label(): string { return match ($this) {
        self::Influencer => 'مؤثّر', self::UgcCreator => 'صانع UGC', self::Both => 'مؤثّر وصانع UGC' }; }
}

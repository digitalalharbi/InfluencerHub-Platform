<?php
namespace App\Domain\Creators\Enums;
enum CreatorStatus: string {
    case Prospect = 'prospect';
    case Active = 'active';
    case Paused = 'paused';
    case Blocked = 'blocked';
    public static function values(): array { return array_map(fn ($c) => $c->value, self::cases()); }
    public function label(): string { return match ($this) {
        self::Prospect => 'مبدئي', self::Active => 'نشط', self::Paused => 'موقوف مؤقتًا', self::Blocked => 'محظور' }; }
}

<?php
namespace App\Domain\Creators\Enums;
enum Platform: string {
    case Instagram = 'instagram';
    case TikTok = 'tiktok';
    case YouTube = 'youtube';
    case Snapchat = 'snapchat';
    case X = 'x';
    public static function values(): array { return array_map(fn ($c) => $c->value, self::cases()); }
    public function label(): string { return match ($this) {
        self::Instagram => 'إنستغرام', self::TikTok => 'تيك توك', self::YouTube => 'يوتيوب', self::Snapchat => 'سناب شات', self::X => 'إكس' }; }
}

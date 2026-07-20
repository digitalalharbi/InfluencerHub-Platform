<?php
namespace App\Domain\CRM\Enums;
enum CustomFieldType: string {
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Date = 'date';
    case Datetime = 'datetime';
    case Boolean = 'boolean';
    case Select = 'select';
    case Multiselect = 'multiselect';
    case Url = 'url';
    case Email = 'email';
    case Phone = 'phone';
    public static function values(): array { return array_map(fn ($c) => $c->value, self::cases()); }
    public function usesOptions(): bool { return in_array($this, [self::Select, self::Multiselect], true); }
}

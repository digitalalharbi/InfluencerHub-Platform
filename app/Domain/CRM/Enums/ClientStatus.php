<?php
namespace App\Domain\CRM\Enums;
enum ClientStatus: string {
    case Lead = 'lead'; case Qualified = 'qualified'; case Active = 'active';
    case Inactive = 'inactive'; case Suspended = 'suspended'; case Archived = 'archived';
    /** الحالات المحسوبة ضمن customers.max (موثّقة). */
    public static function countingValues(): array { return ['qualified','active']; }
    public function counts(): bool { return in_array($this->value, self::countingValues(), true); }
}

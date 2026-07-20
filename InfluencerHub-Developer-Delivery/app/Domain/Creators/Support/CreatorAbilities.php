<?php
namespace App\Domain\Creators\Support;
/** صلاحيات وحدة المبدعين حسب دور المؤسسة (مصدر واحد، deny-by-default). */
final class CreatorAbilities {
    public const VIEW = ['super_admin','agency_admin','operations_manager','campaign_manager','creator_manager','agency_employee','viewer'];
    public const WRITE = ['super_admin','agency_admin','operations_manager','creator_manager'];
    public static function can(?string $role, array $set): bool { return $role !== null && in_array($role, $set, true); }
}

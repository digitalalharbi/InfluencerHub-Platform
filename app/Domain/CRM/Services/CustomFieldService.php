<?php
namespace App\Domain\CRM\Services;
use App\Domain\CRM\Enums\CustomFieldType;
use App\Domain\CRM\Models\{CustomFieldDefinition, CustomFieldValue};
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/** يكتب/يقرأ قيم الحقول المخصّصة مع تحقق صارم لكل نوع. */
class CustomFieldService {
    /** يضبط قيمة حقل لكيان (client|brand). يرمي عند فشل التحقق. */
    public function setValue(CustomFieldDefinition $def, Model $entity, mixed $raw): CustomFieldValue {
        $type = CustomFieldType::from($def->type);
        $stored = $this->validateAndStringify($def, $type, $raw);
        return CustomFieldValue::updateOrCreate(
            ['definition_id' => $def->id, 'entity_type' => $def->entity_type, 'entity_id' => $entity->getKey()],
            ['tenant_id' => $def->tenant_id, 'value' => $stored],
        );
    }

    /** يقرأ القيمة المُحوّلة لنوعها الأصلي. */
    public function getValue(CustomFieldDefinition $def, Model $entity): mixed {
        $row = CustomFieldValue::where('definition_id', $def->id)
            ->where('entity_type', $def->entity_type)->where('entity_id', $entity->getKey())->first();
        if (! $row || $row->value === null) return null;
        return match (CustomFieldType::from($def->type)) {
            CustomFieldType::Number => $row->value + 0,
            CustomFieldType::Boolean => (bool) (int) $row->value,
            CustomFieldType::Multiselect => json_decode($row->value, true) ?: [],
            default => $row->value,
        };
    }

    private function validateAndStringify(CustomFieldDefinition $def, CustomFieldType $type, mixed $raw): ?string {
        if ($raw === null || $raw === '' || $raw === []) {
            if ($def->is_required) throw new RuntimeException("الحقل {$def->label} مطلوب.");
            return null;
        }
        return match ($type) {
            CustomFieldType::Text, CustomFieldType::Textarea => (string) $raw,
            CustomFieldType::Number => is_numeric($raw) ? (string) ($raw + 0) : throw new RuntimeException("{$def->label}: رقم غير صالح."),
            CustomFieldType::Boolean => ($raw ? '1' : '0'),
            CustomFieldType::Date => $this->date($def, (string) $raw, 'Y-m-d'),
            CustomFieldType::Datetime => $this->date($def, (string) $raw, 'Y-m-d H:i:s'),
            CustomFieldType::Email => filter_var($raw, FILTER_VALIDATE_EMAIL) ? (string) $raw : throw new RuntimeException("{$def->label}: بريد غير صالح."),
            CustomFieldType::Url => filter_var($raw, FILTER_VALIDATE_URL) ? (string) $raw : throw new RuntimeException("{$def->label}: رابط غير صالح."),
            CustomFieldType::Phone => preg_match('/^\+?[0-9\s\-()]{6,20}$/', (string) $raw) ? (string) $raw : throw new RuntimeException("{$def->label}: هاتف غير صالح."),
            CustomFieldType::Select => $this->inOptions($def, [(string) $raw])[0],
            CustomFieldType::Multiselect => json_encode(array_values($this->inOptions($def, (array) $raw)), JSON_UNESCAPED_UNICODE),
        };
    }

    private function date(CustomFieldDefinition $def, string $raw, string $fmt): string {
        $ts = strtotime($raw);
        if ($ts === false) throw new RuntimeException("{$def->label}: تاريخ غير صالح.");
        return date($fmt, $ts);
    }

    /** يتحقق أن كل القيم ضمن خيارات التعريف المعرّفة. */
    private function inOptions(CustomFieldDefinition $def, array $values): array {
        $allowed = $def->options()->pluck('value')->all();
        foreach ($values as $v) {
            if (! in_array((string) $v, $allowed, true)) throw new RuntimeException("{$def->label}: خيار غير صالح ({$v}).");
        }
        return array_map('strval', $values);
    }
}

<?php

namespace App\Domain\Exports;

/**
 * بيانات جدولية محايدة للصيغة — عنوان، أعمدة (مفتاح→تسمية عربية)، صفوف، وبيانات
 * وصفية (مساحة العمل، تاريخ التوليد، المرشّحات المطبّقة). الكُتّاب (CSV/XLSX/PDF)
 * يستهلكونها فيتطابق المُصدَّر مع القائمة، ويحمل مصدره وسياقه.
 */
final class TabularData
{
    /**
     * @param  array<string,string>  $columns  key => عنوان العمود
     * @param  iterable<array<string,mixed>>  $rows  صفوف مفهرسة بمفاتيح الأعمدة
     * @param  array<string,string>  $meta  سطور سياق (المرشّحات/الفترة/العميل…)
     */
    public function __construct(
        public readonly string $title,
        public readonly array $columns,
        public readonly iterable $rows,
        public readonly array $meta = [],
        public readonly ?string $workspace = null,
        public readonly ?string $generatedAt = null,
    ) {}

    /** @return string[] عناوين الأعمدة بالترتيب */
    public function headings(): array
    {
        return array_values($this->columns);
    }

    /** @return string[] مفاتيح الأعمدة بالترتيب */
    public function keys(): array
    {
        return array_keys($this->columns);
    }

    /** يحوّل صفًا إلى مصفوفة قيم مرتّبة كسلاسل نصّية آمنة. */
    public function rowValues(array $row): array
    {
        return array_map(function ($key) use ($row) {
            $v = $row[$key] ?? '';
            if (is_bool($v)) {
                return $v ? 'نعم' : 'لا';
            }
            if (is_array($v)) {
                return implode('، ', $v);
            }
            return (string) $v;
        }, $this->keys());
    }
}

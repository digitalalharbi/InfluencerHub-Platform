import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';

/**
 * أزرار تصدير — روابط تنزيل مباشرة (لا زيارة Inertia) تحمل نفس فلاتر القائمة
 * الحالية فيتطابق المُصدَّر مع المعروض. الصيغ حسب الوحدة (csv/xlsx/pdf).
 */
export function ExportButtons({ path, filters, formats = ['xlsx', 'csv'] }: {
  path: string;
  filters?: Record<string, string | number | null | undefined>;
  formats?: ('xlsx' | 'csv' | 'pdf')[];
}) {
  const qs = (format: string) => {
    const p = new URLSearchParams();
    Object.entries(filters ?? {}).forEach(([k, v]) => { if (v !== null && v !== undefined && v !== '') p.set(k, String(v)); });
    p.set('format', format);
    return `${u(path)}?${p.toString()}`;
  };
  const LABEL: Record<string, string> = { xlsx: 'Excel', csv: 'CSV', pdf: 'PDF' };

  return (
    <span style={{ display: 'inline-flex', gap: '.35rem' }}>
      {formats.map((f) => (
        <a key={f} href={qs(f)} className="btn btn-sm btn-outline" title={`تصدير ${LABEL[f]}`} download>
          <Icon name="external-link" size={13} /> {LABEL[f]}
        </a>
      ))}
    </span>
  );
}

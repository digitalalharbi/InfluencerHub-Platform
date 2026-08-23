import { Head, router, useForm, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { ListHead, StatusBadge } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';
import type { SharedProps } from '@/types';

interface Opt { value: string; label: string }
interface Schedule {
  id: number; name: string; reportType: string; format: string; frequency: string;
  delivery: string; enabled: boolean; lastRun: string | null; nextRun: string | null;
}
interface HistoryRow {
  id: number; title: string; format: string; status: string; tone: string;
  rows: number | null; size: string | null; createdAt: string | null; expiresAt: string | null;
  downloadable: boolean; downloadUrl: string | null; scheduled: boolean;
}
interface Props {
  schedules: Schedule[]; history: HistoryRow[];
  reportTypes: Opt[]; frequencies: Opt[]; formats: Opt[];
}

export default function ExportsIndex({ schedules, history, reportTypes, frequencies, formats }: Props) {
  const flash = usePage<SharedProps>().props.flash;
  const form = useForm({
    name: '', report_type: reportTypes[0]?.value ?? '', format: 'xlsx',
    frequency: 'weekly', delivery: 'in_app',
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    form.post(u('/exports/schedules'), { preserveScroll: true, onSuccess: () => form.reset('name') });
  };
  const toggle = (id: number) => router.post(u(`/exports/schedules/${id}/toggle`), {}, { preserveScroll: true });
  const remove = (id: number) => {
    if (confirm('حذف هذا التقرير المجدول؟')) router.delete(u(`/exports/schedules/${id}`), { preserveScroll: true });
  };

  return (
    <AppShell heading="مركز التصدير">
      <Head title="مركز التصدير" />
      {flash?.ok && <div className="card" style={{ padding: '.7rem 1rem', marginBottom: '1rem', borderInlineStart: '3px solid var(--ih-success)', background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)' }}>{flash.ok}</div>}

      <ListHead eyebrow="التقارير" title="مركز التصدير"
        sub="جدولة التقارير الدورية وسجلّ التنزيلات — التنزيل آمن ومقصور على صاحبه." />

      {/* إنشاء تقرير مجدول */}
      <section className="card" style={{ padding: '1.1rem 1.25rem', marginBottom: '1.5rem' }}>
        <h3 style={{ margin: '0 0 .9rem', fontSize: '.95rem', display: 'flex', alignItems: 'center', gap: '.4rem' }}>
          <Icon name="calendar-days" size={16} /> جدولة تقرير جديد
        </h3>
        <form onSubmit={submit} style={{ display: 'grid', gap: '.8rem', gridTemplateColumns: 'repeat(auto-fit, minmax(160px, 1fr))', alignItems: 'end' }}>
          <label style={lbl}>الاسم
            <input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)}
              placeholder="مثال: أداء العملاء الأسبوعي" required style={inp} />
          </label>
          <label style={lbl}>نوع التقرير
            <select value={form.data.report_type} onChange={(e) => form.setData('report_type', e.target.value)} style={inp}>
              {reportTypes.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
            </select>
          </label>
          <label style={lbl}>التكرار
            <select value={form.data.frequency} onChange={(e) => form.setData('frequency', e.target.value)} style={inp}>
              {frequencies.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
            </select>
          </label>
          <label style={lbl}>الصيغة
            <select value={form.data.format} onChange={(e) => form.setData('format', e.target.value)} style={inp}>
              {formats.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
            </select>
          </label>
          <label style={lbl}>التسليم
            <select value={form.data.delivery} onChange={(e) => form.setData('delivery', e.target.value)} style={inp}>
              <option value="in_app">داخل التطبيق</option>
              <option value="email">بريد إلكتروني</option>
            </select>
          </label>
          <button type="submit" className="btn btn-primary" disabled={form.processing || !form.data.name}>
            <Icon name="calendar-days" size={14} /> جدولة
          </button>
        </form>
        {form.errors.name && <div style={{ color: 'var(--ih-danger)', fontSize: '.78rem', marginTop: '.5rem' }}>{form.errors.name}</div>}
      </section>

      {/* التقارير المجدولة */}
      <section style={{ marginBottom: '1.75rem' }}>
        <h3 style={{ margin: '0 0 .7rem', fontSize: '.9rem', color: 'var(--ih-text-muted)' }}>التقارير المجدولة</h3>
        {schedules.length === 0 ? (
          <div className="card" style={{ padding: '1.4rem', textAlign: 'center', color: 'var(--ih-text-muted)', fontSize: '.85rem' }}>
            لا تقارير مجدولة بعد — أنشئ واحدًا من النموذج أعلاه.
          </div>
        ) : (
          <div className="ih-dt-wrap"><div className="ih-dt-scroll">
            <table className="ih-dt">
              <thead><tr>
                <th>الاسم</th><th>النوع</th><th>التكرار</th><th>الصيغة</th>
                <th>آخر تشغيل</th><th>التالي</th><th>الحالة</th><th></th>
              </tr></thead>
              <tbody>
                {schedules.map((s) => (
                  <tr key={s.id}>
                    <td style={{ fontWeight: 600 }}>{s.name}</td>
                    <td>{s.reportType}</td>
                    <td>{s.frequency}</td>
                    <td>{s.format}</td>
                    <td style={num}>{s.lastRun ?? '—'}</td>
                    <td style={num}>{s.enabled ? (s.nextRun ?? '—') : '—'}</td>
                    <td><StatusBadge tone={s.enabled ? 'active' : 'draft'} label={s.enabled ? 'مُفعَّل' : 'مُوقَف'} /></td>
                    <td style={{ whiteSpace: 'nowrap' }}>
                      <button onClick={() => toggle(s.id)} className="btn btn-sm btn-outline" style={{ marginInlineEnd: '.35rem' }}>
                        {s.enabled ? 'إيقاف' : 'تفعيل'}
                      </button>
                      <button onClick={() => remove(s.id)} className="btn btn-sm btn-outline" title="حذف">حذف</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div></div>
        )}
      </section>

      {/* سجلّ التنزيلات */}
      <section>
        <h3 style={{ margin: '0 0 .7rem', fontSize: '.9rem', color: 'var(--ih-text-muted)' }}>سجلّ التنزيلات</h3>
        {history.length === 0 ? (
          <div className="card" style={{ padding: '1.4rem', textAlign: 'center', color: 'var(--ih-text-muted)', fontSize: '.85rem' }}>
            لا تنزيلات بعد.
          </div>
        ) : (
          <div className="ih-dt-wrap"><div className="ih-dt-scroll">
            <table className="ih-dt">
              <thead><tr>
                <th>الملفّ</th><th>الصيغة</th><th>الصفوف</th><th>الحجم</th>
                <th>أُنشئ</th><th>ينتهي</th><th>الحالة</th><th></th>
              </tr></thead>
              <tbody>
                {history.map((h) => (
                  <tr key={h.id}>
                    <td style={{ fontWeight: 600 }}>
                      {h.title}
                      {h.scheduled && <span style={{ marginInlineStart: '.4rem', fontSize: '.62rem', color: 'var(--ih-text-muted)', border: '1px solid var(--ih-border)', borderRadius: 4, padding: '1px 5px' }}>مجدول</span>}
                    </td>
                    <td>{h.format}</td>
                    <td style={num}>{h.rows ?? '—'}</td>
                    <td style={num}>{h.size ?? '—'}</td>
                    <td style={num}>{h.createdAt ?? '—'}</td>
                    <td style={num}>{h.expiresAt ?? '—'}</td>
                    <td><StatusBadge tone={h.tone} label={h.status} /></td>
                    <td>
                      {h.downloadable && h.downloadUrl ? (
                        <a href={u(h.downloadUrl)} className="btn btn-sm btn-outline">
                          <Icon name="file-text" size={13} /> تنزيل
                        </a>
                      ) : <span style={{ color: 'var(--ih-text-muted)', fontSize: '.75rem' }}>—</span>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div></div>
        )}
      </section>
    </AppShell>
  );
}

const lbl: React.CSSProperties = { display: 'grid', gap: '.3rem', fontSize: '.78rem', fontWeight: 600, color: 'var(--ih-text-muted)' };
const inp: React.CSSProperties = { padding: '.5rem .6rem', border: '1px solid var(--ih-border)', borderRadius: 8, fontSize: '.85rem', background: 'var(--ih-surface)', color: 'var(--ih-text)' };
const num: React.CSSProperties = { fontVariantNumeric: 'tabular-nums', whiteSpace: 'nowrap' };

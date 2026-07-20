import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Kpi, ListHead, StatusBadge } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { u } from '@/lib/href';

interface BrandRow {
  id: number; name: string; client: string | null; sector: string | null; version: number;
  status: string; statusLabel: string; statusTone: string; submittedAt: string | null; needsReview: boolean;
}
interface Summary {
  total: number; needs_review: number; submitted: number; under_review: number;
  changes_requested: number; approved: number; suspended: number; draft: number;
}
interface Filters { q?: string; seg?: string }
/** من أين تُنشأ العلامة — تُحسب في الخادم لأنها تعتمد على عدد العملاء. */
interface CreateHint { clientsCount: number; href: string; label: string }
interface Props { brands: Paginated<BrandRow>; filters: Filters; summary: Summary; createHint: CreateHint }

function clean(obj: Record<string, unknown>): Record<string, string> {
  const out: Record<string, string> = {};
  for (const [k, v] of Object.entries(obj)) if (v !== '' && v !== null && v !== undefined) out[k] = String(v);
  return out;
}

export default function BrandsIndex({ brands, filters, summary, createHint }: Props) {
  const [q, setQ] = useState(filters.q ?? '');
  const first = useRef(true);
  useEffect(() => {
    if (first.current) { first.current = false; return; }
    const t = setTimeout(() => router.get(u('/brands'), clean({ ...filters, q }), { preserveState: true, replace: true, preserveScroll: true }), 350);
    return () => clearTimeout(t);
  }, [q]);
  const update = (patch: Filters) => router.get(u('/brands'), clean({ ...filters, ...patch }), { preserveState: true, replace: true, preserveScroll: true });

  const seg = filters.seg ?? '';
  const hasFilters = !!(filters.q || seg);
  const segments: [string, string, number][] = [
    ['', 'الكل', summary.total], ['needs_review', 'بانتظار المراجعة', summary.needs_review],
    ['submitted', 'مُرسلة', summary.submitted], ['under_review', 'قيد المراجعة', summary.under_review],
    ['changes_requested', 'تعديلات مطلوبة', summary.changes_requested], ['approved', 'معتمدة', summary.approved],
    ['suspended', 'معلّقة', summary.suspended], ['draft', 'مسودة', summary.draft],
  ];

  return (
    <AppShell heading="العلامات">
      <Head title="العلامات" />

      <ListHead eyebrow="إدارة العلاقات" title="العلامات"
        sub="ملفات العلامات المرتبطة بالعملاء وطابور اعتمادها ومراجعتها" />

      <div className="ih-kpis">
        <Kpi label="إجمالي العلامات" icon="bookmark" value={summary.total.toLocaleString('en-US')} sub={`${summary.approved} معتمدة`} />
        <Kpi label="بانتظار مراجعتك" icon="shield-check" tone={summary.needs_review ? 'warning' : undefined} value={summary.needs_review.toLocaleString('en-US')} sub={`${summary.submitted} مُرسلة · ${summary.under_review} قيد المراجعة`} />
        <Kpi label="تعديلات مطلوبة" icon="clipboard-check" value={summary.changes_requested.toLocaleString('en-US')} sub="بانتظار تعديل العميل" />
        <Kpi label="معلّقة" icon="shield-check" tone={summary.suspended ? 'danger' : undefined} value={summary.suspended.toLocaleString('en-US')} sub="علامات موقوفة" />
      </div>

      <div className="ih-chips" style={{ marginBottom: '.9rem', overflowX: 'auto', paddingBottom: '.2rem', flexWrap: 'nowrap' }}>
        {segments.map(([key, label, count]) => (
          <button key={key} onClick={() => update({ seg: key })} className={`ih-chip${seg === key ? ' active' : ''}`}>{label} <span className="ih-chip__count">{count}</span></button>
        ))}
      </div>

      <div className="ih-filterbar">
        <label className="ih-search"><Icon name="search" size={16} />
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="ابحث باسم العلامة أو العميل…" />
        </label>
      </div>

      {brands.data.length === 0 ? (
        <div className="ih-dt-wrap"><div className="ih-empty">
          <span className="ih-empty__icon"><Icon name="bookmark" size={26} /></span>
          {hasFilters ? (
            <><div className="ih-empty__title">لا علامات مطابقة</div><div className="ih-empty__text">لا نتائج للبحث أو الشريحة الحالية.</div><a href={u("/brands")} className="btn btn-sm btn-outline">مسح الفلاتر</a></>
          ) : (
            <><div className="ih-empty__title">لا علامات بعد</div>
              <div className="ih-empty__text">
                {createHint.clientsCount === 0
                  ? 'العلامة تتبع عميلًا، وليس لديك عملاء بعد. ابدأ بإضافة عميل ثم أضِف علاماته.'
                  : 'العلامة تُنشأ من صفحة العميل التابعة له، وتظهر هنا بعد إرسالها للاعتماد.'}
              </div>
              <a href={createHint.clientsCount === 0 ? u('/clients') : createHint.href} className="btn btn-sm">
                {createHint.clientsCount === 0 ? 'إضافة عميل' : createHint.label}
              </a></>
          )}
        </div></div>
      ) : (
        <>
          <div className="ih-only-desktop">
            <div className="ih-dt-wrap"><div className="ih-dt-scroll">
              <table className="ih-dt">
                <thead><tr><th>العلامة</th><th>العميل</th><th>القطاع</th><th>الإصدار</th><th>أُرسلت</th><th>الحالة</th><th></th></tr></thead>
                <tbody>
                  {brands.data.map((b) => (
                    <tr key={b.id}>
                      <td>
                        <a href={u(`/brands/${b.id}`)} className="ih-idc" style={{ textDecoration: 'none' }}>
                          <span className="ih-idc__av" style={{ borderRadius: 8 }}>{b.name.slice(0, 1)}</span>
                          <span className="ih-idc__main"><span className="ih-idc__name">{b.name}</span></span>
                        </a>
                      </td>
                      <td>{b.client ?? '—'}</td>
                      <td>{b.sector ? <span className="ih-tag">{b.sector}</span> : '—'}</td>
                      <td className="ih-dt__num">v{b.version}</td>
                      <td style={{ fontSize: '.82rem', color: 'var(--ih-text-muted)' }}>{b.submittedAt ?? '—'}</td>
                      <td><StatusBadge tone={b.statusTone} label={b.statusLabel} /></td>
                      <td style={{ textAlign: 'end' }}>
                        <span className="ih-dt__row-actions">
                          {b.needsReview && <span className="badge" style={{ background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)', fontSize: '.6rem' }}>يحتاج مراجعة</span>}
                          <a href={u(`/brands/${b.id}`)} className="btn btn-xs btn-outline">فتح</a>
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
              <div className="ih-dt__foot"><span>{brands.total} علامة{hasFilters ? ' · مُرشَّح' : ''}</span><Pagination links={brands.links} /></div>
            </div>
          </div>

          <div className="ih-only-mobile">
            <div className="ih-mlist">
              {brands.data.map((b) => (
                <a key={b.id} href={u(`/brands/${b.id}`)} className="ih-mcard">
                  <div style={{ display: 'flex', alignItems: 'center', gap: '.6rem' }}>
                    <span className="ih-idc__av" style={{ width: 42, height: 42, borderRadius: 8 }}>{b.name.slice(0, 1)}</span>
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div className="ih-idc__name">{b.name}</div>
                      <div className="ih-idc__sub">{b.client ?? '—'} · v{b.version}</div>
                    </div>
                    <StatusBadge tone={b.statusTone} label={b.statusLabel} />
                  </div>
                  {b.needsReview && <div style={{ marginTop: '.6rem', fontSize: '.76rem', color: 'var(--ih-warning-ink)', fontWeight: 600 }}>● يحتاج مراجعتك</div>}
                </a>
              ))}
            </div>
            <div style={{ marginTop: '1rem' }}><Pagination links={brands.links} /></div>
          </div>
        </>
      )}
    </AppShell>
  );
}

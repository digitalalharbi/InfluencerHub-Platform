import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Kpi, ListHead, StatusBadge } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { u } from '@/lib/href';

interface ContentRow {
  id: number; number: string; title: string; creator: string | null; campaign: string | null;
  type: string; platform: string | null; version: number; status: string; statusLabel: string; statusTone: string; needsReview: boolean;
  mediaUrl: string | null; scheduledAt: string | null; publishedAt: string | null; needsAction: boolean;
}
interface Summary {
  total: number; agency_review: number; client_review: number; changes_requested: number;
  approved: number; scheduled: number; published: number; draft: number; rejected: number;
}
interface Filters { q?: string; type?: string; seg?: string }
interface Props { items: Paginated<ContentRow>; filters: Filters; typeLabels: Record<string, string>; summary: Summary }

function clean(obj: Record<string, unknown>): Record<string, string> {
  const out: Record<string, string> = {};
  for (const [k, v] of Object.entries(obj)) if (v !== '' && v !== null && v !== undefined) out[k] = String(v);
  return out;
}

export default function ContentIndex({ items, filters, typeLabels, summary }: Props) {
  const [q, setQ] = useState(filters.q ?? '');
  const first = useRef(true);
  useEffect(() => {
    if (first.current) { first.current = false; return; }
    const t = setTimeout(() => router.get(u('/content'), clean({ ...filters, q }), { preserveState: true, replace: true, preserveScroll: true }), 350);
    return () => clearTimeout(t);
  }, [q]);
  const update = (patch: Filters) => router.get(u('/content'), clean({ ...filters, ...patch }), { preserveState: true, replace: true, preserveScroll: true });

  const seg = filters.seg ?? '';
  const hasFilters = !!(filters.q || filters.type || seg);
  const segments: [string, string, number][] = [
    ['', 'الكل', summary.total], ['agency_review', 'بانتظار مراجعتي', summary.agency_review],
    ['client_review', 'بانتظار العميل', summary.client_review], ['changes_requested', 'تعديلات مطلوبة', summary.changes_requested],
    ['approved', 'معتمد', summary.approved], ['scheduled', 'مجدول', summary.scheduled],
    ['published', 'منشور', summary.published], ['draft', 'مسودة', summary.draft], ['rejected', 'مرفوض', summary.rejected],
  ];

  return (
    <AppShell heading="المحتوى">
      <Head title="المحتوى" />

      <ListHead eyebrow="التشغيل" title="المحتوى"
        sub="طابور مراجعة المحتوى واعتماده قبل النشر — من الوكالة إلى العميل" />

      <div className="ih-kpis">
        <Kpi label="بانتظار مراجعتي" icon="image" tone={summary.agency_review ? 'warning' : undefined} value={summary.agency_review.toLocaleString('en-US')} sub="محتوى مُرسَل للوكالة" />
        <Kpi label="بانتظار العميل" icon="clipboard-check" tone="accent" value={summary.client_review.toLocaleString('en-US')} sub="مُرسَل لموافقة العميل" />
        <Kpi label="تعديلات مطلوبة" icon="clipboard-check" value={summary.changes_requested.toLocaleString('en-US')} sub="بانتظار تعديل المبدع" />
        <Kpi label="منشور" icon="shield-check" tone="success" value={summary.published.toLocaleString('en-US')} sub={`${summary.scheduled} مجدول · ${summary.approved} معتمد`} />
      </div>

      <div className="ih-chips" style={{ marginBottom: '.9rem', overflowX: 'auto', paddingBottom: '.2rem', flexWrap: 'nowrap' }}>
        {segments.map(([key, label, count]) => (
          <button key={key} onClick={() => update({ seg: key })} className={`ih-chip${seg === key ? ' active' : ''}`}>{label} <span className="ih-chip__count">{count}</span></button>
        ))}
      </div>

      <div className="ih-filterbar">
        <label className="ih-search"><Icon name="search" size={16} />
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="ابحث بالعنوان أو الرقم أو المبدع…" />
        </label>
        <select className="field" style={{ maxWidth: 130 }} value={filters.type ?? ''} onChange={(e) => update({ type: e.target.value })}>
          <option value="">كل الأنواع</option>
          {Object.entries(typeLabels).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
        </select>
      </div>

      {items.data.length === 0 ? (
        <div className="ih-dt-wrap"><div className="ih-empty">
          <span className="ih-empty__icon" style={{ background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)' }}><Icon name="shield-check" size={26} /></span>
          {hasFilters ? (
            <><div className="ih-empty__title">لا محتوى مطابق</div><div className="ih-empty__text">لا نتائج للبحث أو الشريحة الحالية.</div><a href={u("/content")} className="btn btn-sm btn-outline">مسح الفلاتر</a></>
          ) : (
            <><div className="ih-empty__title">لا محتوى في الطابور</div><div className="ih-empty__text">يظهر هنا المحتوى المُرسَل من المبدعين للمراجعة والاعتماد.</div></>
          )}
        </div></div>
      ) : (
        <>
          {/* معرض المراجعة — المعاينة أولًا، والإجراء ظاهر */}
          <div className="ih-only-desktop">
            <div className="ih-gallery">
              {items.data.map((c) => (
                <a key={c.id} href={u(`/content/${c.id}`)} className="ih-gtile" style={{ textDecoration: 'none', color: 'inherit' }}>
                  <div className="ih-gtile__thumb">
                    {c.mediaUrl ? <img src={c.mediaUrl} alt="" loading="lazy" /> : <Icon name="image" size={26} />}
                    <span className="ih-gtile__badge"><StatusBadge tone={c.statusTone} label={c.statusLabel} /></span>
                    {c.version > 1 && <span className="ih-gtile__ver">v{c.version}</span>}
                  </div>
                  <div className="ih-gtile__body">
                    <div className="ih-gtile__title">{c.title}</div>
                    <div className="ih-gtile__meta">{c.creator ?? '—'} · <span className="ih-tag" style={{ fontSize: '.62rem' }}>{c.type}</span></div>
                    {c.campaign && <div className="ih-gtile__meta" style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{c.campaign}</div>}
                    <div style={{ marginTop: 'auto', display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '.4rem', paddingTop: '.4rem' }}>
                      <span style={{ fontSize: '.68rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{c.publishedAt ?? c.scheduledAt ?? c.number}</span>
                      {c.needsReview
                        ? <span className="btn btn-xs btn-primary" style={{ pointerEvents: 'none' }}>راجِع</span>
                        : c.needsAction ? <span className="ih-tag" style={{ fontSize: '.62rem', background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)' }}>يحتاج إجراء</span> : null}
                    </div>
                  </div>
                </a>
              ))}
            </div>
            <div className="ih-dt__foot" style={{ marginTop: '.9rem' }}><span>{items.total} عنصر{hasFilters ? ' · مُرشَّح' : ''}</span><Pagination links={items.links} /></div>
          </div>

          <div className="ih-only-mobile">
            <div className="ih-mlist">
              {items.data.map((c) => (
                <a key={c.id} href={u(`/content/${c.id}`)} className="ih-mcard">
                  <div style={{ display: 'flex', alignItems: 'flex-start', gap: '.6rem' }}>
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div className="ih-idc__name">{c.title}</div>
                      <div className="ih-idc__sub">{c.creator ?? '—'} · {c.number}</div>
                    </div>
                    <StatusBadge tone={c.statusTone} label={c.statusLabel} />
                  </div>
                  <div style={{ display: 'flex', gap: '.4rem', flexWrap: 'wrap', marginTop: '.7rem', alignItems: 'center' }}>
                    <span className="ih-tag" style={{ fontSize: '.66rem' }}>{c.type}</span>
                    {c.platform && <span className="ih-tag" style={{ fontSize: '.66rem' }}>{c.platform}</span>}
                    <span style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)' }}>v{c.version}</span>
                    {c.needsReview && <span style={{ marginInlineStart: 'auto', fontSize: '.74rem', color: 'var(--ih-warning-ink)', fontWeight: 600 }}>راجِع</span>}
                  </div>
                </a>
              ))}
            </div>
            <div style={{ marginTop: '1rem' }}><Pagination links={items.links} /></div>
          </div>
        </>
      )}
    </AppShell>
  );
}

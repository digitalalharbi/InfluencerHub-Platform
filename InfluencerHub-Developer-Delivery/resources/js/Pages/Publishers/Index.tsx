import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { ListHead, StatusBadge, Kpi, WorkTabs, Bar } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { u } from '@/lib/href';

interface Pub {
  id: number; number: string; name: string; handle: string; platform: string; platformLabel: string;
  followers: number; engagement: number | null; growth: number | null; contentTypes: string[]; categories: string[];
  city: string | null; language: string | null; quality: number | null;
  source: string; sourceLabel: string; sourceTone: string; lastSynced: string | null; saved: boolean; converted: boolean;
}
interface Connector {
  key: string; name: string; nameEn: string; discoveryState: string; discoveryLabel: string; discoveryTone: string;
  manualAvailable: boolean; capabilities: string[]; note: string;
}
interface PlatformStat { platform: string; label: string; count: number; followers: number; avgEngagement: number | null }
interface Analytics {
  totals: { publishers: number; followers: number; avgEngagement: number | null; avgGrowth: number | null };
  byPlatform: PlatformStat[]; topFollowers: Pub[]; topEngagement: Pub[]; topGrowth: Pub[];
}
interface Props {
  tab: string;
  publishers: Paginated<Pub>;
  filters: { q: string | null; platform: string | null; category: string | null };
  platforms: Record<string, string> | { value: string; label: string }[];
  connectors: Connector[];
  connectorSummary: { total: number; manual: number; live: number };
  summary: { total: number; saved: number; converted: number };
  analytics: Analytics | null;
  compareOptions: Pub[] | null;
}

const fmt = (n: number) => n >= 1000 ? Math.round(n / 1000).toLocaleString('en-US') + 'K' : n.toLocaleString('en-US');
const pct = (v: number | null) => v == null ? '—' : `${v}%`;

export default function PublishersIndex({ tab, publishers, filters, platforms, connectors, connectorSummary, summary, analytics, compareOptions }: Props) {
  const [q, setQ] = useState(filters.q ?? '');
  const [picked, setPicked] = useState<number[]>([]);
  const platformOpts = Array.isArray(platforms) ? platforms : Object.entries(platforms).map(([value, label]) => ({ value, label }));

  const go = (patch: Record<string, string | undefined>) =>
    router.get(u('/publishers'), { ...filters, q: q || undefined, tab, ...patch }, { preserveState: true, replace: true });
  const setTab = (t: string) => router.get(u('/publishers'), { tab: t }, { preserveState: false });
  const save = (id: number) => router.post(u(`/publishers/${id}/save`), {}, { preserveScroll: true });
  const toggle = (id: number) => setPicked((p) => p.includes(id) ? p.filter((x) => x !== id) : p.length >= 3 ? p : [...p, id]);

  const compared = (compareOptions ?? []).filter((p) => picked.includes(p.id));

  return (
    <AppShell heading="الناشرون">
      <Head title="الناشرون" />
      <ListHead eyebrow="العلاقات" title="الناشرون"
        sub="اكتشف حسابات المنصّات وحلّلها وحوّل الأنسب إلى مؤثرين." />

      <div className="ih-kpis">
        <Kpi label="الناشرون" icon="radar" value={summary.total.toLocaleString('en-US')} sub="في مساحتك" />
        <Kpi label="محفوظون" icon="bookmark" value={summary.saved.toLocaleString('en-US')} sub="في قوائمك" />
        <Kpi label="مؤثرون" icon="users" tone="success" value={summary.converted.toLocaleString('en-US')} sub="بعد التحويل" />
        <Kpi label="الموصّلات" icon="plug" value={`${connectorSummary.manual}/${connectorSummary.total}`} sub="يدوي متاح" />
      </div>

      <WorkTabs active={tab} onChange={setTab} tabs={[
        { key: 'discovery', label: 'الاكتشاف', icon: 'search' },
        { key: 'analytics', label: 'التحليلات', icon: 'trending-up' },
        { key: 'comparison', label: 'المقارنات', icon: 'list-checks' },
        { key: 'lists', label: 'القوائم', icon: 'bookmark', count: summary.saved },
      ]} />

      <div className="card" style={{ padding: '.75rem 1rem', marginBottom: '1.1rem', borderInlineStart: '3px solid var(--ih-info)', background: 'var(--ih-info-soft)', color: 'var(--ih-info-ink)', fontSize: '.82rem' }}>
        <Icon name="shield-check" size={14} /> لا اتصال حيّ بالمنصّات بعد — مصدر كل رقم موسوم على بطاقته (يدوي/استيراد/تجريبي)، ولا أرقام مُقدَّرة.
      </div>

      {/* الاكتشاف + القوائم — نفس العرض، نطاق مختلف */}
      {(tab === 'discovery' || tab === 'lists') && (
        <>
          {tab === 'discovery' && (
            <div className="ih-filterbar" style={{ marginBottom: '1rem' }}>
              <div className="ih-search">
                <Icon name="search" size={15} />
                <input value={q} onChange={(e) => setQ(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && go({})} placeholder="ابحث بالحساب أو الاسم…" />
              </div>
              <select value={filters.platform ?? ''} onChange={(e) => go({ platform: e.target.value || undefined })} className="field" style={{ maxWidth: 150 }}>
                <option value="">كل المنصّات</option>
                {platformOpts.map((p) => <option key={p.value} value={p.value}>{p.label}</option>)}
              </select>
              <button onClick={() => go({})} className="btn btn-sm">بحث</button>
            </div>
          )}

          {publishers.data.length === 0 ? (
            <div className="card" style={{ padding: '2rem', textAlign: 'center', color: 'var(--ih-text-muted)' }}>
              {tab === 'lists' ? 'لا ناشرين محفوظين بعد.' : 'لا نتائج مطابقة.'}
            </div>
          ) : (
            <>
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))', gap: '1rem' }}>
                {publishers.data.map((p) => (
                  <PubCard key={p.id} p={p} onSave={() => save(p.id)} />
                ))}
              </div>
              <Pagination links={publishers.links} />
            </>
          )}
        </>
      )}

      {/* التحليلات */}
      {tab === 'analytics' && analytics && (
        <div style={{ display: 'grid', gap: '1.2rem' }}>
          <div className="ih-kpis">
            <Kpi label="إجمالي المتابعين" icon="users" value={fmt(analytics.totals.followers)} sub="عبر الناشرين" />
            <Kpi label="متوسط التفاعل" icon="activity" value={pct(analytics.totals.avgEngagement)} sub="من البيانات المتاحة" />
            <Kpi label="متوسط النمو" icon="trending-up" tone={(analytics.totals.avgGrowth ?? 0) >= 0 ? 'success' : 'danger'} value={pct(analytics.totals.avgGrowth)} sub="آخر 30 يومًا" />
            <Kpi label="الناشرون" icon="radar" value={analytics.totals.publishers.toLocaleString('en-US')} sub="مُحلَّلون" />
          </div>

          <div className="ih-sec">
            <div className="ih-sec__head"><span className="ih-sec__title"><Icon name="bar-chart-3" size={16} /> التوزيع حسب المنصّة</span></div>
            <div className="ih-sec__body" style={{ display: 'grid', gap: '.8rem' }}>
              {analytics.byPlatform.map((s) => {
                const max = Math.max(...analytics.byPlatform.map((x) => x.count), 1);
                return (
                  <div key={s.platform}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '.82rem', marginBottom: '.3rem' }}>
                      <span style={{ fontWeight: 600 }}>{s.label}</span>
                      {/* لا direction:ltr هنا — النص عربي مختلط بأرقام (يكسر ترتيب %) */}
                      <span style={{ color: 'var(--ih-text-muted)' }}>
                        {s.count} ناشر · <bdi>{fmt(s.followers)}</bdi> متابع · تفاعل <bdi>{pct(s.avgEngagement)}</bdi>
                      </span>
                    </div>
                    <Bar pct={Math.round((s.count / max) * 100)} />
                  </div>
                );
              })}
            </div>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '1rem' }}>
            <TopList title="الأعلى متابعين" icon="users" items={analytics.topFollowers} render={(p) => fmt(p.followers)} />
            <TopList title="الأعلى تفاعلًا" icon="activity" items={analytics.topEngagement} render={(p) => pct(p.engagement)} />
            <TopList title="الأسرع نموًا" icon="trending-up" items={analytics.topGrowth} render={(p) => pct(p.growth)} />
          </div>
        </div>
      )}

      {/* المقارنات */}
      {tab === 'comparison' && (
        <div style={{ display: 'grid', gap: '1.2rem' }}>
          <div className="ih-sec">
            <div className="ih-sec__head">
              <span className="ih-sec__title"><Icon name="list-checks" size={16} /> اختر حتى 3 ناشرين</span>
              {picked.length > 0 && <button onClick={() => setPicked([])} className="btn btn-xs btn-ghost">مسح</button>}
            </div>
            <div className="ih-sec__body" style={{ display: 'flex', gap: '.4rem', flexWrap: 'wrap' }}>
              {(compareOptions ?? []).map((p) => (
                <button key={p.id} onClick={() => toggle(p.id)}
                  className={`ih-chip${picked.includes(p.id) ? ' active' : ''}`}
                  disabled={!picked.includes(p.id) && picked.length >= 3}>
                  {p.name}
                </button>
              ))}
            </div>
          </div>

          {compared.length < 2 ? (
            <div className="card" style={{ padding: '2rem', textAlign: 'center', color: 'var(--ih-text-muted)' }}>اختر ناشرَين على الأقل للمقارنة.</div>
          ) : (
            <div className="ih-dt-wrap"><div className="ih-dt-scroll">
              <table className="ih-dt">
                <thead><tr><th>المؤشر</th>{compared.map((p) => <th key={p.id}>{p.name}</th>)}</tr></thead>
                <tbody>
                  <CompareRow label="المنصّة" items={compared} get={(p) => p.platformLabel} />
                  <CompareRow label="المتابعون" items={compared} get={(p) => fmt(p.followers)} best={Math.max(...compared.map((p) => p.followers))} raw={(p) => p.followers} />
                  <CompareRow label="التفاعل" items={compared} get={(p) => pct(p.engagement)} best={Math.max(...compared.map((p) => p.engagement ?? -1))} raw={(p) => p.engagement ?? -1} />
                  <CompareRow label="النمو" items={compared} get={(p) => pct(p.growth)} best={Math.max(...compared.map((p) => p.growth ?? -999))} raw={(p) => p.growth ?? -999} />
                  <CompareRow label="الجودة" items={compared} get={(p) => p.quality != null ? `${p.quality}/100` : '—'} best={Math.max(...compared.map((p) => p.quality ?? -1))} raw={(p) => p.quality ?? -1} />
                  <CompareRow label="المدينة" items={compared} get={(p) => p.city ?? '—'} />
                  <CompareRow label="المصدر" items={compared} get={(p) => p.sourceLabel} />
                </tbody>
              </table>
            </div></div>
          )}
        </div>
      )}

      {/* الموصّلات — تظهر أسفل الاكتشاف فقط */}
      {tab === 'discovery' && (
        <div className="ih-sec" style={{ marginTop: '1.4rem' }}>
          <div className="ih-sec__head"><span className="ih-sec__title"><Icon name="plug" size={16} /> حالة المنصّات</span>
            <Link href={u("/integrations")} className="ih-sec__link">التكاملات</Link>
          </div>
          <div className="ih-sec__body">
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))', gap: '.7rem' }}>
              {connectors.map((c) => (
                <div key={c.key} className="card" style={{ padding: '.7rem .8rem', display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: '.5rem' }}>
                  <span style={{ fontWeight: 600, fontSize: '.86rem' }}>{c.name}</span>
                  <StatusBadge tone={c.discoveryTone} label={c.discoveryLabel} />
                </div>
              ))}
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}

function PubCard({ p, onSave }: { p: Pub; onSave: () => void }) {
  return (
    <div className="ih-sec">
      <div className="ih-sec__head">
        <span className="ih-sec__title">
          <Link href={u(`/publishers/${p.id}`)} style={{ color: 'var(--ih-primary)', textDecoration: 'none' }}>{p.name}</Link>
          {p.converted && <span className="ih-tag" style={{ fontSize: '.62rem', background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)' }}>مؤثر</span>}
        </span>
        <StatusBadge tone={p.sourceTone} label={p.sourceLabel} />
      </div>
      <div className="ih-sec__body">
        <div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)', direction: 'ltr', marginBottom: '.5rem' }}>{p.handle} · {p.platformLabel}</div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '.5rem', marginBottom: '.6rem' }}>
          <Metric label="متابعون" value={fmt(p.followers)} />
          <Metric label="تفاعل" value={pct(p.engagement)} />
          <Metric label="نمو" value={pct(p.growth)} tone={p.growth != null ? (p.growth >= 0 ? 'success' : 'danger') : undefined} />
        </div>
        {p.categories.length > 0 && (
          <div style={{ display: 'flex', gap: '.3rem', flexWrap: 'wrap', marginBottom: '.6rem' }}>
            {p.categories.slice(0, 4).map((c, i) => <span key={i} className="ih-tag" style={{ fontSize: '.64rem' }}>{c}</span>)}
          </div>
        )}
        <div style={{ display: 'flex', gap: '.4rem' }}>
          <Link href={u(`/publishers/${p.id}`)} className="btn btn-xs btn-primary" style={{ flex: 1, textAlign: 'center' }}>عرض</Link>
          <button onClick={onSave} className={`btn btn-xs ${p.saved ? 'btn-primary' : 'btn-outline'}`} title={p.saved ? 'محفوظ' : 'حفظ'}><Icon name="bookmark" size={13} /></button>
        </div>
      </div>
    </div>
  );
}

function TopList({ title, icon, items, render }: { title: string; icon: 'users' | 'activity' | 'trending-up'; items: Pub[]; render: (p: Pub) => string }) {
  return (
    <div className="ih-sec">
      <div className="ih-sec__head"><span className="ih-sec__title"><Icon name={icon} size={15} /> {title}</span></div>
      <div className="ih-sec__body" style={{ display: 'grid', gap: '.45rem' }}>
        {items.length === 0 ? <span style={{ fontSize: '.82rem', color: 'var(--ih-text-muted)' }}>لا بيانات.</span> : items.map((p) => (
          <Link key={p.id} href={u(`/publishers/${p.id}`)} style={{ display: 'flex', justifyContent: 'space-between', gap: '.5rem', fontSize: '.84rem', textDecoration: 'none', color: 'inherit', borderBottom: '1px solid var(--ih-border)', paddingBottom: '.35rem' }}>
            <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{p.name}</span>
            <span style={{ fontWeight: 700, direction: 'ltr' }}>{render(p)}</span>
          </Link>
        ))}
      </div>
    </div>
  );
}

function CompareRow({ label, items, get, best, raw }: { label: string; items: Pub[]; get: (p: Pub) => string; best?: number; raw?: (p: Pub) => number }) {
  return (
    <tr>
      <td style={{ fontWeight: 600, color: 'var(--ih-text-muted)' }}>{label}</td>
      {items.map((p) => {
        const isBest = best != null && raw != null && raw(p) === best && items.length > 1;
        return <td key={p.id} style={{ direction: 'ltr', fontWeight: isBest ? 700 : 500, color: isBest ? 'var(--ih-success-ink)' : undefined }}>{get(p)}</td>;
      })}
    </tr>
  );
}

function Metric({ label, value, tone }: { label: string; value: string; tone?: 'success' | 'danger' }) {
  const color = tone === 'success' ? 'var(--ih-success-ink)' : tone === 'danger' ? 'var(--ih-danger-ink)' : 'var(--ih-text)';
  return (
    <div style={{ textAlign: 'center', background: 'var(--ih-surface-2, var(--ih-gray-100))', borderRadius: 8, padding: '.4rem .2rem' }}>
      <div style={{ fontWeight: 700, fontSize: '.9rem', direction: 'ltr', color }}>{value}</div>
      <div style={{ fontSize: '.64rem', color: 'var(--ih-text-muted)' }}>{label}</div>
    </div>
  );
}

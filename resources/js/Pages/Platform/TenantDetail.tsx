import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Icon } from '@/Components/Icon';
import { platformNav } from '@/lib/nav';
import { WorkspaceHeader, Kpi, Sec, StatusBadge } from '@/Components/ui';

interface Tenant { id: number; name: string; slug: string; type: string; status: string; statusLabel: string; statusTone: string; mode: string }
interface Stats { organizations: number; users: number; campaigns: number; hasSubscription: boolean }
interface Org { id: number; name: string; type: string; status: string; members: number }
interface Portals { agency: boolean; client: boolean; creator: boolean; partner: boolean }
interface Activity { action: string; actor: string | null; at: string | null }
interface PreviewCtx { userId: number; userName: string; entityLabel: string; entityId: number; organizationId: number | null; startHref: string }
interface PreviewPortal { portal: keyof Portals; label: string; suggested: PreviewCtx[]; total: number; hasMore: boolean }
interface NominationFeature { key: string; label: string; agency: boolean; client: boolean }
interface Features { influencer_nomination: NominationFeature }
interface Props { tenant: Tenant; stats: Stats; orgs: Org[]; portals: Portals; previewPortals: PreviewPortal[]; activity: Activity[]; features: Features }

const PORTAL_LABEL: Record<keyof Portals, string> = { agency: 'الوكالة', client: 'العميل', creator: 'صانع المحتوى', partner: 'الشريك' };
const n = (v: number) => v.toLocaleString('en-US');
const PER_PAGE = 10;

/**
 * مُنتقي سياق قابل للبحث والتصفيح (§P3-hardening §5) — يبلغ المالك **أيّ** سياق مصرَّح لا
 * الأوّل ٢٥. البحث والتصفيح عند الخادم؛ القائمة الأوّليّة «مقترَحة» صغيرة لا الكون كاملًا.
 */
function PortalContextPicker({ tenantId, pp }: { tenantId: number; pp: PreviewPortal }) {
  const [q, setQ] = useState('');
  const [items, setItems] = useState<PreviewCtx[]>(pp.suggested);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(pp.total);
  const [hasMore, setHasMore] = useState(pp.hasMore);
  const [loading, setLoading] = useState(false);
  const [searching, setSearching] = useState(false);
  const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);

  const fetchPage = useCallback(async (term: string, nextPage: number, append: boolean) => {
    setLoading(true);
    try {
      const url = `/platform/tenants/${tenantId}/contexts?portal=${pp.portal}&q=${encodeURIComponent(term)}&page=${nextPage}&perPage=${PER_PAGE}`;
      const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      if (!res.ok) return;
      const data: { items: PreviewCtx[]; total: number; hasMore: boolean } = await res.json();
      setItems((prev) => (append ? [...prev, ...data.items] : data.items));
      setTotal(data.total);
      setHasMore(data.hasMore);
      setPage(nextPage);
    } finally {
      setLoading(false);
    }
  }, [tenantId, pp.portal]);

  // بحث مُمهَل: يعيد للصفحة الأولى؛ الفراغ يعيد المقترَحات الأوّليّة بلا طلب.
  useEffect(() => {
    if (debounce.current) clearTimeout(debounce.current);
    if (q.trim() === '') {
      setSearching(false);
      setItems(pp.suggested); setTotal(pp.total); setHasMore(pp.hasMore); setPage(1);
      return;
    }
    setSearching(true);
    debounce.current = setTimeout(() => fetchPage(q, 1, false), 300);
    return () => { if (debounce.current) clearTimeout(debounce.current); };
  }, [q, fetchPage, pp.suggested, pp.total, pp.hasMore]);

  return (
    <div>
      <div style={{ fontWeight: 700, fontSize: '.82rem', marginBottom: '.4rem', display: 'flex', alignItems: 'center', gap: '.4rem' }}>
        <span>{pp.label}</span>
        <span style={{ color: 'var(--ih-text-muted)', fontWeight: 500, fontSize: '.72rem' }}>({n(total)})</span>
      </div>
      <div style={{ position: 'relative', marginBottom: '.5rem' }}>
        <input
          type="search" value={q} onChange={(e) => setQ(e.target.value)}
          placeholder={`ابحث بالاسم أو البريد أو ${pp.portal === 'agency' ? 'المؤسسة' : pp.portal === 'client' ? 'العميل' : pp.portal === 'creator' ? 'المبدع' : 'الوكالة'}…`}
          aria-label={`بحث في سياقات ${pp.label}`}
          className="field" style={{ width: '100%', fontSize: '.8rem', paddingInlineStart: '2rem' }}
        />
        <Icon name="search" size={14} style={{ position: 'absolute', insetInlineStart: '.6rem', top: '50%', transform: 'translateY(-50%)', opacity: .5 }} />
      </div>
      {items.length === 0 ? (
        <p className="pub-muted" style={{ fontSize: '.74rem' }}>{searching ? 'لا نتائج مطابقة.' : 'لا مستخدم مؤهَّل نشِط.'}</p>
      ) : (
        <div style={{ display: 'grid', gap: '.35rem' }}>
          {!searching && <div style={{ fontSize: '.68rem', color: 'var(--ih-text-muted)' }}>مقترَحة — ابحث للوصول لأيّ سياق</div>}
          {items.map((c) => (
            <div key={`${pp.portal}:${c.userId}:${c.entityId}:${c.organizationId ?? 0}`} className="ih-risk" style={{ alignItems: 'center' }}>
              <span style={{ fontWeight: 600 }}>{c.userName}</span>
              <span style={{ color: 'var(--ih-text-muted)', fontSize: '.72rem' }}>{c.entityLabel}</span>
              <span style={{ flex: 1 }} />
              <a href={c.startHref} className="btn btn-xs btn-primary" data-testid={`preview-${pp.portal}`}>▶ معاينة</a>
            </div>
          ))}
          {hasMore && (
            <button type="button" className="btn btn-xs btn-ghost" disabled={loading}
              onClick={() => fetchPage(q, page + 1, true)}>
              {loading ? 'جارٍ التحميل…' : 'تحميل المزيد'}
            </button>
          )}
        </div>
      )}
    </div>
  );
}

export default function TenantDetail({ tenant, stats, orgs, portals, previewPortals, activity, features }: Props) {
  const nom = features.influencer_nomination;
  const setNomination = (portal: 'agency' | 'client', enabled: boolean) => {
    router.post(`/platform/tenants/${tenant.id}/features/nomination`, { portal, enabled }, { preserveScroll: true });
  };
  return (
    <AppShell heading="مستأجر" nav={platformNav} portal="platform">
      <Head title={`${tenant.name} · المنصّة`} />

      <div style={{ marginBottom: '.6rem' }}>
        <Link href="/platform/tenants" className="btn btn-xs btn-ghost">→ كل المستأجرين</Link>
      </div>
      <WorkspaceHeader eyebrow={`مستأجر · ${tenant.slug}`} title={tenant.name} statusTone={tenant.statusTone} statusLabel={tenant.statusLabel} />

      <div className="ih-kpis" style={{ marginTop: '1rem' }}>
        <Kpi label="المؤسسات" icon="building-2" value={n(stats.organizations)} sub={tenant.type} />
        <Kpi label="المستخدمون" icon="users" value={n(stats.users)} sub="أعضاء نشِطون" />
        <Kpi label="الحملات" icon="megaphone" value={n(stats.campaigns)} sub="لهذا المستأجر" />
        <Kpi label="الاشتراك" icon="wallet" tone={stats.hasSubscription ? 'success' : 'warning'} value={stats.hasSubscription ? 'فعّال' : 'لا يوجد'} sub={tenant.mode} />
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: '1.2rem', alignItems: 'start' }}>
        <Sec title="البوّابات المتاحة" icon="layout-dashboard">
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: '.5rem' }}>
            {(Object.keys(PORTAL_LABEL) as (keyof Portals)[]).map((p) => (
              <span key={p} className="ih-tag" style={{ opacity: portals[p] ? 1 : .4 }}>
                {portals[p] ? '● ' : '○ '}{PORTAL_LABEL[p]}
              </span>
            ))}
          </div>
        </Sec>

        <Sec title="إتاحة الميزات (إدارة المنصّة)" icon="shield-check">
          <p style={{ fontSize: '.76rem', color: 'var(--ih-text-muted)', marginBottom: '.6rem' }}>
            التحكّم في إتاحة الميزات لهذا المستأجر من مصدر واحد. الإيقاف يُخفي الروابط ويمنع
            الوصول المباشر (403) — دون حذف أي بيان؛ إعادة التفعيل تُرجع نفس السجلّات.
          </p>
          <div className="ih-risk" style={{ alignItems: 'center' }} data-testid="feature-nomination">
            <span style={{ fontWeight: 600 }}>{nom.label}</span>
            <span style={{ color: 'var(--ih-text-muted)', fontSize: '.72rem', fontFamily: 'monospace', direction: 'ltr' }}>{nom.key}</span>
            <span style={{ flex: 1 }} />
            <button
              type="button"
              className={`btn btn-xs ${nom.agency ? 'btn-ghost' : 'btn-primary'}`}
              data-testid="toggle-nomination-agency"
              onClick={() => setNomination('agency', !nom.agency)}
            >
              {nom.agency ? 'الوكالة: مُفعّلة — أوقِف' : 'الوكالة: موقوفة — فعّل'}
            </button>
            <button
              type="button"
              className={`btn btn-xs ${nom.client ? 'btn-ghost' : 'btn-primary'}`}
              data-testid="toggle-nomination-client"
              onClick={() => setNomination('client', !nom.client)}
            >
              {nom.client ? 'العميل: مُفعّلة — أوقِف' : 'العميل: موقوفة — فعّل'}
            </button>
          </div>
        </Sec>

        <Sec title="معاينة البوّابات (قراءة فقط)" icon="eye">
          <p style={{ fontSize: '.76rem', color: 'var(--ih-text-muted)', marginBottom: '.6rem' }}>
            اختر مستخدمًا حقيقيًّا مؤهَّلًا لمعاينة النظام كما يراه — بلا كلمة مروره، وبلا أي إجراء.
            كل معاينة موقَّعة، مؤقّتة، ومُدقَّقة (الفاعل = مالك المنصّة).
          </p>
          {previewPortals.length === 0 ? (
            <p className="pub-muted">لا بوّابة متاحة للمعاينة في هذا المستأجر.</p>
          ) : (
            <div style={{ display: 'grid', gap: '1rem' }}>
              {previewPortals.map((pp) => (
                <PortalContextPicker key={pp.portal} tenantId={tenant.id} pp={pp} />
              ))}
            </div>
          )}
        </Sec>

        <Sec title={`المؤسسات (${n(orgs.length)})`} icon="building-2">
          <div style={{ display: 'grid', gap: '.4rem' }}>
            {orgs.length === 0 ? <p className="pub-muted">لا مؤسسات.</p> :
              orgs.map((o) => (
                <div key={o.id} className="ih-risk" style={{ alignItems: 'center' }}>
                  <span style={{ fontWeight: 600 }}>{o.name}</span>
                  <span style={{ color: 'var(--ih-text-muted)', fontSize: '.72rem' }}>{o.type} · {n(o.members)} عضو</span>
                  <span style={{ flex: 1 }} />
                  <StatusBadge tone="neutral" label={o.status} />
                </div>
              ))}
          </div>
        </Sec>

        <Sec title="نشاط المستأجر الأخير" icon="activity">
          <div style={{ display: 'grid', gap: '.35rem' }}>
            {activity.length === 0 ? <p className="pub-muted">لا نشاط.</p> :
              activity.map((a, i) => (
                <div key={i} style={{ fontSize: '.78rem', display: 'flex', gap: '.5rem' }}>
                  <span style={{ direction: 'ltr', color: 'var(--ih-text-muted)', minWidth: 92 }}>{a.at}</span>
                  <span style={{ fontFamily: 'monospace', direction: 'ltr' }}>{a.action}</span>
                  <span style={{ color: 'var(--ih-text-muted)' }}>{a.actor ?? '—'}</span>
                </div>
              ))}
          </div>
        </Sec>
      </div>
    </AppShell>
  );
}

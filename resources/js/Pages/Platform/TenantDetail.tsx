import { Head, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { platformNav } from '@/lib/nav';
import { WorkspaceHeader, Kpi, Sec, StatusBadge } from '@/Components/ui';

interface Tenant { id: number; name: string; slug: string; type: string; status: string; statusLabel: string; statusTone: string; mode: string }
interface Stats { organizations: number; users: number; campaigns: number; hasSubscription: boolean }
interface Org { id: number; name: string; type: string; status: string; members: number }
interface Portals { agency: boolean; client: boolean; creator: boolean; partner: boolean }
interface Activity { action: string; actor: string | null; at: string | null }
interface Props { tenant: Tenant; stats: Stats; orgs: Org[]; portals: Portals; activity: Activity[] }

const PORTAL_LABEL: Record<keyof Portals, string> = { agency: 'الوكالة', client: 'العميل', creator: 'صانع المحتوى', partner: 'الشريك' };
const n = (v: number) => v.toLocaleString('en-US');

export default function TenantDetail({ tenant, stats, orgs, portals, activity }: Props) {
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
          <p style={{ fontSize: '.76rem', color: 'var(--ih-text-muted)', marginBottom: '.6rem' }}>
            المعاينة داخل كل بوّابة (بمستخدم حقيقي) تُضاف في المرحلة القادمة.
          </p>
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: '.5rem' }}>
            {(Object.keys(PORTAL_LABEL) as (keyof Portals)[]).map((p) => (
              <span key={p} className="ih-tag" style={{ opacity: portals[p] ? 1 : .4 }}>
                {portals[p] ? '● ' : '○ '}{PORTAL_LABEL[p]}
              </span>
            ))}
          </div>
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

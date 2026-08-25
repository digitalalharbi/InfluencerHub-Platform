import { Head } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { platformNav } from '@/lib/nav';
import { ListHead, Kpi, Sec, StatusBadge } from '@/Components/ui';
import { Icon } from '@/Components/Icon';

interface Stats {
  tenants: number; activeTenants: number; organizations: number;
  users: number; activeUsers: number; campaigns: number; activeSubscriptions: number;
}
interface StatusRow { status: string; label: string; tone: string; count: number }
interface TenantRow { id: number; name: string; slug: string; type: string; status: string; statusLabel: string; statusTone: string; orgs: number }
interface Activity { action: string; actor: string | null; tenantId: number | null; at: string | null }
interface SecurityEvent { action: string; actor: string | null; ip: string | null; at: string | null }
interface Props {
  stats: Stats;
  tenantsByStatus: StatusRow[];
  recentTenants: TenantRow[];
  recentActivity: Activity[];
  securityEvents: SecurityEvent[];
  links: { tenants: string; subscriptions: string; audit: string; systemHealth: string; integrations: string };
}

const n = (v: number) => v.toLocaleString('en-US');

export default function ControlCenter({ stats, tenantsByStatus, recentTenants, recentActivity, securityEvents, links }: Props) {
  return (
    <AppShell heading="مركز التحكّم" nav={platformNav} portal="platform">
      <Head title="مركز تحكّم المنصّة" />
      <ListHead eyebrow="المنصّة" title="مركز تحكّم المالك" sub="نظرة عابرة للمستأجرين من بيانات فعلية — إدارة النظام بالكامل من مكان واحد." />

      <div className="ih-kpis">
        <Kpi label="المستأجرون" icon="building-2" value={n(stats.tenants)} sub={`${n(stats.activeTenants)} نشِط`} />
        <Kpi label="المؤسسات" icon="building-2" value={n(stats.organizations)} sub="عبر المنصّة" />
        <Kpi label="المستخدمون" icon="users" value={n(stats.users)} sub={`${n(stats.activeUsers)} نشِط`} />
        <Kpi label="الحملات" icon="megaphone" value={n(stats.campaigns)} sub="كل المستأجرين" />
        <Kpi label="الاشتراكات الفعّالة" icon="wallet" value={n(stats.activeSubscriptions)} sub="تجربة/نشِط" />
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: '1.2rem', alignItems: 'start' }}>
        <Sec title="المستأجرون حسب الحالة" icon="building-2">
          {tenantsByStatus.length === 0 ? <p className="pub-muted">لا مستأجرين.</p> : (
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '.5rem' }}>
              {tenantsByStatus.map((r) => (
                <span key={r.status} className="ih-chip"><StatusBadge tone={r.tone} label={r.label} /> <span className="ih-chip__count">{n(r.count)}</span></span>
              ))}
            </div>
          )}
        </Sec>

        <Sec title="أحدث المستأجرين" icon="building-2" link={{ label: 'إدارة المستأجرين', href: links.tenants }}>
          <div style={{ display: 'grid', gap: '.4rem' }}>
            {recentTenants.map((t) => (
              <div key={t.id} className="ih-risk" style={{ alignItems: 'center' }}>
                <span style={{ fontWeight: 600 }}>{t.name}</span>
                <span style={{ color: 'var(--ih-text-muted)', fontSize: '.72rem' }}>{t.type} · {n(t.orgs)} مؤسسة</span>
                <span style={{ flex: 1 }} />
                <StatusBadge tone={t.statusTone} label={t.statusLabel} />
              </div>
            ))}
          </div>
        </Sec>

        <Sec title="نشاط المنصّة الأخير" icon="activity" link={{ label: 'سجل التدقيق', href: links.audit }}>
          <div style={{ display: 'grid', gap: '.35rem' }}>
            {recentActivity.length === 0 ? <p className="pub-muted">لا نشاط.</p> :
              recentActivity.map((a, i) => (
                <div key={i} style={{ fontSize: '.78rem', display: 'flex', gap: '.5rem' }}>
                  <span style={{ direction: 'ltr', color: 'var(--ih-text-muted)', minWidth: 92 }}>{a.at}</span>
                  <span style={{ fontFamily: 'monospace', direction: 'ltr' }}>{a.action}</span>
                  <span style={{ color: 'var(--ih-text-muted)' }}>{a.actor ?? '—'}</span>
                </div>
              ))}
          </div>
        </Sec>

        <Sec title="أحداث الأمان" icon="shield-check">
          <div style={{ display: 'grid', gap: '.35rem' }}>
            {securityEvents.length === 0 ? <p className="pub-muted">لا أحداث أمنية حديثة.</p> :
              securityEvents.map((e, i) => (
                <div key={i} style={{ fontSize: '.78rem', display: 'flex', gap: '.5rem' }}>
                  <span style={{ direction: 'ltr', color: 'var(--ih-text-muted)', minWidth: 92 }}>{e.at}</span>
                  <span style={{ fontFamily: 'monospace', direction: 'ltr' }}>{e.action}</span>
                  <span style={{ color: 'var(--ih-text-muted)' }}>{e.actor ?? '—'}</span>
                  {e.ip && <span style={{ direction: 'ltr', color: 'var(--ih-text-muted)' }}>{e.ip}</span>}
                </div>
              ))}
          </div>
        </Sec>
      </div>

      <div style={{ display: 'flex', gap: '.6rem', flexWrap: 'wrap', marginTop: '1.2rem' }}>
        <a href={links.subscriptions} className="btn btn-sm btn-outline"><Icon name="wallet" size={14} /> الاشتراكات</a>
        <a href={links.systemHealth} className="btn btn-sm btn-outline"><Icon name="activity" size={14} /> صحّة النظام</a>
        <a href={links.integrations} className="btn btn-sm btn-outline"><Icon name="plug" size={14} /> التكاملات</a>
      </div>
    </AppShell>
  );
}

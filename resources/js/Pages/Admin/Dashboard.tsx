import { Head, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { adminNav } from '@/lib/nav';
import { DonutChart, Kpi, Sec, StatusBadge } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';

interface Tenant { id: number; name: string; slug: string; mode: string; status: string; statusLabel: string; statusTone: string; orgs: number }
interface Audit { action: string; actor: string | null; at: string | null }
interface Props {
  stats: { tenants: number; orgs: number; users: number; activeSubs: number; plans: number };
  tenantsByStatus: { status: string; label: string; tone: string; count: number }[];
  recentTenants: Tenant[];
  recentAudit: Audit[];
}


export default function AdminDashboard({ stats, tenantsByStatus, recentTenants, recentAudit }: Props) {
  return (
    <AppShell heading="لوحة التحكم" nav={adminNav} portal="admin" wsName="إدارة المنصّة" wsPlan="مدير النظام" brand="InfluencerHub">
      <Head title="إدارة المنصّة" />

      <div className="ih-listhead">
        <div>
          <div className="ih-listhead__eyebrow">مدير النظام · SaaS</div>
          <h1 className="ih-listhead__title">نظرة عامة على المنصّة</h1>
          <div className="ih-listhead__sub">إشراف عبر المستأجرين — عرض فقط.</div>
        </div>
      </div>

      <div className="ih-kpis">
        <Kpi label="المستأجرون" icon="building-2" value={stats.tenants.toLocaleString('en-US')} sub="إجمالي" href={u("/tenants")} />
        <Kpi label="المؤسسات" icon="building-2" value={stats.orgs.toLocaleString('en-US')} sub="وكالات/عملاء" />
        <Kpi label="المستخدمون" icon="users" value={stats.users.toLocaleString('en-US')} sub="إجمالي" />
        <Kpi label="اشتراكات نشطة" icon="wallet" tone="success" value={stats.activeSubs.toLocaleString('en-US')} sub="trialing/active" href={u("/subscriptions")} />
      </div>

      <div className="card" style={{ padding: '.8rem 1rem', marginBottom: '1.2rem', borderInlineStart: '3px solid var(--ih-info)', background: 'var(--ih-info-soft)', color: 'var(--ih-info-ink)', fontSize: '.82rem' }}>
        <Icon name="shield-check" size={14} /> لوحة إشراف للقراءة فقط — لا انتحال هوية ولا وصول لبيانات المستأجرين التشغيلية ولا إجراءات هدّامة من هنا.
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0, 1.4fr) minmax(0, 1fr)', gap: '1.2rem', alignItems: 'start' }} className="ih-settings-grid">
        <Sec title="أحدث المستأجرين" icon="building-2" link={{ href: u('/tenants'), label: 'الكل' }}>
          <div className="ih-dt-wrap"><div className="ih-dt-scroll">
            <table className="ih-dt">
              <thead><tr><th>المستأجر</th><th>النمط</th><th>المؤسسات</th><th>الحالة</th></tr></thead>
              <tbody>
                {recentTenants.map((t) => (
                  <tr key={t.id}>
                    <td>
                      <Link href={u(`/tenants?q=${t.slug}`)} style={{ fontWeight: 600, color: 'var(--ih-primary)', textDecoration: 'none' }}>{t.name}</Link>
                      <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{t.slug}</div>
                    </td>
                    <td style={{ direction: 'ltr' }}>{t.mode}</td>
                    <td style={{ direction: 'ltr' }}>{t.orgs.toLocaleString('en-US')}</td>
                    <td><StatusBadge tone={t.statusTone} label={t.statusLabel} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div></div>
        </Sec>

        <div style={{ display: 'grid', gap: '1.2rem' }}>
          <Sec title="المستأجرون حسب الحالة" icon="activity">
            <div className="ih-sec__body">
              <DonutChart
                centerValue={String(stats.tenants)} centerLabel="مستأجر"
                slices={tenantsByStatus.map((x, i) => ({
                  label: x.label, value: x.count,
                  color: ['var(--ih-success)', 'var(--ih-primary)', 'var(--ih-warning)', 'var(--ih-danger)', 'var(--ih-gray-400)'][i] ?? 'var(--ih-gray-300)',
                }))} />
            </div>
          </Sec>

          <Sec title="أحدث التدقيق" icon="file-text" link={{ href: u('/audit'), label: 'الكل' }}>
            {recentAudit.length === 0 ? (
              <div style={{ fontSize: '.84rem', color: 'var(--ih-text-muted)' }}>لا سجلات.</div>
            ) : (
              <div style={{ display: 'grid', gap: '.5rem' }}>
                {recentAudit.map((a, i) => (
                  <div key={i} style={{ fontSize: '.8rem', borderBottom: '1px solid var(--ih-border)', paddingBottom: '.4rem' }}>
                    <div style={{ fontWeight: 600, direction: 'ltr' }}>{a.action}</div>
                    <div style={{ color: 'var(--ih-text-muted)', display: 'flex', justifyContent: 'space-between' }}>
                      <span>{a.actor ?? '—'}</span><span style={{ direction: 'ltr' }}>{a.at}</span>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </Sec>
        </div>
      </div>
    </AppShell>
  );
}

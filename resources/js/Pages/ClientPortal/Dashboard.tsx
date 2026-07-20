import { Head, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { clientNav } from '@/lib/nav';
import { Kpi, Sec, StatusBadge, Bar } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';

interface Pending { key: string; label: string; count: number; icon: string; link: string }
interface RecentCampaign { id: number; name: string; number: string; status: string; statusLabel: string; statusTone: string; deliverables: number; budgetMinor: number }
interface Props {
  client: { name: string; sector: string | null; completion: number; brands: number; team: number; documents: number; contacts: number };
  pending: Pending[];
  stats: { activeCampaigns: number; brands: number; team: number; documents: number };
  recent: RecentCampaign[];
}

const money = (m: number) => (m / 100).toLocaleString('en-US') + ' ر.س';

export default function ClientDashboard({ client, pending, stats, recent }: Props) {
  const actionable = pending.filter((p) => p.count > 0);

  return (
    <AppShell heading="لوحة التحكم" nav={clientNav} portal="client" wsName={client.name} wsPlan="بوابة العميل">
      <Head title={`${client.name} — البوابة`} />

      <div className="ih-listhead">
        <div>
          <div className="ih-listhead__eyebrow">بوابة العميل</div>
          <h1 className="ih-listhead__title">مرحبًا، {client.name}</h1>
          <div className="ih-listhead__sub">متابعة حملاتك وموافقاتك وعقودك في مكان واحد.</div>
        </div>
      </div>

      {/* ما يحتاج قرارك الآن */}
      {actionable.length > 0 ? (
        <div className="ih-nba" style={{ alignItems: 'stretch', flexWrap: 'wrap' }}>
          <span className="ih-nba__icon"><Icon name="clipboard-check" size={22} /></span>
          <div className="ih-nba__body" style={{ flex: 1, minWidth: 200 }}>
            <div className="ih-nba__eyebrow">يحتاج قرارك الآن</div>
            <div className="ih-nba__title">{actionable.reduce((s, p) => s + p.count, 0)} عنصرًا بانتظارك</div>
          </div>
          <div style={{ display: 'flex', gap: '.5rem', flexWrap: 'wrap' }}>
            {actionable.map((p) => (
              <Link key={p.key} href={u(p.link)} className="btn btn-sm btn-outline" style={{ display: 'inline-flex', alignItems: 'center', gap: '.4rem' }}>
                <Icon name={p.icon as never} size={14} /> {p.label} <span className="ih-nav__badge">{p.count}</span>
              </Link>
            ))}
          </div>
        </div>
      ) : (
        <div className="card" style={{ padding: '.9rem 1.1rem', marginBottom: '1.2rem', borderInlineStart: '3px solid var(--ih-success)', background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)', fontSize: '.86rem' }}>
          <Icon name="shield-check" size={15} /> لا عناصر بانتظار قرارك — كل شيء محدَّث.
        </div>
      )}

      <div className="ih-kpis">
        <Kpi label="حملات نشطة" icon="megaphone" value={stats.activeCampaigns.toLocaleString('en-US')} sub="قيد التنفيذ" href={u("/campaigns")} />
        <Kpi label="العلامات" icon="bookmark" value={stats.brands.toLocaleString('en-US')} sub="مسجّلة" href={u("/brands")} />
        <Kpi label="أعضاء الفريق" icon="users" value={stats.team.toLocaleString('en-US')} sub="نشطون" href={u("/team")} />
        <Kpi label="المستندات" icon="file-text" value={stats.documents.toLocaleString('en-US')} sub="مرفوعة" href={u("/documents")} />
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0, 1.5fr) minmax(0, 1fr)', gap: '1.2rem', alignItems: 'start', marginTop: '1.4rem' }} className="ih-settings-grid">
        <Sec title="أحدث الحملات" icon="megaphone" link={{ href: u('/campaigns'), label: 'عرض الكل' }}>
          {recent.length === 0 ? (
            <div style={{ padding: '1.6rem', textAlign: 'center', color: 'var(--ih-text-muted)', fontSize: '.86rem' }}>لا حملات بعد.</div>
          ) : (
            <div className="ih-dt-wrap"><div className="ih-dt-scroll">
              <table className="ih-dt">
                <thead><tr><th>الحملة</th><th>الحالة</th><th>المخرجات</th><th>الميزانية</th></tr></thead>
                <tbody>
                  {recent.map((cm) => (
                    <tr key={cm.id}>
                      <td>
                        <Link href={u(`/campaigns/${cm.id}`)} style={{ fontWeight: 600, color: 'var(--ih-primary)', textDecoration: 'none' }}>{cm.name}</Link>
                        <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{cm.number}</div>
                      </td>
                      <td><StatusBadge tone={cm.statusTone} label={cm.statusLabel} /></td>
                      <td style={{ direction: 'ltr' }}>{cm.deliverables.toLocaleString('en-US')}</td>
                      <td style={{ direction: 'ltr', fontWeight: 600 }}>{cm.budgetMinor ? money(cm.budgetMinor) : '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div></div>
          )}
        </Sec>

        <Sec title="اكتمال ملفك" icon="gauge">
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', marginBottom: '.4rem' }}>
            <span style={{ fontSize: '.84rem', fontWeight: 600 }}>البيانات الأساسية</span>
            <span style={{ fontSize: '.82rem', color: 'var(--ih-text-muted)', direction: 'ltr', fontWeight: 700 }}>{client.completion}٪</span>
          </div>
          <Bar pct={client.completion} />
          <div style={{ marginTop: '.7rem', fontSize: '.8rem', color: 'var(--ih-text-muted)' }}>
            {client.completion >= 100 ? 'ملفك مكتمل — شكرًا لك.' : 'أكمل بيانات ملفك ليسهل على فريقنا خدمتك بدقّة.'}
          </div>
          <div style={{ marginTop: '.9rem', display: 'grid', gap: '.5rem' }}>
            {[['العلامات', client.brands], ['جهات الاتصال', client.contacts], ['المستندات', client.documents], ['أعضاء الفريق', client.team]].map(([label, val]) => (
              <div key={label as string} style={{ display: 'flex', justifyContent: 'space-between', fontSize: '.82rem', borderBottom: '1px solid var(--ih-border)', paddingBottom: '.4rem' }}>
                <span style={{ color: 'var(--ih-text-muted)' }}>{label}</span>
                <span style={{ fontWeight: 600, direction: 'ltr' }}>{(val as number).toLocaleString('en-US')}</span>
              </div>
            ))}
          </div>
        </Sec>
      </div>
    </AppShell>
  );
}

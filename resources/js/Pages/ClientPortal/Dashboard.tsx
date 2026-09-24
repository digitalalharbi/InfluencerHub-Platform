import { Head, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { clientNav } from '@/lib/nav';
import { Kpi, Sec, StatusBadge } from '@/Components/ui';
import { ProgressRing } from '@/Components/Charts';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';
import { useT } from '@/lib/i18n';

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
  const t = useT();
  const actionable = pending.filter((p) => p.count > 0);

  return (
    <AppShell heading={t('dashboard.title')} nav={clientNav} portal="client" wsName={client.name} wsPlan={t('client_dashboard.eyebrow')}>
      <Head title={t('client_dashboard.head', { name: client.name })} />

      <div className="ih-listhead">
        <div>
          <div className="ih-listhead__eyebrow">{t('client_dashboard.eyebrow')}</div>
          <h1 className="ih-listhead__title">{t('client_dashboard.greeting', { name: client.name })}</h1>
          <div className="ih-listhead__sub">{t('client_dashboard.sub')}</div>
        </div>
      </div>

      {/* ما يحتاج قرارك الآن */}
      {actionable.length > 0 ? (
        <div className="ih-nba" style={{ alignItems: 'stretch', flexWrap: 'wrap' }}>
          <span className="ih-nba__icon"><Icon name="clipboard-check" size={22} /></span>
          <div className="ih-nba__body" style={{ flex: 1, minWidth: 200 }}>
            <div className="ih-nba__eyebrow">{t('client_dashboard.needs_decision')}</div>
            <div className="ih-nba__title">{t('client_dashboard.awaiting_you', { n: actionable.reduce((s, p) => s + p.count, 0) })}</div>
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
          <Icon name="shield-check" size={15} /> {t('client_dashboard.all_clear')}
        </div>
      )}

      <div className="ih-kpis">
        <Kpi label={t('client_dashboard.kpi_active_campaigns')} icon="megaphone" value={stats.activeCampaigns.toLocaleString('en-US')} sub={t('client_dashboard.kpi_active_campaigns_sub')} href={u("/campaigns")} />
        <Kpi label={t('client_dashboard.kpi_brands')} icon="bookmark" value={stats.brands.toLocaleString('en-US')} sub={t('client_dashboard.kpi_brands_sub')} href={u("/brands")} />
        <Kpi label={t('client_dashboard.kpi_team')} icon="users" value={stats.team.toLocaleString('en-US')} sub={t('client_dashboard.kpi_team_sub')} href={u("/team")} />
        <Kpi label={t('client_dashboard.kpi_documents')} icon="file-text" value={stats.documents.toLocaleString('en-US')} sub={t('client_dashboard.kpi_documents_sub')} href={u("/documents")} />
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0, 1.5fr) minmax(0, 1fr)', gap: '1.2rem', alignItems: 'start', marginTop: '1.4rem' }} className="ih-settings-grid">
        <Sec title={t('client_dashboard.recent_campaigns')} icon="megaphone" link={{ href: u('/campaigns'), label: t('client_dashboard.view_all') }}>
          {recent.length === 0 ? (
            <div style={{ padding: '1.6rem', textAlign: 'center', color: 'var(--ih-text-muted)', fontSize: '.86rem' }}>{t('client_dashboard.no_campaigns')}</div>
          ) : (
            <div className="ih-dt-wrap"><div className="ih-dt-scroll">
              <table className="ih-dt">
                <thead><tr><th>{t('client_dashboard.th_campaign')}</th><th>{t('client_dashboard.th_status')}</th><th>{t('client_dashboard.th_deliverables')}</th><th>{t('client_dashboard.th_budget')}</th></tr></thead>
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

        <Sec title={t('client_dashboard.completion_title')} icon="gauge">
          <div style={{ display: 'flex', alignItems: 'center', gap: '1rem', flexWrap: 'wrap' }}>
            <ProgressRing value={client.completion} size={96} label={t('client_dashboard.completion_basics')} tone={client.completion >= 100 ? 'success' : 'primary'} />
            <div style={{ flex: 1, minWidth: 150, fontSize: '.8rem', color: 'var(--ih-text-muted)' }}>
              {client.completion >= 100 ? t('client_dashboard.completion_done') : t('client_dashboard.completion_todo')}
            </div>
          </div>
          <div style={{ marginTop: '.9rem', display: 'grid', gap: '.5rem' }}>
            {[[t('client_dashboard.field_brands'), client.brands], [t('client_dashboard.field_contacts'), client.contacts], [t('client_dashboard.field_documents'), client.documents], [t('client_dashboard.field_team'), client.team]].map(([label, val]) => (
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

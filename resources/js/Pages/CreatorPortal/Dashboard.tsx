import { Head, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { creatorNav } from '@/lib/nav';
import { Kpi, Sec, StatusBadge } from '@/Components/ui';
import { ProgressRing } from '@/Components/Charts';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';
import { useT } from '@/lib/i18n';

interface Pending { key: string; label: string; count: number; icon: string; link: string }
interface RecentCollab { id: number; number: string; campaign: string | null; client: string | null; status: string; statusLabel: string; statusTone: string; feeMinor: number }
interface Props {
  creator: { name: string; handle: string | null; platform: string | null; verified: boolean; followers: number };
  pending: Pending[];
  earnings: { paidMinor: number; openMinor: number };
  recent: RecentCollab[];
}

const money = (m: number) => (m / 100).toLocaleString('en-US') + ' ر.س';
const fmt = (n: number) => n >= 1000 ? Math.round(n / 1000).toLocaleString('en-US') + 'K' : n.toLocaleString('en-US');

export default function CreatorDashboard({ creator, pending, earnings, recent }: Props) {
  const t = useT();
  const actionable = pending.filter((p) => p.count > 0);
  const subText = creator.platform
    ? t('creator_dashboard.sub', { handle: creator.handle ?? '', platform: creator.platform, followers: fmt(creator.followers) })
    : t('creator_dashboard.sub_no_platform', { handle: creator.handle ?? '', followers: fmt(creator.followers) });

  return (
    <AppShell heading={t('dashboard.title')} nav={creatorNav} portal="creator" wsName={creator.name} wsPlan={t('creator_dashboard.eyebrow')}>
      <Head title={t('creator_dashboard.head', { name: creator.name })} />

      <div className="ih-listhead">
        <div>
          <div className="ih-listhead__eyebrow">{t('creator_dashboard.eyebrow')}</div>
          <h1 className="ih-listhead__title" style={{ display: 'flex', alignItems: 'center', gap: '.4rem' }}>
            {t('creator_dashboard.greeting', { name: creator.name })}{creator.verified && <Icon name="shield-check" size={18} />}
          </h1>
          <div className="ih-listhead__sub" style={{ direction: 'ltr' }}>
            {subText}
          </div>
        </div>
      </div>

      {actionable.length > 0 ? (
        <div className="ih-nba" style={{ alignItems: 'stretch', flexWrap: 'wrap' }}>
          <span className="ih-nba__icon"><Icon name="rocket" size={22} /></span>
          <div className="ih-nba__body" style={{ flex: 1, minWidth: 200 }}>
            <div className="ih-nba__eyebrow">{t('creator_dashboard.tasks_now')}</div>
            <div className="ih-nba__title">{t('creator_dashboard.needs_action', { n: actionable.reduce((s, p) => s + p.count, 0) })}</div>
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
          <Icon name="shield-check" size={15} /> {t('creator_dashboard.all_clear')}
        </div>
      )}

      <div className="ih-kpis">
        <Kpi label={t('creator_dashboard.kpi_paid')} icon="wallet" tone="success" value={money(earnings.paidMinor)} sub={t('creator_dashboard.kpi_paid_sub')} href={u("/payouts")} />
        <Kpi label={t('creator_dashboard.kpi_open')} icon="wallet" tone={earnings.openMinor ? 'warning' : undefined} value={money(earnings.openMinor)} sub={t('creator_dashboard.kpi_open_sub')} href={u("/payouts")} />
        <Kpi label={t('creator_dashboard.kpi_followers')} icon="users" value={fmt(creator.followers)} sub={t('creator_dashboard.kpi_followers_sub')} />
        <Kpi label={t('creator_dashboard.kpi_verification')} icon="shield-check" tone={creator.verified ? 'success' : 'warning'} value={creator.verified ? t('creator_dashboard.verified') : t('creator_dashboard.unverified')} sub={t('creator_dashboard.kpi_verification_sub')} />
      </div>

      {/* نظرة الأرباح — كم سُدِّد من إجمالي المستحق (بيانات فعلية) */}
      {(earnings.paidMinor + earnings.openMinor) > 0 && (() => {
        const total = earnings.paidMinor + earnings.openMinor;
        const pct = Math.round((earnings.paidMinor / total) * 100);
        return (
          <div className="card" style={{ padding: '1.1rem 1.3rem', marginBottom: '1.2rem', display: 'flex', alignItems: 'center', gap: '1.4rem', flexWrap: 'wrap' }}>
            <ProgressRing value={pct} size={96} label={t('creator_dashboard.earnings_settled')} tone={pct >= 100 ? 'success' : 'primary'} />
            <div style={{ flex: 1, minWidth: 180, display: 'grid', gap: '.5rem', fontSize: '.85rem' }}>
              <div style={{ fontWeight: 800, fontSize: '.95rem' }}>{t('creator_dashboard.earnings_headline', { pct, total: money(total) })}</div>
              <div style={{ display: 'flex', alignItems: 'center', gap: '.5rem' }}>
                <span style={{ width: 10, height: 10, borderRadius: 3, background: 'var(--ih-success-700, #067647)', flexShrink: 0 }} />
                <span style={{ color: 'var(--ih-text-muted)', flex: 1 }}>{t('creator_dashboard.earnings_paid')}</span>
                <span style={{ fontWeight: 700, direction: 'ltr' }}>{money(earnings.paidMinor)}</span>
              </div>
              <div style={{ display: 'flex', alignItems: 'center', gap: '.5rem' }}>
                <span style={{ width: 10, height: 10, borderRadius: 3, background: 'var(--ih-warning-ink, #B54708)', flexShrink: 0 }} />
                <span style={{ color: 'var(--ih-text-muted)', flex: 1 }}>{t('creator_dashboard.earnings_open')}</span>
                <span style={{ fontWeight: 700, direction: 'ltr' }}>{money(earnings.openMinor)}</span>
              </div>
            </div>
          </div>
        );
      })()}

      <Sec title={t('creator_dashboard.recent_collabs')} icon="git-merge" link={{ href: u('/collaborations'), label: t('creator_dashboard.view_all') }}>
        {recent.length === 0 ? (
          <div style={{ padding: '1.6rem', textAlign: 'center', color: 'var(--ih-text-muted)', fontSize: '.86rem' }}>{t('creator_dashboard.no_collabs')}</div>
        ) : (
          <div className="ih-dt-wrap"><div className="ih-dt-scroll">
            <table className="ih-dt">
              <thead><tr><th>{t('creator_dashboard.th_collab')}</th><th>{t('creator_dashboard.th_client')}</th><th>{t('creator_dashboard.th_status')}</th><th>{t('creator_dashboard.th_fee')}</th></tr></thead>
              <tbody>
                {recent.map((cl) => (
                  <tr key={cl.id}>
                    <td>
                      <Link href={u(`/collaborations/${cl.id}`)} style={{ fontWeight: 600, color: 'var(--ih-primary)', textDecoration: 'none' }}>{cl.campaign ?? cl.number}</Link>
                      <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{cl.number}</div>
                    </td>
                    <td>{cl.client ?? '—'}</td>
                    <td><StatusBadge tone={cl.statusTone} label={cl.statusLabel} /></td>
                    <td style={{ direction: 'ltr', fontWeight: 600 }}>{cl.feeMinor ? money(cl.feeMinor) : '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div></div>
        )}
      </Sec>
    </AppShell>
  );
}

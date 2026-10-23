import { Head, usePage, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppShell from '@/Layouts/AppShell';
import { StatusBadge, numFmt, sarShort } from '@/Components/ui';
import { ProgressRing, Donut, AreaChart } from '@/Components/Charts';
import { Icon, type IconName } from '@/Components/Icon';
import type { SharedProps } from '@/types';
import { u } from '@/lib/href';
import { useT, useLocale } from '@/lib/i18n';

interface Brief { tasks: number; approvals: number; overdue: number; total: number }
interface WorkItem {
  key: string; title: string; entity: string; reason: string;
  prio: 'overdue' | 'critical' | 'today' | 'approval' | 'soon' | 'normal';
  prioLabel: string; due: string | null; sla: boolean; count: number | null;
  actionLabel: string; href: string;
}
interface TeamMember { id: number; name: string; role: string; open: number; breached: number }
interface Team { members: TeamMember[]; unassigned: number; breached: number }
interface ActiveCampaign {
  id: number; name: string; client: string | null; brand: string | null;
  statusTone: string; statusLabel: string; progress: number; creators: number; deliverables: number; isLate: boolean;
}
interface TopClient { id: number; name: string; revenueMinor: number; activeCampaigns: number; brands: number; isVip: boolean }
interface TrendPoint { label: string; value: number; content: number; collabs: number }
interface Overview {
  trend: TrendPoint[];
  kpis: {
    clientsTotal: number; revenueMinor: number; profitMinor: number; margin: number;
    activeCampaigns: number; awaitingClient: number; late: number; campaignsActive: number;
    pendingPayoutMinor: number; pendingPayouts: number;
    creatorsTotal: number; creatorsVerified: number; creatorsTierA: number; avgCompletion: number;
  };
  topClients: TopClient[]; activeCampaigns: ActiveCampaign[];
}
interface SetupStep { key: string; title: string; why: string; done: boolean; count: number; href: string; action: string; optional: boolean }
interface Setup {
  steps: SetupStep[]; doneCount: number; total: number;
  requiredDone: number; requiredTotal: number; isSettingUp: boolean;
  next: { key: string; title: string; href: string; action: string } | null;
}
type DashboardProps = SharedProps & {
  role: string | null; canSeeTeam: boolean; brief: Brief; myWork: WorkItem[];
  team: Team | null; overview?: Overview; setup?: Setup;
};

const PRIO: Record<WorkItem['prio'], { bg: string; fg: string; dot: string }> = {
  overdue: { bg: 'var(--ih-danger-soft)', fg: 'var(--ih-danger-ink)', dot: 'var(--ih-danger)' },
  critical: { bg: 'var(--ih-danger-soft)', fg: 'var(--ih-danger-ink)', dot: 'var(--ih-danger)' },
  today: { bg: 'var(--ih-warning-soft)', fg: 'var(--ih-warning-ink)', dot: 'var(--ih-warning)' },
  approval: { bg: 'var(--ih-primary-soft)', fg: 'var(--ih-primary-700)', dot: 'var(--ih-primary)' },
  soon: { bg: 'var(--ih-info-soft)', fg: 'var(--ih-info-ink)', dot: 'var(--ih-info)' },
  normal: { bg: 'var(--ih-surface-sunken)', fg: 'var(--ih-text-secondary)', dot: 'var(--ih-text-muted)' },
};

/* ── بطاقات مميّزة الوظيفة (كلٌّ يبدو مختلفًا) ─────────────────────── */

/** مؤشّر مضغوط: رقم كبير + أيقونة + حالة دقيقة. */
function KpiTile({ label, value, icon, tone = 'neutral', hint, href }: {
  label: string; value: ReactNode; icon: IconName;
  tone?: 'neutral' | 'primary' | 'warning' | 'danger' | 'success' | 'accent'; hint?: ReactNode; href?: string;
}) {
  const body = (
    <>
      <span className={`ih-tile__icon ih-tile__icon--${tone}`}><Icon name={icon} size={18} /></span>
      <span className="ih-tile__label">{label}</span>
      <span className="ih-tile__value">{value}</span>
      {hint != null && <span className="ih-tile__hint">{hint}</span>}
    </>
  );
  return href
    ? <Link href={href} className="ih-tile ih-tile--link">{body}</Link>
    : <div className="ih-tile">{body}</div>;
}

/** بطاقة محتواها رسم/كتلة بصرية كبيرة، برأس عنوان + رابط اختياري. */
function ChartCard({ title, sub, icon, action, children, className }: {
  title: string; sub?: string; icon?: IconName; action?: { href: string; label: string }; children: ReactNode; className?: string;
}) {
  return (
    <section className={`ih-vcard${className ? ` ${className}` : ''}`}>
      <div className="ih-vcard__head">
        <div>
          <h2 className="ih-vcard__title">{icon && <Icon name={icon} size={16} />}{title}</h2>
          {sub && <div className="ih-vcard__sub">{sub}</div>}
        </div>
        {action && <Link href={action.href} className="ih-vcard__link">{action.label}<Icon name="chevron-left" size={13} /></Link>}
      </div>
      {children}
    </section>
  );
}

/** بطاقة مهمّة بصرية — ليست صفًّا في جدول. */
function TaskCard({ w }: { w: WorkItem }) {
  const p = PRIO[w.prio];
  return (
    <Link href={w.href} className="ih-task">
      <span className="ih-task__bar" style={{ background: p.dot }} aria-hidden="true" />
      <div className="ih-task__body">
        <div className="ih-task__top">
          <span className="badge" style={{ background: p.bg, color: p.fg }}>{w.prioLabel}</span>
          {w.sla && <span className="badge" style={{ background: 'var(--ih-danger-soft)', color: 'var(--ih-danger-ink)', fontSize: '.56rem' }}>SLA</span>}
          {w.due && <span className="ih-task__due"><Icon name="calendar-days" size={12} /> {w.due}</span>}
        </div>
        <div className="ih-task__title">{w.title}{w.count != null && <span className="ih-chip__count">{w.count}</span>}</div>
        <div className="ih-task__meta">{w.entity} · {w.reason}</div>
      </div>
      <span className="ih-task__go">{w.actionLabel} <Icon name="chevron-left" size={14} /></span>
    </Link>
  );
}

/** بطاقة حملة بصرية — حلقة تقدّم + مرحلة + إجراء تالٍ. */
function CampaignCard({ cm }: { cm: ActiveCampaign }) {
  const t = useT();
  return (
    <Link href={u(`/campaigns/${cm.id}`)} className="ih-ccard">
      <div className="ih-ccard__ring"><ProgressRing value={cm.progress} size={62} thickness={7} tone={cm.isLate ? 'danger' : 'primary'} /></div>
      <div className="ih-ccard__body">
        <div className="ih-ccard__top">
          <span className="ih-ccard__name">{cm.name}</span>
          <StatusBadge tone={cm.statusTone} label={cm.statusLabel} />
          {cm.isLate && <span className="badge" style={{ background: 'var(--ih-danger-soft)', color: 'var(--ih-danger-ink)' }}>{t('dashboard.late')}</span>}
        </div>
        <div className="ih-ccard__meta">{cm.client ?? '—'}{cm.brand ? ` · ${cm.brand}` : ''}</div>
        <div className="ih-ccard__stats">
          <span><Icon name="users" size={13} /> {t('dashboard.campaign_creators', { n: cm.creators })}</span>
          <span><Icon name="image" size={13} /> {t('dashboard.campaign_deliverables', { n: cm.deliverables })}</span>
        </div>
      </div>
      <span className="ih-ccard__go" aria-hidden="true"><Icon name="chevron-left" size={16} /></span>
    </Link>
  );
}

function RailGroup({ title, icon, children }: { title: string; icon: IconName; children: ReactNode }) {
  return (
    <div className="ih-rail__group">
      <div className="ih-rail__title"><Icon name={icon} size={14} /> {title}</div>
      {children}
    </div>
  );
}

export default function Dashboard() {
  const { props } = usePage<DashboardProps>();
  const t = useT();
  const { locale } = useLocale();
  const { brief, myWork, overview, auth, workspace } = props;
  const setup = props.setup;

  const today = new Intl.DateTimeFormat(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US',
    { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date());

  // لمحة علوية — 4 مؤشّرات تشغيلية حقيقية (للمديرين)
  const kpis = overview ? [
    { label: t('dashboard.k_campaigns'), value: numFmt(overview.kpis.campaignsActive), icon: 'megaphone' as IconName, tone: 'primary' as const, href: u('/campaigns') },
    { label: t('dashboard.k_decisions'), value: numFmt(overview.kpis.awaitingClient), icon: 'handshake' as IconName, tone: overview.kpis.awaitingClient > 0 ? ('warning' as const) : ('neutral' as const), href: u('/campaigns') },
    { label: t('dashboard.k_review'), value: numFmt(brief.approvals), icon: 'clipboard-check' as IconName, tone: brief.approvals > 0 ? ('warning' as const) : ('neutral' as const), href: u('/content') },
    { label: t('dashboard.k_payouts'), value: <>{sarShort(overview.kpis.pendingPayoutMinor)} <small>{t('common.currency_sar')}</small></>, icon: 'wallet' as IconName, tone: overview.kpis.pendingPayouts > 0 ? ('warning' as const) : ('neutral' as const), href: u('/payouts'), hint: t('dashboard.kpi_pending_payouts_sub', { n: overview.kpis.pendingPayouts }) },
  ] : [];

  // توزيع «المطلوب» الحقيقي (للدونات)
  const cnt = (pred: (p: string) => boolean) => myWork.filter((w) => pred(w.prio)).reduce((n, w) => n + (w.count ?? 1), 0);
  const urgent = cnt((p) => p === 'overdue' || p === 'critical');
  const approvalN = cnt((p) => p === 'approval');
  const upcoming = cnt((p) => !['overdue', 'critical', 'approval'].includes(p));
  const workSegs = [
    { label: t('dashboard.insight_urgent'), value: urgent, color: 'var(--ih-danger)' },
    { label: t('dashboard.insight_approval'), value: approvalN, color: 'var(--ih-warning)' },
    { label: t('dashboard.insight_upcoming'), value: upcoming, color: 'var(--ih-lime-600)' },
  ];
  const totalWork = urgent + approvalN + upcoming;

  const topCampaign = overview?.activeCampaigns[0];

  // ── لوحة السياق (يمين) ─────────────────────────────────
  const rail = (
    <>
      <RailGroup title={t('dashboard.rail_today')} icon="calendar-days">
        <div className="ih-rail__date">{today}</div>
        <ul className="ih-rail__list">
          <li><span className="ih-rail__dot" style={{ background: 'var(--ih-danger)' }} /> {t('dashboard.rail_overdue', { n: brief.overdue })}</li>
          <li><span className="ih-rail__dot" style={{ background: 'var(--ih-warning)' }} /> {t('dashboard.rail_approvals', { n: brief.approvals })}</li>
          {overview && <li><span className="ih-rail__dot" style={{ background: 'var(--ih-primary)' }} /> {t('dashboard.rail_decisions', { n: overview.kpis.awaitingClient })}</li>}
        </ul>
      </RailGroup>

      <RailGroup title={t('dashboard.rail_attention')} icon="shield-check">
        {myWork.length === 0 ? (
          <div className="ih-rail__empty">{t('dashboard.rail_clear')}</div>
        ) : myWork.slice(0, 3).map((w) => (
          <Link key={w.key} href={w.href} className="ih-rail__item">
            <span className="ih-rail__dot" style={{ background: PRIO[w.prio].dot }} />
            <span className="ih-rail__item-body">
              <span className="ih-rail__item-title">{w.title}</span>
              <span className="ih-rail__item-meta">{w.entity}{w.due ? ` · ${w.due}` : ''}</span>
            </span>
          </Link>
        ))}
      </RailGroup>

      {topCampaign && (
        <RailGroup title={t('dashboard.rail_lead')} icon="megaphone">
          <Link href={u(`/campaigns/${topCampaign.id}`)} className="ih-rail__lead">
            <div className="ih-rail__lead-top">
              <span className="ih-rail__item-title">{topCampaign.name}</span>
              <StatusBadge tone={topCampaign.statusTone} label={topCampaign.statusLabel} />
            </div>
            <div className="ih-rail__item-meta">{topCampaign.client ?? '—'}</div>
            <div className="ih-rail__lead-bar"><span style={{ width: `${topCampaign.progress}%` }} /></div>
            <div className="ih-rail__lead-foot"><span>{t('dashboard.completion')}</span><b>{topCampaign.progress}%</b></div>
          </Link>
        </RailGroup>
      )}
    </>
  );

  return (
    <AppShell heading={t('dashboard.title')} contextRail={overview ? rail : undefined}>
      <Head title={t('dashboard.title')} />

      {/* رأس مدمج — تحيّة + إجراء مهيمن */}
      <header className="ih-dash-head">
        <div>
          <div className="ih-dash-head__eyebrow">{t('dashboard.eyebrow')}</div>
          <h1 className="ih-dash-head__title">{t('dashboard.greeting', { name: auth.user?.name ?? '' })}</h1>
          <div className="ih-dash-head__sub">{t('dashboard.sub', { workspace: workspace ?? t('dashboard.workspace_fallback') })}</div>
        </div>
        <Link href={u('/service-requests')} className="btn btn-primary"><Icon name="inbox" size={16} /> {t('dashboard.requests')}</Link>
      </header>

      {/* إعداد المساحة إن كانت ناقصة — أولوية قبل أي شيء */}
      {setup?.isSettingUp && setup.next && (
        <Link href={u(setup.next.href)} className="ih-setup-cta">
          <span className="ih-setup-cta__icon"><Icon name="rocket" size={20} /></span>
          <span className="ih-setup-cta__body">
            <b>{t('dashboard.brief_setup', { next: setup.next.title })}</b>
            <small>{t('dashboard.setup_progress', { done: setup.doneCount, total: setup.total })}</small>
          </span>
          <span className="btn btn-sm" style={{ pointerEvents: 'none' }}>{setup.next.action}</span>
        </Link>
      )}

      {/* STRIP — 4 مؤشّرات تشغيلية */}
      {kpis.length > 0 && (
        <div className="ih-tiles">
          {kpis.map((k) => <KpiTile key={k.label} {...k} />)}
        </div>
      )}

      {/* BENTO — رسم اتّجاه كبير مهيمن + دونات التركيز */}
      {overview && (
        <div className="ih-bento">
          <ChartCard className="ih-bento__hero" title={t('dashboard.trend_title')} sub={t('dashboard.trend_sub')} icon="trending-up">
            <AreaChart data={overview.trend} height={210} ariaLabel={t('dashboard.trend_title')} />
          </ChartCard>
          <ChartCard className="ih-bento__side" title={t('dashboard.focus_title')} icon="gauge">
            <div className="ih-focus">
              <ProgressRing value={overview.kpis.avgCompletion} size={128} label={t('dashboard.insight_avg_completion')} />
              <Donut segments={workSegs} size={108} centerValue={totalWork} centerLabel={t('dashboard.insight_required')} emptyLabel={t('dashboard.insight_no_tasks')} />
            </div>
          </ChartCard>
        </div>
      )}

      {/* المطلوب منك الآن — بطاقات مهام بصرية */}
      <section style={{ marginTop: '1.5rem' }}>
        <div className="ih-sectionbar">
          <h2 className="ih-sectionbar__title"><Icon name="clipboard-check" size={18} /> {t('dashboard.my_work')}</h2>
          {myWork.length > 0 && <span className="ih-sectionbar__count">{myWork.length}</span>}
        </div>
        {myWork.length === 0 ? (
          <div className="ih-vcard ih-empty-pane">
            <span className="ih-empty__icon" style={{ background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)' }}><Icon name="shield-check" size={26} /></span>
            <div style={{ fontWeight: 800, marginTop: '.5rem' }}>{t('dashboard.my_work_empty_title')}</div>
            <div style={{ fontSize: '.85rem', color: 'var(--ih-text-muted)' }}>{t('dashboard.my_work_empty_text')}</div>
          </div>
        ) : (
          <div className="ih-task-grid">
            {myWork.map((w) => <TaskCard key={w.key} w={w} />)}
          </div>
        )}
      </section>

      {/* الحملات النشطة — بطاقات بصرية */}
      {overview && overview.activeCampaigns.length > 0 && (
        <section style={{ marginTop: '1.5rem' }}>
          <div className="ih-sectionbar">
            <h2 className="ih-sectionbar__title"><Icon name="megaphone" size={18} /> {t('dashboard.campaigns_follow')}</h2>
            <Link href={u('/campaigns')} className="ih-vcard__link">{t('dashboard.all_campaigns')}<Icon name="chevron-left" size={13} /></Link>
          </div>
          <div className="ih-ccard-grid">
            {overview.activeCampaigns.map((cm) => <CampaignCard key={cm.id} cm={cm} />)}
          </div>
        </section>
      )}
    </AppShell>
  );
}

import { Head, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Avatar, Bar, Kpi, ListHead, Sec, StatusBadge, numFmt, sarShort } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import type { SharedProps } from '@/types';
import { u } from '@/lib/href';

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
interface Overview {
  kpis: {
    clientsTotal: number; revenueMinor: number; profitMinor: number; margin: number;
    activeCampaigns: number; awaitingClient: number; late: number; campaignsActive: number;
    pendingPayoutMinor: number; pendingPayouts: number;
    creatorsTotal: number; creatorsVerified: number; creatorsTierA: number; avgCompletion: number;
  };
  topClients: TopClient[]; activeCampaigns: ActiveCampaign[];
}
interface SetupStep {
  key: string; title: string; why: string; done: boolean; count: number;
  href: string; action: string; optional: boolean;
}
interface Setup {
  steps: SetupStep[]; doneCount: number; total: number;
  requiredDone: number; requiredTotal: number; isSettingUp: boolean;
  next: { key: string; title: string; href: string; action: string } | null;
}
type DashboardProps = SharedProps & {
  role: string | null; canSeeTeam: boolean; brief: Brief; myWork: WorkItem[];
  team: Team | null; overview?: Overview; setup?: Setup;
};

/**
 * قائمة تهيئة المساحة — تظهر ما دامت خطوة إلزامية ناقصة ثم تختفي.
 * سببها: «لا مهامّ» في مساحة فارغة ليس اطمئنانًا بل غياب نقطة بداية.
 */
function SetupChecklist({ setup }: { setup: Setup }) {
  const pct = Math.round((setup.doneCount / setup.total) * 100)

  return (
    <Sec title="لنُجهّز مساحتك" icon="clipboard-check">
      <div style={{ display: 'flex', alignItems: 'center', gap: '.75rem', marginBottom: '1rem' }}>
        <div style={{ flex: 1 }}><Bar pct={pct} /></div>
        <span style={{ fontSize: '.8rem', color: 'var(--ih-text-secondary)', whiteSpace: 'nowrap' }}>
          {setup.doneCount} من {setup.total}
        </span>
      </div>

      <ol style={{ listStyle: 'none', margin: 0, padding: 0, display: 'grid', gap: '.5rem' }}>
        {setup.steps.map((step) => {
          const isNext = setup.next?.key === step.key
          return (
            <li
              key={step.key}
              style={{
                display: 'flex', alignItems: 'flex-start', gap: '.75rem',
                padding: '.75rem .9rem', borderRadius: 'var(--ih-radius-sm)',
                border: `1px solid ${isNext ? 'var(--ih-primary-300)' : 'var(--ih-border)'}`,
                background: isNext ? 'var(--ih-primary-soft)' : 'transparent',
                opacity: step.done ? 0.62 : 1,
              }}
            >
              <span
                aria-hidden="true"
                style={{
                  flex: 'none', width: 22, height: 22, borderRadius: '50%',
                  display: 'grid', placeItems: 'center', fontSize: '.7rem', fontWeight: 700,
                  background: step.done ? 'var(--ih-success-600, #079455)' : 'var(--ih-surface-sunken)',
                  color: step.done ? '#fff' : 'var(--ih-text-muted)',
                  border: step.done ? 'none' : '1px solid var(--ih-border-strong)',
                }}
              >
                {step.done ? '✓' : ''}
              </span>
              <span style={{ flex: 1, minWidth: 0 }}>
                <span style={{ display: 'flex', alignItems: 'center', gap: '.4rem', flexWrap: 'wrap' }}>
                  <b style={{ textDecoration: step.done ? 'line-through' : 'none' }}>{step.title}</b>
                  {step.optional && !step.done && (
                    <span className="badge" style={{ fontSize: '.58rem' }}>اختياري</span>
                  )}
                  {step.done && step.count > 0 && <span className="ih-chip__count">{step.count}</span>}
                </span>
                <span style={{ display: 'block', fontSize: '.75rem', color: 'var(--ih-text-muted)', marginTop: 2 }}>
                  {step.why}
                </span>
              </span>
              {!step.done && (
                <a href={u(step.href)} className={`btn btn-xs${isNext ? '' : ' btn-outline'}`} style={{ flexShrink: 0 }}>
                  {step.action}
                </a>
              )}
            </li>
          )
        })}
      </ol>
    </Sec>
  )
}

const PRIO_TONE: Record<WorkItem['prio'], { bg: string; fg: string }> = {
  overdue: { bg: 'var(--ih-danger-soft)', fg: 'var(--ih-danger-ink)' },
  critical: { bg: 'var(--ih-danger-soft)', fg: 'var(--ih-danger-ink)' },
  today: { bg: 'var(--ih-warning-soft)', fg: 'var(--ih-warning-ink)' },
  approval: { bg: 'var(--ih-primary-soft)', fg: 'var(--ih-primary-700)' },
  soon: { bg: 'var(--ih-info-soft)', fg: 'var(--ih-info-ink)' },
  normal: { bg: 'var(--ih-surface-sunken)', fg: 'var(--ih-text-secondary)' },
};

function WorkRow({ w }: { w: WorkItem }) {
  const t = PRIO_TONE[w.prio];
  return (
    <a href={w.href} className="ih-risk" style={{ marginBottom: '.5rem', alignItems: 'flex-start', gap: '.8rem' }}>
      <span className="badge" style={{ background: t.bg, color: t.fg, flexShrink: 0, marginTop: 2 }}>{w.prioLabel}</span>
      <span style={{ flex: 1, minWidth: 0 }}>
        <span style={{ display: 'flex', alignItems: 'center', gap: '.4rem', flexWrap: 'wrap' }}>
          <span style={{ fontWeight: 700 }}>{w.title}</span>
          {w.count != null && <span className="ih-chip__count">{w.count}</span>}
          {w.sla && <span className="badge" style={{ background: 'var(--ih-danger-soft)', color: 'var(--ih-danger-ink)', fontSize: '.58rem' }}>SLA</span>}
        </span>
        <span style={{ display: 'block', fontSize: '.75rem', color: 'var(--ih-text-muted)', fontWeight: 500, marginTop: 2 }}>
          {w.entity} · {w.reason}{w.due ? ` · يستحق ${w.due}` : ''}
        </span>
      </span>
      <span className="btn btn-xs btn-outline" style={{ flexShrink: 0, alignSelf: 'center', pointerEvents: 'none' }}>{w.actionLabel}</span>
    </a>
  );
}

export default function Dashboard() {
  const { props } = usePage<DashboardProps>();
  const { brief, myWork, team, overview, canSeeTeam, auth, workspace } = props;
  const briefParts: string[] = [];
  if (brief.tasks) briefParts.push(`${brief.tasks} مهمة`);
  if (brief.approvals) briefParts.push(`${brief.approvals} بانتظار موافقتك`);
  if (brief.overdue) briefParts.push(`${brief.overdue} متأخر/حرج`);
  const setup = props.setup;
  // في مساحة قيد التهيئة لا يُقال «كل شيء تحت السيطرة»: لا مهامّ لأن لا شيء أُنشئ بعد
  const briefText = briefParts.length
    ? `لديك اليوم: ${briefParts.join('، ')}.`
    : setup?.isSettingUp
      ? `مساحتك جاهزة للبدء — التالي: ${setup.next?.title ?? 'أضِف أوّل عميل'}.`
      : 'لا مهام معلّقة تحتاج تدخّلك الآن — كل شيء تحت السيطرة.';

  return (
    <AppShell heading="لوحة التحكم">
      <Head title="لوحة التحكم" />

      <ListHead
        eyebrow="مساحة عملي"
        title={`مرحبًا، ${auth.user?.name ?? ''}`}
        sub={`${workspace ?? 'وكالتك'} · مركز عملك اليومي والمتابعة`}
        actions={<a href={u("/service-requests")} className="btn btn-sm btn-primary"><Icon name="inbox" size={15} /> الطلبات</a>}
      />

      {/* إجراءات سريعة — مداخل رحلة العمل */}
      <div style={{ display: 'flex', gap: '.6rem', flexWrap: 'wrap', marginBottom: '1.2rem' }}>
        <a href={u("/publishers")} className="btn btn-sm btn-outline"><Icon name="radar" size={15} /> اكتشف ناشرين</a>
        <a href={u("/shortlisting")} className="btn btn-sm btn-outline"><Icon name="list-checks" size={15} /> ابدأ ترشيحًا</a>
        {canSeeTeam && <a href={u("/campaigns")} className="btn btn-sm btn-outline"><Icon name="megaphone" size={15} /> حملة جديدة</a>}
        <a href={u("/creators")} className="btn btn-sm btn-outline"><Icon name="users" size={15} /> صناع المحتوى</a>
      </div>

      {/* الملخّص اليومي */}
      <div className="ih-nba" style={{ marginBottom: '1.2rem' }}>
        <span className="ih-nba__icon"><Icon name="rocket" size={22} /></span>
        <div className="ih-nba__body">
          <div className="ih-nba__eyebrow">الملخّص اليومي</div>
          <div className="ih-nba__title" style={{ fontSize: '1rem' }}>{briefText}</div>
        </div>
        {setup?.isSettingUp && setup.next
          ? <a href={u(setup.next.href)} className="btn btn-sm">{setup.next.action}</a>
          : <a href="#my-work" className="btn btn-sm">ابدأ العمل</a>}
      </div>

      {/* مؤشرات المدير المالية/التشغيلية — فقط لمن يملك الصلاحية */}
      {overview && (
        <div className="ih-kpis">
          <Kpi label="العملاء" icon="building-2" href={u("/clients")} value={numFmt(overview.kpis.clientsTotal)}
            sub={<><span className="ih-delta ih-delta--up">{overview.kpis.campaignsActive}</span> حملة نشطة</>} />
          <Kpi label="الإيراد (تقديري)" icon="wallet" tone="success" value={<>{sarShort(overview.kpis.revenueMinor)} <small>ر.س</small></>}
            sub={<>هامش <span className={`ih-delta ${overview.kpis.margin >= 0 ? 'ih-delta--up' : 'ih-delta--down'}`}>{overview.kpis.margin}%</span> · ربح {sarShort(overview.kpis.profitMinor)}</>} />
          <Kpi label="مستحقات معلّقة" icon="wallet" tone="warning" href={u("/payouts")} value={<>{sarShort(overview.kpis.pendingPayoutMinor)} <small>ر.س</small></>}
            sub={`${overview.kpis.pendingPayouts} دفعة بانتظار الإجراء`} />
          <Kpi label="المبدعون" icon="users" href={u("/creators")} value={numFmt(overview.kpis.creatorsTotal)}
            sub={`${overview.kpis.creatorsVerified} موثّق · ${overview.kpis.creatorsTierA} فئة A`} />
        </div>
      )}

      {/* التهيئة تتصدّر ما دامت ناقصة: هي عمل اليوم الحقيقي في مساحة جديدة */}
      {setup?.isSettingUp && (
        <div style={{ marginBottom: '1.2rem' }}>
          <SetupChecklist setup={setup} />
        </div>
      )}

      <div className="ih-overview-grid" style={{ display: 'grid', gridTemplateColumns: '1.15fr .85fr', gap: '1.1rem', alignItems: 'start' }}>
        {/* المطلوب مني الآن */}
        <div style={{ display: 'grid', gap: '1.1rem' }} id="my-work">
          <Sec title="المطلوب مني الآن" icon="clipboard-check">
            <div style={{ padding: '.7rem' }}>
              {myWork.length === 0 ? (
                <div style={{ textAlign: 'center', padding: '2rem 1rem' }}>
                  <span className="ih-empty__icon" style={{ background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)' }}><Icon name="shield-check" size={26} /></span>
                  <div style={{ marginTop: '.6rem', fontWeight: 800 }}>لا شيء عاجل الآن</div>
                  <div style={{ fontSize: '.85rem', color: 'var(--ih-text-muted)' }}>لا مهام أو موافقات معلّقة ضمن صلاحياتك.</div>
                </div>
              ) : (
                myWork.map((w) => <WorkRow key={w.key} w={w} />)
              )}
            </div>
          </Sec>

          {overview && overview.activeCampaigns.length > 0 && (
            <Sec title="حملات تحتاج متابعة" icon="megaphone" link={{ href: u('/campaigns'), label: 'كل الحملات ←' }}>
              <div style={{ padding: '.4rem .5rem' }}>
                {overview.activeCampaigns.map((cm) => (
                  <a key={cm.id} href={u(`/campaigns/${cm.id}`)} className="ih-row-link" style={{ display: 'flex', alignItems: 'center', gap: '.8rem', padding: '.7rem', borderRadius: 'var(--ih-radius-sm)', textDecoration: 'none', color: 'var(--ih-text)' }}>
                    <Avatar name={cm.name} />
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '.5rem', flexWrap: 'wrap' }}>
                        <span className="ih-idc__name">{cm.name}</span>
                        <StatusBadge tone={cm.statusTone} label={cm.statusLabel} />
                        {cm.isLate && <span className="badge" style={{ background: 'var(--ih-danger-soft)', color: 'var(--ih-danger-ink)' }}>متأخرة</span>}
                      </div>
                      <div className="ih-idc__sub">{cm.client ?? '—'}{cm.brand ? ` · ${cm.brand}` : ''} · {cm.creators} مبدع · {cm.deliverables} مخرج</div>
                      <div style={{ marginTop: '.4rem', maxWidth: 220 }}><Bar pct={cm.progress} /></div>
                    </div>
                    <span style={{ fontWeight: 800, fontVariantNumeric: 'tabular-nums', color: 'var(--ih-primary)' }}>{cm.progress}%</span>
                  </a>
                ))}
              </div>
            </Sec>
          )}
        </div>

        {/* العمود الجانبي: متابعة الفريق (للمديرين) + أبرز العملاء */}
        <div style={{ display: 'grid', gap: '1.1rem' }}>
          {canSeeTeam && team && (
            <Sec title="متابعة الفريق" icon="users">
              <div style={{ padding: '.5rem .6rem' }}>
                <div style={{ display: 'flex', gap: '.5rem', marginBottom: '.6rem' }}>
                  <div className="ih-tag" style={{ flex: 1, justifyContent: 'center', padding: '.4rem' }}>غير مُسنَد: <b style={{ marginInlineStart: 4 }}>{team.unassigned}</b></div>
                  <div className="ih-tag" style={{ flex: 1, justifyContent: 'center', padding: '.4rem', background: team.breached ? 'var(--ih-danger-soft)' : undefined, color: team.breached ? 'var(--ih-danger-ink)' : undefined }}>تجاوز SLA: <b style={{ marginInlineStart: 4 }}>{team.breached}</b></div>
                </div>
                {team.members.length === 0 ? (
                  <div style={{ textAlign: 'center', padding: '1rem', color: 'var(--ih-text-muted)', fontSize: '.82rem' }}>لا أعمال مسندة حاليًا.</div>
                ) : team.members.map((m) => (
                  <div key={m.id} style={{ display: 'flex', alignItems: 'center', gap: '.6rem', padding: '.5rem .4rem', borderBottom: '1px solid var(--ih-border)' }}>
                    <Avatar name={m.name} />
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div className="ih-idc__name" style={{ fontSize: '.86rem' }}>{m.name}</div>
                      <div className="ih-idc__sub">{m.role}</div>
                    </div>
                    <div style={{ minWidth: 90 }}><Bar pct={Math.min(100, m.open * 20)} over={m.breached > 0} /></div>
                    <span style={{ fontWeight: 800, fontVariantNumeric: 'tabular-nums', minWidth: 24, textAlign: 'end' }}>{m.open}</span>
                  </div>
                ))}
              </div>
            </Sec>
          )}

          {overview && (
            <Sec title="أبرز العملاء" icon="building-2" link={{ href: u('/clients'), label: 'الكل ←' }}>
              <div style={{ padding: '.4rem .5rem' }}>
                {overview.topClients.map((c) => (
                  <a key={c.id} href={u(`/clients/${c.id}`)} className="ih-row-link" style={{ display: 'flex', alignItems: 'center', gap: '.7rem', padding: '.6rem .7rem', borderRadius: 'var(--ih-radius-sm)', textDecoration: 'none', color: 'var(--ih-text)' }}>
                    <Avatar name={c.name} />
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '.4rem' }}>
                        <span className="ih-idc__name">{c.name}</span>
                        {c.isVip && <span className="badge" style={{ background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)', fontSize: '.62rem' }}>VIP</span>}
                      </div>
                      <div className="ih-idc__sub">{c.activeCampaigns} حملة نشطة · {c.brands} علامة</div>
                    </div>
                    <span style={{ fontWeight: 800, fontVariantNumeric: 'tabular-nums' }}>{sarShort(c.revenueMinor)}</span>
                  </a>
                ))}
              </div>
            </Sec>
          )}
        </div>
      </div>
    </AppShell>
  );
}

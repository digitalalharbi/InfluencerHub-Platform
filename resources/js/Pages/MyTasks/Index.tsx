import { Head, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { ListHead, Kpi } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';

/** عنصر عمل واحد — إمّا مهمة فردية أو طابور موافقة مجمّع (count). */
interface WorkItem {
  key: string;
  title: string;
  entity: string;
  reason: string;
  prio: 'overdue' | 'critical' | 'today' | 'approval' | 'soon' | 'normal';
  prioLabel: string;
  prioRank: number;
  due: string | null;
  sla: boolean;
  count: number | null;
  actionLabel: string;
  href: string;
}
interface Brief { tasks: number; approvals: number; overdue: number; total: number }
interface Props { role: string | null; brief: Brief; myWork: WorkItem[] }

/** ألوان الأولوية — متّسقة مع نظام الحالات (أحمر=متأخر/حرج، كهرماني=قريب، أساسي=موافقة). */
const PRIO_TONE: Record<WorkItem['prio'], { bg: string; fg: string }> = {
  overdue: { bg: 'var(--ih-danger-soft)', fg: 'var(--ih-danger-ink)' },
  critical: { bg: 'var(--ih-danger-soft)', fg: 'var(--ih-danger-ink)' },
  today: { bg: 'var(--ih-warning-soft)', fg: 'var(--ih-warning-ink)' },
  approval: { bg: 'var(--ih-primary-soft)', fg: 'var(--ih-primary-700)' },
  soon: { bg: 'var(--ih-warning-soft)', fg: 'var(--ih-warning-ink)' },
  normal: { bg: 'var(--ih-surface-sunken, #F2F4F7)', fg: 'var(--ih-text-muted)' },
};

function WorkRow({ item }: { item: WorkItem }) {
  const tone = PRIO_TONE[item.prio];
  return (
    <Link href={u(item.href)} className="card ih-risk" style={{ display: 'flex', alignItems: 'center', gap: '.8rem', padding: '.8rem 1rem', textDecoration: 'none', color: 'inherit' }}>
      <span style={{ flexShrink: 0, fontSize: '.66rem', fontWeight: 700, padding: '.15rem .5rem', borderRadius: 999, background: tone.bg, color: tone.fg }}>
        {item.prioLabel}{item.sla ? ' · SLA' : ''}
      </span>
      <div style={{ minWidth: 0, flex: 1 }}>
        <div style={{ fontWeight: 600, fontSize: '.92rem', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
          {item.title}{item.count ? <span style={{ color: 'var(--ih-text-muted)', fontWeight: 500 }}> · {item.count}</span> : null}
        </div>
        <div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>
          {item.entity} · {item.reason}{item.due ? ` · يستحق ${item.due}` : ''}
        </div>
      </div>
      <span className="btn btn-xs btn-outline" style={{ flexShrink: 0 }}>{item.actionLabel} ←</span>
    </Link>
  );
}

export default function MyTasksIndex({ brief, myWork }: Props) {
  return (
    <AppShell heading="عملي">
      <Head title="عملي" />
      <ListHead eyebrow="العمل" title="عملي" sub="كل ما يحتاج إجراءك الآن، مرتّبًا حسب الأولوية — من بيانات فعلية." />

      <div className="ih-kpis">
        <Kpi label="بحاجة إجراء" icon="list-checks" tone={brief.total ? 'warning' : 'success'} value={brief.total.toLocaleString('en-US')} sub={brief.total ? 'عنصرًا' : 'لا شيء'} />
        <Kpi label="بانتظار موافقتك" icon="shield-check" value={brief.approvals.toLocaleString('en-US')} sub="طوابير اعتماد" />
        <Kpi label="متأخر / حرج" icon="activity" tone={brief.overdue ? 'danger' : 'success'} value={brief.overdue.toLocaleString('en-US')} sub={brief.overdue ? 'يحتاج انتباهك' : 'لا متأخرات'} />
      </div>

      {myWork.length === 0 ? (
        <div className="card" style={{ padding: '2.5rem', textAlign: 'center', color: 'var(--ih-success-ink)', background: 'var(--ih-success-soft)' }}>
          <Icon name="shield-check" size={26} />
          <div style={{ marginTop: '.6rem', fontWeight: 600 }}>لا شيء بانتظارك الآن</div>
          <div style={{ fontSize: '.82rem', marginTop: '.2rem' }}>ما إن يصلك عمل يحتاج قرارك حتى يظهر هنا مرتّبًا.</div>
        </div>
      ) : (
        <div style={{ display: 'grid', gap: '.55rem', maxWidth: 860 }}>
          {myWork.map((item) => <WorkRow key={item.key} item={item} />)}
        </div>
      )}
    </AppShell>
  );
}

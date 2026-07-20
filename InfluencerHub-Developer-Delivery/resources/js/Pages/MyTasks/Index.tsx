import { Head, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { ListHead, Sec, StatusBadge, Kpi } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';

interface Req { id: number; title: string; client: string | null; status: string; statusLabel: string; statusTone: string; link: string; overdue: boolean }
interface ContentT { id: number; title: string; campaign: string | null; link: string }
interface BrandT { id: number; title: string; client: string | null; link: string }
interface Props { myRequests: Req[]; contentReview: ContentT[]; brandReviews: BrandT[]; canReview: boolean }

function TaskRow({ href, title, sub, right }: { href: string; title: string; sub?: string; right?: React.ReactNode }) {
  return (
    <Link href={href} className="card" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: '.7rem', padding: '.7rem .9rem', textDecoration: 'none', color: 'inherit' }}>
      <div style={{ minWidth: 0 }}>
        <div style={{ fontWeight: 600, fontSize: '.9rem', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{title}</div>
        {sub && <div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>{sub}</div>}
      </div>
      {right}
    </Link>
  );
}

export default function MyTasksIndex({ myRequests, contentReview, brandReviews, canReview }: Props) {
  const total = myRequests.length + contentReview.length + brandReviews.length;

  return (
    <AppShell heading="مهامي">
      <Head title="مهامي" />
      <ListHead eyebrow="العمل" title="مهامي" sub="ما يحتاج إجراءك الآن — من بيانات فعلية." />

      <div className="ih-kpis">
        <Kpi label="إجمالي المهام" icon="list-checks" tone={total ? 'warning' : 'success'} value={total.toLocaleString('en-US')} sub={total ? 'بانتظارك' : 'لا مهام'} />
        <Kpi label="طلبات مُسنَدة إليّ" icon="inbox" value={myRequests.length.toLocaleString('en-US')} sub="مفتوحة" />
        <Kpi label="محتوى للمراجعة" icon="image" value={contentReview.length.toLocaleString('en-US')} sub="مرحلة الوكالة" />
      </div>

      {total === 0 && (
        <div className="card" style={{ padding: '2rem', textAlign: 'center', color: 'var(--ih-success-ink)', background: 'var(--ih-success-soft)' }}>
          <Icon name="shield-check" size={22} /><div style={{ marginTop: '.5rem' }}>لا مهام عاجلة — أنت على المسار.</div>
        </div>
      )}

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: '1.2rem', alignItems: 'start' }}>
        {myRequests.length > 0 && (
          <Sec title={`طلبات مُسنَدة إليّ (${myRequests.length})`} icon="inbox">
            <div style={{ display: 'grid', gap: '.5rem' }}>
              {myRequests.map((s) => (
                <TaskRow key={s.id} href={u(s.link)} title={s.title} sub={s.client ?? undefined}
                  right={<div style={{ display: 'flex', gap: '.3rem', alignItems: 'center' }}>{s.overdue && <span className="ih-tag" style={{ background: 'var(--ih-danger-soft)', color: 'var(--ih-danger-ink)', fontSize: '.64rem' }}>متأخر</span>}<StatusBadge tone={s.statusTone} label={s.statusLabel} /></div>} />
              ))}
            </div>
          </Sec>
        )}

        {contentReview.length > 0 && (
          <Sec title={`محتوى بانتظار المراجعة (${contentReview.length})`} icon="image">
            <div style={{ display: 'grid', gap: '.5rem' }}>
              {contentReview.map((c) => <TaskRow key={c.id} href={u(c.link)} title={c.title} sub={c.campaign ?? undefined} right={<Icon name="chevron-left" size={16} />} />)}
            </div>
          </Sec>
        )}

        {canReview && brandReviews.length > 0 && (
          <Sec title={`علامات للمراجعة (${brandReviews.length})`} icon="bookmark">
            <div style={{ display: 'grid', gap: '.5rem' }}>
              {brandReviews.map((b) => <TaskRow key={b.id} href={u(b.link)} title={b.title} sub={b.client ?? undefined} right={<Icon name="chevron-left" size={16} />} />)}
            </div>
          </Sec>
        )}
      </div>
    </AppShell>
  );
}

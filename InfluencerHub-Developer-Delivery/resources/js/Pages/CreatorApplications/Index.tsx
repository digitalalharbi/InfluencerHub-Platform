import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { ListHead, StatusBadge, Kpi } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { u } from '@/lib/href';

interface Row {
  id: number; reference: string; name: string; email: string | null; type: string; typeLabel: string;
  country: string | null; status: string; statusLabel: string; statusTone: string; submittedAt: string | null;
}
interface Props { applications: Paginated<Row>; filters: { q: string | null; status: string | null; type: string | null }; summary: { pending: number; total: number } }

export default function CreatorApplicationsIndex({ applications, filters, summary }: Props) {
  const [q, setQ] = useState(filters.q ?? '');
  const go = (patch: Record<string, string | undefined>) => router.get(u('/creator-applications'), { ...filters, q: q || undefined, ...patch }, { preserveState: true, replace: true });

  return (
    <AppShell heading="طلبات الانضمام">
      <Head title="طلبات الانضمام" />
      <ListHead eyebrow="العلاقات" title="طلبات الانضمام" sub="طابور مراجعة طلبات انضمام المبدعين." />

      <div className="ih-kpis">
        <Kpi label="بانتظار المراجعة" icon="user-plus" tone={summary.pending ? 'warning' : 'success'} value={summary.pending.toLocaleString('en-US')} sub={summary.pending ? 'يحتاج فرزك' : 'لا شيء معلّق'} />
        <Kpi label="إجمالي الطلبات" icon="users" value={summary.total.toLocaleString('en-US')} sub="كل الوقت" />
      </div>

      <div className="ih-filterbar" style={{ marginBottom: '1rem' }}>
        <div className="ih-search">
          <Icon name="search" size={15} />
          <input value={q} onChange={(e) => setQ(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && go({})} placeholder="ابحث بالاسم أو البريد أو المرجع…" />
        </div>
        <select value={filters.status ?? ''} onChange={(e) => go({ status: e.target.value || undefined })} className="field" style={{ maxWidth: 160 }}>
          <option value="">كل الحالات</option>
          <option value="submitted">مُرسل</option>
          <option value="under_review">قيد المراجعة</option>
          <option value="completion_required">مطلوب استكمال</option>
          <option value="approved">معتمد</option>
          <option value="rejected">مرفوض</option>
        </select>
        <select value={filters.type ?? ''} onChange={(e) => go({ type: e.target.value || undefined })} className="field" style={{ maxWidth: 130 }}>
          <option value="">كل الأنواع</option>
          <option value="influencer">مؤثر</option>
          <option value="ugc_creator">صانع UGC</option>
        </select>
        <button onClick={() => go({})} className="btn btn-sm">بحث</button>
      </div>

      {applications.data.length === 0 ? (
        <div className="card" style={{ padding: '2rem', textAlign: 'center', color: 'var(--ih-text-muted)' }}>لا طلبات مطابقة.</div>
      ) : (
        <>
          <div className="ih-dt-wrap"><div className="ih-dt-scroll">
            <table className="ih-dt">
              <thead><tr><th>المتقدّم</th><th>النوع</th><th>الدولة</th><th>أُرسل</th><th>الحالة</th><th>—</th></tr></thead>
              <tbody>
                {applications.data.map((a) => (
                  <tr key={a.id}>
                    <td>
                      <Link href={u(`/creator-applications/${a.id}`)} style={{ fontWeight: 600, color: 'var(--ih-primary)', textDecoration: 'none' }}>{a.name}</Link>
                      <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{a.reference} · {a.email}</div>
                    </td>
                    <td>{a.typeLabel}</td>
                    <td style={{ direction: 'ltr' }}>{a.country ?? '—'}</td>
                    <td style={{ direction: 'ltr', fontSize: '.8rem', color: 'var(--ih-text-muted)' }}>{a.submittedAt ?? '—'}</td>
                    <td><StatusBadge tone={a.statusTone} label={a.statusLabel} /></td>
                    <td><Link href={u(`/creator-applications/${a.id}`)} className="btn btn-xs btn-outline">مراجعة</Link></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div></div>
          <Pagination links={applications.links} />
        </>
      )}
    </AppShell>
  );
}

import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { adminNav } from '@/lib/nav';
import { ListHead, StatusBadge } from '@/Components/ui';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { u } from '@/lib/href';

interface Row {
  id: number; org: string; plan: string; version: number; status: string; statusLabel: string; statusTone: string;
  provider: string | null; trialEndsAt: string | null; periodEnd: string | null;
}
interface Props { subs: Paginated<Row>; filters: { status: string | null } }


export default function AdminSubscriptions({ subs, filters }: Props) {
  const [status, setStatus] = useState(filters.status ?? '');
  const apply = (s: string) => { setStatus(s); router.get(u('/subscriptions'), { status: s || undefined }, { preserveState: true, replace: true }); };

  return (
    <AppShell heading="الاشتراكات" nav={adminNav} portal="admin" wsName="إدارة المنصّة" wsPlan="مدير النظام" brand="InfluencerHub">
      <Head title="الاشتراكات" />
      <ListHead eyebrow="المنصّة" title="الاشتراكات" sub={`${subs.total.toLocaleString('en-US')} اشتراك`} />

      <div className="ih-filterbar" style={{ marginBottom: '1rem' }}>
        <select value={status} onChange={(e) => apply(e.target.value)} className="field" style={{ maxWidth: 170 }}>
          <option value="">كل الحالات</option>
          <option value="active">active</option>
          <option value="trialing">trialing</option>
          <option value="past_due">past_due</option>
          <option value="canceled">canceled</option>
          <option value="expired">expired</option>
        </select>
      </div>

      <div className="ih-dt-wrap"><div className="ih-dt-scroll">
        <table className="ih-dt">
          <thead><tr><th>المؤسسة</th><th>الخطة</th><th>المزوّد</th><th>انتهاء التجربة</th><th>نهاية الدورة</th><th>الحالة</th></tr></thead>
          <tbody>
            {subs.data.map((s) => (
              <tr key={s.id}>
                <td style={{ fontWeight: 600 }}>{s.org}</td>
                <td>{s.plan} <span style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>v{s.version}</span></td>
                <td style={{ direction: 'ltr' }}>{s.provider ?? '—'}</td>
                <td style={{ direction: 'ltr', fontSize: '.8rem', color: 'var(--ih-text-muted)' }}>{s.trialEndsAt ?? '—'}</td>
                <td style={{ direction: 'ltr', fontSize: '.8rem', color: 'var(--ih-text-muted)' }}>{s.periodEnd ?? '—'}</td>
                <td><StatusBadge tone={s.statusTone} label={s.statusLabel} /></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div></div>
      <Pagination links={subs.links} />
    </AppShell>
  );
}

import { Head, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { creatorNav } from '@/lib/nav';
import { ListHead, StatusBadge, Kpi } from '@/Components/ui';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { u } from '@/lib/href';

interface Row {
  id: number; number: string; title: string; campaignName: string | null; client: string | null;
  feeMinor: number; currency: string; status: string; statusLabel: string; statusTone: string;
}
interface Props { creatorName: string; items: Paginated<Row>; actionable: number }

const money = (m: number, cur: string) => (m / 100).toLocaleString('en-US') + ' ' + cur;

export default function CreatorCollaborationsIndex({ creatorName, items, actionable }: Props) {
  return (
    <AppShell heading="التعاونات" nav={creatorNav} portal="creator" wsName={creatorName} wsPlan="بوابة المبدع">
      <Head title="التعاونات" />
      <ListHead eyebrow="بوابة المبدع" title="التعاونات" sub="عروض التعاون وحالتها — اقبل، ابدأ، وسلّم أعمالك." />

      <div className="ih-kpis">
        <Kpi label="بحاجة إجراءك" icon="git-merge" tone={actionable ? 'warning' : 'success'}
          value={actionable.toLocaleString('en-US')} sub={actionable ? 'عروض/أعمال نشطة' : 'لا شيء عاجل'} />
        <Kpi label="إجمالي التعاونات" icon="git-merge" value={items.total.toLocaleString('en-US')} sub="لديك" />
      </div>

      {items.data.length === 0 ? (
        <div className="card" style={{ padding: '2rem', textAlign: 'center', color: 'var(--ih-text-muted)' }}>لا تعاونات بعد.</div>
      ) : (
        <>
          <div className="ih-dt-wrap"><div className="ih-dt-scroll">
            <table className="ih-dt">
              <thead><tr><th>التعاون</th><th>العميل</th><th>الأجر</th><th>الحالة</th><th>—</th></tr></thead>
              <tbody>
                {items.data.map((cl) => (
                  <tr key={cl.id}>
                    <td>
                      <Link href={u(`/collaborations/${cl.id}`)} style={{ fontWeight: 600, color: 'var(--ih-primary)', textDecoration: 'none' }}>{cl.campaignName ?? cl.title}</Link>
                      <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{cl.number}</div>
                    </td>
                    <td>{cl.client ?? '—'}</td>
                    <td style={{ direction: 'ltr', fontWeight: 600 }}>{cl.feeMinor ? money(cl.feeMinor, cl.currency) : '—'}</td>
                    <td><StatusBadge tone={cl.statusTone} label={cl.statusLabel} /></td>
                    <td><Link href={u(`/collaborations/${cl.id}`)} className="btn btn-xs btn-outline">عرض</Link></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div></div>
          <Pagination links={items.links} />
        </>
      )}
    </AppShell>
  );
}

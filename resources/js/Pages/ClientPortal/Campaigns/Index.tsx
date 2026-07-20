import { Head, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { clientNav } from '@/lib/nav';
import { ListHead, StatusBadge } from '@/Components/ui';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { u } from '@/lib/href';

interface Row {
  id: number; name: string; number: string; status: string; statusLabel: string; statusTone: string;
  deliverables: number; budgetMinor: number; startDate: string | null; endDate: string | null;
}
interface Props { clientName: string; items: Paginated<Row> }

const money = (m: number) => (m / 100).toLocaleString('en-US') + ' ر.س';

export default function ClientCampaignsIndex({ clientName, items }: Props) {
  return (
    <AppShell heading="الحملات" nav={clientNav} portal="client" wsName={clientName} wsPlan="بوابة العميل">
      <Head title="الحملات" />
      <ListHead eyebrow="بوابة العميل" title="الحملات" sub="متابعة حملاتك ومخرجاتها." />

      {items.data.length === 0 ? (
        <div className="card" style={{ padding: '2rem', textAlign: 'center', color: 'var(--ih-text-muted)' }}>لا حملات بعد.</div>
      ) : (
        <>
          <div className="ih-dt-wrap"><div className="ih-dt-scroll">
            <table className="ih-dt">
              <thead><tr><th>الحملة</th><th>الحالة</th><th>المخرجات</th><th>الميزانية</th><th>الفترة</th><th>—</th></tr></thead>
              <tbody>
                {items.data.map((cm) => (
                  <tr key={cm.id}>
                    <td>
                      <Link href={u(`/campaigns/${cm.id}`)} style={{ fontWeight: 600, color: 'var(--ih-primary)', textDecoration: 'none' }}>{cm.name}</Link>
                      <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{cm.number}</div>
                    </td>
                    <td><StatusBadge tone={cm.statusTone} label={cm.statusLabel} /></td>
                    <td style={{ direction: 'ltr' }}>{cm.deliverables.toLocaleString('en-US')}</td>
                    <td style={{ direction: 'ltr', fontWeight: 600 }}>{cm.budgetMinor ? money(cm.budgetMinor) : '—'}</td>
                    <td style={{ direction: 'ltr', fontSize: '.78rem', color: 'var(--ih-text-muted)' }}>{cm.startDate ?? '—'} → {cm.endDate ?? '—'}</td>
                    <td><Link href={u(`/campaigns/${cm.id}`)} className="btn btn-xs btn-outline">عرض</Link></td>
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

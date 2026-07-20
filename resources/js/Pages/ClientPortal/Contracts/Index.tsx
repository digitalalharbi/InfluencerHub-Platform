import { Head, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { clientNav } from '@/lib/nav';
import { ListHead, StatusBadge, Kpi } from '@/Components/ui';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { u } from '@/lib/href';

interface Row {
  id: number; number: string; title: string; campaignName: string | null;
  valueMinor: number; currency: string; status: string; statusLabel: string; statusTone: string;
}
interface Props { clientName: string; items: Paginated<Row>; awaiting: number }

const money = (m: number, cur: string) => (m / 100).toLocaleString('en-US') + ' ' + cur;

export default function ClientContractsIndex({ clientName, items, awaiting }: Props) {
  return (
    <AppShell heading="العقود" nav={clientNav} portal="client" wsName={clientName} wsPlan="بوابة العميل">
      <Head title="العقود" />
      <ListHead eyebrow="بوابة العميل" title="العقود" sub="راجع عقودك ووقّع ما بانتظار قبولك." />

      <div className="ih-kpis">
        <Kpi label="بانتظار توقيعك" icon="clipboard-check" tone={awaiting ? 'warning' : 'success'}
          value={awaiting.toLocaleString('en-US')} sub={awaiting ? 'يحتاج قبولك' : 'لا شيء معلّق'} />
        <Kpi label="إجمالي العقود" icon="file-text" value={items.total.toLocaleString('en-US')} sub="في نطاقك" />
      </div>

      {items.data.length === 0 ? (
        <div className="card" style={{ padding: '2rem', textAlign: 'center', color: 'var(--ih-text-muted)' }}>لا عقود بعد.</div>
      ) : (
        <>
          <div className="ih-dt-wrap"><div className="ih-dt-scroll">
            <table className="ih-dt">
              <thead><tr><th>العقد</th><th>الحملة</th><th>القيمة</th><th>الحالة</th><th>—</th></tr></thead>
              <tbody>
                {items.data.map((ct) => (
                  <tr key={ct.id}>
                    <td>
                      <Link href={u(`/contracts/${ct.id}`)} style={{ fontWeight: 600, color: 'var(--ih-primary)', textDecoration: 'none' }}>{ct.title}</Link>
                      <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{ct.number}</div>
                    </td>
                    <td>{ct.campaignName ?? '—'}</td>
                    <td style={{ direction: 'ltr', fontWeight: 600 }}>{ct.valueMinor ? money(ct.valueMinor, ct.currency) : '—'}</td>
                    <td><StatusBadge tone={ct.statusTone} label={ct.statusLabel} /></td>
                    <td><Link href={u(`/contracts/${ct.id}`)} className="btn btn-xs btn-outline">{ct.status === 'sent' ? 'مراجعة وتوقيع' : 'عرض'}</Link></td>
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

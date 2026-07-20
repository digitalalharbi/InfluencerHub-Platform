import { Head } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { adminNav } from '@/lib/nav';
import { ListHead } from '@/Components/ui';
import { Pagination, type Paginated } from '@/Components/Pagination';

interface Row {
  id: number; action: string; actor: string; type: string; auditableId: number | null;
  tenantId: number | null; ip: string | null; at: string | null;
}
interface Props { logs: Paginated<Row> }

export default function AdminAudit({ logs }: Props) {
  return (
    <AppShell heading="سجل التدقيق" nav={adminNav} portal="admin" wsName="إدارة المنصّة" wsPlan="مدير النظام" brand="InfluencerHub">
      <Head title="سجل التدقيق" />
      <ListHead eyebrow="الإشراف" title="سجل التدقيق" sub={`${logs.total.toLocaleString('en-US')} حدث — أحدث أولًا`} />

      {logs.data.length === 0 ? (
        <div className="card" style={{ padding: '2rem', textAlign: 'center', color: 'var(--ih-text-muted)' }}>لا سجلات تدقيق.</div>
      ) : (
        <>
          <div className="ih-dt-wrap"><div className="ih-dt-scroll">
            <table className="ih-dt">
              <thead><tr><th>الحدث</th><th>المنفِّذ</th><th>الكائن</th><th>المستأجر</th><th>IP</th><th>الوقت</th></tr></thead>
              <tbody>
                {logs.data.map((a) => (
                  <tr key={a.id}>
                    <td style={{ fontWeight: 600, direction: 'ltr' }}>{a.action}</td>
                    <td>{a.actor}</td>
                    <td style={{ direction: 'ltr', fontSize: '.8rem' }}>{a.type}{a.auditableId ? `#${a.auditableId}` : ''}</td>
                    <td style={{ direction: 'ltr' }}>{a.tenantId ?? '—'}</td>
                    <td style={{ direction: 'ltr', fontSize: '.76rem', color: 'var(--ih-text-muted)' }}>{a.ip ?? '—'}</td>
                    <td style={{ direction: 'ltr', fontSize: '.76rem', color: 'var(--ih-text-muted)' }}>{a.at}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div></div>
          <Pagination links={logs.links} />
        </>
      )}
    </AppShell>
  );
}

import { Head } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { clientNav } from '@/lib/nav';
import { ListHead } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';

interface Doc {
  id: number; title: string; category: string; categoryLabel: string;
  name: string | null; ext: string | null; sizeKb: number | null; uploadedAt: string | null;
}
interface Props { clientName: string; docs: Doc[] }

export default function ClientDocumentsIndex({ clientName, docs }: Props) {
  return (
    <AppShell heading="المستندات" nav={clientNav} portal="client" wsName={clientName} wsPlan="بوابة العميل">
      <Head title="المستندات" />
      <ListHead eyebrow="بوابة العميل" title="المستندات" sub="المستندات التي شاركتها معك الوكالة." />

      {docs.length === 0 ? (
        <div className="card" style={{ padding: '2rem', textAlign: 'center', color: 'var(--ih-text-muted)' }}>لا مستندات متاحة بعد.</div>
      ) : (
        <div className="ih-dt-wrap"><div className="ih-dt-scroll">
          <table className="ih-dt">
            <thead><tr><th>المستند</th><th>الفئة</th><th>الحجم</th><th>التاريخ</th><th>—</th></tr></thead>
            <tbody>
              {docs.map((d) => (
                <tr key={d.id}>
                  <td>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '.5rem' }}>
                      <Icon name="file-text" size={16} />
                      <div>
                        <div style={{ fontWeight: 600 }}>{d.title}</div>
                        {d.name && <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{d.name}</div>}
                      </div>
                    </div>
                  </td>
                  <td><span className="ih-tag" style={{ fontSize: '.7rem' }}>{d.categoryLabel}</span></td>
                  <td style={{ direction: 'ltr', fontSize: '.8rem', color: 'var(--ih-text-muted)' }}>{d.sizeKb ? `${d.sizeKb.toLocaleString('en-US')} KB` : '—'}</td>
                  <td style={{ direction: 'ltr', fontSize: '.8rem', color: 'var(--ih-text-muted)' }}>{d.uploadedAt ?? '—'}</td>
                  <td><a href={u(`/documents/${d.id}/download`)} className="btn btn-xs btn-outline">تنزيل</a></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div></div>
      )}
    </AppShell>
  );
}

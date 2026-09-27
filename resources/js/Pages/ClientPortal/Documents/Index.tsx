import { Head } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { clientNav } from '@/lib/nav';
import { ListHead } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';
import { useT } from '@/lib/i18n';

interface Doc {
  id: number; title: string; category: string; categoryLabel: string;
  name: string | null; ext: string | null; sizeKb: number | null; uploadedAt: string | null;
}
interface Props { clientName: string; docs: Doc[] }

export default function ClientDocumentsIndex({ clientName, docs }: Props) {
  const t = useT();
  return (
    <AppShell heading={t('client_portal.documents_title')} nav={clientNav} portal="client" wsName={clientName} wsPlan={t('client_portal.ws_plan')}>
      <Head title={t('client_portal.documents_title')} />
      <ListHead eyebrow={t('client_portal.eyebrow')} title={t('client_portal.documents_title')} sub={t('client_portal.documents_sub')} />

      {docs.length === 0 ? (
        <div className="card" style={{ padding: '2rem', textAlign: 'center', color: 'var(--ih-text-muted)' }}>{t('client_portal.documents_empty')}</div>
      ) : (
        <div className="ih-dt-wrap"><div className="ih-dt-scroll">
          <table className="ih-dt">
            <thead><tr><th>{t('client_portal.th_document')}</th><th>{t('client_portal.th_category')}</th><th>{t('client_portal.th_size')}</th><th>{t('client_portal.th_date')}</th><th>—</th></tr></thead>
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
                  <td><a href={u(`/documents/${d.id}/download`)} className="btn btn-xs btn-outline">{t('client_portal.download')}</a></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div></div>
      )}
    </AppShell>
  );
}

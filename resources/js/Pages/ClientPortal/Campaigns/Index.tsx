import { Head, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { clientNav } from '@/lib/nav';
import { ListHead, StatusBadge } from '@/Components/ui';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { u } from '@/lib/href';
import { useT } from '@/lib/i18n';

interface Row {
  id: number; name: string; number: string; status: string; statusLabel: string; statusTone: string;
  deliverables: number; budgetMinor: number; startDate: string | null; endDate: string | null;
}
interface Props { clientName: string; items: Paginated<Row> }

export default function ClientCampaignsIndex({ clientName, items }: Props) {
  const t = useT();
  const money = (m: number) => (m / 100).toLocaleString('en-US') + ' ' + t('common.currency_sar');
  return (
    <AppShell heading={t('client_portal.campaigns_title')} nav={clientNav} portal="client" wsName={clientName} wsPlan={t('client_portal.ws_plan')}>
      <Head title={t('client_portal.campaigns_title')} />
      <ListHead eyebrow={t('client_portal.eyebrow')} title={t('client_portal.campaigns_title')} sub={t('client_portal.campaigns_sub')} />

      {items.data.length === 0 ? (
        <div className="card" style={{ padding: '2rem', textAlign: 'center', color: 'var(--ih-text-muted)' }}>{t('client_portal.campaigns_empty')}</div>
      ) : (
        <>
          <div className="ih-dt-wrap"><div className="ih-dt-scroll">
            <table className="ih-dt">
              <thead><tr><th>{t('client_portal.th_campaign')}</th><th>{t('client_portal.th_status')}</th><th>{t('client_portal.th_deliverables')}</th><th>{t('client_portal.th_budget')}</th><th>{t('client_portal.th_period')}</th><th>—</th></tr></thead>
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
                    <td><Link href={u(`/campaigns/${cm.id}`)} className="btn btn-xs btn-outline">{t('client_portal.view')}</Link></td>
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

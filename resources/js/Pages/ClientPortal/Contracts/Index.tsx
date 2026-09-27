import { Head, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { clientNav } from '@/lib/nav';
import { ListHead, StatusBadge, Kpi } from '@/Components/ui';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { u } from '@/lib/href';
import { useT } from '@/lib/i18n';

interface Row {
  id: number; number: string; title: string; campaignName: string | null;
  valueMinor: number; currency: string; status: string; statusLabel: string; statusTone: string;
}
interface Props { clientName: string; items: Paginated<Row>; awaiting: number }

const money = (m: number, cur: string) => (m / 100).toLocaleString('en-US') + ' ' + cur;

export default function ClientContractsIndex({ clientName, items, awaiting }: Props) {
  const t = useT();
  return (
    <AppShell heading={t('client_portal.contracts_title')} nav={clientNav} portal="client" wsName={clientName} wsPlan={t('client_portal.ws_plan')}>
      <Head title={t('client_portal.contracts_title')} />
      <ListHead eyebrow={t('client_portal.eyebrow')} title={t('client_portal.contracts_title')} sub={t('client_portal.contracts_sub')} />

      <div className="ih-kpis">
        <Kpi label={t('client_portal.ct_kpi_awaiting')} icon="clipboard-check" tone={awaiting ? 'warning' : 'success'}
          value={awaiting.toLocaleString('en-US')} sub={awaiting ? t('client_portal.ct_kpi_awaiting_sub') : t('client_portal.c_kpi_none')} />
        <Kpi label={t('client_portal.ct_kpi_total')} icon="file-text" value={items.total.toLocaleString('en-US')} sub={t('client_portal.c_kpi_total_sub')} />
      </div>

      {items.data.length === 0 ? (
        <div className="card" style={{ padding: '2rem', textAlign: 'center', color: 'var(--ih-text-muted)' }}>{t('client_portal.contracts_empty')}</div>
      ) : (
        <>
          <div className="ih-dt-wrap"><div className="ih-dt-scroll">
            <table className="ih-dt">
              <thead><tr><th>{t('client_portal.th_contract')}</th><th>{t('client_portal.th_campaign')}</th><th>{t('client_portal.th_value')}</th><th>{t('client_portal.th_status')}</th><th>—</th></tr></thead>
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
                    <td><Link href={u(`/contracts/${ct.id}`)} className="btn btn-xs btn-outline">{ct.status === 'sent' ? t('client_portal.review_sign') : t('client_portal.view')}</Link></td>
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

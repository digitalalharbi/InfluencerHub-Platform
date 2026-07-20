import { Head, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { partnerNav } from '@/lib/nav';
import { Kpi, Sec } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';

interface LinkRow { id: number; client: string; brand: string | null; scopes: string[] }
interface Props {
  agency: { name: string; number: string };
  stats: { clients: number; links: number; openRequests: number };
  links: LinkRow[];
}

export default function PartnerDashboard({ agency, stats, links }: Props) {
  return (
    <AppShell heading="لوحة التحكم" nav={partnerNav} portal="partner" wsName={agency.name} wsPlan="بوابة الشريك">
      <Head title={`${agency.name} — البوابة`} />

      <div className="ih-listhead">
        <div>
          <div className="ih-listhead__eyebrow">بوابة الشريك</div>
          <h1 className="ih-listhead__title">{agency.name}</h1>
          <div className="ih-listhead__sub" style={{ direction: 'ltr' }}>{agency.number}</div>
        </div>
        <div className="ih-listhead__actions">
          <Link href={u("/requests")} className="btn btn-sm">الطلبات</Link>
        </div>
      </div>

      <div className="ih-kpis">
        <Kpi label="العملاء" icon="building-2" value={stats.clients.toLocaleString('en-US')} sub="ضمن نطاقك" />
        <Kpi label="الروابط النشطة" icon="handshake" value={stats.links.toLocaleString('en-US')} sub="عميل/علامة" />
        <Kpi label="طلبات مفتوحة" icon="inbox" tone={stats.openRequests ? 'warning' : 'success'} value={stats.openRequests.toLocaleString('en-US')} sub="قيد التنفيذ" href={u("/requests")} />
      </div>

      <div className="card" style={{ padding: '.8rem 1rem', marginBottom: '1.2rem', borderInlineStart: '3px solid var(--ih-info)', background: 'var(--ih-info-soft)', color: 'var(--ih-info-ink)', fontSize: '.82rem' }}>
        <Icon name="shield-check" size={14} /> ترى فقط العملاء والعلامات التي رُبطت بها الوكالة صراحةً، ضمن النطاقات الممنوحة لكل رابط.
      </div>

      <Sec title="روابطك النشطة" icon="handshake">
        {links.length === 0 ? (
          <div style={{ padding: '1.6rem', textAlign: 'center', color: 'var(--ih-text-muted)', fontSize: '.86rem' }}>لا روابط نشطة بعد.</div>
        ) : (
          <div className="ih-dt-wrap"><div className="ih-dt-scroll">
            <table className="ih-dt">
              <thead><tr><th>العميل</th><th>العلامة</th><th>النطاقات الممنوحة</th></tr></thead>
              <tbody>
                {links.map((l) => (
                  <tr key={l.id}>
                    <td style={{ fontWeight: 600 }}>{l.client}</td>
                    <td>{l.brand ?? '—'}</td>
                    <td>
                      {l.scopes.length === 0 ? <span style={{ color: 'var(--ih-text-muted)', fontSize: '.8rem' }}>—</span> : (
                        <div style={{ display: 'flex', gap: '.3rem', flexWrap: 'wrap' }}>
                          {l.scopes.map((s, i) => <span key={i} className="ih-tag" style={{ fontSize: '.68rem' }}>{s}</span>)}
                        </div>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div></div>
        )}
      </Sec>
    </AppShell>
  );
}

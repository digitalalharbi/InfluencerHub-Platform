import { Head, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { clientNav } from '@/lib/nav';
import { WorkspaceHeader, SummaryStrip, Sec, StatusBadge } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';

interface Deliverable { id: number; type: string; platform: string | null; quantity: number; creator: string | null; status: string; statusLabel: string; statusTone: string }
interface Campaign {
  id: number; name: string; number: string; brand: string | null; objective: string | null;
  status: string; statusLabel: string; statusTone: string; budgetMinor: number; currency: string; startDate: string | null; endDate: string | null;
}
interface Shortlist { version: number; status: string; pending: number; link: string }
interface Props { clientName: string; campaign: Campaign; deliverables: Deliverable[]; shortlist: Shortlist | null }

const money = (m: number, cur: string) => (m / 100).toLocaleString('en-US') + ' ' + cur;

export default function ClientCampaignShow({ clientName, campaign, deliverables, shortlist }: Props) {
  return (
    <AppShell heading="حملة" nav={clientNav} portal="client" wsName={clientName} wsPlan="بوابة العميل">
      <Head title={campaign.name} />

      <WorkspaceHeader
        eyebrow={`حملة · ${campaign.number}`}
        title={campaign.name}
        statusTone={campaign.statusTone} statusLabel={campaign.statusLabel}
        back={u("/campaigns")} backLabel="الحملات"
        meta={[
          ['العلامة', campaign.brand ?? '—'], ['البداية', campaign.startDate ?? '—'], ['النهاية', campaign.endDate ?? '—'],
        ]}
        actions={
          shortlist ? (
            <Link href={u(shortlist.link)} className="btn btn-sm">
              قرار الترشيح{shortlist.pending > 0 && <span className="ih-nav__badge" style={{ marginInlineStart: '.4rem' }}>{shortlist.pending}</span>}
            </Link>
          ) : undefined
        }
      />

      {shortlist && shortlist.pending > 0 && (
        <div className="ih-nba">
          <span className="ih-nba__icon"><Icon name="users" size={22} /></span>
          <div className="ih-nba__body">
            <div className="ih-nba__eyebrow">يحتاج قرارك</div>
            <div className="ih-nba__title">{shortlist.pending} مؤثرًا بانتظار اعتمادك في قائمة الترشيح</div>
          </div>
          <Link href={u(shortlist.link)} className="btn btn-sm">مراجعة الترشيح</Link>
        </div>
      )}

      <SummaryStrip
        items={[
          { label: 'الحالة', value: campaign.statusLabel, icon: 'activity' },
          { label: 'المخرجات', value: deliverables.length.toLocaleString('en-US'), icon: 'image' },
          { label: 'الميزانية', value: campaign.budgetMinor ? money(campaign.budgetMinor, campaign.currency) : '—', icon: 'wallet' },
          { label: 'العلامة', value: campaign.brand ?? '—', icon: 'bookmark' },
        ]}
      />

      {campaign.objective && (
        <div className="card" style={{ padding: '.9rem 1.1rem', marginBottom: '1.2rem', fontSize: '.88rem', lineHeight: 1.7 }}>
          <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', marginBottom: '.2rem' }}>هدف الحملة</div>
          {campaign.objective}
        </div>
      )}

      <Sec title="المخرجات" icon="image">
        {deliverables.length === 0 ? (
          <div style={{ padding: '1.6rem', textAlign: 'center', color: 'var(--ih-text-muted)', fontSize: '.86rem' }}>لا مخرجات مُعرّفة بعد.</div>
        ) : (
          <div className="ih-dt-wrap"><div className="ih-dt-scroll">
            <table className="ih-dt">
              <thead><tr><th>النوع</th><th>المنصّة</th><th>العدد</th><th>المبدع</th><th>الحالة</th></tr></thead>
              <tbody>
                {deliverables.map((d) => (
                  <tr key={d.id}>
                    <td style={{ fontWeight: 600 }}>{d.type}</td>
                    <td style={{ direction: 'ltr' }}>{d.platform ?? '—'}</td>
                    <td style={{ direction: 'ltr' }}>{d.quantity.toLocaleString('en-US')}</td>
                    <td>{d.creator ?? '—'}</td>
                    <td><StatusBadge tone={d.statusTone} label={d.statusLabel} /></td>
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

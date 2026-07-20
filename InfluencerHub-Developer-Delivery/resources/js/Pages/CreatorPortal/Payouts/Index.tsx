import { Head } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { creatorNav } from '@/lib/nav';
import { ListHead, StatusBadge, Kpi } from '@/Components/ui';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { Icon } from '@/Components/Icon';

interface Row {
  id: number; number: string; description: string | null; campaign: string | null;
  amountMinor: number; currency: string; status: string; statusLabel: string; statusTone: string;
  dueDate: string | null; paidAt: string | null; reference: string | null;
}
interface Props { creatorName: string; items: Paginated<Row>; paidMinor: number; openMinor: number; currency: string }

const money = (m: number, cur: string) => (m / 100).toLocaleString('en-US') + ' ' + cur;

export default function CreatorPayoutsIndex({ creatorName, items, paidMinor, openMinor, currency }: Props) {
  return (
    <AppShell heading="المستحقات" nav={creatorNav} portal="creator" wsName={creatorName} wsPlan="بوابة المبدع">
      <Head title="المستحقات" />
      <ListHead eyebrow="بوابة المبدع" title="المستحقات" sub="سجل أرباحك ومستحقاتك — أرقام فعلية." />

      <div className="ih-kpis">
        <Kpi label="أرباح مدفوعة" icon="wallet" tone="success" value={money(paidMinor, currency)} sub="إجمالي المستلَم" />
        <Kpi label="مستحقات مفتوحة" icon="wallet" tone={openMinor ? 'warning' : undefined} value={money(openMinor, currency)} sub="قيد الصرف" />
        <Kpi label="عدد المستحقات" icon="clipboard-check" value={items.total.toLocaleString('en-US')} sub="إجمالي" />
      </div>

      <div className="card" style={{ padding: '.8rem 1rem', marginBottom: '1.2rem', borderInlineStart: '3px solid var(--ih-info)', background: 'var(--ih-info-soft)', color: 'var(--ih-info-ink)', fontSize: '.82rem' }}>
        <Icon name="shield-check" size={14} /> المستحقات تُصرف من فريق مالية الوكالة. الحالة والمرجع يظهران هنا فور التحديث.
      </div>

      {items.data.length === 0 ? (
        <div className="card" style={{ padding: '2rem', textAlign: 'center', color: 'var(--ih-text-muted)' }}>لا مستحقات بعد.</div>
      ) : (
        <>
          {/* أرباحي — مقسّمة حسب حالة الصرف */}
          {([['paid', 'مدفوع'], ['open', 'قيد الصرف']] as [string, string][]).map(([bk, label]) => {
            const grp = items.data.filter((p) => (bk === 'paid' ? p.status === 'paid' : p.status !== 'paid'));
            if (grp.length === 0) return null;
            const total = grp.reduce((t, p) => t + p.amountMinor, 0);
            return (
              <div key={bk} style={{ marginBottom: '1.2rem' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '.5rem', marginBottom: '.55rem' }}>
                  <span style={{ fontWeight: 700, fontSize: '.88rem' }}>{label}</span>
                  <span className="ih-pipe__count">{grp.length}</span>
                  <span style={{ marginInlineStart: 'auto', fontWeight: 700, direction: 'ltr', fontSize: '.88rem' }}>{money(total, currency)}</span>
                </div>
                <div className="ih-triage">
                  {grp.map((p) => (
                    <div key={p.id} className={`ih-trow${p.status === 'paid' ? ' ih-trow--done' : ' ih-trow--new'}`} style={{ cursor: 'default' }}>
                      <div style={{ minWidth: 0, flex: 1 }}>
                        <div style={{ fontWeight: 650, fontSize: '.87rem' }}>{p.description ?? p.number}</div>
                        <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)' }}>
                          <span style={{ direction: 'ltr' }}>{p.number}</span>{p.campaign ? ` · ${p.campaign}` : ''}
                          {p.reference ? <> · مرجع <span style={{ direction: 'ltr' }}>{p.reference}</span></> : ''}
                        </div>
                      </div>
                      <span style={{ fontSize: '.73rem', color: 'var(--ih-text-muted)', direction: 'ltr', flexShrink: 0 }}>{p.paidAt ?? p.dueDate ?? '—'}</span>
                      <span style={{ fontWeight: 700, direction: 'ltr', fontSize: '.9rem', flexShrink: 0 }}>{money(p.amountMinor, p.currency)}</span>
                      <StatusBadge tone={p.statusTone} label={p.statusLabel} />
                    </div>
                  ))}
                </div>
              </div>
            );
          })}
          <Pagination links={items.links} />
        </>
      )}
    </AppShell>
  );
}

import { Head, Link } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { creatorNav } from '@/lib/nav';
import { Kpi, Sec, StatusBadge } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';

interface Pending { key: string; label: string; count: number; icon: string; link: string }
interface RecentCollab { id: number; number: string; campaign: string | null; client: string | null; status: string; statusLabel: string; statusTone: string; feeMinor: number }
interface Props {
  creator: { name: string; handle: string | null; platform: string | null; verified: boolean; followers: number };
  pending: Pending[];
  earnings: { paidMinor: number; openMinor: number };
  recent: RecentCollab[];
}

const money = (m: number) => (m / 100).toLocaleString('en-US') + ' ر.س';
const fmt = (n: number) => n >= 1000 ? Math.round(n / 1000).toLocaleString('en-US') + 'K' : n.toLocaleString('en-US');

export default function CreatorDashboard({ creator, pending, earnings, recent }: Props) {
  const actionable = pending.filter((p) => p.count > 0);

  return (
    <AppShell heading="لوحة التحكم" nav={creatorNav} portal="creator" wsName={creator.name} wsPlan="بوابة المبدع">
      <Head title={`${creator.name} — البوابة`} />

      <div className="ih-listhead">
        <div>
          <div className="ih-listhead__eyebrow">بوابة المبدع</div>
          <h1 className="ih-listhead__title" style={{ display: 'flex', alignItems: 'center', gap: '.4rem' }}>
            مرحبًا، {creator.name}{creator.verified && <Icon name="shield-check" size={18} />}
          </h1>
          <div className="ih-listhead__sub" style={{ direction: 'ltr' }}>
            {creator.handle ?? ''} {creator.platform ? `· ${creator.platform}` : ''} · {fmt(creator.followers)} متابع
          </div>
        </div>
      </div>

      {actionable.length > 0 ? (
        <div className="ih-nba" style={{ alignItems: 'stretch', flexWrap: 'wrap' }}>
          <span className="ih-nba__icon"><Icon name="rocket" size={22} /></span>
          <div className="ih-nba__body" style={{ flex: 1, minWidth: 200 }}>
            <div className="ih-nba__eyebrow">مهامك الآن</div>
            <div className="ih-nba__title">{actionable.reduce((s, p) => s + p.count, 0)} عنصرًا يحتاج إجراءك</div>
          </div>
          <div style={{ display: 'flex', gap: '.5rem', flexWrap: 'wrap' }}>
            {actionable.map((p) => (
              <Link key={p.key} href={u(p.link)} className="btn btn-sm btn-outline" style={{ display: 'inline-flex', alignItems: 'center', gap: '.4rem' }}>
                <Icon name={p.icon as never} size={14} /> {p.label} <span className="ih-nav__badge">{p.count}</span>
              </Link>
            ))}
          </div>
        </div>
      ) : (
        <div className="card" style={{ padding: '.9rem 1.1rem', marginBottom: '1.2rem', borderInlineStart: '3px solid var(--ih-success)', background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)', fontSize: '.86rem' }}>
          <Icon name="shield-check" size={15} /> لا مهام عاجلة — أنت على المسار.
        </div>
      )}

      <div className="ih-kpis">
        <Kpi label="أرباح مدفوعة" icon="wallet" tone="success" value={money(earnings.paidMinor)} sub="إجمالي المستلَم" href={u("/payouts")} />
        <Kpi label="مستحقات مفتوحة" icon="wallet" tone={earnings.openMinor ? 'warning' : undefined} value={money(earnings.openMinor)} sub="قيد الصرف" href={u("/payouts")} />
        <Kpi label="المتابعون" icon="users" value={fmt(creator.followers)} sub="عبر منصّاتك" />
        <Kpi label="التوثيق" icon="shield-check" tone={creator.verified ? 'success' : 'warning'} value={creator.verified ? 'موثّق' : 'غير موثّق'} sub="موثوق" />
      </div>

      <Sec title="أحدث التعاونات" icon="git-merge" link={{ href: u('/collaborations'), label: 'عرض الكل' }}>
        {recent.length === 0 ? (
          <div style={{ padding: '1.6rem', textAlign: 'center', color: 'var(--ih-text-muted)', fontSize: '.86rem' }}>لا تعاونات بعد.</div>
        ) : (
          <div className="ih-dt-wrap"><div className="ih-dt-scroll">
            <table className="ih-dt">
              <thead><tr><th>التعاون</th><th>العميل</th><th>الحالة</th><th>الأجر</th></tr></thead>
              <tbody>
                {recent.map((cl) => (
                  <tr key={cl.id}>
                    <td>
                      <Link href={u(`/collaborations/${cl.id}`)} style={{ fontWeight: 600, color: 'var(--ih-primary)', textDecoration: 'none' }}>{cl.campaign ?? cl.number}</Link>
                      <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{cl.number}</div>
                    </td>
                    <td>{cl.client ?? '—'}</td>
                    <td><StatusBadge tone={cl.statusTone} label={cl.statusLabel} /></td>
                    <td style={{ direction: 'ltr', fontWeight: 600 }}>{cl.feeMinor ? money(cl.feeMinor) : '—'}</td>
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

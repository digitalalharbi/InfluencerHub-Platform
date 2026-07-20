import { Head } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { BarChart, DonutChart, Kpi, ListHead, Sec, StatusBadge } from '@/Components/ui';
import type { IconName } from '@/Components/Icon';
import { u } from '@/lib/href';

/** كل حدّ معرَّف في FinancialMetrics — الإيراد صافٍ من الضريبة. */
interface Financial {
  revenueMinor: number; taxMinor: number; billedMinor: number;
  collectedMinor: number; outstandingMinor: number;
  costMinor: number; costPaidMinor: number;
  profitMinor: number; margin: number;
  openPayoutMinor: number; activeContractValueMinor: number;
}
interface Kpis {
  clients: number; clientsActive: number; creators: number; creatorsActive: number;
  campaigns: number; campaignsActive: number; campaignsBudgetMinor: number;
  requestsOpen: number; requestsOverdue: number; contentPublished: number; contentAwaiting: number; collaborations: number;
}
interface Bar { label: string; tone: string; count: number }
interface TimelinePoint { key: string; label: string; paidMinor: number; budgetMinor: number; campaigns: number; published: number }
interface TopClient { id: number; name: string; revenueMinor: number; campaigns: number }
interface Props {
  timeline: TimelinePoint[]; topClients: TopClient[];
  financial: Financial; kpis: Kpis;
  breakdowns: { campaigns: Bar[]; requests: Bar[]; content: Bar[]; collaborations: Bar[] };
  creatorsByType: { label: string; count: number }[];
}

const TONE_COLOR: Record<string, string> = {
  draft: 'var(--ih-gray-400)', submitted: 'var(--ih-info)', under_review: 'var(--ih-warning)',
  changes_requested: '#C2410C', approved: 'var(--ih-success)', active: 'var(--ih-primary)',
  paused: 'var(--ih-gray-500)', rejected: 'var(--ih-danger)', completed: '#047857', archived: 'var(--ih-gray-400)',
};
function sar(m: number): string {
  const v = m / 100;
  if (v >= 1_000_000) return (v / 1_000_000).toFixed(1) + 'M';
  if (v >= 1000) return Math.round(v / 1000) + 'K';
  return v.toLocaleString('en-US');
}

function Breakdown({ title, icon, bars }: { title: string; icon: IconName; bars: Bar[] }) {
  const max = Math.max(1, ...bars.map((b) => b.count));
  return (
    <Sec title={title} icon={icon}>
      <div className="ih-sec__body" style={{ display: 'grid', gap: '.7rem' }}>
        {bars.length === 0 ? <div style={{ color: 'var(--ih-text-muted)', fontSize: '.85rem' }}>لا بيانات.</div> :
          bars.map((b, i) => (
            <div key={i}>
              <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '.78rem', marginBottom: '.25rem' }}>
                <StatusBadge tone={b.tone} label={b.label} />
                <span style={{ fontWeight: 700, fontVariantNumeric: 'tabular-nums' }}>{b.count}</span>
              </div>
              <div className="ih-bar"><span style={{ width: `${(b.count / max) * 100}%`, background: TONE_COLOR[b.tone] ?? 'var(--ih-primary)' }} /></div>
            </div>
          ))}
      </div>
    </Sec>
  );
}

export default function ReportsIndex({ timeline, topClients, financial, kpis, breakdowns, creatorsByType }: Props) {
  return (
    <AppShell heading="التقارير">
      <Head title="التقارير" />

      <ListHead eyebrow="البيانات والتقارير" title="التقارير"
        sub="نظرة تجميعية على الأداء المالي والتشغيلي — مشتقّة من بيانات PostgreSQL الحقيقية" />

      {/* المالية */}
      <div className="ih-kpis">
        <Kpi label="صافي الإيراد" icon="wallet" tone="success"
          value={<>{sar(financial.revenueMinor)} <small>ر.س</small></>}
          sub={`بلا ضريبة ${sar(financial.taxMinor)} · مفوتَر ${sar(financial.billedMinor)}`} />
        <Kpi label="تكلفة صناع المحتوى" icon="wallet"
          value={<>{sar(financial.costMinor)} <small>ر.س</small></>}
          sub={`صُرف منها ${sar(financial.costPaidMinor)}`} />
        <Kpi label="الربح" icon="wallet" tone="accent"
          value={<>{sar(financial.profitMinor)} <small>ر.س</small></>}
          sub={<>هامش <span className={`ih-delta ${financial.margin >= 0 ? 'ih-delta--up' : 'ih-delta--down'}`}>{financial.margin}%</span></>} />
        <Kpi label="التحصيل" icon="wallet"
          value={<>{sar(financial.collectedMinor)} <small>ر.س</small></>}
          sub={`متبقٍّ ${sar(financial.outstandingMinor)}`} />
      </div>

      {/* اتجاه زمني حقيقي — آخر 6 أشهر */}
      <div className="ih-overview-grid" style={{ display: 'grid', gridTemplateColumns: 'minmax(0,1.45fr) minmax(0,1fr)', gap: '1.1rem', alignItems: 'start', marginBottom: '1.2rem' }}>
        <Sec title="المدفوع للمبدعين — آخر 6 أشهر" icon="trending-up">
          <div className="ih-sec__body">
            <BarChart points={timeline.map((t) => ({ label: t.label, value: Math.round(t.paidMinor / 100) }))} format={(v) => v >= 1000 ? Math.round(v / 1000) + 'K' : String(v)} />
          </div>
        </Sec>
        <Sec title="حملات ومحتوى منشور" icon="bar-chart-3">
          <div className="ih-sec__body" style={{ display: 'grid', gap: '.55rem' }}>
            {timeline.map((t) => {
              const maxC = Math.max(...timeline.map((x) => x.campaigns + x.published), 1);
              return (
                <div key={t.key}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '.78rem', marginBottom: '.2rem' }}>
                    <span style={{ fontWeight: 600 }}>{t.label}</span>
                    <span style={{ color: 'var(--ih-text-muted)', direction: 'ltr' }}>{t.campaigns} حملة · {t.published} منشور</span>
                  </div>
                  <div className="ih-bar"><span style={{ width: `${Math.round(((t.campaigns + t.published) / maxC) * 100)}%` }} /></div>
                </div>
              );
            })}
          </div>
        </Sec>
      </div>

      {/* توزيع الحملات + أبرز العملاء */}
      <div className="ih-overview-grid" style={{ display: 'grid', gridTemplateColumns: 'minmax(0,1fr) minmax(0,1.35fr)', gap: '1.1rem', alignItems: 'start', marginBottom: '1.2rem' }}>
        <Sec title="توزيع الحملات" icon="megaphone">
          <div className="ih-sec__body">
            <DonutChart
              centerValue={String(kpis.campaigns)} centerLabel="حملة"
              slices={breakdowns.campaigns.slice(0, 5).map((b, i) => ({
                label: b.label, value: b.count,
                color: ['var(--ih-primary)', 'var(--ih-accent-500)', 'var(--ih-success)', 'var(--ih-warning)', 'var(--ih-gray-400)'][i] ?? 'var(--ih-gray-300)',
              }))} />
          </div>
        </Sec>
        <Sec title="أبرز العملاء بالإيراد" icon="building-2">
          {topClients.length === 0 ? (
            <div style={{ padding: '1.6rem', textAlign: 'center', color: 'var(--ih-text-muted)', fontSize: '.84rem' }}>لا إيرادات مسجّلة بعد.</div>
          ) : (
            <div className="ih-sec__body" style={{ display: 'grid', gap: '.6rem' }}>
              {topClients.map((c) => {
                const max = Math.max(...topClients.map((x) => x.revenueMinor), 1);
                return (
                  <a key={c.id} href={u(`/clients/${c.id}`)} style={{ textDecoration: 'none', color: 'inherit', display: 'block' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '.82rem', marginBottom: '.2rem' }}>
                      <span style={{ fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{c.name}</span>
                      <span style={{ color: 'var(--ih-text-muted)', direction: 'ltr', flexShrink: 0 }}>{sar(c.revenueMinor)} ر.س · {c.campaigns} نشطة</span>
                    </div>
                    <div className="ih-bar"><span style={{ width: `${Math.round((c.revenueMinor / max) * 100)}%` }} /></div>
                  </a>
                );
              })}
            </div>
          )}
        </Sec>
      </div>

      {/* تشغيلي */}
      <div className="ih-kpis">
        <Kpi label="العملاء" icon="building-2" value={kpis.clients.toLocaleString('en-US')} sub={`${kpis.clientsActive} نشط`} />
        <Kpi label="المبدعون" icon="users" value={kpis.creators.toLocaleString('en-US')} sub={`${kpis.creatorsActive} نشط`} />
        <Kpi label="الحملات" icon="megaphone" tone="accent" value={kpis.campaigns.toLocaleString('en-US')} sub={`${kpis.campaignsActive} نشطة · ميزانية ${sar(kpis.campaignsBudgetMinor)}`} />
        <Kpi label="طلبات مفتوحة" icon="inbox" tone={kpis.requestsOverdue ? 'danger' : undefined} value={kpis.requestsOpen.toLocaleString('en-US')} sub={`${kpis.requestsOverdue} متأخرة`} />
        <Kpi label="محتوى منشور" icon="image" value={kpis.contentPublished.toLocaleString('en-US')} sub={`${kpis.contentAwaiting} بانتظار المراجعة`} />
        <Kpi label="التعاونات" icon="handshake" value={kpis.collaborations.toLocaleString('en-US')} sub="إجمالي" />
      </div>

      <div className="ih-overview-grid" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1.1rem', alignItems: 'start' }}>
        <Breakdown title="الحملات حسب الحالة" icon="megaphone" bars={breakdowns.campaigns} />
        <Breakdown title="الطلبات حسب الحالة" icon="inbox" bars={breakdowns.requests} />
        <Breakdown title="المحتوى حسب الحالة" icon="image" bars={breakdowns.content} />
        <Breakdown title="التعاونات حسب الحالة" icon="handshake" bars={breakdowns.collaborations} />
      </div>

      <div style={{ marginTop: '1.1rem' }}>
        <Sec title="المبدعون حسب النوع" icon="users">
          <div className="ih-sec__body" style={{ display: 'flex', gap: '1.5rem', flexWrap: 'wrap' }}>
            {creatorsByType.length === 0 ? <div style={{ color: 'var(--ih-text-muted)' }}>لا بيانات.</div> :
              creatorsByType.map((t, i) => (
                <div key={i} style={{ textAlign: 'center' }}>
                  <div style={{ fontSize: '1.8rem', fontWeight: 800, color: 'var(--ih-primary)' }}>{t.count}</div>
                  <div style={{ fontSize: '.8rem', color: 'var(--ih-text-muted)' }}>{t.label}</div>
                </div>
              ))}
          </div>
        </Sec>
      </div>
    </AppShell>
  );
}

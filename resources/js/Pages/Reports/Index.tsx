import { Head } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { BarChart, DonutChart, Kpi, ListHead, Sec, StatusBadge } from '@/Components/ui';
import { Icon, type IconName } from '@/Components/Icon';
import { u } from '@/lib/href';
import { useT } from '@/lib/i18n';
import { ExportButtons } from '@/Components/ExportButtons';
import { PdfPreviewModal, type PreviewDoc } from '@/Components/PdfPreviewModal';

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
  documents: { report: PreviewDoc };
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
  const t = useT();
  const max = Math.max(1, ...bars.map((b) => b.count));
  return (
    <Sec title={title} icon={icon}>
      <div className="ih-sec__body" style={{ display: 'grid', gap: '.7rem' }}>
        {bars.length === 0 ? <div style={{ color: 'var(--ih-text-muted)', fontSize: '.85rem' }}>{t('reports.no_data')}</div> :
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

export default function ReportsIndex({ timeline, topClients, financial, kpis, breakdowns, creatorsByType, documents }: Props) {
  const t = useT();
  const [reportOpen, setReportOpen] = useState(false);
  return (
    <AppShell heading={t('reports.title')}>
      <Head title={t('reports.title')} />

      <ListHead eyebrow={t('reports.eyebrow')} title={t('reports.title')}
        sub={t('reports.sub')}
        actions={<span style={{ display: 'inline-flex', gap: '.4rem', alignItems: 'center' }}>
          <button onClick={() => setReportOpen(true)} className="btn btn-sm btn-outline" title={t('reports.pdf_preview_title')}>
            <Icon name="file-text" size={14} /> {t('reports.pdf_preview')}{documents.report.stale && <span style={{ width: 7, height: 7, borderRadius: '50%', background: 'var(--ih-warning-ink, #B54708)', display: 'inline-block', marginInlineStart: 5 }} />}
          </button>
          <ExportButtons path="/reports/export" formats={['xlsx', 'csv']} />
        </span>} />

      {/* المالية */}
      <div className="ih-kpis">
        <Kpi label={t('reports.fin_revenue')} icon="wallet" tone="success"
          value={<>{sar(financial.revenueMinor)} <small>{t('common.currency_sar')}</small></>}
          sub={t('reports.fin_revenue_sub', { tax: sar(financial.taxMinor), billed: sar(financial.billedMinor) })} />
        <Kpi label={t('reports.fin_cost')} icon="wallet"
          value={<>{sar(financial.costMinor)} <small>{t('common.currency_sar')}</small></>}
          sub={t('reports.fin_cost_sub', { paid: sar(financial.costPaidMinor) })} />
        <Kpi label={t('reports.fin_profit')} icon="wallet" tone="accent"
          value={<>{sar(financial.profitMinor)} <small>{t('common.currency_sar')}</small></>}
          sub={<>{t('reports.fin_margin')} <span className={`ih-delta ${financial.margin >= 0 ? 'ih-delta--up' : 'ih-delta--down'}`}>{financial.margin}%</span></>} />
        <Kpi label={t('reports.fin_collected')} icon="wallet"
          value={<>{sar(financial.collectedMinor)} <small>{t('common.currency_sar')}</small></>}
          sub={t('reports.fin_collected_sub', { out: sar(financial.outstandingMinor) })} />
      </div>

      {/* اتجاه زمني حقيقي — آخر 6 أشهر */}
      <div className="ih-overview-grid" style={{ display: 'grid', gridTemplateColumns: 'minmax(0,1.45fr) minmax(0,1fr)', gap: '1.1rem', alignItems: 'start', marginBottom: '1.2rem' }}>
        <Sec title={t('reports.sec_paid_timeline')} icon="trending-up">
          <div className="ih-sec__body">
            <BarChart points={timeline.map((pt) => ({ label: pt.label, value: Math.round(pt.paidMinor / 100) }))} format={(v) => v >= 1000 ? Math.round(v / 1000) + 'K' : String(v)} />
          </div>
        </Sec>
        <Sec title={t('reports.sec_campaigns_content')} icon="bar-chart-3">
          <div className="ih-sec__body" style={{ display: 'grid', gap: '.55rem' }}>
            {timeline.map((pt) => {
              const maxC = Math.max(...timeline.map((x) => x.campaigns + x.published), 1);
              return (
                <div key={pt.key}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '.78rem', marginBottom: '.2rem' }}>
                    <span style={{ fontWeight: 600 }}>{pt.label}</span>
                    <span style={{ color: 'var(--ih-text-muted)', direction: 'ltr' }}>{t('reports.tl_campaigns_published', { c: pt.campaigns, p: pt.published })}</span>
                  </div>
                  <div className="ih-bar"><span style={{ width: `${Math.round(((pt.campaigns + pt.published) / maxC) * 100)}%` }} /></div>
                </div>
              );
            })}
          </div>
        </Sec>
      </div>

      {/* توزيع الحملات + أبرز العملاء */}
      <div className="ih-overview-grid" style={{ display: 'grid', gridTemplateColumns: 'minmax(0,1fr) minmax(0,1.35fr)', gap: '1.1rem', alignItems: 'start', marginBottom: '1.2rem' }}>
        <Sec title={t('reports.sec_campaign_dist')} icon="megaphone">
          <div className="ih-sec__body">
            <DonutChart
              centerValue={String(kpis.campaigns)} centerLabel={t('reports.center_campaign')}
              slices={breakdowns.campaigns.slice(0, 5).map((b, i) => ({
                label: b.label, value: b.count,
                color: ['var(--ih-primary)', 'var(--ih-accent-500)', 'var(--ih-success)', 'var(--ih-warning)', 'var(--ih-gray-400)'][i] ?? 'var(--ih-gray-300)',
              }))} />
          </div>
        </Sec>
        <Sec title={t('reports.sec_top_clients')} icon="building-2">
          {topClients.length === 0 ? (
            <div style={{ padding: '1.6rem', textAlign: 'center', color: 'var(--ih-text-muted)', fontSize: '.84rem' }}>{t('reports.no_revenue')}</div>
          ) : (
            <div className="ih-sec__body" style={{ display: 'grid', gap: '.6rem' }}>
              {topClients.map((c) => {
                const max = Math.max(...topClients.map((x) => x.revenueMinor), 1);
                return (
                  <a key={c.id} href={u(`/clients/${c.id}`)} style={{ textDecoration: 'none', color: 'inherit', display: 'block' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '.82rem', marginBottom: '.2rem' }}>
                      <span style={{ fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{c.name}</span>
                      <span style={{ color: 'var(--ih-text-muted)', direction: 'ltr', flexShrink: 0 }}>{sar(c.revenueMinor)} {t('common.currency_sar')} · {t('reports.tc_active', { n: c.campaigns })}</span>
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
        <Kpi label={t('reports.op_clients')} icon="building-2" value={kpis.clients.toLocaleString('en-US')} sub={t('reports.active_n', { n: kpis.clientsActive })} />
        <Kpi label={t('reports.op_creators')} icon="users" value={kpis.creators.toLocaleString('en-US')} sub={t('reports.active_n', { n: kpis.creatorsActive })} />
        <Kpi label={t('reports.op_campaigns')} icon="megaphone" tone="accent" value={kpis.campaigns.toLocaleString('en-US')} sub={t('reports.op_campaigns_sub', { n: kpis.campaignsActive, budget: sar(kpis.campaignsBudgetMinor) })} />
        <Kpi label={t('reports.op_requests')} icon="inbox" tone={kpis.requestsOverdue ? 'danger' : undefined} value={kpis.requestsOpen.toLocaleString('en-US')} sub={t('reports.op_requests_sub', { n: kpis.requestsOverdue })} />
        <Kpi label={t('reports.op_content')} icon="image" value={kpis.contentPublished.toLocaleString('en-US')} sub={t('reports.op_content_sub', { n: kpis.contentAwaiting })} />
        <Kpi label={t('reports.op_collabs')} icon="handshake" value={kpis.collaborations.toLocaleString('en-US')} sub={t('reports.op_collabs_sub')} />
      </div>

      <div className="ih-overview-grid" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1.1rem', alignItems: 'start' }}>
        <Breakdown title={t('reports.bd_campaigns')} icon="megaphone" bars={breakdowns.campaigns} />
        <Breakdown title={t('reports.bd_requests')} icon="inbox" bars={breakdowns.requests} />
        <Breakdown title={t('reports.bd_content')} icon="image" bars={breakdowns.content} />
        <Breakdown title={t('reports.bd_collabs')} icon="handshake" bars={breakdowns.collaborations} />
      </div>

      <div style={{ marginTop: '1.1rem' }}>
        <Sec title={t('reports.sec_creators_type')} icon="users">
          <div className="ih-sec__body" style={{ display: 'flex', gap: '1.5rem', flexWrap: 'wrap' }}>
            {creatorsByType.length === 0 ? <div style={{ color: 'var(--ih-text-muted)' }}>{t('reports.no_data')}</div> :
              creatorsByType.map((ct, i) => (
                <div key={i} style={{ textAlign: 'center' }}>
                  <div style={{ fontSize: '1.8rem', fontWeight: 800, color: 'var(--ih-primary)' }}>{ct.count}</div>
                  <div style={{ fontSize: '.8rem', color: 'var(--ih-text-muted)' }}>{ct.label}</div>
                </div>
              ))}
          </div>
        </Sec>
      </div>
      <PdfPreviewModal doc={documents.report} open={reportOpen} onClose={() => setReportOpen(false)} />
    </AppShell>
  );
}

import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Bar, Sec, StatusBadge, SummaryStrip, WorkTabs, WorkspaceHeader } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import type { SharedProps } from '@/types';
import { u } from '@/lib/href';

interface Brand {
  id: number; name: string; client: string | null; clientId: number | null; sector: string | null;
  website: string | null; description: string | null; toneOfVoice: string | null; targetAudience: string | null;
  preferredLanguage: string | null; visualGuidelines: string | null; prohibitedTopics: string[]; requiredMessages: string[];
  status: string; statusLabel: string; statusTone: string; version: number;
  submittedAt: string | null; reviewedAt: string | null; changesReason: string | null;
}
type Action = [string, string, string, boolean];
interface Decision { decision: string; note: string | null; version: number; by: string; at: string | null }
interface History { from: string; to: string; by: string; reason: string | null; at: string | null }
interface BrandCampaign { id: number; name: string; deliverables: number; budgetMinor: number; content: number; published: number; progress: number; startDate: string | null; endDate: string | null; status: string; statusLabel: string; statusTone: string }
interface BrandContent { id: number; title: string; creator: string | null; platform: string | null; mediaUrl: string | null; version: number; type: string; publishedAt: string | null; needsAction: boolean; status: string; statusLabel: string; statusTone: string }
interface Metrics { campaigns: number; activeCampaigns: number; content: number; awaitingContent: number; budgetMinor: number }
interface Props {
  brand: Brand; canReview: boolean; actions: Action[];
  socialAccounts: { platform: string; handle: string | null; url: string | null }[];
  decisions: Decision[]; history: History[];
  metrics: Metrics; campaigns: BrandCampaign[]; content: BrandContent[];
}

const sar = (m: number) => Math.round(m / 100).toLocaleString('en-US') + ' ر.س';
const B_TABS = ['overview','campaigns','content','accounts','review'] as const;

const BTN: Record<string, string> = { primary: 'btn-primary', danger: 'btn-danger', ghost: 'btn-ghost' };
const DECISION_LABEL: Record<string, string> = { approved: 'موافقة', changes_requested: 'طلب تعديل', rejected: 'رفض' };

export default function BrandShow({ brand, canReview, actions, socialAccounts, decisions, history, metrics, campaigns, content }: Props) {
  const { props } = usePage<SharedProps>();
  const [tab, setTab] = useState('overview');
  useEffect(() => {
    const applyHash = () => {
      const h = window.location.hash.replace('#','');
      if ((B_TABS as readonly string[]).includes(h)) setTab(h);
    };
    applyHash();
    window.addEventListener('hashchange', applyHash);
    return () => window.removeEventListener('hashchange', applyHash);
  }, []);
  // ملاحظة: الخاصية `history` محجوزة لسجل الحالة، لذا نستخدم window.history صراحة
  const go = (k: string) => {
    setTab(k);
    window.history.replaceState(null, '', k === 'overview' ? window.location.pathname : `#${k}`);
  };
  const [reasonFor, setReasonFor] = useState<Action | null>(null);
  const [reason, setReason] = useState('');

  const runAction = (a: Action) => {
    if (a[3]) { setReasonFor(a); setReason(''); return; }
    router.post(u(`/brands/${brand.id}/${a[0]}`), {}, { preserveScroll: true });
  };
  const submitReason = () => {
    if (!reasonFor) return;
    router.post(u(`/brands/${brand.id}/${reasonFor[0]}`), { reason }, { preserveScroll: true, onSuccess: () => setReasonFor(null) });
  };

  const facts: [string, string | null][] = [
    ['الموقع', brand.website], ['اللغة المفضّلة', brand.preferredLanguage],
    ['نبرة الصوت', brand.toneOfVoice], ['الجمهور المستهدف', brand.targetAudience],
  ];

  return (
    <AppShell heading="علامة تجارية">
      <Head title={brand.name} />

      {props.flash?.ok && <div className="card" style={{ padding: '.7rem 1rem', marginBottom: '1rem', borderInlineStart: '3px solid var(--ih-success)', background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)' }}>{props.flash.ok}</div>}

      <WorkspaceHeader
        eyebrow={`علامة · ${brand.client ?? '—'}`}
        title={brand.name}
        statusTone={brand.statusTone} statusLabel={brand.statusLabel}
        back={u("/brands")} backLabel="كل العلامات"
        meta={[
          ['القطاع', brand.sector ?? '—'], ['الإصدار', `v${brand.version}`],
          ['أُرسلت', brand.submittedAt ?? '—'], ['روجعت', brand.reviewedAt ?? '—'],
        ]}
        actions={canReview && actions.length > 0 ? <>{actions.map((a) => (
          <button key={a[0]} onClick={() => runAction(a)} className={`btn btn-sm ${BTN[a[2]] ?? 'btn-outline'}`}>{a[1]}</button>
        ))}</> : undefined}
      />

      {brand.status === 'changes_requested' && brand.changesReason && (
        <div className="card" style={{ padding: '.8rem 1rem', marginBottom: '1.2rem', borderInlineStart: '3px solid var(--ih-warning)', background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)' }}>
          <b>تعديلات مطلوبة:</b> {brand.changesReason}
        </div>
      )}

      <SummaryStrip items={[
        { label: 'الحملات', value: `${metrics.activeCampaigns}/${metrics.campaigns}`, icon: 'megaphone' },
        { label: 'الميزانية', value: sar(metrics.budgetMinor), icon: 'wallet', tone: 'primary' },
        { label: 'المحتوى', value: metrics.content, icon: 'image' },
        { label: 'بانتظار المراجعة', value: metrics.awaitingContent, icon: 'clipboard-check', tone: metrics.awaitingContent ? 'warning' : undefined },
        { label: 'الإصدار', value: `v${brand.version}` },
      ]} />

      <WorkTabs active={tab} onChange={go} tabs={[
        { key: 'overview', label: 'نظرة عامة', icon: 'bookmark' },
        { key: 'campaigns', label: 'الحملات', icon: 'megaphone', count: campaigns.length },
        { key: 'content', label: 'المحتوى', icon: 'image', count: content.length },
        { key: 'accounts', label: 'الحسابات', icon: 'git-merge', count: socialAccounts.length },
        { key: 'review', label: 'المراجعة', icon: 'clipboard-check', count: decisions.length },
      ]} />

      {/* حملات العلامة — بطاقات بتقدّم فعلي */}
      {tab === 'campaigns' && (
        campaigns.length === 0 ? (
          <div className="card" style={{ padding: '2.4rem', textAlign: 'center' }}>
            <span className="ih-empty__icon" style={{ width: 48, height: 48 }}><Icon name="megaphone" size={22} /></span>
            <div style={{ marginTop: '.6rem', fontWeight: 700 }}>لا حملات لهذه العلامة</div>
          </div>
        ) : (
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(300px, 1fr))', gap: '.9rem' }}>
            {campaigns.map((c) => (
              <a key={c.id} href={u(`/campaigns/${c.id}`)} className="ih-wcard">
                <div style={{ display: 'flex', justifyContent: 'space-between', gap: '.5rem', alignItems: 'flex-start' }}>
                  <span className="ih-wcard__title">{c.name}</span>
                  <StatusBadge tone={c.statusTone} label={c.statusLabel} />
                </div>
                <div className="ih-wcard__meta">{c.startDate ?? '—'} → {c.endDate ?? '—'}</div>
                {c.content > 0 && (
                  <div style={{ marginTop: '.55rem' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '.7rem', color: 'var(--ih-text-muted)', marginBottom: '.2rem' }}>
                      <span>المحتوى المنشور</span><span style={{ direction: 'ltr' }}>{c.published}/{c.content}</span>
                    </div>
                    <Bar pct={c.progress} />
                  </div>
                )}
                <div className="ih-wcard__row">
                  <span style={{ display: 'flex', gap: '.6rem', fontSize: '.72rem', color: 'var(--ih-text-muted)' }}>
                    <span><Icon name="image" size={12} /> {c.deliverables} مخرج</span>
                  </span>
                  <span style={{ fontWeight: 700, direction: 'ltr', fontSize: '.84rem' }}>{sar(c.budgetMinor)}</span>
                </div>
              </a>
            ))}
          </div>
        )
      )}

      {/* محتوى العلامة — معرض معاينات */}
      {tab === 'content' && (
        content.length === 0 ? (
          <div className="card" style={{ padding: '2.4rem', textAlign: 'center' }}>
            <span className="ih-empty__icon" style={{ width: 48, height: 48 }}><Icon name="image" size={22} /></span>
            <div style={{ marginTop: '.6rem', fontWeight: 700 }}>لا محتوى مرتبط</div>
          </div>
        ) : (
          <div className="ih-gallery">
            {content.map((c) => (
              <a key={c.id} href={u(`/content/${c.id}`)} className="ih-gtile" style={{ textDecoration: 'none', color: 'inherit' }}>
                <div className="ih-gtile__thumb">
                  {c.mediaUrl ? <img src={c.mediaUrl} alt="" loading="lazy" /> : <Icon name="image" size={26} />}
                  <span className="ih-gtile__badge"><StatusBadge tone={c.statusTone} label={c.statusLabel} /></span>
                  {c.version > 1 && <span className="ih-gtile__ver">v{c.version}</span>}
                </div>
                <div className="ih-gtile__body">
                  <div className="ih-gtile__title">{c.title}</div>
                  <div className="ih-gtile__meta">{c.creator ?? '—'}{c.platform ? ` · ${c.platform}` : ''}</div>
                  <div style={{ marginTop: 'auto', display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: '.4rem', paddingTop: '.35rem' }}>
                    <span style={{ fontSize: '.68rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{c.publishedAt ?? ''}</span>
                    {c.needsAction && <span className="ih-tag" style={{ fontSize: '.62rem', background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)' }}>يحتاج إجراء</span>}
                  </div>
                </div>
              </a>
            ))}
          </div>
        )
      )}

      <div className="ih-overview-grid" style={{ display: tab === 'overview' ? 'grid' : 'none', gridTemplateColumns: '1.3fr .7fr', gap: '1.1rem', alignItems: 'start' }}>
        <div style={{ display: 'grid', gap: '1.1rem' }}>
          <Sec title="ملف العلامة" icon="bookmark">
            <div className="ih-sec__body" style={{ display: 'grid', gap: '.9rem' }}>
              {brand.description && <p style={{ margin: 0, lineHeight: 1.8 }}>{brand.description}</p>}
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(200px,1fr))', gap: '.7rem' }}>
                {facts.filter(([, v]) => v).map(([k, v]) => (
                  <div key={k}><div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>{k}</div><div style={{ fontWeight: 600 }}>{v}</div></div>
                ))}
              </div>
              {brand.prohibitedTopics.length > 0 && (
                <div><div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)', marginBottom: '.3rem' }}>مواضيع محظورة</div>
                  <div style={{ display: 'flex', gap: '.4rem', flexWrap: 'wrap' }}>{brand.prohibitedTopics.map((t, i) => <span key={i} className="badge" style={{ background: 'var(--ih-danger-soft)', color: 'var(--ih-danger-ink)' }}>{t}</span>)}</div>
                </div>
              )}
              {brand.requiredMessages.length > 0 && (
                <div><div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)', marginBottom: '.3rem' }}>رسائل إلزامية</div>
                  <div style={{ display: 'flex', gap: '.4rem', flexWrap: 'wrap' }}>{brand.requiredMessages.map((t, i) => <span key={i} className="ih-tag">{t}</span>)}</div>
                </div>
              )}
              {brand.visualGuidelines && <div><div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>إرشادات بصرية</div><p style={{ margin: '.2rem 0 0', lineHeight: 1.7 }}>{brand.visualGuidelines}</p></div>}
            </div>
          </Sec>

        </div>

        {/* لمحة جانبية في النظرة العامة */}
        <div style={{ display: 'grid', gap: '1.1rem' }}>
          <Sec title="آخر قرار" icon="clipboard-check" link={decisions.length ? { href: '#review', label: 'كل القرارات' } : undefined}>
            <div className="ih-sec__body">
              {decisions.length === 0 ? <div style={{ color: 'var(--ih-text-muted)', fontSize: '.85rem' }}>لا قرارات بعد.</div> : (
                <div style={{ padding: '.6rem .8rem', background: 'var(--ih-surface-muted)', borderRadius: 'var(--ih-radius-sm)' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '.78rem' }}>
                    <span style={{ fontWeight: 700 }}>{DECISION_LABEL[decisions[0].decision] ?? decisions[0].decision} · v{decisions[0].version}</span>
                    <span style={{ color: 'var(--ih-text-muted)' }}>{decisions[0].at}</span>
                  </div>
                  <div style={{ fontSize: '.76rem', color: 'var(--ih-text-muted)' }}>{decisions[0].by}{decisions[0].note ? ` · ${decisions[0].note}` : ''}</div>
                </div>
              )}
            </div>
          </Sec>
        </div>
      </div>

      {/* الحسابات — بطاقات منصّات */}
      {tab === 'accounts' && (
        socialAccounts.length === 0 ? (
          <div className="card" style={{ padding: '2.4rem', textAlign: 'center' }}>
            <span className="ih-empty__icon" style={{ width: 48, height: 48 }}><Icon name="git-merge" size={22} /></span>
            <div style={{ marginTop: '.6rem', fontWeight: 700 }}>لا حسابات مسجّلة</div>
            <div style={{ fontSize: '.84rem', color: 'var(--ih-text-muted)' }}>أضِف حسابات العلامة لمتابعة نشاطها.</div>
          </div>
        ) : (
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(230px, 1fr))', gap: '.8rem' }}>
            {socialAccounts.map((a, i) => (
              <div key={i} className="ih-idcard">
                <div className="ih-idcard__top">
                  <span className="ih-idcard__logo">{a.platform.slice(0, 1).toUpperCase()}</span>
                  <div style={{ minWidth: 0, flex: 1 }}>
                    <div style={{ fontWeight: 700, fontSize: '.9rem' }}>{a.platform}</div>
                    <div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)', direction: 'ltr', textAlign: 'start' }}>{a.handle ? `@${a.handle.replace(/^@+/, '')}` : '—'}</div>
                  </div>
                </div>
                {a.url && <a href={a.url} target="_blank" rel="noopener noreferrer" className="btn btn-xs btn-outline" style={{ textAlign: 'center' }}>فتح الحساب</a>}
              </div>
            ))}
          </div>
        )
      )}

      {/* المراجعة — مسار زمني للقرارات وتغيّر الحالة */}
      {tab === 'review' && (
        <div className="ih-overview-grid" style={{ display: 'grid', gridTemplateColumns: 'minmax(0,1fr) minmax(0,1fr)', gap: '1.1rem', alignItems: 'start' }}>
          <Sec title="قرارات المراجعة" icon="clipboard-check">
            {decisions.length === 0 ? (
              <div style={{ padding: '1.6rem', textAlign: 'center', color: 'var(--ih-text-muted)', fontSize: '.85rem' }}>لا قرارات بعد.</div>
            ) : (
              <div style={{ padding: '.8rem .9rem', display: 'grid', gap: '.6rem' }}>
                {decisions.map((d, i) => {
                  const tone = d.decision === 'approved' ? 'success' : d.decision === 'rejected' ? 'danger' : 'warning';
                  return (
                    <div key={i} style={{ display: 'flex', gap: '.65rem', alignItems: 'flex-start' }}>
                      <span style={{ width: 26, height: 26, borderRadius: '50%', flexShrink: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', background: `var(--ih-${tone}-soft)`, color: `var(--ih-${tone}-ink)` }}>
                        <Icon name={d.decision === 'approved' ? 'shield-check' : 'activity'} size={13} />
                      </span>
                      <div style={{ flex: 1, minWidth: 0, borderBottom: i < decisions.length - 1 ? '1px solid var(--ih-border)' : undefined, paddingBottom: '.55rem' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', gap: '.5rem', fontSize: '.84rem' }}>
                          <span style={{ fontWeight: 700 }}>{DECISION_LABEL[d.decision] ?? d.decision} <span style={{ direction: 'ltr', fontWeight: 400, color: 'var(--ih-text-muted)' }}>v{d.version}</span></span>
                          <span style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{d.at}</span>
                        </div>
                        <div style={{ fontSize: '.76rem', color: 'var(--ih-text-muted)' }}>{d.by}</div>
                        {d.note && <div style={{ fontSize: '.8rem', marginTop: '.2rem' }}>{d.note}</div>}
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </Sec>
          <Sec title="سجل الحالة" icon="bar-chart-3">
            <div className="ih-sec__body">
              {history.length === 0 ? <div style={{ color: 'var(--ih-text-muted)', fontSize: '.85rem' }}>لا سجل بعد.</div> :
                <div className="ih-tl">
                  {history.map((h, i) => (
                    <div key={i} className="ih-tl__item"><span className="ih-tl__dot" />
                      <div className="ih-tl__text">{h.from} → {h.to}</div>
                      <div className="ih-tl__meta">{[h.by, h.at, h.reason].filter(Boolean).join(' · ')}</div>
                    </div>
                  ))}
                </div>}
            </div>
          </Sec>
        </div>
      )}

      {reasonFor && (
        <div className="modal-backdrop" onClick={(e) => e.target === e.currentTarget && setReasonFor(null)}>
          <div className="modal" style={{ padding: '1.3rem' }}>
            <h3 style={{ fontWeight: 800, margin: '0 0 1rem' }}>{reasonFor[1]}</h3>
            <textarea className="field" rows={3} value={reason} onChange={(e) => setReason(e.target.value)} placeholder="السبب / الملاحظة" autoFocus />
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button className={`btn ${BTN[reasonFor[2]] ?? 'btn-primary'}`} onClick={submitReason} disabled={!reason.trim()}>تأكيد</button>
              <button className="btn btn-ghost" onClick={() => setReasonFor(null)}>إلغاء</button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}

import { Head, router, usePage, Link } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Avatar, StatusBadge } from '@/Components/ui';
import { Bars, ProgressRing } from '@/Components/Charts';
import { MetricTile, ChartCard, SectionBar, RailCard } from '@/Components/Dash';
import { Icon, type IconName } from '@/Components/Icon';
import { u } from '@/lib/href';
import { useT } from '@/lib/i18n';
import type { SharedProps } from '@/types';

interface Metrics {
  followers: number; engagement: number; campaigns: number; active_collabs: number;
  completed_collabs: number; content_published: number; paid_minor: number;
  avg_price_minor: number | null; commitment_rate: number | null; accept_rate: number | null; overdue: number;
}
interface Intel {
  score: number; tier: string; tierLabel: string; risk: number;
  reasons: { label: string; value: number }[]; metrics: Metrics;
  subscores: { key: string; label: string; value: number }[];
}
interface Creator {
  id: number; name: string; handle: string | null; number: string; type: string; capabilities: string[];
  status: string; statusLabel: string; statusTone: string; platform: string | null;
  followers: number; city: string | null; email: string | null; phone: string | null;
  rateMinor: number | null; verified: boolean; bio: string | null; categories: string[];
}
interface Row { id: number; status: string; statusLabel: string; statusTone: string }
type Collab = Row & { title: string; campaign: string | null; feeMinor: number };
type Content = Row & { title: string; type: string; platform: string | null; mediaUrl: string | null; version: number; publishedAt: string | null; needsAction: boolean };
type ContractRow = Row & { title: string; number: string; valueMinor: number };
type PayoutRow = Row & { number: string; amountMinor: number };
interface Invitation {
  id: number; email: string; phone: string | null;
  expiresAt: string | null; lastSentAt: string | null;
  sentCount: number; maxSends: number;
  emailVerified: boolean; phoneVerified: boolean;
  canResend: boolean; canRevoke: boolean;
}
interface Access {
  state: 'unlinked' | 'pending' | 'email_verified' | 'phone_verified' | 'active' | 'expired' | 'revoked';
  label: string; tone: string;
  email: string | null; phone: string | null;
  canInvite: boolean; blockedReason: string | null;
  invitation: Invitation | null;
}
interface Props {
  creator: Creator; intel: Intel; access: Access;
  platforms: { platform: string; handle: string | null; followers: number }[];
  collaborations: Collab[]; content: Content[]; contracts: ContractRow[]; payouts: PayoutRow[];
}

function fnum(n: number): string {
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'M';
  if (n >= 1000) return Math.round(n / 1000) + 'K';
  return n.toLocaleString('en-US');
}
type TFn = ReturnType<typeof useT>;
const sarWith = (m: number | null, t: TFn) => (m == null ? '—' : Math.round(m / 100).toLocaleString('en-US') + ' ' + t('creators.currency_sar'));

/** صفّ علاقة بصري (تعاون/عقد/مستحق) — بديل الجداول على أسطح المراجعة. */
function RelRow({ title, meta, value, tone, label, href }: {
  title: string; meta?: string; value?: string; tone: string; label: string; href?: string;
}) {
  const inner = (
    <>
      <span className="ih-lrow__body">
        <span className="ih-lrow__title">{title}</span>
        {meta && <span className="ih-lrow__meta">{meta}</span>}
      </span>
      {value && <span className="ih-lrow__value">{value}</span>}
      <StatusBadge tone={tone} label={label} />
    </>
  );
  return href
    ? <Link href={href} className="ih-lrow ih-lrow--link">{inner}</Link>
    : <div className="ih-lrow">{inner}</div>;
}

function EmptyPane({ icon, text }: { icon: IconName; text: string }) {
  return (
    <div className="ih-vcard ih-empty-pane">
      <span className="ih-empty__icon"><Icon name={icon} size={24} /></span>
      <div style={{ marginTop: '.5rem', fontWeight: 650, color: 'var(--ih-text-muted)' }}>{text}</div>
    </div>
  );
}

/**
 * بوابة صانع المحتوى: حالتها وسبيل فتحها. تُشتقّ في الخادم؛ الواجهة تعرض وتتصرّف.
 * (منطق الدعوة/الإرسال/الإلغاء كما هو — أُعيد تغليفه في بطاقة النظام الجديد فقط.)
 */
function AccessPanel({ access, creatorId, link }: { access: Access; creatorId: number; link: string | null }) {
  const t = useT();
  const [email, setEmail] = useState(access.email ?? '');
  const [phone, setPhone] = useState(access.phone ?? '');
  const [busy, setBusy] = useState(false);
  const inv = access.invitation;

  const post = (url: string, data: Record<string, string | null> = {}) => {
    setBusy(true);
    router.post(u(url), data, { preserveScroll: true, onFinish: () => setBusy(false) });
  };

  return (
    <ChartCard title={t('creators.acc_title')} icon="users">
      <div style={{ display: 'grid', gap: '.9rem' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '.6rem', flexWrap: 'wrap' }}>
          <StatusBadge tone={access.tone} label={access.label} />
          {access.state === 'active' && <span style={{ fontSize: '.8rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{access.email}</span>}
        </div>

        {link && (
          <div style={{ padding: '.75rem .9rem', borderRadius: 'var(--ih-radius-sm)', background: 'var(--ih-info-soft)', color: 'var(--ih-info-ink)', fontSize: '.8rem', lineHeight: 1.8 }}>
            <b>{t('creators.acc_link_once')}</b>
            <div style={{ direction: 'ltr', wordBreak: 'break-all', marginTop: '.3rem', fontFamily: 'var(--ih-font-mono)' }}>{link}</div>
          </div>
        )}

        {inv && (
          <div style={{ display: 'grid', gap: '.3rem', fontSize: '.8rem', color: 'var(--ih-text-secondary)' }}>
            <div><span style={{ color: 'var(--ih-text-muted)' }}>{t('creators.acc_sent_to')}</span> <bdi style={{ direction: 'ltr' }}>{inv.email}</bdi>
              {inv.emailVerified && <span style={{ color: 'var(--ih-success-ink)' }}> ✓ {t('creators.acc_verified')}</span>}</div>
            {inv.phone && (
              <div><span style={{ color: 'var(--ih-text-muted)' }}>{t('creators.acc_phone')}</span> <bdi style={{ direction: 'ltr' }}>{inv.phone}</bdi>
                {inv.phoneVerified && <span style={{ color: 'var(--ih-success-ink)' }}> ✓ {t('creators.acc_verified')}</span>}</div>
            )}
            <div><span style={{ color: 'var(--ih-text-muted)' }}>{t('creators.acc_expires')}</span> {inv.expiresAt ?? '—'}</div>
            <div><span style={{ color: 'var(--ih-text-muted)' }}>{t('creators.acc_last_sent')}</span> {inv.lastSentAt ?? '—'} · {inv.sentCount}/{inv.maxSends}</div>
          </div>
        )}

        {access.blockedReason && <div style={{ fontSize: '.82rem', color: 'var(--ih-text-muted)', lineHeight: 1.8 }}>{access.blockedReason}</div>}

        {access.canInvite && ['unlinked', 'expired', 'revoked'].includes(access.state) && (
          <div style={{ display: 'grid', gap: '.5rem' }}>
            <div>
              <label htmlFor="inv-email" style={{ display: 'block', fontSize: '.78rem', fontWeight: 500, marginBottom: '.2rem' }}>{t('creators.acc_f_email')}</label>
              <input id="inv-email" className="field" style={{ width: '100%', direction: 'ltr' }} value={email} onChange={(e) => setEmail(e.target.value)} placeholder="creator@example.com" />
            </div>
            <div>
              <label htmlFor="inv-phone" style={{ display: 'block', fontSize: '.78rem', fontWeight: 500, marginBottom: '.2rem' }}>{t('creators.acc_f_phone')}</label>
              <input id="inv-phone" className="field" style={{ width: '100%', direction: 'ltr' }} value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="+9665…" />
            </div>
            <button className="btn btn-sm btn-primary" disabled={busy || !email.trim()}
              onClick={() => post(`/creators/${creatorId}/invite`, { email, phone: phone || null })}>{t('creators.acc_send')}</button>
          </div>
        )}

        {inv && (inv.canResend || inv.canRevoke) && (
          <div style={{ display: 'flex', gap: '.5rem', flexWrap: 'wrap' }}>
            {inv.canResend && <button className="btn btn-sm btn-outline" disabled={busy} onClick={() => post(`/creator-invitations/${inv.id}/resend`)}>{t('creators.acc_resend')}</button>}
            {inv.canRevoke && <button className="btn btn-sm btn-ghost" disabled={busy} onClick={() => post(`/creator-invitations/${inv.id}/revoke`)}>{t('creators.acc_revoke')}</button>}
          </div>
        )}
      </div>
    </ChartCard>
  );
}

export default function CreatorShow({ creator, intel, access, platforms, collaborations, content, contracts, payouts }: Props) {
  const t = useT();
  const sar = (m: number | null) => sarWith(m, t);
  const { props: shared } = usePage<SharedProps & { flash?: { invitation_link?: string } }>();
  const invitationLink = shared.flash?.invitation_link ?? null;
  const [savingStatus, setSavingStatus] = useState(false);
  const m = intel.metrics;
  const scoreTone = intel.score >= 72 ? 'success' : intel.score >= 50 ? 'warning' : 'danger';

  // عمود السياق (يمين) — بطاقات بيضاء: التقييم + الملف + حالة الحساب
  const rail = (
    <>
      <RailCard title={t('creators.score_label')} icon="gauge">
        <div style={{ display: 'grid', placeItems: 'center', gap: '.5rem', padding: '.2rem 0 .4rem' }}>
          <ProgressRing value={intel.score} size={118} tone={scoreTone} label={t('creators.score_label')} />
          <span className="badge" style={{ background: 'var(--ih-lime-soft)', color: 'var(--ih-lime-ink)', fontWeight: 700 }}>{t('creators.tier_prefix')} {intel.tierLabel}</span>
        </div>
        {intel.reasons.length > 0 && (
          <div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)', lineHeight: 1.7, marginTop: '.3rem' }}>
            {t('creators.top_factors')} {intel.reasons.map((r) => `${r.label} (${r.value})`).join(' · ')}
            {m.overdue > 0 && <> · <span style={{ color: 'var(--ih-danger-ink)' }}>{t('creators.risk_label', { n: m.overdue })}</span></>}
          </div>
        )}
      </RailCard>

      <RailCard title={t('creators.sec_contact')} icon="wallet">
        <ul className="ih-rail__list">
          {([[t('creators.c_rate'), sar(creator.rateMinor)], [t('creators.m_city'), creator.city], [t('creators.m_platform'), creator.platform], [t('creators.c_email'), creator.email], [t('creators.c_phone'), creator.phone], [t('creators.c_accept_rate'), m.accept_rate == null ? '—' : `${m.accept_rate}%`]] as [string, string | null][]).map(([k, v]) => (
            <li key={k} style={{ justifyContent: 'space-between', gap: '.6rem' }}>
              <span style={{ color: 'var(--ih-text-muted)' }}>{k}</span>
              <span style={{ fontWeight: 600, direction: 'ltr', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{v ?? '—'}</span>
            </li>
          ))}
        </ul>
      </RailCard>

      <RailCard title={t('creators.acc_title')} icon="shield-check">
        <div style={{ display: 'flex', alignItems: 'center', gap: '.5rem', flexWrap: 'wrap' }}>
          <StatusBadge tone={access.tone} label={access.label} />
        </div>
        {access.blockedReason && <div style={{ fontSize: '.73rem', color: 'var(--ih-text-muted)', marginTop: '.45rem', lineHeight: 1.7 }}>{access.blockedReason}</div>}
      </RailCard>
    </>
  );

  return (
    <AppShell heading={t('creators.show_heading')} contextRail={rail}>
      <Head title={creator.name} />

      {/* رأس مدمج — هوية المبدع + تغيير الحالة */}
      <header className="ih-entity-head">
        <Avatar name={creator.name} round />
        <div className="ih-entity-head__id">
          <div className="ih-entity-head__eyebrow">{(creator.capabilities.join(' · ') || t('creators.no_capabilities')) + ' · ' + creator.number}</div>
          <h1 className="ih-entity-head__title">
            {creator.name}
            {creator.verified && <span className="ih-verified" title={t('creators.verified')}><Icon name="shield-check" size={16} /></span>}
            <StatusBadge tone={creator.statusTone} label={creator.statusLabel} />
          </h1>
          <div className="ih-entity-head__sub">
            {creator.handle ? <bdi style={{ direction: 'ltr' }}>@{creator.handle.replace(/^@+/, '')}</bdi> : null}
            {creator.platform ? ` · ${creator.platform}` : ''}{creator.city ? ` · ${creator.city}` : ''}
          </div>
        </div>
        <div className="ih-entity-head__actions">
          <Link href={u('/creators')} className="btn btn-sm btn-outline"><Icon name="chevron-left" size={15} /> {t('creators.back_all')}</Link>
          <label className="ih-statuspick">
            <span>{t('creators.status_label')}</span>
            <select className="field" value={creator.status} disabled={savingStatus}
              onChange={(e) => { setSavingStatus(true); router.post(u(`/creators/${creator.id}/update`), { status: e.target.value }, { preserveScroll: true, onFinish: () => setSavingStatus(false) }); }}>
              <option value="prospect">{t('creators.opt_prospect')}</option>
              <option value="active">{t('creators.opt_active')}</option>
              <option value="paused">{t('creators.opt_paused')}</option>
              <option value="blocked">{t('creators.opt_blocked')}</option>
            </select>
          </label>
        </div>
      </header>

      {/* STRIP — مؤشّرات العلاقة */}
      <div className="ih-tiles ih-tiles--4">
        <MetricTile label={t('creators.ss_followers')} value={fnum(m.followers)} icon="users" tone="primary" />
        <MetricTile label={t('creators.ss_engagement')} value={`${m.engagement}%`} icon="activity" />
        <MetricTile label={t('creators.ss_active_collabs')} value={fnum(m.active_collabs)} icon="megaphone" tone={m.active_collabs ? 'primary' : 'neutral'} />
        <MetricTile label={t('creators.ss_paid')} value={sar(m.paid_minor)} icon="wallet" tone="success" />
      </div>

      {/* BENTO — الدرجات الفرعية (رسم) + النبذة */}
      <div className="ih-bento" style={{ marginTop: '1.25rem' }}>
        <ChartCard className="ih-bento__hero" title={t('creators.subscores_title')} icon="gauge">
          <Bars bars={intel.subscores.map((s) => ({ label: s.label, value: s.value }))} emptyLabel={t('creators.no_bio')} />
        </ChartCard>
        <ChartCard className="ih-bento__side" title={t('creators.sec_bio')} icon="file-text">
          <p style={{ margin: 0, lineHeight: 1.85, fontSize: '.88rem', color: creator.bio ? 'var(--ih-text)' : 'var(--ih-text-muted)' }}>{creator.bio ?? t('creators.no_bio')}</p>
          {creator.categories.length > 0 && (
            <div style={{ display: 'flex', gap: '.4rem', flexWrap: 'wrap', marginTop: '.8rem' }}>
              {creator.categories.map((c) => <span key={c} className="ih-tag">{c}</span>)}
            </div>
          )}
        </ChartCard>
      </div>

      {/* الوصول إلى البوابة */}
      <div style={{ marginTop: '1.25rem' }}>
        <AccessPanel access={access} creatorId={creator.id} link={invitationLink} />
      </div>

      {/* المنصّات */}
      <section style={{ marginTop: '1.5rem' }}>
        <SectionBar title={t('creators.tab_platforms')} icon="radar" count={platforms.length} />
        {platforms.length === 0 ? <EmptyPane icon="radar" text={t('creators.no_platforms')} /> : (
          <>
            {platforms.length > 1 && platforms.some((p) => p.followers > 0) && (
              <ChartCard title={t('creators.platforms_compare')} icon="users">
                <Bars bars={[...platforms].sort((a, b) => b.followers - a.followers).map((p) => ({ label: p.platform, value: p.followers }))} />
              </ChartCard>
            )}
            <div className="ih-ccard-grid" style={{ marginTop: platforms.length > 1 ? '1rem' : 0 }}>
              {platforms.map((p, i) => (
                <div key={i} className="ih-lrow">
                  <span className="ih-lrow__logo">{p.platform.slice(0, 1).toUpperCase()}</span>
                  <span className="ih-lrow__body">
                    <span className="ih-lrow__title">{p.platform}</span>
                    <span className="ih-lrow__meta" style={{ direction: 'ltr' }}>{p.handle ? `@${p.handle.replace(/^@+/, '')}` : '—'}</span>
                  </span>
                  <span className="ih-lrow__value">{fnum(p.followers)}</span>
                </div>
              ))}
            </div>
          </>
        )}
      </section>

      {/* التعاونات */}
      <section style={{ marginTop: '1.5rem' }}>
        <SectionBar title={t('creators.tab_collaborations')} icon="megaphone" count={collaborations.length} />
        {collaborations.length === 0 ? <EmptyPane icon="megaphone" text={t('creators.no_collaborations')} /> : (
          <div className="ih-lrow-grid">
            {collaborations.map((c) => <RelRow key={c.id} title={c.title} meta={c.campaign ?? '—'} value={sar(c.feeMinor)} tone={c.statusTone} label={c.statusLabel} />)}
          </div>
        )}
      </section>

      {/* المحتوى — معرض */}
      <section style={{ marginTop: '1.5rem' }}>
        <SectionBar title={t('creators.tab_content')} icon="image" count={content.length} />
        {content.length === 0 ? <EmptyPane icon="image" text={t('creators.no_content')} /> : (
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
                  <div className="ih-gtile__meta">{c.type}{c.platform ? ` · ${c.platform}` : ''}</div>
                  <div style={{ marginTop: 'auto', display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: '.4rem', paddingTop: '.35rem' }}>
                    <span style={{ fontSize: '.68rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{c.publishedAt ?? ''}</span>
                    {c.needsAction && <span className="ih-tag" style={{ fontSize: '.62rem', background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)' }}>{t('creators.needs_action')}</span>}
                  </div>
                </div>
              </a>
            ))}
          </div>
        )}
      </section>

      {/* العقود + المستحقات */}
      <div className="ih-overview-grid" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1.1rem', marginTop: '1.5rem', alignItems: 'start' }}>
        <section>
          <SectionBar title={t('creators.tab_contracts')} icon="file-text" count={contracts.length} />
          {contracts.length === 0 ? <EmptyPane icon="file-text" text={t('creators.no_contracts')} /> : (
            <div className="ih-lrow-grid">
              {contracts.map((c) => <RelRow key={c.id} title={c.title} meta={c.number} value={sar(c.valueMinor)} tone={c.statusTone} label={c.statusLabel} />)}
            </div>
          )}
        </section>
        <section>
          <SectionBar title={t('creators.tab_payouts')} icon="wallet" count={payouts.length} />
          {payouts.length === 0 ? <EmptyPane icon="wallet" text={t('creators.no_payouts')} /> : (
            <div className="ih-lrow-grid">
              {payouts.map((p) => <RelRow key={p.id} title={p.number} value={sar(p.amountMinor)} tone={p.statusTone} label={p.statusLabel} />)}
            </div>
          )}
        </section>
      </div>
    </AppShell>
  );
}

import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState, type ReactNode } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Bar, Sec, StatusBadge, SummaryStrip, WorkTabs, WorkspaceHeader } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';
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
/** حالة وصول صانع المحتوى إلى بوابته — تُشتقّ في الخادم، والواجهة تعرضها. */
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
const sar = (m: number | null) => (m == null ? '—' : Math.round(m / 100).toLocaleString('en-US') + ' ر.س');

function DataTable({ head, children }: { head: string[]; children: ReactNode }) {
  return (
    <div className="ih-dt-wrap"><div className="ih-dt-scroll">
      <table className="ih-dt"><thead><tr>{head.map((h) => <th key={h}>{h}</th>)}</tr></thead>
        <tbody>{children}</tbody></table>
    </div></div>
  );
}
function EmptyRow({ span, text }: { span: number; text: string }) {
  return <tr><td colSpan={span} style={{ textAlign: 'center', color: 'var(--ih-text-muted)', padding: '1.6rem' }}>{text}</td></tr>;
}

/**
 * بوابة صانع المحتوى: حالتها وسبيل فتحها.
 *
 * 165 من 168 سجلًّا بلا حساب دخول، وكانت الصفحة صامتة عن ذلك: لا تقول إن
 * الرحلة مقطوعة ولا تعرض ما يُصلحها. القسم يقول الحالة، ويعطي الإجراء، ويشرح
 * سبب تعذّره حين يتعذّر — لا زرّ يفشل عند الضغط.
 */
function AccessPanel({ access, creatorId, link }: { access: Access; creatorId: number; link: string | null }) {
  const [email, setEmail] = useState(access.email ?? '');
  const [phone, setPhone] = useState(access.phone ?? '');
  const [busy, setBusy] = useState(false);
  const inv = access.invitation;

  const post = (url: string, data: Record<string, string | null> = {}) => {
    setBusy(true);
    router.post(u(url), data, { preserveScroll: true, onFinish: () => setBusy(false) });
  };

  return (
    <Sec title="بوابة صانع المحتوى" icon="users">
      <div className="ih-sec__body" style={{ display: 'grid', gap: '.9rem' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '.6rem', flexWrap: 'wrap' }}>
          <StatusBadge tone={access.tone} label={access.label} />
          {access.state === 'active' && (
            <span style={{ fontSize: '.8rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{access.email}</span>
          )}
        </div>

        {/* الرابط يُعرض مرّة واحدة بعد الإرسال — الرمز لا يُخزَّن خامًا */}
        {link && (
          <div style={{ padding: '.75rem .9rem', borderRadius: 'var(--ih-radius-sm)',
            background: 'var(--ih-info-soft)', color: 'var(--ih-info-ink)', fontSize: '.8rem', lineHeight: 1.8 }}>
            <b>انسخ الرابط الآن — يُعرض مرّة واحدة فقط.</b>
            <div style={{ direction: 'ltr', wordBreak: 'break-all', marginTop: '.3rem', fontFamily: 'var(--ih-font-mono)' }}>{link}</div>
          </div>
        )}

        {inv && (
          <div style={{ display: 'grid', gap: '.3rem', fontSize: '.8rem', color: 'var(--ih-text-secondary)' }}>
            <div><span style={{ color: 'var(--ih-text-muted)' }}>البريد المُرسَل إليه:</span> <bdi style={{ direction: 'ltr' }}>{inv.email}</bdi>
              {inv.emailVerified && <span style={{ color: 'var(--ih-success-ink)' }}> ✓ متحقّق</span>}</div>
            {inv.phone && (
              <div><span style={{ color: 'var(--ih-text-muted)' }}>الجوال:</span> <bdi style={{ direction: 'ltr' }}>{inv.phone}</bdi>
                {inv.phoneVerified && <span style={{ color: 'var(--ih-success-ink)' }}> ✓ متحقّق</span>}</div>
            )}
            <div><span style={{ color: 'var(--ih-text-muted)' }}>تنتهي:</span> {inv.expiresAt ?? '—'}</div>
            <div><span style={{ color: 'var(--ih-text-muted)' }}>آخر إرسال:</span> {inv.lastSentAt ?? '—'} · {inv.sentCount}/{inv.maxSends}</div>
          </div>
        )}

        {access.blockedReason && (
          <div style={{ fontSize: '.82rem', color: 'var(--ih-text-muted)', lineHeight: 1.8 }}>{access.blockedReason}</div>
        )}

        {/* دعوة جديدة: غير مرتبط، أو انتهت/أُلغيت السابقة */}
        {access.canInvite && ['unlinked', 'expired', 'revoked'].includes(access.state) && (
          <div style={{ display: 'grid', gap: '.5rem' }}>
            <div>
              <label htmlFor="inv-email" style={{ display: 'block', fontSize: '.78rem', fontWeight: 500, marginBottom: '.2rem' }}>البريد</label>
              <input id="inv-email" className="field" style={{ width: '100%', direction: 'ltr' }}
                value={email} onChange={(e) => setEmail(e.target.value)} placeholder="creator@example.com" />
            </div>
            <div>
              <label htmlFor="inv-phone" style={{ display: 'block', fontSize: '.78rem', fontWeight: 500, marginBottom: '.2rem' }}>الجوال (اختياري)</label>
              <input id="inv-phone" className="field" style={{ width: '100%', direction: 'ltr' }}
                value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="+9665…" />
            </div>
            <button className="btn btn-sm btn-primary" disabled={busy || !email.trim()}
              onClick={() => post(`/creators/${creatorId}/invite`, { email, phone: phone || null })}>
              إرسال دعوة
            </button>
          </div>
        )}

        {inv && (inv.canResend || inv.canRevoke) && (
          <div style={{ display: 'flex', gap: '.5rem', flexWrap: 'wrap' }}>
            {inv.canResend && (
              <button className="btn btn-sm btn-outline" disabled={busy}
                onClick={() => post(`/creator-invitations/${inv.id}/resend`)}>إعادة إرسال</button>
            )}
            {inv.canRevoke && (
              <button className="btn btn-sm btn-ghost" disabled={busy}
                onClick={() => post(`/creator-invitations/${inv.id}/revoke`)}>إلغاء الدعوة</button>
            )}
          </div>
        )}
      </div>
    </Sec>
  );
}

export default function CreatorShow({ creator, intel, access, platforms, collaborations, content, contracts, payouts }: Props) {
  const { props: shared } = usePage<SharedProps & { flash?: { invitation_link?: string } }>();
  const invitationLink = shared.flash?.invitation_link ?? null;
  const [tab, setTab] = useState('overview');
  const [savingStatus, setSavingStatus] = useState(false);
  const CR_TABS = ['overview','platforms','collaborations','content','contracts','payouts'];
  useEffect(() => {
    const applyHash = () => {
      const h = window.location.hash.replace('#','');
      if (CR_TABS.includes(h)) setTab(h);
    };
    applyHash();
    window.addEventListener('hashchange', applyHash);
    return () => window.removeEventListener('hashchange', applyHash);
  }, []);
  const go = (k: string) => {
    setTab(k);
    window.history.replaceState(null, '', k === 'overview' ? window.location.pathname : `#${k}`);
  };
  const scoreColor = intel.score >= 72 ? 'var(--ih-success)' : intel.score >= 50 ? 'var(--ih-warning)' : 'var(--ih-danger)';
  const m = intel.metrics;

  return (
    <AppShell heading="ملف المبدع">
      <Head title={creator.name} />

      <WorkspaceHeader
        eyebrow={`${creator.capabilities.join(' · ') || 'بلا قدرات'} · ${creator.number}`}
        title={creator.name}
        statusTone={creator.statusTone} statusLabel={creator.statusLabel}
        back={u("/creators")} backLabel="كل المبدعين"
        meta={[
          ['المنصّة', creator.platform ?? '—'],
          ['المدينة', creator.city ?? '—'],
          ['الموثوقية', creator.verified ? 'موثّق' : 'غير موثّق'],
          ...(creator.categories.length ? [['المجالات', creator.categories.slice(0, 3).join('، ')] as [string, string]] : []),
        ]}
        actions={
          <label style={{ display: 'flex', alignItems: 'center', gap: '.4rem', fontSize: '.8rem' }}>
            <span style={{ color: 'var(--ih-text-muted)' }}>الحالة</span>
            {/* الترشيح يعرض النشطين فقط، ولم يكن للمبدع مسار تحديث — فيُضاف
                ثم يختفي من الترشيح بلا سبب معروف. */}
            <select
              className="field"
              style={{ minWidth: 120 }}
              value={creator.status}
              disabled={savingStatus}
              onChange={(e) => {
                setSavingStatus(true);
                router.post(u(`/creators/${creator.id}/update`), { status: e.target.value }, {
                  preserveScroll: true,
                  onFinish: () => setSavingStatus(false),
                });
              }}
            >
              <option value="prospect">مبدئي</option>
              <option value="active">نشط</option>
              <option value="paused">موقوف مؤقتًا</option>
              <option value="blocked">محظور</option>
            </select>
          </label>
        }
      />

      {/* درجة المبدع + الدرجات الفرعية */}
      <div className="ih-sec" style={{ marginBottom: '1.2rem' }}>
        <div className="ih-sec__body" style={{ display: 'flex', gap: '1.6rem', flexWrap: 'wrap', alignItems: 'center' }}>
          <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '.5rem' }}>
            <div style={{ width: 96, height: 96, borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center', background: `conic-gradient(${scoreColor} ${intel.score * 3.6}deg, var(--ih-surface-sunken) 0)` }}>
              <div style={{ width: 74, height: 74, borderRadius: '50%', background: 'var(--ih-surface)', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center' }}>
                <span style={{ fontSize: '1.6rem', fontWeight: 800, lineHeight: 1 }}>{intel.score}</span>
                <span style={{ fontSize: '.62rem', color: 'var(--ih-text-muted)' }}>درجة المبدع</span>
              </div>
            </div>
            <span className="badge" style={{ background: 'var(--ih-primary-soft)', color: 'var(--ih-primary-700)', fontWeight: 800 }}>فئة {intel.tierLabel}</span>
          </div>
          <div style={{ flex: 1, minWidth: 240 }}>
            <div style={{ fontWeight: 700, fontSize: '.9rem', marginBottom: '.6rem' }}>الدرجات الفرعية (محسوبة آليًا من بيانات فعلية)</div>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(180px,1fr))', gap: '.5rem .9rem' }}>
              {intel.subscores.map((s) => (
                <div key={s.key}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '.74rem', marginBottom: '.2rem' }}>
                    <span style={{ color: 'var(--ih-text-secondary)' }}>{s.label}</span><span style={{ fontWeight: 700 }}>{s.value}</span>
                  </div>
                  <Bar pct={s.value} />
                </div>
              ))}
            </div>
            <div style={{ marginTop: '.8rem', fontSize: '.76rem', color: 'var(--ih-text-muted)' }}>
              أبرز العوامل: {intel.reasons.map((r) => `${r.label} (${r.value})`).join(' · ')}
              {m.overdue > 0 && <> · <span style={{ color: 'var(--ih-danger-ink)' }}>مخاطر: {m.overdue} تأخير</span></>}
            </div>
          </div>
        </div>
      </div>

      <SummaryStrip items={[
        { label: 'المتابعون', value: fnum(m.followers), icon: 'users' },
        { label: 'التفاعل (تقديري)', value: `${m.engagement}%` },
        { label: 'الحملات', value: m.campaigns },
        { label: 'تعاونات نشطة', value: m.active_collabs, tone: m.active_collabs ? 'primary' : undefined },
        { label: 'محتوى منشور', value: m.content_published, icon: 'image' },
        { label: 'المدفوع', value: sar(m.paid_minor), tone: 'success' },
        { label: 'الالتزام', value: m.commitment_rate == null ? '—' : `${m.commitment_rate}%` },
      ]} />

      {/* الوصول إلى البوابة — قبل التبويبات: «هل يستطيع الدخول؟» يسبق التفاصيل */}
      <div style={{ marginBottom: '1.1rem' }}>
        <AccessPanel access={access} creatorId={creator.id} link={invitationLink} />
      </div>

      <WorkTabs active={tab} onChange={go} tabs={[
        { key: 'overview', label: 'نظرة عامة', icon: 'layout-dashboard' },
        { key: 'platforms', label: 'المنصّات', icon: 'radar', count: platforms.length },
        { key: 'collaborations', label: 'الحملات', icon: 'megaphone', count: collaborations.length },
        { key: 'content', label: 'المحتوى', icon: 'image', count: content.length },
        { key: 'contracts', label: 'العقود', icon: 'file-text', count: contracts.length },
        { key: 'payouts', label: 'المستحقات', icon: 'wallet', count: payouts.length },
      ]} />

      {tab === 'overview' && (
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1.1rem' }} className="ih-overview-grid">
          <Sec title="نبذة ومجالات" icon="file-text">
            <div className="ih-sec__body">
              <p style={{ margin: 0, lineHeight: 1.8, color: creator.bio ? 'var(--ih-text)' : 'var(--ih-text-muted)' }}>{creator.bio ?? 'لا نبذة بعد.'}</p>
              {creator.categories.length > 0 && (
                <div style={{ display: 'flex', gap: '.4rem', flexWrap: 'wrap', marginTop: '.8rem' }}>
                  {creator.categories.map((c) => <span key={c} className="ih-tag">{c}</span>)}
                </div>
              )}
            </div>
          </Sec>
          <Sec title="التواصل والتسعير" icon="wallet">
            <div className="ih-sec__body" style={{ display: 'grid', gap: '.7rem' }}>
              {([['البريد', creator.email], ['الهاتف', creator.phone], ['سعر المنشور', sar(creator.rateMinor)], ['معدّل القبول', m.accept_rate == null ? '—' : `${m.accept_rate}%`]] as [string, string | null][]).map(([k, v]) => (
                <div key={k} style={{ display: 'flex', justifyContent: 'space-between', fontSize: '.86rem' }}>
                  <span style={{ color: 'var(--ih-text-muted)' }}>{k}</span><span style={{ fontWeight: 600, direction: 'ltr' }}>{v ?? '—'}</span>
                </div>
              ))}
            </div>
          </Sec>
        </div>
      )}

      {/* المنصّات — بطاقات جمهور لكل منصّة */}
      {tab === 'platforms' && (
        platforms.length === 0 ? (
          <div className="card" style={{ padding: '2.4rem', textAlign: 'center' }}>
            <span className="ih-empty__icon" style={{ width: 48, height: 48 }}><Icon name="radar" size={22} /></span>
            <div style={{ marginTop: '.6rem', fontWeight: 700 }}>لا منصّات مسجّلة</div>
          </div>
        ) : (
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))', gap: '.8rem' }}>
            {platforms.map((p, i) => {
              const maxF = Math.max(...platforms.map((x) => x.followers), 1);
              return (
                <div key={i} className="ih-idcard">
                  <div className="ih-idcard__top">
                    <span className="ih-idcard__logo">{p.platform.slice(0, 1).toUpperCase()}</span>
                    <div style={{ minWidth: 0, flex: 1 }}>
                      <div style={{ fontWeight: 700, fontSize: '.9rem' }}>{p.platform}</div>
                      <div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)', direction: 'ltr', textAlign: 'start' }}>{p.handle ? `@${p.handle.replace(/^@+/, '')}` : '—'}</div>
                    </div>
                  </div>
                  <div>
                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '.72rem', color: 'var(--ih-text-muted)', marginBottom: '.2rem' }}>
                      <span>المتابعون</span><span style={{ fontWeight: 700, direction: 'ltr', color: 'var(--ih-text)' }}>{fnum(p.followers)}</span>
                    </div>
                    <Bar pct={Math.round((p.followers / maxF) * 100)} />
                  </div>
                </div>
              );
            })}
          </div>
        )
      )}

      {tab === 'collaborations' && (
        <DataTable head={['التعاون', 'الحملة', 'الأجر', 'الحالة']}>
          {collaborations.length === 0 ? <EmptyRow span={4} text="لا تعاونات بعد." /> :
            collaborations.map((c) => (
              <tr key={c.id}><td style={{ fontWeight: 600 }}>{c.title}</td><td>{c.campaign ?? '—'}</td>
                <td className="ih-dt__num" style={{ direction: 'ltr', textAlign: 'right' }}>{sar(c.feeMinor)}</td>
                <td><StatusBadge tone={c.statusTone} label={c.statusLabel} /></td></tr>
            ))}
        </DataTable>
      )}

      {/* المحتوى — معرض أعمال المبدع */}
      {tab === 'content' && (
        content.length === 0 ? (
          <div className="card" style={{ padding: '2.4rem', textAlign: 'center' }}>
            <span className="ih-empty__icon" style={{ width: 48, height: 48 }}><Icon name="image" size={22} /></span>
            <div style={{ marginTop: '.6rem', fontWeight: 700 }}>لا محتوى بعد</div>
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
                  <div className="ih-gtile__meta">{c.type}{c.platform ? ` · ${c.platform}` : ''}</div>
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

      {tab === 'contracts' && (
        <DataTable head={['العقد', 'الرقم', 'القيمة', 'الحالة']}>
          {contracts.length === 0 ? <EmptyRow span={4} text="لا عقود بعد." /> :
            contracts.map((c) => (
              <tr key={c.id}><td style={{ fontWeight: 600 }}>{c.title}</td><td style={{ direction: 'ltr', textAlign: 'right' }}>{c.number}</td>
                <td className="ih-dt__num" style={{ direction: 'ltr', textAlign: 'right' }}>{sar(c.valueMinor)}</td>
                <td><StatusBadge tone={c.statusTone} label={c.statusLabel} /></td></tr>
            ))}
        </DataTable>
      )}

      {tab === 'payouts' && (
        <DataTable head={['المستحق', 'المبلغ', 'الحالة']}>
          {payouts.length === 0 ? <EmptyRow span={3} text="لا مستحقات بعد." /> :
            payouts.map((p) => (
              <tr key={p.id}><td style={{ direction: 'ltr', textAlign: 'right', fontWeight: 600 }}>{p.number}</td>
                <td className="ih-dt__num" style={{ direction: 'ltr', textAlign: 'right' }}>{sar(p.amountMinor)}</td>
                <td><StatusBadge tone={p.statusTone} label={p.statusLabel} /></td></tr>
            ))}
        </DataTable>
      )}
    </AppShell>
  );
}

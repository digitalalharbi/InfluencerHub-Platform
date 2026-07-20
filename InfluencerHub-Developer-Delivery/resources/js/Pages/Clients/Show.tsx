import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState, type ReactNode } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Bar, Field, Kpi, Sec, StatusBadge, SummaryStrip, WorkTabs, WorkspaceHeader, type WorkTab } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import type { SharedProps } from '@/types';
import { u } from '@/lib/href';

interface Client {
  id: number; name: string; number: string; sector: string | null; status: string; statusLabel: string; statusTone: string;
  manager: string | null; email: string | null; phone: string | null; website: string | null; city: string | null;
  cr: string | null; tax: string | null; isVip: boolean;
}
interface Metrics {
  revenueMinor: number; costMinor: number; profitMinor: number; margin: number;
  campaigns: number; activeCampaigns: number; creators: number; receivableMinor: number; pendingPayouts: number; completion: number;
}
interface Risk { label: string; tone: string; href: string; tab?: string }
interface Activity { icon: string; text: string; href: string; at: string }
interface Row { id: number; status: string; statusLabel: string; statusTone: string }
type Campaign = Row & {
  name: string; brand: string | null; deliverables: number; budgetMinor: number; committedMinor: number;
  budgetPct: number; overBudget: boolean; creators: number; content: number; contentPublished: number;
  progress: number; awaiting: number; payouts: number; late: boolean;
  startDate: string | null; endDate: string | null; stage: string; risk: string | null;
};
type Brand = Row & { name: string };
type Content = Row & {
  title: string; creator: string | null; platform: string | null; type: string;
  mediaUrl: string | null; caption: string | null; version: number; campaign: string | null;
  scheduledAt: string | null; publishedAt: string | null; needsAction: boolean;
};
type Stage = { key: string; label: string; count: number };
type Contract = Row & {
  title: string; number: string; party: string | null; valueMinor: number;
  sentAt: string | null; signedAt: string | null; signedBy: string | null;
  startDate: string | null; endDate: string | null; expiringSoon: boolean; expired: boolean; awaitingSignature: boolean;
};
type Payout = Row & { number: string; creator: string | null; amountMinor: number; campaign: string | null; dueDate: string | null; paidAt: string | null; overdue: boolean };
type Finance = {
  byCampaign: { id: number; name: string; budgetMinor: number; costMinor: number; payoutsPaid: number }[];
  buckets: { pending: number; paid: number; overdue: number };
  timeline: { at: string; label: string; amountMinor: number }[];
};
type ClientRequest = Row & {
  title: string; number: string; type: string; priority: string; priorityLabel: string;
  assignee: string | null; dueAt: string | null; updatedAt: string | null;
  open: boolean; overdue: boolean; dueSoon: boolean; blocked: string | null; bucket: string;
};
type ClientCreator = {
  id: number; name: string; handle: string | null; platform: string | null; followers: number; verified: boolean;
  collaborations: number; completed: number; active: number; feeMinor: number; content: number; published: number;
  quality: number; lastAt: string | null; daysSince: number | null; relation: string;
};
type Document = Row & { title: string; category: string | null; sizeKb: number; expiresAt: string | null; expiringSoon: boolean; expired: boolean; pending: boolean };
type CustomField = { id: number; label: string; type: string | null; required: boolean; value: string };
interface Props {
  client: Client; metrics: Metrics; risks: Risk[];
  campaigns: Campaign[]; brands: Brand[];
  contacts: {
    name: string; role: string | null; department: string | null; email: string | null; phone: string | null;
    whatsapp: string | null; isPrimary: boolean; preferredChannel: string | null; hasPortal: boolean;
  }[];
  team: { name: string; role: string; status: string; statusTone: string }[]; content: Content[]; contracts: Contract[]; payouts: Payout[];
  requests: ClientRequest[]; creators: ClientCreator[]; documents: Document[]; customFields: CustomField[];
  nextAction: Risk | null; activity: Activity[]; contentStages: Stage[]; finance: Finance;
  can: { update: boolean; documents: boolean; portal: boolean };
  fieldDefinitions: { id: number; key: string; label: string; type: string }[];
}

const FLBL: React.CSSProperties = { fontSize: '.78rem', fontWeight: 600, display: 'block', marginBottom: '.25rem' };

/** لوح إضافة مُدمج داخل التبويب — يُفتح عند الطلب فلا يزاحم المحتوى التشغيلي. */
function AddPanel({ label, open, onToggle, onSubmit, busy, disabled, children }: {
  label: string; open: boolean; onToggle: () => void; onSubmit: () => void;
  busy: boolean; disabled: boolean; children: React.ReactNode;
}) {
  return (
    <div style={{ marginBottom: '.9rem' }}>
      <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
        <button onClick={onToggle} className={`btn btn-sm ${open ? 'btn-ghost' : 'btn-primary'}`}>
          {open ? 'إلغاء' : label}
        </button>
      </div>
      {open && (
        <div className="card" style={{ padding: '1rem', marginTop: '.6rem', display: 'grid', gap: '.8rem' }}>
          {children}
          <div><button disabled={busy || disabled} onClick={onSubmit} className="btn btn-sm btn-primary">حفظ</button></div>
        </div>
      )}
    </div>
  );
}

const sar = (m: number) => Math.round(m / 100).toLocaleString('en-US') + ' ر.س';

/** تبويبات العميل — الترتيب معتمد، والعدّاد اختياري (يُخفى عند الصفر). */
const TABS = (d: {
  campaigns: unknown[]; creators: unknown[]; content: unknown[]; requests: unknown[]; contracts: unknown[];
  documents: unknown[]; payouts: unknown[]; brands: unknown[]; contacts: unknown[]; team: unknown[]; customFields: unknown[];
}): WorkTab[] => [
  { key: 'overview', label: 'نظرة عامة', icon: 'layout-dashboard' },
  { key: 'campaigns', label: 'الحملات', icon: 'megaphone', count: d.campaigns.length },
  { key: 'creators', label: 'صناع المحتوى', icon: 'users', count: d.creators.length },
  { key: 'content', label: 'المحتوى', icon: 'image', count: d.content.length },
  { key: 'requests', label: 'الطلبات', icon: 'inbox', count: d.requests.length },
  { key: 'docs', label: 'العقود والمستندات', icon: 'file-text', count: d.contracts.length + d.documents.length },
  { key: 'finance', label: 'المالية', icon: 'wallet', count: d.payouts.length },
  { key: 'brands', label: 'العلامات', icon: 'bookmark', count: d.brands.length },
  { key: 'contacts', label: 'جهات الاتصال', icon: 'user-plus', count: d.contacts.length },
  { key: 'team', label: 'الفريق', icon: 'users', count: d.team.length },
  { key: 'custom', label: 'حقول مخصّصة', icon: 'clipboard-check', count: d.customFields.length },
];
const RISK_TONE: Record<string, { bg: string; fg: string }> = {
  danger: { bg: 'var(--ih-danger-soft)', fg: 'var(--ih-danger-ink)' },
  warning: { bg: 'var(--ih-warning-soft)', fg: 'var(--ih-warning-ink)' },
  info: { bg: 'var(--ih-info-soft)', fg: 'var(--ih-info-ink)' },
  primary: { bg: 'var(--ih-primary-soft)', fg: 'var(--ih-primary-700)' },
};

function DataTable({ head, children }: { head: string[]; children: ReactNode }) {
  return <div className="ih-dt-wrap"><div className="ih-dt-scroll"><table className="ih-dt"><thead><tr>{head.map((h) => <th key={h}>{h}</th>)}</tr></thead><tbody>{children}</tbody></table></div></div>;
}
function EmptyState({ icon, title, hint }: { icon: 'megaphone' | 'bookmark' | 'image' | 'users' | 'inbox' | 'user-plus'; title: string; hint: string }) {
  return (
    <div className="card" style={{ padding: '2.4rem 1.5rem', textAlign: 'center' }}>
      <span className="ih-empty__icon" style={{ width: 48, height: 48 }}><Icon name={icon} size={22} /></span>
      <div style={{ marginTop: '.6rem', fontWeight: 700 }}>{title}</div>
      <div style={{ fontSize: '.84rem', color: 'var(--ih-text-muted)', marginTop: '.15rem' }}>{hint}</div>
    </div>
  );
}
function SignStep({ on, label, at }: { on: boolean; label: string; at: string | null }) {
  return (
    <span style={{ display: 'inline-flex', alignItems: 'center', gap: '.25rem', color: on ? 'var(--ih-success-ink)' : 'var(--ih-text-muted)', fontWeight: on ? 700 : 400 }}>
      <Icon name={on ? 'shield-check' : 'circle'} size={12} />{label}{on && at ? ` ${at}` : ''}
    </span>
  );
}
function EmptyRow({ span, text }: { span: number; text: string }) {
  return <tr><td colSpan={span} style={{ textAlign: 'center', color: 'var(--ih-text-muted)', padding: '1.6rem' }}>{text}</td></tr>;
}

const TAB_KEYS = ['overview', 'campaigns', 'creators', 'content', 'requests', 'docs', 'finance', 'brands', 'contacts', 'team', 'custom'] as const;

export default function ClientShow({ client, metrics, risks, campaigns, brands, contacts, team, content, contracts, payouts, requests, creators, documents, customFields, nextAction, activity, contentStages, finance, can, fieldDefinitions }: Props) {
  // إجراءات الوحدات الفرعية — لوح واحد مفتوح في كل مرة داخل التبويب الحالي
  const [panel, setPanel] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [errs, setErrs] = useState<Record<string, string>>({});
  const [brandForm, setBrandForm] = useState({ name: '', sector: '', website: '' });
  const [contactForm, setContactForm] = useState({ name: '', job_title: '', email: '', phone: '' });
  const [docForm, setDocForm] = useState<{ title: string; category: string; file: File | null }>({ title: '', category: 'contract', file: null });
  const [inviteForm, setInviteForm] = useState({ email: '', role: 'client_member' });
  const [fieldForm, setFieldForm] = useState({ key: '', label: '', type: 'text' });
  const [fieldValues, setFieldValues] = useState<Record<number, string>>({});

  const inviteToken = usePage<SharedProps>().props.flash?.inviteToken ?? null;
  const togglePanel = (k: string) => { setPanel(panel === k ? null : k); setErrs({}); };
  const post = (path: string, data: Record<string, string | File | null>, done: () => void) => {
    setBusy(true);
    router.post(u(`/clients/${client.id}${path}`), data as Record<string, string | File | null>, {
      preserveScroll: true, forceFormData: data.file instanceof File,
      onFinish: () => setBusy(false),
      onError: (e) => setErrs(e as Record<string, string>),
      onSuccess: () => { setErrs({}); setPanel(null); done(); },
    });
  };
  const Err = ({ k }: { k: string }) => errs[k] ? <div style={{ color: 'var(--ih-danger-ink)', fontSize: '.74rem', marginTop: '.25rem' }}>{errs[k]}</div> : null;

  // رابط مباشر لكل تبويب (#tab) + استعادة آخر تبويب عند الرجوع
  const [tab, setTab] = useState('overview');
  useEffect(() => {
    const isTab = (k: string) => (TAB_KEYS as readonly string[]).includes(k);
    const applyHash = () => {
      const fromHash = window.location.hash.replace('#', '');
      if (isTab(fromHash)) { setTab(fromHash); return true; }
      return false;
    };
    // ?tab= أيضًا لا الهاش وحده: الروابط المولَّدة في الخادم تستعمل معاملات
    // الاستعلام، وكان التبويب المطلوب يُتجاهَل فيهبط القادم على «نظرة عامة».
    const fromQuery = new URLSearchParams(window.location.search).get('tab');
    if (fromQuery && isTab(fromQuery)) {
      setTab(fromQuery);
    } else if (!applyHash()) {
      const saved = sessionStorage.getItem(`ih.clientTab.${client.id}`);
      if (saved && (TAB_KEYS as readonly string[]).includes(saved)) setTab(saved);
    }
    // التنقّل بالهاش داخل نفس الصفحة لا يُعيد التركيب — نستمع للتغيّر
    window.addEventListener('hashchange', applyHash);
    return () => window.removeEventListener('hashchange', applyHash);
  }, [client.id]);
  const docAlerts = [
    contracts.filter((c) => c.awaitingSignature).length ? `${contracts.filter((c) => c.awaitingSignature).length} عقد بانتظار التوقيع` : null,
    contracts.filter((c) => c.expiringSoon).length ? `${contracts.filter((c) => c.expiringSoon).length} عقد ينتهي خلال 30 يومًا` : null,
    documents.filter((d) => d.expired).length ? `${documents.filter((d) => d.expired).length} مستند منتهٍ` : null,
    documents.filter((d) => d.pending).length ? `${documents.filter((d) => d.pending).length} مستند بانتظار المراجعة` : null,
  ].filter(Boolean) as string[];

  const go = (k: string) => {
    setTab(k);
    sessionStorage.setItem(`ih.clientTab.${client.id}`, k);
    history.replaceState(null, '', k === 'overview' ? window.location.pathname : `#${k}`);
  };

  return (
    <AppShell heading="ملف العميل">
      <Head title={client.name} />

      <WorkspaceHeader
        eyebrow={`عميل · ${client.number}`}
        title={client.name}
        statusTone={client.statusTone} statusLabel={client.statusLabel}
        back={u("/clients")} backLabel="كل العملاء"
        meta={[
          ['القطاع', client.sector ?? '—'], ['مدير الحساب', client.manager ?? '—'],
          ['المدينة', client.city ?? '—'], ['التصنيف', client.isVip ? 'VIP' : 'عادي'],
        ]}
        actions={
          <label style={{ display: 'flex', alignItems: 'center', gap: '.4rem', fontSize: '.8rem' }}>
            <span style={{ color: 'var(--ih-text-muted)' }}>الحالة</span>
            {/* تغيير الحالة من هنا: كان العميل يُنشأ «مهتمًّا» بلا مسار تحديث،
                فتبقى الحملة محجوبة بشرط «عميل نشط» لا سبيل إلى رفعه. */}
            <select
              className="field"
              style={{ minWidth: 130 }}
              value={client.status}
              onChange={(e) => post('/update', { status: e.target.value }, () => undefined)}
              disabled={busy}
            >
              <option value="lead">مهتم</option>
              <option value="qualified">مؤهّل</option>
              <option value="active">نشط</option>
              <option value="inactive">غير نشط</option>
              <option value="suspended">موقوف</option>
            </select>
          </label>
        }
      />

      <SummaryStrip items={[
        { label: 'الإيراد', value: sar(metrics.revenueMinor), tone: 'primary', icon: 'wallet' },
        { label: 'التكلفة', value: sar(metrics.costMinor), tone: 'warning' },
        { label: 'الربح', value: sar(metrics.profitMinor), tone: 'success' },
        { label: 'الهامش', value: `${metrics.margin}%` },
        { label: 'الحملات', value: `${metrics.activeCampaigns}/${metrics.campaigns}` },
        { label: 'صناع المحتوى', value: metrics.creators, icon: 'users' },
        { label: 'المستحق', value: sar(metrics.receivableMinor) },
        { label: 'الاكتمال', value: `${metrics.completion}%` },
      ]} />

      {/* الترتيب المعتمد (docs/PRODUCT-TERMINOLOGY.md). العدّاد يظهر فقط عند وجود قيمة تشغيلية. */}
      <WorkTabs active={tab} onChange={go} tabs={TABS({ campaigns, creators, content, requests, contracts, documents, payouts, brands, contacts, team, customFields })} />

      {tab === 'overview' && (
        <>
          {/* الخطوة التالية — إجراء واحد واضح */}
          {nextAction ? (
            <div className="ih-nba" style={{ marginBottom: '1.1rem' }}>
              <span className="ih-nba__icon"><Icon name="rocket" size={22} /></span>
              <div className="ih-nba__body">
                <div className="ih-nba__eyebrow">الخطوة التالية</div>
                <div className="ih-nba__title">{nextAction.label}</div>
              </div>
              <button onClick={() => nextAction.tab && go(nextAction.tab)} className="btn btn-sm">معالجة</button>
            </div>
          ) : (
            <div className="card" style={{ padding: '.85rem 1.1rem', marginBottom: '1.1rem', display: 'flex', alignItems: 'center', gap: '.6rem', borderInlineStart: '3px solid var(--ih-success)', background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)', fontSize: '.87rem' }}>
              <Icon name="shield-check" size={16} /> لا شيء يحتاج تدخّلًا الآن.
            </div>
          )}

          <div className="ih-overview-grid" style={{ display: 'grid', gridTemplateColumns: 'minmax(0,1.25fr) minmax(0,1fr)', gap: '1.1rem', alignItems: 'start' }}>
            <div style={{ display: 'grid', gap: '1.1rem' }}>
              {risks.length > 0 && (
                <Sec title="المخاطر" icon="activity">
                  <div style={{ padding: '.6rem' }}>
                    {risks.map((r, i) => {
                      const t = RISK_TONE[r.tone] ?? RISK_TONE.info;
                      return (
                        <button key={i} onClick={() => r.tab && go(r.tab)} className="ih-risk"
                          style={{ marginBottom: '.4rem', justifyContent: 'flex-start', gap: '.6rem', width: '100%', border: 0, cursor: 'pointer', font: 'inherit', textAlign: 'start' }}>
                          <span className="ih-risk__dot" style={{ background: t.fg }} />
                          <span style={{ flex: 1, fontWeight: 600 }}>{r.label}</span>
                          <span style={{ color: 'var(--ih-primary)', fontSize: '.82rem' }}>معالجة ←</span>
                        </button>
                      );
                    })}
                  </div>
                </Sec>
              )}

              {/* الحملات النشطة — لمحة سريعة بدل جدول كامل */}
              <Sec title="الحملات النشطة" icon="megaphone" link={campaigns.length ? { href: '#campaigns', label: 'كل الحملات' } : undefined}>
                {campaigns.length === 0 ? (
                  <div style={{ padding: '1.5rem', textAlign: 'center', color: 'var(--ih-text-muted)', fontSize: '.86rem' }}>لا حملات بعد.</div>
                ) : (
                  <div style={{ display: 'grid', gap: '.5rem', padding: '.6rem' }}>
                    {campaigns.slice(0, 4).map((c) => (
                      <Link key={c.id} href={u(`/campaigns/${c.id}`)} className="card"
                        style={{ display: 'flex', alignItems: 'center', gap: '.7rem', padding: '.65rem .8rem', textDecoration: 'none', color: 'inherit' }}>
                        <div style={{ flex: 1, minWidth: 0 }}>
                          <div style={{ fontWeight: 600, fontSize: '.88rem', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{c.name}</div>
                          <div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>{c.brand ?? '—'} · {c.deliverables} مخرج</div>
                        </div>
                        <span style={{ fontWeight: 700, fontSize: '.84rem', direction: 'ltr' }}>{sar(c.budgetMinor)}</span>
                        <StatusBadge tone={c.statusTone} label={c.statusLabel} />
                      </Link>
                    ))}
                  </div>
                )}
              </Sec>

              {/* آخر نشاط */}
              <Sec title="آخر نشاط" icon="activity">
                {activity.length === 0 ? (
                  <div style={{ padding: '1.5rem', textAlign: 'center', color: 'var(--ih-text-muted)', fontSize: '.86rem' }}>لا نشاط مسجّل.</div>
                ) : (
                  <div style={{ padding: '.7rem .9rem', display: 'grid', gap: '.1rem' }}>
                    {activity.map((a, i) => (
                      <Link key={i} href={u(a.href)} className="ih-feed__item"
                        style={{ display: 'flex', alignItems: 'flex-start', gap: '.6rem', padding: '.5rem 0', textDecoration: 'none', color: 'inherit', borderBottom: i < activity.length - 1 ? '1px solid var(--ih-border)' : undefined }}>
                        <span style={{ color: 'var(--ih-gray-400)', marginTop: 1 }}><Icon name={a.icon as never} size={15} /></span>
                        <span style={{ flex: 1, fontSize: '.84rem', minWidth: 0, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{a.text}</span>
                        <span style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', direction: 'ltr', flexShrink: 0 }}>{a.at}</span>
                      </Link>
                    ))}
                  </div>
                )}
              </Sec>
            </div>

            <div style={{ display: 'grid', gap: '1.1rem' }}>
              <Sec title="اكتمال الملف" icon="gauge">
                <div className="ih-sec__body">
                  <div style={{ display: 'flex', alignItems: 'center', gap: '.6rem' }}>
                    <div className="ih-bar" style={{ flex: 1 }}><span style={{ width: `${metrics.completion}%` }} /></div>
                    <span style={{ fontWeight: 800 }}>{metrics.completion}%</span>
                  </div>
                  <div style={{ marginTop: '.5rem', fontSize: '.8rem', color: 'var(--ih-text-muted)' }}>
                    {metrics.completion >= 100 ? 'الملف مكتمل.' : 'أكمل البيانات القانونية والمالية.'}
                  </div>
                </div>
              </Sec>

              <Sec title="بيانات التواصل" icon="file-text">
                <div className="ih-sec__body" style={{ display: 'grid', gap: '.55rem' }}>
                  {([['البريد', client.email], ['الهاتف', client.phone], ['الموقع', client.website], ['المدينة', client.city], ['السجل التجاري', client.cr], ['الرقم الضريبي', client.tax]] as [string, string | null][]).map(([k, v]) => (
                    <div key={k} style={{ display: 'flex', justifyContent: 'space-between', gap: '.7rem', fontSize: '.85rem', borderBottom: '1px solid var(--ih-border)', paddingBottom: '.45rem' }}>
                      <span style={{ color: 'var(--ih-text-muted)', flexShrink: 0 }}>{k}</span>
                      <span style={{ fontWeight: 600, direction: 'ltr', minWidth: 0, overflow: 'hidden', textOverflow: 'ellipsis' }}>{v || '—'}</span>
                    </div>
                  ))}
                </div>
              </Sec>

              {contacts.length > 0 && (
                <Sec title="جهات الاتصال" icon="user-plus" link={{ href: '#contacts', label: 'الكل' }}>
                  <div style={{ padding: '.7rem .9rem', display: 'grid', gap: '.6rem' }}>
                    {contacts.slice(0, 3).map((c, i) => (
                      <div key={i} style={{ display: 'flex', alignItems: 'center', gap: '.6rem' }}>
                        <span className="ih-idc__av" style={{ width: 32, height: 32, fontSize: '.78rem' }}>{c.name.slice(0, 1)}</span>
                        <div style={{ minWidth: 0, flex: 1 }}>
                          <div style={{ fontWeight: 600, fontSize: '.85rem' }}>{c.name}</div>
                          <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)' }}>{c.role ?? '—'}</div>
                        </div>
                        {c.email && <a href={`mailto:${c.email}`} className="btn btn-xs btn-outline" title={c.email}>مراسلة</a>}
                      </div>
                    ))}
                  </div>
                </Sec>
              )}
            </div>
          </div>
        </>
      )}

      {/* الحملات — Pipeline حسب المرحلة، بطاقات غنية بالتقدّم والصحّة */}
      {tab === 'campaigns' && (
        campaigns.length === 0 ? (
          <EmptyState icon="megaphone" title="لا حملات بعد" hint="ستظهر حملات هذا العميل هنا فور إنشائها." />
        ) : (
          <div className="ih-pipe">
            {([['planning', 'التخطيط'], ['running', 'التنفيذ'], ['closed', 'المنتهية']] as [string, string][]).map(([stage, label]) => {
              const col = campaigns.filter((c) => c.stage === stage);
              return (
                <div key={stage} className="ih-pipe__col">
                  <div className="ih-pipe__head"><span>{label}</span><span className="ih-pipe__count">{col.length}</span></div>
                  <div className="ih-pipe__body">
                    {col.length === 0 ? <div className="ih-pipe__empty">لا حملات في هذه المرحلة.</div> : col.map((c) => (
                      <Link key={c.id} href={u(`/campaigns/${c.id}`)} className="ih-wcard">
                        <div style={{ display: 'flex', justifyContent: 'space-between', gap: '.5rem', alignItems: 'flex-start' }}>
                          <span className="ih-wcard__title">{c.name}</span>
                          <StatusBadge tone={c.statusTone} label={c.statusLabel} />
                        </div>
                        <div className="ih-wcard__meta">{c.brand ?? '—'} · {c.startDate ?? '—'} → {c.endDate ?? '—'}</div>

                        {c.content > 0 && (
                          <div style={{ marginTop: '.55rem' }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '.7rem', color: 'var(--ih-text-muted)', marginBottom: '.2rem' }}>
                              <span>المحتوى المنشور</span><span style={{ direction: 'ltr' }}>{c.contentPublished}/{c.content}</span>
                            </div>
                            <Bar pct={c.progress} />
                          </div>
                        )}
                        {c.budgetMinor > 0 && (
                          <div style={{ marginTop: '.45rem' }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '.7rem', color: c.overBudget ? 'var(--ih-danger-ink)' : 'var(--ih-text-muted)', marginBottom: '.2rem' }}>
                              <span>الميزانية</span><span style={{ direction: 'ltr' }}>{sar(c.committedMinor)} / {sar(c.budgetMinor)}</span>
                            </div>
                            <Bar pct={c.budgetPct} over={c.overBudget} />
                          </div>
                        )}

                        <div className="ih-wcard__row">
                          <span style={{ display: 'flex', gap: '.6rem', fontSize: '.72rem', color: 'var(--ih-text-muted)' }}>
                            <span><Icon name="users" size={12} /> {c.creators}</span>
                            <span><Icon name="image" size={12} /> {c.content}</span>
                            {c.payouts > 0 && <span><Icon name="wallet" size={12} /> {c.payouts}</span>}
                          </span>
                          {c.awaiting > 0 && <span className="ih-tag" style={{ fontSize: '.64rem', background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)' }}>{c.awaiting} بانتظار مراجعة</span>}
                        </div>
                        {c.risk && <div className="ih-wcard__risk">{c.risk}</div>}
                      </Link>
                    ))}
                  </div>
                </div>
              );
            })}
          </div>
        )
      )}
      {/* العلامات — بطاقات هوية غنية */}
      {tab === 'brands' && (
        <>
        {can.update && (
          <AddPanel label="إضافة علامة" open={panel === 'brand'} onToggle={() => togglePanel('brand')} busy={busy}
            disabled={!brandForm.name.trim()}
            onSubmit={() => post('/brands', brandForm, () => setBrandForm({ name: '', sector: '', website: '' }))}>
            <Field label="اسم العلامة" labelStyle={FLBL}>
              <input value={brandForm.name} onChange={(e) => setBrandForm({ ...brandForm, name: e.target.value })} className="field" style={{ width: '100%' }} /><Err k="name" /></Field>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
              <Field label="القطاع" labelStyle={FLBL}>
                <input value={brandForm.sector} onChange={(e) => setBrandForm({ ...brandForm, sector: e.target.value })} className="field" style={{ width: '100%' }} /></Field>
              <Field label="الموقع" labelStyle={FLBL}>
                <input value={brandForm.website} onChange={(e) => setBrandForm({ ...brandForm, website: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} /><Err k="website" /></Field>
            </div>
          </AddPanel>
        )}
        {brands.length === 0 ? (
          <EmptyState icon="bookmark" title="لا علامات بعد" hint="علامات هذا العميل تظهر هنا مع نشاطها." />
        ) : (
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(250px, 1fr))', gap: '.9rem' }}>
            {brands.map((b) => {
              const bc = campaigns.filter((c) => c.brand === b.name);
              const active = bc.filter((c) => c.stage === 'running').length;
              const budget = bc.reduce((t, c) => t + c.budgetMinor, 0);
              return (
                <Link key={b.id} href={u(`/brands/${b.id}`)} className="ih-idcard" style={{ textDecoration: 'none', color: 'inherit' }}>
                  <div className="ih-idcard__top">
                    <span className="ih-idcard__logo">{b.name.slice(0, 1)}</span>
                    <div style={{ minWidth: 0, flex: 1 }}>
                      <div style={{ fontWeight: 700, fontSize: '.92rem', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{b.name}</div>
                      <div style={{ fontSize: '.73rem', color: 'var(--ih-text-muted)' }}>{client.sector ?? '—'}</div>
                    </div>
                    <StatusBadge tone={b.statusTone} label={b.statusLabel} />
                  </div>
                  <div className="ih-idcard__stats">
                    <div className="ih-idcard__stat"><div className="ih-idcard__sv">{bc.length}</div><div className="ih-idcard__sl">حملات</div></div>
                    <div className="ih-idcard__stat"><div className="ih-idcard__sv">{active}</div><div className="ih-idcard__sl">نشطة</div></div>
                    <div className="ih-idcard__stat"><div className="ih-idcard__sv">{budget ? sar(budget).replace(' ر.س', '') : '—'}</div><div className="ih-idcard__sl">ميزانية</div></div>
                  </div>
                </Link>
              );
            })}
          </div>
        )}
        </>
      )}

      {/* المحتوى — شريط سير العمل + معرض معاينات */}
      {tab === 'content' && (
        content.length === 0 ? (
          <EmptyState icon="image" title="لا محتوى بعد" hint="محتوى حملات هذا العميل يظهر هنا للمراجعة والاعتماد." />
        ) : (
          <div style={{ display: 'grid', gap: '1.1rem' }}>
            <div className="ih-flow">
              {contentStages.map((sg) => (
                <div key={sg.key} className={`ih-flow__step${sg.count > 0 ? ' on' : ''}`}>
                  <div className="ih-flow__n">{sg.count}</div>
                  <div className="ih-flow__l">{sg.label}</div>
                </div>
              ))}
            </div>

            <div className="ih-gallery">
              {content.map((c) => (
                <Link key={c.id} href={u(`/content/${c.id}`)} className="ih-gtile" style={{ textDecoration: 'none', color: 'inherit' }}>
                  <div className="ih-gtile__thumb">
                    {c.mediaUrl ? <img src={c.mediaUrl} alt="" loading="lazy" /> : <Icon name="image" size={26} />}
                    <span className="ih-gtile__badge"><StatusBadge tone={c.statusTone} label={c.statusLabel} /></span>
                    {c.version > 1 && <span className="ih-gtile__ver">v{c.version}</span>}
                  </div>
                  <div className="ih-gtile__body">
                    <div className="ih-gtile__title">{c.title}</div>
                    <div className="ih-gtile__meta">{c.creator ?? '—'}{c.platform ? ` · ${c.platform}` : ''}</div>
                    {c.campaign && <div className="ih-gtile__meta" style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{c.campaign}</div>}
                    <div style={{ marginTop: 'auto', display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '.4rem', paddingTop: '.35rem' }}>
                      <span style={{ fontSize: '.68rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{c.publishedAt ?? c.scheduledAt ?? ''}</span>
                      {c.needsAction && <span className="ih-tag" style={{ fontSize: '.62rem', background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)' }}>يحتاج إجراء</span>}
                    </div>
                  </div>
                </Link>
              ))}
            </div>
          </div>
        )
      )}

      {/* العقود والمستندات — مساحة مستندات بتنبيهات وفصل بصري */}
      {tab === 'docs' && (
        <div style={{ display: 'grid', gap: '1.1rem' }}>
          {(docAlerts.length > 0) && (
            <div className="card" style={{ padding: '.8rem 1rem', borderInlineStart: '3px solid var(--ih-warning)', background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)', fontSize: '.85rem', display: 'grid', gap: '.3rem' }}>
              {docAlerts.map((a, i) => <div key={i}><Icon name="activity" size={13} /> {a}</div>)}
            </div>
          )}

          <Sec title="العقود" icon="file-text">
            {contracts.length === 0 ? (
              <div style={{ padding: '1.6rem', textAlign: 'center', color: 'var(--ih-text-muted)', fontSize: '.86rem' }}>لا عقود بعد.</div>
            ) : (
              <div style={{ display: 'grid', gap: '.5rem', padding: '.7rem' }}>
                {contracts.map((c) => (
                  <Link key={c.id} href={u(`/contracts/${c.id}`)} className="ih-wcard">
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: '.5rem', alignItems: 'flex-start' }}>
                      <div style={{ minWidth: 0 }}>
                        <div className="ih-wcard__title">{c.title}</div>
                        <div className="ih-wcard__meta">{c.party ?? '—'} · <span style={{ direction: 'ltr' }}>{c.number}</span></div>
                      </div>
                      <div style={{ textAlign: 'end', flexShrink: 0 }}>
                        <div style={{ fontWeight: 700, direction: 'ltr', fontSize: '.88rem' }}>{sar(c.valueMinor)}</div>
                        <StatusBadge tone={c.statusTone} label={c.statusLabel} />
                      </div>
                    </div>
                    {/* مسار التوقيع */}
                    <div style={{ display: 'flex', alignItems: 'center', gap: '.4rem', marginTop: '.6rem', fontSize: '.71rem', color: 'var(--ih-text-muted)', flexWrap: 'wrap' }}>
                      <SignStep on={!!c.sentAt} label="أُرسل" at={c.sentAt} />
                      <span style={{ opacity: .4 }}>←</span>
                      <SignStep on={!!c.signedAt} label="وُقّع" at={c.signedAt} />
                      <span style={{ opacity: .4 }}>←</span>
                      <SignStep on={c.status === 'active' || c.status === 'completed'} label="سارٍ" at={c.startDate} />
                      {c.endDate && <span style={{ marginInlineStart: 'auto', direction: 'ltr' }}>ينتهي {c.endDate}</span>}
                    </div>
                    {c.awaitingSignature && <div className="ih-wcard__risk" style={{ background: 'var(--ih-info-soft)', color: 'var(--ih-info-ink)' }}>بانتظار التوقيع</div>}
                    {c.expiringSoon && <div className="ih-wcard__risk" style={{ background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)' }}>ينتهي خلال 30 يومًا</div>}
                    {c.expired && <div className="ih-wcard__risk">منتهٍ</div>}
                  </Link>
                ))}
              </div>
            )}
          </Sec>

          <Sec title="المستندات" icon="file-text">
            {can.documents && (
              <div style={{ padding: '.8rem .9rem 0' }}>
                <AddPanel label="رفع مستند" open={panel === 'doc'} onToggle={() => togglePanel('doc')} busy={busy}
                  disabled={!docForm.file || !docForm.title.trim()}
                  onSubmit={() => post('/documents', { title: docForm.title, category: docForm.category, file: docForm.file },
                    () => setDocForm({ title: '', category: 'contract', file: null }))}>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
                    <Field label="العنوان" labelStyle={FLBL}>
                      <input value={docForm.title} onChange={(e) => setDocForm({ ...docForm, title: e.target.value })} className="field" style={{ width: '100%' }} /><Err k="title" /></Field>
                    <Field label="التصنيف" labelStyle={FLBL}>
                      <select value={docForm.category} onChange={(e) => setDocForm({ ...docForm, category: e.target.value })} className="field" style={{ width: '100%' }}>
                        <option value="contract">عقد</option>
                        <option value="commercial_registration">سجل تجاري</option>
                        <option value="tax_certificate">شهادة ضريبية</option>
                        <option value="other">أخرى</option>
                      </select><Err k="category" />
                    </Field>
                  </div>
                  <Field label="الملف (حتى 20 ميغابايت)" labelStyle={FLBL}>
                    <input type="file" onChange={(e) => setDocForm({ ...docForm, file: e.target.files?.[0] ?? null })} className="field" style={{ width: '100%' }} />
                    <Err k="file" />
                  </Field>
                </AddPanel>
              </div>
            )}
            {documents.length === 0 ? (
              <div style={{ padding: '1.6rem', textAlign: 'center', color: 'var(--ih-text-muted)', fontSize: '.86rem' }}>لا مستندات.</div>
            ) : (
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(230px, 1fr))', gap: '.7rem', padding: '.7rem' }}>
                {documents.map((d) => (
                  <div key={d.id} className="card" style={{ padding: '.7rem .8rem', display: 'flex', gap: '.6rem', alignItems: 'flex-start' }}>
                    <span style={{ color: 'var(--ih-gray-400)', marginTop: 2 }}><Icon name="file-text" size={18} /></span>
                    <div style={{ minWidth: 0, flex: 1 }}>
                      <div style={{ fontWeight: 600, fontSize: '.85rem', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{d.title}</div>
                      <div style={{ fontSize: '.71rem', color: 'var(--ih-text-muted)' }}>{d.category ?? '—'} · <span style={{ direction: 'ltr' }}>{d.sizeKb.toLocaleString('en-US')} KB</span></div>
                      <div style={{ marginTop: '.4rem', display: 'flex', gap: '.3rem', alignItems: 'center', flexWrap: 'wrap' }}>
                        <StatusBadge tone={d.statusTone} label={d.statusLabel} />
                        {d.expired && <span className="ih-tag" style={{ fontSize: '.62rem', background: 'var(--ih-danger-soft)', color: 'var(--ih-danger-ink)' }}>منتهٍ</span>}
                        {d.expiringSoon && <span className="ih-tag" style={{ fontSize: '.62rem', background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)' }}>قارب الانتهاء</span>}
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </Sec>
        </div>
      )}

      {/* المالية — مساحة مالية: دلاء + توزيع حسب الحملة + جدول زمني للدفعات */}
      {tab === 'finance' && (
        <div style={{ display: 'grid', gap: '1.1rem' }}>
          <div className="ih-kpis">
            <Kpi label="الإيراد" icon="wallet" tone="success" value={sar(metrics.revenueMinor)} sub="من الحملات" />
            <Kpi label="التكلفة" icon="wallet" tone="warning" value={sar(metrics.costMinor)} sub="أتعاب المبدعين" />
            <Kpi label="الربح" icon="trending-up" value={sar(metrics.profitMinor)} sub={`هامش ${metrics.margin}%`} />
            <Kpi label="متأخر الصرف" icon="activity" tone={finance.buckets.overdue ? 'danger' : undefined}
              value={sar(finance.buckets.overdue)} sub={finance.buckets.overdue ? 'يحتاج معالجة' : 'لا متأخرات'} />
          </div>

          <div className="ih-overview-grid" style={{ display: 'grid', gridTemplateColumns: 'minmax(0,1.2fr) minmax(0,1fr)', gap: '1.1rem', alignItems: 'start' }}>
            <Sec title="التوزيع حسب الحملة" icon="bar-chart-3">
              {finance.byCampaign.length === 0 ? (
                <div style={{ padding: '1.5rem', textAlign: 'center', color: 'var(--ih-text-muted)', fontSize: '.86rem' }}>لا بيانات مالية بعد.</div>
              ) : (
                <div style={{ padding: '.8rem .9rem', display: 'grid', gap: '.8rem' }}>
                  {finance.byCampaign.map((r) => {
                    const max = Math.max(...finance.byCampaign.map((x) => Math.max(x.budgetMinor, x.costMinor)), 1);
                    return (
                      <div key={r.id}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '.82rem', marginBottom: '.25rem' }}>
                          <span style={{ fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{r.name}</span>
                          <span style={{ color: 'var(--ih-text-muted)', direction: 'ltr', flexShrink: 0 }}>{sar(r.costMinor)} / {sar(r.budgetMinor)}</span>
                        </div>
                        <Bar pct={Math.round((r.budgetMinor / max) * 100)} />
                        <div style={{ marginTop: '.2rem' }}><Bar pct={Math.round((r.costMinor / max) * 100)} over={r.costMinor > r.budgetMinor && r.budgetMinor > 0} /></div>
                      </div>
                    );
                  })}
                  <div style={{ fontSize: '.7rem', color: 'var(--ih-text-muted)' }}>الشريط العلوي: الميزانية · السفلي: التكلفة الفعلية</div>
                </div>
              )}
            </Sec>

            <div style={{ display: 'grid', gap: '1.1rem' }}>
              <Sec title="حالة المستحقات" icon="wallet">
                <div className="ih-sec__body" style={{ display: 'grid', gap: '.55rem' }}>
                  {([['مدفوع', finance.buckets.paid, 'success'], ['بانتظار الصرف', finance.buckets.pending, 'warning'], ['متأخر', finance.buckets.overdue, 'danger']] as [string, number, string][]).map(([l, v, t]) => (
                    <div key={l} style={{ display: 'flex', justifyContent: 'space-between', fontSize: '.85rem', borderBottom: '1px solid var(--ih-border)', paddingBottom: '.4rem' }}>
                      <span style={{ color: 'var(--ih-text-muted)' }}>{l}</span>
                      <span style={{ fontWeight: 700, direction: 'ltr', color: v > 0 && t !== 'success' ? `var(--ih-${t}-ink)` : undefined }}>{sar(v)}</span>
                    </div>
                  ))}
                </div>
              </Sec>

              <Sec title="آخر الدفعات" icon="activity">
                {finance.timeline.length === 0 ? (
                  <div style={{ padding: '1.2rem', textAlign: 'center', color: 'var(--ih-text-muted)', fontSize: '.84rem' }}>لا دفعات منفّذة.</div>
                ) : (
                  <div className="ih-mline" style={{ padding: '.7rem .9rem' }}>
                    {finance.timeline.map((t, i) => (
                      <div key={i} className="ih-mline__row">
                        <span style={{ color: 'var(--ih-success-ink)' }}><Icon name="wallet" size={14} /></span>
                        <span style={{ flex: 1, minWidth: 0, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{t.label}</span>
                        <span style={{ fontWeight: 700, direction: 'ltr' }}>{sar(t.amountMinor)}</span>
                        <span style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{t.at}</span>
                      </div>
                    ))}
                  </div>
                )}
              </Sec>
            </div>
          </div>

          <Sec title="المستحقات" icon="wallet">
            {payouts.length === 0 ? (
              <div style={{ padding: '1.6rem', textAlign: 'center', color: 'var(--ih-text-muted)', fontSize: '.86rem' }}>لا مستحقات بعد.</div>
            ) : (
              <DataTable head={['المستحق', 'المبدع', 'الحملة', 'الاستحقاق', 'المبلغ', 'الحالة']}>
                {payouts.map((p) => (
                  <tr key={p.id}>
                    <td style={{ direction: 'ltr', textAlign: 'right', fontWeight: 600 }}>{p.number}</td>
                    <td>{p.creator ?? '—'}</td>
                    <td style={{ fontSize: '.8rem', color: 'var(--ih-text-muted)' }}>{p.campaign ?? '—'}</td>
                    <td style={{ direction: 'ltr', fontSize: '.8rem', color: p.overdue ? 'var(--ih-danger-ink)' : 'var(--ih-text-muted)', fontWeight: p.overdue ? 700 : 400 }}>{p.dueDate ?? '—'}</td>
                    <td className="ih-dt__num" style={{ direction: 'ltr', textAlign: 'right', fontWeight: 600 }}>{sar(p.amountMinor)}</td>
                    <td><StatusBadge tone={p.statusTone} label={p.statusLabel} /></td>
                  </tr>
                ))}
              </DataTable>
            )}
          </Sec>
        </div>
      )}

      {/* صناع المحتوى — بطاقات علاقة مصنّفة (نشط/حديث/متوقف) */}
      {tab === 'creators' && (
        creators.length === 0 ? (
          <EmptyState icon="users" title="لا صنّاع محتوى مرتبطين" hint="صناع المحتوى الذين تعاونوا في حملات هذا العميل يظهرون هنا." />
        ) : (
          <div style={{ display: 'grid', gap: '1.2rem' }}>
            {([['active', 'متعاونون الآن'], ['recent', 'تعاونوا مؤخّرًا'], ['dormant', 'متوقفون']] as [string, string][]).map(([rel, label]) => {
              const grp = creators.filter((c) => c.relation === rel);
              if (grp.length === 0) return null;
              return (
                <div key={rel}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '.5rem', marginBottom: '.6rem' }}>
                    <span style={{ fontWeight: 700, fontSize: '.9rem' }}>{label}</span>
                    <span className="ih-pipe__count">{grp.length}</span>
                  </div>
                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(260px, 1fr))', gap: '.8rem' }}>
                    {grp.map((c) => (
                      <div key={c.id} className="ih-idcard">
                        <div className="ih-idcard__top">
                          <span className="ih-idcard__logo" style={{ borderRadius: '50%' }}>{c.name.slice(0, 1)}</span>
                          <div style={{ minWidth: 0, flex: 1 }}>
                            <Link href={u(`/creators/${c.id}`)} style={{ fontWeight: 700, fontSize: '.9rem', color: 'var(--ih-primary)', textDecoration: 'none', display: 'flex', alignItems: 'center', gap: '.3rem' }}>
                              {c.name}{c.verified && <Icon name="shield-check" size={13} />}
                            </Link>
                            <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', direction: 'ltr', textAlign: 'start' }}>
                              {c.handle ?? '—'}{c.platform ? ` · ${c.platform}` : ''}
                            </div>
                          </div>
                        </div>
                        <div className="ih-idcard__stats">
                          <div className="ih-idcard__stat"><div className="ih-idcard__sv">{c.collaborations}</div><div className="ih-idcard__sl">تعاون</div></div>
                          <div className="ih-idcard__stat"><div className="ih-idcard__sv">{c.published}</div><div className="ih-idcard__sl">منشور</div></div>
                          <div className="ih-idcard__stat"><div className="ih-idcard__sv">{sar(c.feeMinor).replace(' ر.س', '')}</div><div className="ih-idcard__sl">القيمة</div></div>
                        </div>
                        <div>
                          <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '.71rem', color: 'var(--ih-text-muted)', marginBottom: '.2rem' }}>
                            <span>جودة التعاون</span><span style={{ direction: 'ltr' }}>{c.quality}%</span>
                          </div>
                          <Bar pct={c.quality} />
                        </div>
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '.4rem' }}>
                          <span style={{ fontSize: '.71rem', color: 'var(--ih-text-muted)' }}>{c.lastAt ? `آخر تعاون ${c.lastAt}` : 'لا تعاون سابق'}</span>
                          <Link href={u(`/campaigns`)} className="btn btn-xs btn-outline">تعاون جديد</Link>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              );
            })}
          </div>
        )
      )}

      {/* الطلبات — طابور فرز مقسّم حسب الإلحاح */}
      {tab === 'requests' && (
        requests.length === 0 ? (
          <EmptyState icon="inbox" title="لا طلبات" hint="طلبات هذا العميل تظهر هنا مرتّبة حسب الإلحاح." />
        ) : (
          <div style={{ display: 'grid', gap: '1.1rem' }}>
            {([['overdue', 'متأخرة', 'danger'], ['new', 'جديدة', 'primary'], ['open', 'قيد العمل', 'warning'], ['done', 'منتهية', 'success']] as [string, string, string][]).map(([bk, label]) => {
              const grp = requests.filter((q) => q.bucket === bk);
              if (grp.length === 0) return null;
              return (
                <div key={bk}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '.5rem', marginBottom: '.5rem' }}>
                    <span style={{ fontWeight: 700, fontSize: '.88rem' }}>{label}</span>
                    <span className="ih-pipe__count">{grp.length}</span>
                  </div>
                  <div className="ih-triage">
                    {grp.map((q) => (
                      <Link key={q.id} href={u(`/service-requests/${q.id}`)}
                        className={`ih-trow ih-trow--${q.overdue ? 'overdue' : q.dueSoon ? 'soon' : bk === 'new' ? 'new' : bk === 'done' ? 'done' : ''}`}>
                        <div style={{ minWidth: 0, flex: 1 }}>
                          <div style={{ fontWeight: 650, fontSize: '.87rem', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{q.title}</div>
                          <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)' }}>
                            <span style={{ direction: 'ltr' }}>{q.number}</span>
                            {q.assignee ? ` · ${q.assignee}` : ' · غير مُسنَد'}
                            {q.dueAt ? ` · يستحق ${q.dueAt}` : ''}
                          </div>
                        </div>
                        {q.blocked && <span className="ih-tag" style={{ fontSize: '.64rem', background: 'var(--ih-danger-soft)', color: 'var(--ih-danger-ink)', flexShrink: 0 }}>{q.blocked}</span>}
                        <span className="ih-tag" style={{ fontSize: '.64rem', flexShrink: 0 }}>{q.priorityLabel}</span>
                        <StatusBadge tone={q.statusTone} label={q.statusLabel} />
                      </Link>
                    ))}
                  </div>
                </div>
              );
            })}
          </div>
        )
      )}

      {/* جهات الاتصال — بطاقات تواصل بقنوات مباشرة */}
      {tab === 'contacts' && (
        <>
        {can.update && (
          <AddPanel label="إضافة جهة اتصال" open={panel === 'contact'} onToggle={() => togglePanel('contact')} busy={busy}
            disabled={!contactForm.name.trim()}
            onSubmit={() => post('/contacts', contactForm, () => setContactForm({ name: '', job_title: '', email: '', phone: '' }))}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
              <Field label="الاسم" labelStyle={FLBL}>
                <input value={contactForm.name} onChange={(e) => setContactForm({ ...contactForm, name: e.target.value })} className="field" style={{ width: '100%' }} /><Err k="name" /></Field>
              <Field label="المسمّى" labelStyle={FLBL}>
                <input value={contactForm.job_title} onChange={(e) => setContactForm({ ...contactForm, job_title: e.target.value })} className="field" style={{ width: '100%' }} /></Field>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
              <Field label="البريد" labelStyle={FLBL}>
                <input value={contactForm.email} onChange={(e) => setContactForm({ ...contactForm, email: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} /><Err k="email" /></Field>
              <Field label="الهاتف" labelStyle={FLBL}>
                <input value={contactForm.phone} onChange={(e) => setContactForm({ ...contactForm, phone: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} /><Err k="phone" /></Field>
            </div>
          </AddPanel>
        )}
        {contacts.length === 0 ? (
          <EmptyState icon="user-plus" title="لا جهات اتصال" hint="أضِف جهات اتصال العميل للتواصل المباشر." />
        ) : (
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(260px, 1fr))', gap: '.8rem' }}>
            {contacts.map((c, i) => (
              <div key={i} className="ih-idcard">
                <div className="ih-idcard__top">
                  <span className="ih-idcard__logo" style={{ borderRadius: '50%' }}>{c.name.slice(0, 1)}</span>
                  <div style={{ minWidth: 0, flex: 1 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '.35rem' }}>
                      <span style={{ fontWeight: 700, fontSize: '.9rem' }}>{c.name}</span>
                      {c.isPrimary && <span className="ih-tag" style={{ fontSize: '.6rem', background: 'var(--ih-primary-soft)', color: 'var(--ih-primary-800)' }}>أساسي</span>}
                    </div>
                    <div style={{ fontSize: '.73rem', color: 'var(--ih-text-muted)' }}>{c.role ?? '—'}{c.department ? ` · ${c.department}` : ''}</div>
                  </div>
                </div>
                <div style={{ display: 'grid', gap: '.3rem', fontSize: '.78rem' }}>
                  {c.email && <div style={{ display: 'flex', justifyContent: 'space-between', gap: '.5rem' }}><span style={{ color: 'var(--ih-text-muted)' }}>البريد</span><span style={{ direction: 'ltr', minWidth: 0, overflow: 'hidden', textOverflow: 'ellipsis' }}>{c.email}</span></div>}
                  {c.phone && <div style={{ display: 'flex', justifyContent: 'space-between', gap: '.5rem' }}><span style={{ color: 'var(--ih-text-muted)' }}>الهاتف</span><span style={{ direction: 'ltr' }}>{c.phone}</span></div>}
                  {c.preferredChannel && <div style={{ display: 'flex', justifyContent: 'space-between' }}><span style={{ color: 'var(--ih-text-muted)' }}>القناة المفضّلة</span><span>{c.preferredChannel}</span></div>}
                </div>
                <div style={{ display: 'flex', gap: '.35rem', alignItems: 'center' }}>
                  {c.email && <a href={`mailto:${c.email}`} className="btn btn-xs btn-outline" style={{ flex: 1, textAlign: 'center' }}>بريد</a>}
                  {c.phone && <a href={`tel:${c.phone}`} className="btn btn-xs btn-outline" style={{ flex: 1, textAlign: 'center' }}>اتصال</a>}
                  {c.whatsapp && <a href={`https://wa.me/${c.whatsapp.replace(/[^0-9]/g, '')}`} target="_blank" rel="noopener noreferrer" className="btn btn-xs btn-outline" style={{ flex: 1, textAlign: 'center' }}>واتساب</a>}
                </div>
                {c.hasPortal && <span className="ih-tag" style={{ fontSize: '.62rem', background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)', alignSelf: 'flex-start' }}>له وصول للبوابة</span>}
              </div>
            ))}
          </div>
        )}
        </>
      )}

      {tab === 'team' && (
        <>
        {can.portal && (
          <AddPanel label="دعوة عضو بوابة" open={panel === 'invite'} onToggle={() => togglePanel('invite')} busy={busy}
            disabled={!inviteForm.email.trim()}
            onSubmit={() => post('/members/invite', inviteForm, () => setInviteForm({ email: '', role: 'client_member' }))}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
              <Field label="البريد" labelStyle={FLBL}>
                <input value={inviteForm.email} onChange={(e) => setInviteForm({ ...inviteForm, email: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} /><Err k="email" /></Field>
              <Field label="الدور" labelStyle={FLBL}>
                <select value={inviteForm.role} onChange={(e) => setInviteForm({ ...inviteForm, role: e.target.value })} className="field" style={{ width: '100%' }}>
                  <option value="client_admin">مدير حساب العميل</option>
                  <option value="client_member">عضو</option>
                </select><Err k="role" />
              </Field>
            </div>
          </AddPanel>
        )}
        {inviteToken && (
          <div className="card" style={{ padding: '.9rem 1rem', marginBottom: '.9rem', borderInlineStart: '3px solid var(--ih-warning)', background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)' }}>
            <div style={{ fontWeight: 700, marginBottom: '.3rem' }}>رمز الدعوة — يُعرض مرة واحدة</div>
            <div style={{ fontSize: '.8rem', marginBottom: '.5rem' }}>انسخه الآن وسلّمه للعضو؛ لا يمكن استرجاعه بعد مغادرة الصفحة.</div>
            <code style={{ direction: 'ltr', display: 'block', wordBreak: 'break-all', fontSize: '.86rem', fontWeight: 700 }}>{inviteToken}</code>
          </div>
        )}
        <Sec title="الفريق" icon="user-plus">
          <DataTable head={['العضو', 'الدور', 'الحالة']}>
            {team.length === 0 ? <EmptyRow span={3} text="لا أعضاء." /> :
              team.map((m, i) => <tr key={i}><td style={{ fontWeight: 600 }}>{m.name}</td><td>{m.role}</td><td><StatusBadge tone={m.statusTone} label={m.status} /></td></tr>)}
          </DataTable>
        </Sec>
        </>
      )}

      {/* حقول مخصّصة — بطاقات مقروءة لا قائمة حقول تقنية */}
      {tab === 'custom' && (
        <Sec title="حقول مخصّصة" icon="clipboard-check">
          {can.update && (
            <div style={{ padding: '.9rem .9rem 0' }}>
              <AddPanel label="تعريف حقل" open={panel === 'field'} onToggle={() => togglePanel('field')} busy={busy}
                disabled={!fieldForm.key.trim() || !fieldForm.label.trim()}
                onSubmit={() => post('/custom-fields', fieldForm, () => setFieldForm({ key: '', label: '', type: 'text' }))}>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '.8rem' }}>
                  <Field label="المفتاح" labelStyle={FLBL}>
                    <input value={fieldForm.key} onChange={(e) => setFieldForm({ ...fieldForm, key: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} placeholder="contract_owner" /><Err k="key" /></Field>
                  <Field label="التسمية" labelStyle={FLBL}>
                    <input value={fieldForm.label} onChange={(e) => setFieldForm({ ...fieldForm, label: e.target.value })} className="field" style={{ width: '100%' }} /><Err k="label" /></Field>
                  <Field label="النوع" labelStyle={FLBL}>
                    <select value={fieldForm.type} onChange={(e) => setFieldForm({ ...fieldForm, type: e.target.value })} className="field" style={{ width: '100%' }}>
                      {[['text', 'نص'], ['textarea', 'نص طويل'], ['number', 'رقم'], ['date', 'تاريخ'], ['boolean', 'نعم/لا'],
                        ['url', 'رابط'], ['email', 'بريد'], ['phone', 'هاتف']].map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                    </select><Err k="type" />
                  </Field>
                </div>
              </AddPanel>
              {fieldDefinitions.length > 0 && (
                <div className="card" style={{ padding: '.9rem', marginBottom: '.9rem', display: 'grid', gap: '.7rem' }}>
                  <div style={{ fontWeight: 700, fontSize: '.85rem' }}>ضبط القيم</div>
                  {fieldDefinitions.map((d) => (
                    <div key={d.id} style={{ display: 'grid', gridTemplateColumns: '1fr 2fr auto', gap: '.6rem', alignItems: 'center' }}>
                      <span style={{ fontSize: '.82rem', color: 'var(--ih-text-muted)' }}>{d.label}</span>
                      <input value={fieldValues[d.id] ?? (customFields.find((c) => c.id === d.id)?.value ?? '')}
                        onChange={(e) => setFieldValues({ ...fieldValues, [d.id]: e.target.value })}
                        className="field" style={{ width: '100%' }} />
                      <button disabled={busy} className="btn btn-xs btn-outline"
                        onClick={() => post(`/custom-fields/${d.id}/set`, { value: fieldValues[d.id] ?? '' }, () => undefined)}>حفظ</button>
                    </div>
                  ))}
                  <Err k="value" />
                </div>
              )}
            </div>
          )}
          {customFields.length === 0 ? (
            <div style={{ padding: '2rem', textAlign: 'center' }}>
              <span className="ih-empty__icon" style={{ width: 44, height: 44 }}><Icon name="clipboard-check" size={20} /></span>
              <div style={{ marginTop: '.5rem', fontWeight: 700 }}>لا حقول مخصّصة</div>
              <div style={{ fontSize: '.82rem', color: 'var(--ih-text-muted)' }}>تُعرّف الحقول من الإعدادات وتظهر هنا لكل عميل.</div>
            </div>
          ) : (
            <div style={{ padding: '.9rem', display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))', gap: '.8rem' }}>
              {customFields.map((f) => (
                <div key={f.id} className="card" style={{ padding: '.75rem .9rem' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '.35rem', marginBottom: '.3rem' }}>
                    <span style={{ fontSize: '.75rem', color: 'var(--ih-text-muted)', fontWeight: 600 }}>{f.label}</span>
                    {f.required && <span className="ih-tag" style={{ fontSize: '.6rem', background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)' }}>إلزامي</span>}
                  </div>
                  <div style={{ fontSize: '.92rem', fontWeight: 600, wordBreak: 'break-word' }}>
                    {f.value || <span style={{ color: 'var(--ih-text-muted)', fontWeight: 400 }}>— غير مُعبّأ</span>}
                  </div>
                </div>
              ))}
            </div>
          )}
        </Sec>
      )}
    </AppShell>
  );
}

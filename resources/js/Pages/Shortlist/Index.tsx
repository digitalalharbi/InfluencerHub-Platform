import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState, type ReactNode } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Sec, StatusBadge, SummaryStrip, WorkTabs, WorkspaceHeader, Bar, type WorkTab } from '@/Components/ui';
import { Donut, Legend } from '@/Components/Charts';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';
import { PdfPreviewModal, type PreviewDoc } from '@/Components/PdfPreviewModal';
import { OverflowMenu } from '@/Components/OverflowMenu';

interface Campaign { id: number; name: string; number: string; client: string | null; brand: string | null; budgetMinor: number; committedMinor: number }
interface Version { number: number; status: string; statusLabel: string; statusTone: string; submittedAt: string | null; decidedAt: string | null }
interface Item {
  id: number; creator: string; handle: string | null; platform: string | null; isBackup: boolean;
  feeMinor: number; score: number; reasons: string[]; decision: string; decisionLabel: string; decisionTone: string;
}
interface Candidate {
  id: number; name: string; handle: string | null; platform: string | null; followers: number;
  feeMinor: number; verified: boolean; score: number; reasons: string[]; flags: string[];
  avatar: string | null; categories: string[]; creatorType: string | null;
}
interface CandidatePool { active: number; inactive: number }
interface VersionRow { number: number; status: string; statusLabel: string; statusTone: string; items: number; isCurrent: boolean; submittedAt: string | null; decidedAt: string | null }
interface Conversion { approved: number; pending: number; converted: number; eligible: number; canConvert: boolean; blockedReason: string | null }
interface Props {
  campaign: Campaign; version: Version; items: Item[]; candidates: Candidate[];
  filters: { q: string | null; platform: string | null };
  canEdit: boolean; budgetPct: number; overBudget: boolean; versions: VersionRow[];
  candidatePool: CandidatePool; conversion: Conversion;
  documents: { proposal: PreviewDoc };
}

const money = (m: number) => (m / 100).toLocaleString('en-US') + ' ر.س';
const fmt = (n: number) => n >= 1000 ? Math.round(n / 1000) + 'K' : n.toLocaleString('en-US');

function ScorePill({ score }: { score: number }) {
  const tone = score >= 75 ? 'success' : score >= 50 ? 'warning' : 'danger';
  const bg = { success: 'var(--ih-success-soft)', warning: 'var(--ih-warning-soft)', danger: 'var(--ih-danger-soft)' }[tone];
  const fg = { success: 'var(--ih-success-ink)', warning: 'var(--ih-warning-ink)', danger: 'var(--ih-danger-ink)' }[tone];
  return <span className="badge" style={{ background: bg, color: fg, direction: 'ltr' }}>{score}٪ ملاءمة</span>;
}

function DataTable({ head, children }: { head: string[]; children: ReactNode }) {
  return (
    <div className="ih-dt-wrap"><div className="ih-dt-scroll">
      <table className="ih-dt"><thead><tr>{head.map((h) => <th key={h}>{h}</th>)}</tr></thead><tbody>{children}</tbody></table>
    </div></div>
  );
}

// مسار الترشيح — ست مراحل تُجيب دائمًا: أين نحن؟ ما المطلوب الآن؟
const JOURNEY = ['اكتشاف', 'اختيار', 'مراجعة القائمة', 'إرسال للعميل', 'قرار العميل', 'التنفيذ'] as const;

/** يحسب المرحلة الحالية والإجراء المطلوب من حالة الإصدار الحقيقية (لا حالة مُختلَقة). */
function journeyState(status: string, primaryCount: number, conv: Conversion): { idx: number; next: string; done?: boolean } {
  if (status === 'draft') {
    return primaryCount === 0
      ? { idx: 1, next: 'أضِف مرشّحين أساسيين — من قائمة المرشّحين أو من قاعدة المؤثرين.' }
      : { idx: 2, next: 'راجِع القائمة ثم أرسِلها لاعتماد العميل.' };
  }
  if (status === 'submitted') return { idx: 4, next: 'بانتظار قرار العميل على القائمة المُرسَلة.' };
  if (status === 'changes_requested') return { idx: 4, next: 'العميل طلب بديلًا — أنشئ إصدارًا جديدًا وقدِّم البدائل.' };
  if (status === 'rejected') return { idx: 4, next: 'القائمة مرفوضة — أنشئ إصدارًا جديدًا بمرشّحين مختلفين.' };
  // approved / partially_approved
  if (conv.canConvert) return { idx: 5, next: `حوِّل ${conv.eligible} معتمَدًا للتنفيذ.` };
  if (conv.converted > 0 && conv.eligible === 0) return { idx: 5, next: 'اكتمل التحويل — المعتمَدون في مرحلة التنفيذ.', done: true };
  return { idx: 5, next: 'راجِع قرارات العميل على القائمة.' };
}

function JourneyStepper({ idx, allDone }: { idx: number; allDone?: boolean }) {
  return (
    <ol className="ih-journey" aria-label="مسار الترشيح">
      {JOURNEY.map((label, i) => {
        const state = (allDone && i <= idx) || i < idx ? 'done' : i === idx ? 'current' : 'todo';
        return (
          <li key={label} className={`ih-journey__step is-${state}`} aria-current={state === 'current' ? 'step' : undefined}>
            <span className="ih-journey__dot">{state === 'done' ? <Icon name="check" size={12} /> : i + 1}</span>
            <span className="ih-journey__label">{label}</span>
          </li>
        );
      })}
    </ol>
  );
}

export default function ShortlistIndex({ campaign, version, items, candidates, filters, canEdit, budgetPct, overBudget, versions, candidatePool, conversion, documents }: Props) {
  // إتاحة «قاعدة المؤثرين» من القدرات المشتركة (نفس بوّابة الاكتشاف) — لا رابط لسطح غير مستحقّ
  const navCan = (usePage().props as { nav?: { can?: Record<string, boolean> } }).nav?.can ?? {};
  const hasCreatorDatabase = Boolean(navCan.creator_database);
  const [proposalOpen, setProposalOpen] = useState(false);
  const [tab, setTab] = useState(canEdit ? 'list' : 'list');
  useEffect(() => {
    const applyHash = () => {
      const h = window.location.hash.replace('#','');
      if (['list','candidates','versions'].includes(h)) setTab(h);
    };
    applyHash();
    window.addEventListener('hashchange', applyHash);
    return () => window.removeEventListener('hashchange', applyHash);
  }, []);
  const goTab = (k: string) => { setTab(k); window.history.replaceState(null,'', k==='list'? window.location.pathname : `#${k}`); };
  const [q, setQ] = useState(filters.q ?? '');
  const [platform, setPlatform] = useState(filters.platform ?? '');
  const [busy, setBusy] = useState(false);

  const primary = items.filter((i) => !i.isBackup);
  const backups = items.filter((i) => i.isBackup);
  const journey = journeyState(version.status, primary.length, conversion);
  const base = u(`/campaigns/${campaign.id}/shortlist`);

  const post = (url: string, data: Record<string, string | number | boolean> = {}) => {
    setBusy(true);
    router.post(url, data, { preserveScroll: true, onFinish: () => setBusy(false) });
  };
  const search = () => router.get(base, { q: q || undefined, platform: platform || undefined }, { preserveState: true, preserveScroll: true, replace: true });

  return (
    <AppShell heading="الترشيحات">
      <Head title={`ترشيح · ${campaign.name}`} />

      <WorkspaceHeader
        eyebrow={`ترشيح · حملة ${campaign.number}`}
        title={campaign.name}
        statusTone={version.statusTone} statusLabel={`${version.statusLabel} · إصدار ${version.number}`}
        back={u(`/campaigns/${campaign.id}`)} backLabel="الحملة"
        meta={[
          ['العميل', campaign.client ?? '—'], ['العلامة', campaign.brand ?? '—'],
          ...(version.submittedAt ? [['أُرسل', version.submittedAt] as [string, string]] : []),
          ...(version.decidedAt ? [['قرار العميل', version.decidedAt] as [string, string]] : []),
        ]}
        actions={
          <>
            <button onClick={() => setProposalOpen(true)} className="btn btn-sm btn-outline" title="معاينة مقترح PDF آمن للعميل">
              <Icon name="file-text" size={13} /> مقترح للعميل{documents.proposal.stale && <span style={{ width: 7, height: 7, borderRadius: '50%', background: 'var(--ih-warning-ink, #B54708)', display: 'inline-block', marginInlineStart: 5 }} />}
            </button>
            <OverflowMenu label="تصدير" items={[
              { label: 'Excel (‎.xlsx‎)', icon: 'external-link', href: u(`${base}/export?format=xlsx`), download: true },
              { label: 'CSV', icon: 'external-link', href: u(`${base}/export?format=csv`), download: true },
              { label: 'PDF', icon: 'file-text', href: u(`${base}/export?format=pdf`), download: true },
            ]} />
            {canEdit ? (
              <>
                <button disabled={busy || primary.length === 0} onClick={() => post(`${base}/submit`)} className="btn btn-sm">إرسال لاعتماد العميل</button>
                {primary.length === 0 && (
                  <span style={{ fontSize: '.74rem', color: 'var(--ih-warning-ink)' }}>
                    أضِف مرشّحًا أساسيًا واحدًا على الأقل ليُصبح الإرسال متاحًا
                  </span>
                )}
              </>
            ) : version.status !== 'draft' ? (
              <>
                {conversion.canConvert && (
                  <button disabled={busy} onClick={() => post(`${base}/convert`)} className="btn btn-sm" title="إنشاء تعاون تنفيذ لكل مؤثّر اعتمده العميل">
                    <Icon name="clipboard-check" size={13} /> تحويل المعتمَدين للتنفيذ ({conversion.eligible})
                  </button>
                )}
                {!conversion.canConvert && conversion.converted > 0 && conversion.eligible === 0 && (
                  <span className="badge" style={{ background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)' }}>
                    <Icon name="clipboard-check" size={12} /> حُوِّل {conversion.converted} معتمَد للتنفيذ
                  </span>
                )}
                {!conversion.canConvert && conversion.blockedReason && conversion.approved > 0 && (
                  <span style={{ fontSize: '.74rem', color: 'var(--ih-warning-ink)' }}>{conversion.blockedReason}</span>
                )}
                <button disabled={busy} onClick={() => post(`${base}/revise`)} className="btn btn-sm btn-outline">إنشاء إصدار جديد</button>
              </>
            ) : null}
          </>
        }
      />

      {/* مسار الترشيح — أين نحن + المطلوب الآن، من الحالة الحقيقية */}
      <div className="ih-journey-wrap">
        <JourneyStepper idx={journey.idx} allDone={journey.done} />
        <div className="ih-journey-next"><Icon name="rocket" size={14} /> <strong>المطلوب الآن:</strong> {journey.next}</div>
      </div>

      <SummaryStrip
        items={[
          { label: 'الأساسيون', value: primary.length.toLocaleString('en-US'), icon: 'users', tone: primary.length ? 'success' : undefined },
          { label: 'الاحتياط', value: backups.length.toLocaleString('en-US'), icon: 'user-plus' },
          { label: 'الملتزم به', value: money(campaign.committedMinor), icon: 'wallet', tone: overBudget ? 'danger' : undefined },
          { label: 'الميزانية', value: campaign.budgetMinor ? money(campaign.budgetMinor) : '—', icon: 'gauge' },
        ]}
      />

      {campaign.budgetMinor > 0 && (
        <div className="ih-sec" style={{ marginBottom: '1.2rem' }}>
          <div className="ih-sec__head">
            <span className="ih-sec__title"><Icon name="wallet" size={16} /> الميزانية مقابل الملتزم به (الأساسيون)</span>
            <span style={{ fontSize: '.82rem', color: overBudget ? 'var(--ih-danger-ink)' : 'var(--ih-text-muted)', direction: 'ltr', fontWeight: 600 }}>
              {money(campaign.committedMinor)} / {money(campaign.budgetMinor)} ({budgetPct}٪)
            </span>
          </div>
          <div className="ih-sec__body"><Bar pct={budgetPct} over={overBudget} /></div>
          {overBudget && <div className="ih-sec__body" style={{ color: 'var(--ih-danger-ink)', fontSize: '.8rem' }}><Icon name="activity" size={13} /> التكلفة المقترحة تتجاوز ميزانية الحملة.</div>}
        </div>
      )}

      <WorkTabs active={tab} onChange={goTab} tabs={([
        { key: 'list', label: 'القائمة', icon: 'clipboard-check', count: items.length },
        ...(canEdit ? [{ key: 'candidates', label: 'المرشّحون', icon: 'users', count: candidates.length }] : []),
        { key: 'versions', label: 'الإصدارات', icon: 'list-checks', count: versions.length },
      ] as WorkTab[])} />

      {tab === 'versions' && (
        <Sec title="الإصدارات" icon="list-checks">
          <DataTable head={['الإصدار', 'المرشّحون', 'أُرسل', 'قرار العميل', 'الحالة']}>
            {versions.map((v) => (
              <tr key={v.number} style={v.isCurrent ? { background: 'var(--ih-primary-soft)' } : undefined}>
                <td style={{ fontWeight: 700, direction: 'ltr' }}>v{v.number}{v.isCurrent && <span className="ih-tag" style={{ marginInlineStart: '.4rem', fontSize: '.62rem' }}>الحالي</span>}</td>
                <td style={{ direction: 'ltr' }}>{v.items}</td>
                <td style={{ direction: 'ltr', fontSize: '.8rem', color: 'var(--ih-text-muted)' }}>{v.submittedAt ?? '—'}</td>
                <td style={{ direction: 'ltr', fontSize: '.8rem', color: 'var(--ih-text-muted)' }}>{v.decidedAt ?? '—'}</td>
                <td><StatusBadge tone={v.statusTone} label={v.statusLabel} /></td>
              </tr>
            ))}
          </DataTable>
        </Sec>
      )}

      {/* القائمة الحالية */}
      {/* نظرة قرار العميل — تظهر بعد الإرسال: تُجيب فورًا كم اعتمد/طلب بديلًا/رفض/بقي */}
      {tab === 'list' && version.status !== 'draft' && items.length > 0 && (() => {
        const cnt = (d: string) => items.filter((i) => i.decision === d).length;
        const decSegs = [
          { label: 'معتمَد', value: cnt('approved'), color: 'var(--ih-success-700, #067647)' },
          { label: 'طلب بديلًا', value: cnt('needs_alternative'), color: 'var(--ih-warning-ink, #B54708)' },
          { label: 'مرفوض', value: cnt('rejected'), color: 'var(--ih-danger-ink, #B42318)' },
          { label: 'بانتظار العميل', value: cnt('pending'), color: 'var(--ih-primary-300, #B4A8FF)' },
        ];
        const decided = items.length - cnt('pending');
        const decidedPct = items.length ? Math.round((decided / items.length) * 100) : 0;
        return (
          <div className="card" style={{ padding: '1.1rem 1.2rem', marginBottom: '1rem', display: 'flex', alignItems: 'center', gap: '1.4rem', flexWrap: 'wrap' }}>
            <Donut segments={decSegs} size={124} centerValue={`${decided}/${items.length}`} centerLabel="حُسم" ariaLabel={decSegs.map((s) => `${s.label}: ${s.value}`).join('، ')} />
            <div style={{ flex: 1, minWidth: 190, display: 'grid', gap: '.7rem' }}>
              <div style={{ fontWeight: 800, fontSize: '.95rem' }}>قرار العميل — {decidedPct}٪ مكتمل</div>
              <Legend segments={decSegs} />
            </div>
          </div>
        );
      })()}

      {tab === 'list' && (
      <Sec title={`القائمة الحالية — إصدار ${version.number}`} icon="clipboard-check">
        {items.length === 0 ? (
          <div style={{ padding: '1.6rem', textAlign: 'center', color: 'var(--ih-text-muted)', fontSize: '.86rem' }}>لا مؤثرين في هذا الإصدار بعد — أضِفهم من قائمة المرشّحين أدناه.</div>
        ) : (
          <DataTable head={['المؤثر', 'المنصّة', 'الملاءمة', 'الأجر المقترح', 'النوع', 'قرار العميل', ...(canEdit ? ['—'] : [])]}>
            {items.map((it) => (
              <tr key={it.id}>
                <td>
                  <div style={{ fontWeight: 600 }}>{it.creator}</div>
                  {it.handle && <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{it.handle}</div>}
                </td>
                <td style={{ direction: 'ltr' }}>{it.platform ?? '—'}</td>
                <td><ScorePill score={it.score} /></td>
                <td style={{ direction: 'ltr', fontWeight: 600 }}>{money(it.feeMinor)}</td>
                <td>{it.isBackup ? <span className="ih-tag">احتياط</span> : <span className="ih-tag" style={{ background: 'var(--ih-primary-100)', color: 'var(--ih-primary-800)' }}>أساسي</span>}</td>
                <td><StatusBadge tone={it.decisionTone} label={it.decisionLabel} /></td>
                {canEdit && (
                  <td><button disabled={busy} onClick={() => post(`${base}/items/${it.id}/remove`)} className="btn btn-xs btn-danger">إزالة</button></td>
                )}
              </tr>
            ))}
          </DataTable>
        )}
      </Sec>
      )}

      {/* المرشّحون */}
      {/* التبويب شيء والصلاحية شيء: كان فرع «لا يمكن التعديل» هو else لفحص
          التبويب، فتُخبر مسوّدةً قابلة للتحرير أنها غير قابلة له لمجرّد أن
          المستخدم واقف على تبويب آخر. */}
      {tab === 'candidates' && canEdit ? (
        <Sec title="المرشّحون" icon="users">
          {hasCreatorDatabase && (
            <div style={{ marginBottom: '.9rem', display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: '.6rem', flexWrap: 'wrap' }}>
              <span style={{ fontSize: '.82rem', color: 'var(--ih-text-muted)' }}>اكتشف مؤثرين جددًا وأضِفهم مباشرةً لهذه الحملة (أساسي/احتياط بنقرة).</span>
              <a href={u(`/creator-database?campaign=${campaign.id}`)} className="btn btn-sm btn-primary">
                <Icon name="radar" size={14} /> اكتشف من قاعدة المؤثرين
              </a>
            </div>
          )}
          <div className="ih-filterbar" style={{ marginBottom: '1rem' }}>
            <div className="ih-search">
              <Icon name="search" size={15} />
              <input value={q} onChange={(e) => setQ(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && search()} placeholder="ابحث بالاسم أو المعرّف…" />
            </div>
            <select value={platform} onChange={(e) => { setPlatform(e.target.value); }} className="field" style={{ maxWidth: 150 }}>
              <option value="">كل المنصّات</option>
              <option value="instagram">Instagram</option>
              <option value="tiktok">TikTok</option>
              <option value="snapchat">Snapchat</option>
              <option value="youtube">YouTube</option>
              <option value="x">X</option>
            </select>
            <button onClick={search} className="btn btn-sm">بحث</button>
          </div>

          {candidates.length === 0 ? (
            <div style={{ padding: '1.4rem', textAlign: 'center', color: 'var(--ih-text-muted)', fontSize: '.86rem' }}>
              {candidatePool.active === 0 && candidatePool.inactive > 0 ? (
                <>
                  {/* السبب الحقيقي: المبدعون موجودون وغير نشطين. «لا نتائج» وحدها
                      تُرسل المستخدم يبحث في الفلاتر عن خلل ليس فيها. */}
                  <div>لا مبدعين نشطين — لديك {candidatePool.inactive} مبدعًا بحالة غير نشطة.</div>
                  <div style={{ marginBlockStart: '.3rem' }}>الترشيح يعرض النشطين فقط.</div>
                  <a href={u('/creators')} className="btn btn-sm btn-outline" style={{ marginBlockStart: '.7rem' }}>
                    فعّل مبدعًا
                  </a>
                </>
              ) : candidatePool.active === 0 ? (
                <>
                  <div>لا مبدعين في قاعدتك بعد.</div>
                  <a href={u('/creators')} className="btn btn-sm" style={{ marginBlockStart: '.7rem' }}>أضِف مبدعًا</a>
                </>
              ) : (
                'لا مرشّحين مطابقين — عدّل البحث أو الفلاتر.'
              )}
            </div>
          ) : (
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(300px, 1fr))', gap: '.9rem' }}>
              {candidates.map((c) => (
                <div key={c.id} className="card" style={{ padding: '.9rem 1rem' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '.5rem', marginBottom: '.5rem' }}>
                    <div style={{ display: 'flex', gap: '.55rem', alignItems: 'center', minWidth: 0 }}>
                      <span className="ih-idc__av" style={{ width: 40, height: 40, flexShrink: 0, overflow: 'hidden' }} aria-hidden={!!c.avatar}>
                        {c.avatar ? <img src={c.avatar} alt="" style={{ width: '100%', height: '100%', objectFit: 'cover' }} /> : c.name.slice(0, 1)}
                      </span>
                      <div style={{ minWidth: 0 }}>
                        <div style={{ fontWeight: 700, display: 'flex', alignItems: 'center', gap: '.35rem' }}>
                          <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{c.name}</span>
                          {c.verified && <Icon name="shield-check" size={14} />}
                        </div>
                        <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', direction: 'ltr', textAlign: 'right' }}>
                          {c.handle ?? '—'}{c.creatorType ? <span style={{ marginInlineStart: 6 }} className="ih-tag">{c.creatorType}</span> : null}
                        </div>
                      </div>
                    </div>
                    <ScorePill score={c.score} />
                  </div>
                  <div style={{ display: 'flex', gap: '.8rem', fontSize: '.76rem', color: 'var(--ih-text-muted)', marginBottom: '.6rem', direction: 'ltr', justifyContent: 'flex-end', alignItems: 'center' }}>
                    <span>{c.platform ?? '—'}</span>
                    <span>{fmt(c.followers)} متابع</span>
                    {c.feeMinor > 0
                      ? <span style={{ fontWeight: 600, color: 'var(--ih-text)' }}>{money(c.feeMinor)}</span>
                      : <span className="ih-tag" style={{ color: 'var(--ih-warning-ink)' }}>السعر غير مضاف</span>}
                  </div>
                  <div style={{ display: 'flex', gap: '.3rem', flexWrap: 'wrap', marginBottom: '.55rem' }}>
                    {c.categories.length > 0
                      ? c.categories.slice(0, 4).map((cat, i) => <span key={i} className="ih-tag" style={{ fontSize: '.66rem' }}>{cat}</span>)
                      : <span style={{ fontSize: '.68rem', color: 'var(--ih-text-muted)' }}>بيانات التصنيف غير مكتملة</span>}
                  </div>
                  {c.reasons.length > 0 && (
                    <div style={{ display: 'flex', gap: '.3rem', flexWrap: 'wrap', marginBottom: c.flags.length ? '.35rem' : '.7rem' }}>
                      {c.reasons.map((r, i) => <span key={i} className="ih-tag" style={{ fontSize: '.66rem' }}>{r}</span>)}
                    </div>
                  )}
                  {c.flags.length > 0 && (
                    <div style={{ display: 'flex', gap: '.3rem', flexWrap: 'wrap', marginBottom: '.7rem' }} title="تنبيهات صادقة من بيانات ناقصة">
                      {c.flags.map((f, i) => (
                        <span key={i} className="ih-tag" style={{ fontSize: '.66rem', background: 'var(--ih-warning-soft, #FEF3C7)', color: 'var(--ih-warning-ink, #B54708)' }}>⚠ {f}</span>
                      ))}
                    </div>
                  )}
                  <div style={{ display: 'flex', gap: '.4rem' }}>
                    <button disabled={busy} onClick={() => post(`${base}/add`, { creator_id: c.id, backup: false })} className="btn btn-xs" style={{ flex: 1 }}>أساسي +</button>
                    <button disabled={busy} onClick={() => post(`${base}/add`, { creator_id: c.id, backup: true })} className="btn btn-xs btn-outline" style={{ flex: 1 }}>احتياط +</button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </Sec>
      ) : null}

      {!canEdit && (
        <div className="card" style={{ padding: '.9rem 1.1rem', borderInlineStart: '3px solid var(--ih-info)', background: 'var(--ih-info-soft)', color: 'var(--ih-info-ink)', fontSize: '.84rem' }}>
          <Icon name="clipboard-check" size={15} /> هذا الإصدار {version.statusLabel} — لا يمكن تعديله. لإجراء تغييرات أنشئ إصدارًا جديدًا (يَنسخ العناصر الحالية ويحفظ التاريخ).
        </div>
      )}
      <PdfPreviewModal doc={documents.proposal} open={proposalOpen} onClose={() => setProposalOpen(false)} />
    </AppShell>
  );
}

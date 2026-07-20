import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Field, Kpi, ListHead, StatusBadge } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { u } from '@/lib/href';

interface PayoutRow {
  id: number; number: string; creator: string | null; amountMinor: number; currency: string;
  ibanLast4: string | null; dueDate: string | null; status: string; statusLabel: string; statusTone: string;
  campaign: string | null; paidAt: string | null; overdue: boolean; bucket: string;
}
interface Summary {
  total: number; openMinor: number; openCount: number; readyMinor: number; readyCount: number;
  paidMinor: number; pending: number; waiting: number; failed: number; paid: number;
}
interface Filters { q?: string; seg?: string }
interface CreatorOption { id: number; name: string }
interface Props {
  payouts: Paginated<PayoutRow>; filters: Filters; summary: Summary;
  canCreate: boolean; creatorOptions: CreatorOption[];
}

const LBL: React.CSSProperties = { fontSize: '.8rem', fontWeight: 600, display: 'block', marginBottom: '.3rem' };

function kfmt(minor: number): string {
  const v = minor / 100;
  if (v >= 1_000_000) return (v / 1_000_000).toFixed(1) + 'M';
  if (v >= 1000) return Math.round(v / 1000) + 'K';
  return v.toLocaleString('en-US');
}
function clean(obj: Record<string, unknown>): Record<string, string> {
  const out: Record<string, string> = {};
  for (const [k, v] of Object.entries(obj)) if (v !== '' && v !== null && v !== undefined) out[k] = String(v);
  return out;
}

export default function PayoutsIndex({ payouts, filters, summary, canCreate, creatorOptions }: Props) {
  const [createOpen, setCreateOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  // المبلغ يُدخل بالريال ويُحوَّل إلى هللات عند الإرسال — المصدر يبقى وحدات صغرى صحيحة
  const [form, setForm] = useState({ creator_id: '', amount: '', description: '', due_date: '' });

  const submitCreate = () => {
    const riyals = Number(form.amount);
    if (!form.creator_id || !Number.isFinite(riyals) || riyals <= 0) return;
    setBusy(true);
    router.post(u('/payouts'), {
      creator_id: form.creator_id,
      amount_minor: Math.round(riyals * 100),
      description: form.description,
      due_date: form.due_date,
    }, {
      onFinish: () => setBusy(false),
      onError: (e) => setErrors(e as Record<string, string>),
      onSuccess: () => { setCreateOpen(false); setErrors({}); },
    });
  };
  const [q, setQ] = useState(filters.q ?? '');
  const first = useRef(true);
  useEffect(() => {
    if (first.current) { first.current = false; return; }
    const t = setTimeout(() => router.get(u('/payouts'), clean({ ...filters, q }), { preserveState: true, replace: true, preserveScroll: true }), 350);
    return () => clearTimeout(t);
  }, [q]);
  const update = (patch: Filters) => router.get(u('/payouts'), clean({ ...filters, ...patch }), { preserveState: true, replace: true, preserveScroll: true });

  const seg = filters.seg ?? '';
  const hasFilters = !!(filters.q || seg);
  const segments: [string, string, number][] = [
    ['', 'الكل', summary.total], ['open', 'مفتوحة', summary.openCount], ['ready', 'جاهزة للصرف', summary.readyCount],
    ['pending', 'قيد الاعتماد', summary.pending], ['waiting_for_provider', 'بانتظار المزوّد', summary.waiting],
    ['paid', 'مدفوعة', summary.paid], ['failed', 'فاشلة', summary.failed],
  ];

  return (
    <AppShell heading="المستحقات">
      <Head title="المستحقات" />

      <ListHead eyebrow="المالية" title="المستحقات"
        sub="مستحقات المبدعين: اعتماد، جدولة، وتسجيل الصرف — النظام لا ينفّذ تحويلات (تسجيل يدوي)"
        actions={canCreate ? <button onClick={() => setCreateOpen(true)} className="btn btn-sm btn-primary"><Icon name="plus" size={15} /> مستحق جديد</button> : undefined} />

      <div className="ih-kpis">
        <Kpi label="مستحق مفتوح" icon="wallet" tone="warning" value={<>{kfmt(summary.openMinor)} <small>ر.س</small></>} sub={`${summary.openCount} دفعة`} />
        <Kpi label="جاهز للصرف" icon="wallet" tone="accent" value={<>{kfmt(summary.readyMinor)} <small>ر.س</small></>} sub={`${summary.readyCount} معتمدة/مجدولة`} />
        <Kpi label="مدفوع" icon="shield-check" tone="success" value={<>{kfmt(summary.paidMinor)} <small>ر.س</small></>} sub={`${summary.paid} دفعة`} />
        <Kpi label="بانتظار المزوّد" icon="clipboard-check" value={summary.waiting.toLocaleString('en-US')} sub={`${summary.failed} فاشلة`} />
      </div>

      <div className="ih-chips" style={{ marginBottom: '.9rem', overflowX: 'auto', paddingBottom: '.2rem', flexWrap: 'nowrap' }}>
        {segments.map(([key, label, count]) => (
          <button key={key} onClick={() => update({ seg: key })} className={`ih-chip${seg === key ? ' active' : ''}`}>{label} <span className="ih-chip__count">{count}</span></button>
        ))}
      </div>

      <div className="ih-filterbar">
        <label className="ih-search"><Icon name="search" size={16} />
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="ابحث برقم المستحق أو المبدع…" />
        </label>
      </div>

      {payouts.data.length === 0 ? (
        <div className="ih-dt-wrap"><div className="ih-empty">
          <span className="ih-empty__icon" style={{ background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)' }}><Icon name="wallet" size={26} /></span>
          {hasFilters ? (
            <><div className="ih-empty__title">لا مستحقات مطابقة</div><div className="ih-empty__text">لا نتائج للبحث أو الشريحة الحالية.</div><a href={u("/payouts")} className="btn btn-sm btn-outline">مسح الفلاتر</a></>
          ) : (
            <><div className="ih-empty__title">لا مستحقات بعد</div><div className="ih-empty__text">تظهر هنا مستحقات المبدعين عند إنشائها.</div></>
          )}
        </div></div>
      ) : (
        <>
          {/* صرف المستحقات — مقسّم حسب جاهزية الصرف، والمتأخر ظاهر */}
          <div className="ih-only-desktop">
            {([['ready', 'جاهز للصرف'], ['pending', 'بانتظار الاعتماد'], ['paid', 'مدفوع'], ['closed', 'مغلق']] as [string, string][]).map(([bk, label]) => {
              const grp = payouts.data.filter((p) => p.bucket === bk);
              if (grp.length === 0) return null;
              const total = grp.reduce((t, p) => t + p.amountMinor, 0);
              return (
                <div key={bk} style={{ marginBottom: '1.2rem' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '.5rem', marginBottom: '.55rem' }}>
                    <span style={{ fontWeight: 700, fontSize: '.88rem' }}>{label}</span>
                    <span className="ih-pipe__count">{grp.length}</span>
                    <span style={{ marginInlineStart: 'auto', fontWeight: 700, direction: 'ltr', fontSize: '.86rem' }}>{kfmt(total)} ر.س</span>
                  </div>
                  <div className="ih-triage">
                    {grp.map((p) => (
                      <a key={p.id} href={u(`/payouts/${p.id}`)} className={`ih-trow${p.overdue ? ' ih-trow--overdue' : bk === 'paid' ? ' ih-trow--done' : bk === 'ready' ? ' ih-trow--new' : ''}`}>
                        <div style={{ minWidth: 0, flex: 1 }}>
                          <div style={{ fontWeight: 650, fontSize: '.87rem' }}>{p.creator ?? '—'}</div>
                          <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)' }}>
                            <span style={{ direction: 'ltr' }}>{p.number}</span>{p.campaign ? ` · ${p.campaign}` : ''}
                          </div>
                        </div>
                        {p.dueDate && (
                          <span style={{ fontSize: '.73rem', direction: 'ltr', color: p.overdue ? 'var(--ih-danger-ink)' : 'var(--ih-text-muted)', fontWeight: p.overdue ? 700 : 400, flexShrink: 0 }}>
                            {p.overdue ? 'تأخر ' : ''}{p.dueDate}
                          </span>
                        )}
                        <span style={{ fontWeight: 700, direction: 'ltr', fontSize: '.88rem', flexShrink: 0 }}>{kfmt(p.amountMinor)} ر.س</span>
                        <StatusBadge tone={p.statusTone} label={p.statusLabel} />
                      </a>
                    ))}
                  </div>
                </div>
              );
            })}
            <div className="ih-dt__foot"><span>{payouts.total} مستحق</span><Pagination links={payouts.links} /></div>
          </div>

          <div className="ih-only-mobile">
            <div className="ih-mlist">
              {payouts.data.map((p) => (
                <a key={p.id} href={u(`/payouts/${p.id}`)} className="ih-mcard">
                  <div style={{ display: 'flex', alignItems: 'center', gap: '.6rem' }}>
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div className="ih-idc__name">{p.creator ?? '—'}</div>
                      <div className="ih-idc__sub" style={{ direction: 'ltr', textAlign: 'right' }}>{p.number}</div>
                    </div>
                    <StatusBadge tone={p.statusTone} label={p.statusLabel} />
                  </div>
                  <div style={{ display: 'flex', gap: '.6rem', marginTop: '.7rem', fontSize: '.82rem' }}>
                    <span style={{ fontWeight: 800, direction: 'ltr', display: 'inline-block' }}>{kfmt(p.amountMinor)} {p.currency}</span>
                    <span style={{ marginInlineStart: 'auto', color: 'var(--ih-text-muted)' }}>{p.ibanLast4 ? `•••• ${p.ibanLast4}` : ''}</span>
                  </div>
                </a>
              ))}
            </div>
            <div style={{ marginTop: '1rem' }}><Pagination links={payouts.links} /></div>
          </div>
        </>
      )}
      {createOpen && (
        <div className="modal-backdrop" onClick={(e) => e.target === e.currentTarget && !busy && setCreateOpen(false)}>
          <div className="modal" style={{ padding: '1.3rem', maxWidth: 520 }}>
            <h3 style={{ fontWeight: 800, margin: '0 0 .3rem' }}>مستحق جديد</h3>
            <p style={{ margin: '0 0 1rem', fontSize: '.78rem', color: 'var(--ih-text-muted)' }}>
              يُسجَّل المستحق للمتابعة والاعتماد فقط — لا ينفّذ النظام أي تحويل مالي.
            </p>
            <div style={{ display: 'grid', gap: '.8rem' }}>
              <Field label="المبدع" labelStyle={LBL}>
                <select value={form.creator_id} onChange={(e) => setForm({ ...form, creator_id: e.target.value })} className="field" style={{ width: '100%' }} autoFocus>
                  <option value="">— اختر —</option>
                  {creatorOptions.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
                {errors.creator_id && <div style={{ color: 'var(--ih-danger-ink)', fontSize: '.76rem', marginTop: '.3rem' }}>{errors.creator_id}</div>}
              </Field>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
                <Field label="المبلغ (ر.س)" labelStyle={LBL}>
                  <input type="number" min={0} step="0.01" value={form.amount} onChange={(e) => setForm({ ...form, amount: e.target.value })}
                    className="field" style={{ width: '100%', direction: 'ltr' }} placeholder="13500" />
                  {errors.amount_minor && <div style={{ color: 'var(--ih-danger-ink)', fontSize: '.76rem', marginTop: '.3rem' }}>{errors.amount_minor}</div>}
                </Field>
                <Field label="تاريخ الاستحقاق" labelStyle={LBL}>
                  <input type="date" value={form.due_date} onChange={(e) => setForm({ ...form, due_date: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} />
                </Field>
              </div>
              <Field label="الوصف" labelStyle={LBL}>
                <input value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })}
                  className="field" style={{ width: '100%' }} placeholder="أجر تعاون حملة…" />
              </Field>
              {errors.payout && <div style={{ color: 'var(--ih-danger-ink)', fontSize: '.8rem' }}>{errors.payout}</div>}
            </div>
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button disabled={busy || !form.creator_id || !form.amount} onClick={submitCreate} className="btn btn-primary">إنشاء المستحق</button>
              <button disabled={busy} onClick={() => setCreateOpen(false)} className="btn btn-ghost">إلغاء</button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}

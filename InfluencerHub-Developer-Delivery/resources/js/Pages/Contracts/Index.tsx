import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Field, Kpi, ListHead, StatusBadge } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { u } from '@/lib/href';

interface ContractRow {
  id: number; number: string; title: string; party: string | null; partyType: string;
  valueMinor: number; currency: string; endDate: string | null; status: string; statusLabel: string; statusTone: string;
  sentAt: string | null; signedAt: string | null; startDate: string | null; expiringSoon: boolean; expired: boolean; bucket: string;
}
interface Summary {
  total: number; active: number; sent: number; signed: number; draft: number;
  completed: number; terminated: number; cancelled: number; activeValueMinor: number;
}
interface Filters { q?: string; seg?: string }
interface PartyOption { id: number; name: string }
interface Props {
  contracts: Paginated<ContractRow>; filters: Filters; summary: Summary;
  canCreate: boolean; creatorOptions: PartyOption[]; clientOptions: PartyOption[];
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

export default function ContractsIndex({ contracts, filters, summary, canCreate, creatorOptions, clientOptions }: Props) {
  const [createOpen, setCreateOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [form, setForm] = useState({
    party_type: 'creator', creator_id: '', client_id: '', title: '',
    value: '', start_date: '', end_date: '', terms: '',
  });
  const parties = form.party_type === 'creator' ? creatorOptions : clientOptions;
  const partyId = form.party_type === 'creator' ? form.creator_id : form.client_id;

  const submitCreate = () => {
    if (!partyId || !form.title.trim()) return;
    const riyals = Number(form.value || 0);
    setBusy(true);
    router.post(u('/contracts'), {
      party_type: form.party_type,
      creator_id: form.party_type === 'creator' ? form.creator_id : null,
      client_id: form.party_type === 'client' ? form.client_id : null,
      title: form.title, terms: form.terms,
      value_minor: Number.isFinite(riyals) ? Math.round(riyals * 100) : 0,
      start_date: form.start_date, end_date: form.end_date,
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
    const t = setTimeout(() => router.get(u('/contracts'), clean({ ...filters, q }), { preserveState: true, replace: true, preserveScroll: true }), 350);
    return () => clearTimeout(t);
  }, [q]);
  const update = (patch: Filters) => router.get(u('/contracts'), clean({ ...filters, ...patch }), { preserveState: true, replace: true, preserveScroll: true });

  const seg = filters.seg ?? '';
  const hasFilters = !!(filters.q || seg);
  const segments: [string, string, number][] = [
    ['', 'الكل', summary.total], ['draft', 'مسودة', summary.draft], ['sent', 'مُرسَل', summary.sent],
    ['signed', 'مُوقَّع', summary.signed], ['active', 'نافذ', summary.active], ['completed', 'مكتمل', summary.completed],
    ['terminated', 'مُنهى', summary.terminated], ['cancelled', 'ملغى', summary.cancelled],
  ];

  return (
    <AppShell heading="العقود">
      <Head title="العقود" />

      <ListHead eyebrow="التشغيل" title="العقود"
        sub="عقود العملاء والمبدعين: إصدار، إرسال، تفعيل، ومتابعة القيمة والمدة"
        actions={canCreate ? <button onClick={() => setCreateOpen(true)} className="btn btn-sm btn-primary"><Icon name="plus" size={15} /> عقد جديد</button> : undefined} />

      <div className="ih-kpis">
        <Kpi label="عقود نافذة" icon="file-text" tone="accent" value={summary.active.toLocaleString('en-US')} sub={`${summary.signed} مُوقَّع`} />
        <Kpi label="بانتظار التوقيع" icon="clipboard-check" tone={summary.sent ? 'warning' : undefined} value={summary.sent.toLocaleString('en-US')} sub="مُرسَلة للطرف المقابل" />
        <Kpi label="قيمة العقود النافذة" icon="wallet" tone="success" value={<>{kfmt(summary.activeValueMinor)} <small>ر.س</small></>} sub="موقّعة/نافذة" />
        <Kpi label="مكتملة" icon="shield-check" value={summary.completed.toLocaleString('en-US')} sub={`${summary.draft} مسودة`} />
      </div>

      <div className="ih-chips" style={{ marginBottom: '.9rem', overflowX: 'auto', paddingBottom: '.2rem', flexWrap: 'nowrap' }}>
        {segments.map(([key, label, count]) => (
          <button key={key} onClick={() => update({ seg: key })} className={`ih-chip${seg === key ? ' active' : ''}`}>{label} <span className="ih-chip__count">{count}</span></button>
        ))}
      </div>

      <div className="ih-filterbar">
        <label className="ih-search"><Icon name="search" size={16} />
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="ابحث بعنوان العقد أو الرقم…" />
        </label>
      </div>

      {contracts.data.length === 0 ? (
        <div className="ih-dt-wrap"><div className="ih-empty">
          <span className="ih-empty__icon"><Icon name="file-text" size={26} /></span>
          {hasFilters ? (
            <><div className="ih-empty__title">لا عقود مطابقة</div><div className="ih-empty__text">لا نتائج للبحث أو الشريحة الحالية.</div><a href={u("/contracts")} className="btn btn-sm btn-outline">مسح الفلاتر</a></>
          ) : (
            <><div className="ih-empty__title">لا عقود بعد</div><div className="ih-empty__text">تظهر هنا العقود الصادرة للعملاء والمبدعين.</div></>
          )}
        </div></div>
      ) : (
        <>
          {/* مساحة عقود — مقسّمة حسب مرحلة التوقيع مع تنبيهات الانتهاء */}
          <div className="ih-only-desktop">
            {([['awaiting', 'بانتظار التوقيع'], ['active', 'سارية'], ['draft', 'مسودات'], ['closed', 'منتهية']] as [string, string][]).map(([bk, label]) => {
              const grp = contracts.data.filter((c) => c.bucket === bk);
              if (grp.length === 0) return null;
              return (
                <div key={bk} style={{ marginBottom: '1.2rem' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '.5rem', marginBottom: '.6rem' }}>
                    <span style={{ fontWeight: 700, fontSize: '.88rem' }}>{label}</span>
                    <span className="ih-pipe__count">{grp.length}</span>
                  </div>
                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))', gap: '.8rem' }}>
                    {grp.map((c) => (
                      <a key={c.id} href={u(`/contracts/${c.id}`)} className="ih-wcard">
                        <div style={{ display: 'flex', justifyContent: 'space-between', gap: '.5rem', alignItems: 'flex-start' }}>
                          <div style={{ minWidth: 0 }}>
                            <div className="ih-wcard__title">{c.title}</div>
                            <div className="ih-wcard__meta">{c.party ?? '—'} · {c.partyType} · <span style={{ direction: 'ltr' }}>{c.number}</span></div>
                          </div>
                          <div style={{ textAlign: 'end', flexShrink: 0 }}>
                            <div style={{ fontWeight: 700, direction: 'ltr', fontSize: '.86rem' }}>{kfmt(c.valueMinor)} ر.س</div>
                            <StatusBadge tone={c.statusTone} label={c.statusLabel} />
                          </div>
                        </div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '.4rem', marginTop: '.6rem', fontSize: '.71rem', color: 'var(--ih-text-muted)', flexWrap: 'wrap' }}>
                          <span style={{ color: c.sentAt ? 'var(--ih-success-ink)' : undefined, fontWeight: c.sentAt ? 700 : 400 }}>أُرسل {c.sentAt ?? '—'}</span>
                          <span style={{ opacity: .4 }}>←</span>
                          <span style={{ color: c.signedAt ? 'var(--ih-success-ink)' : undefined, fontWeight: c.signedAt ? 700 : 400 }}>وُقّع {c.signedAt ?? '—'}</span>
                          {c.endDate && <span style={{ marginInlineStart: 'auto', direction: 'ltr' }}>ينتهي {c.endDate}</span>}
                        </div>
                        {c.expiringSoon && <div className="ih-wcard__risk" style={{ background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)' }}>ينتهي خلال 30 يومًا</div>}
                        {c.expired && <div className="ih-wcard__risk">منتهٍ</div>}
                      </a>
                    ))}
                  </div>
                </div>
              );
            })}
            <div className="ih-dt__foot"><span>{contracts.total} عقد</span><Pagination links={contracts.links} /></div>
          </div>

          <div className="ih-only-mobile">
            <div className="ih-mlist">
              {contracts.data.map((c) => (
                <a key={c.id} href={u(`/contracts/${c.id}`)} className="ih-mcard">
                  <div style={{ display: 'flex', alignItems: 'flex-start', gap: '.6rem' }}>
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div className="ih-idc__name">{c.title}</div>
                      <div className="ih-idc__sub">{c.party ?? '—'} · {c.number}</div>
                    </div>
                    <StatusBadge tone={c.statusTone} label={c.statusLabel} />
                  </div>
                  <div style={{ display: 'flex', gap: '.6rem', marginTop: '.7rem', fontSize: '.8rem', color: 'var(--ih-text-secondary)' }}>
                    <span>القيمة <b style={{ direction: 'ltr', display: 'inline-block' }}>{kfmt(c.valueMinor)} {c.currency}</b></span>
                    <span style={{ marginInlineStart: 'auto' }}>ينتهي {c.endDate ?? '—'}</span>
                  </div>
                </a>
              ))}
            </div>
            <div style={{ marginTop: '1rem' }}><Pagination links={contracts.links} /></div>
          </div>
        </>
      )}
      {createOpen && (
        <div className="modal-backdrop" onClick={(e) => e.target === e.currentTarget && !busy && setCreateOpen(false)}>
          <div className="modal" style={{ padding: '1.3rem', maxWidth: 580 }}>
            <h3 style={{ fontWeight: 800, margin: '0 0 1rem' }}>عقد جديد</h3>
            <div style={{ display: 'grid', gap: '.8rem' }}>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
                <Field label="الطرف" labelStyle={LBL}>
                  <select value={form.party_type} onChange={(e) => setForm({ ...form, party_type: e.target.value, creator_id: '', client_id: '' })}
                    className="field" style={{ width: '100%' }}>
                    <option value="creator">مبدع</option>
                    <option value="client">عميل</option>
                  </select>
                </Field>
                <Field label={form.party_type === 'creator' ? 'المبدع' : 'العميل'} labelStyle={LBL}>
                  <select value={partyId}
                    onChange={(e) => setForm({ ...form, [form.party_type === 'creator' ? 'creator_id' : 'client_id']: e.target.value })}
                    className="field" style={{ width: '100%' }}>
                    <option value="">— اختر —</option>
                    {parties.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                  </select>
                </Field>
              </div>
              <Field label="عنوان العقد" labelStyle={LBL}>
                <input value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} className="field" style={{ width: '100%' }} autoFocus />
                {errors.title && <div style={{ color: 'var(--ih-danger-ink)', fontSize: '.76rem', marginTop: '.3rem' }}>{errors.title}</div>}
              </Field>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '.8rem' }}>
                <Field label="القيمة (ر.س)" labelStyle={LBL}>
                  <input type="number" min={0} step="0.01" value={form.value} onChange={(e) => setForm({ ...form, value: e.target.value })}
                    className="field" style={{ width: '100%', direction: 'ltr' }} placeholder="0" />
                </Field>
                <Field label="البداية" labelStyle={LBL}>
                  <input type="date" value={form.start_date} onChange={(e) => setForm({ ...form, start_date: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} />
                </Field>
                <Field label="النهاية" labelStyle={LBL}>
                  <input type="date" value={form.end_date} onChange={(e) => setForm({ ...form, end_date: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} />
                  {errors.end_date && <div style={{ color: 'var(--ih-danger-ink)', fontSize: '.72rem', marginTop: '.3rem' }}>{errors.end_date}</div>}
                </Field>
              </div>
              <Field label="البنود" labelStyle={LBL}>
                <textarea value={form.terms} onChange={(e) => setForm({ ...form, terms: e.target.value })} className="field" rows={4} style={{ width: '100%' }} />
              </Field>
              {errors.contract && <div style={{ color: 'var(--ih-danger-ink)', fontSize: '.8rem' }}>{errors.contract}</div>}
            </div>
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button disabled={busy || !partyId || !form.title.trim()} onClick={submitCreate} className="btn btn-primary">إنشاء مسودة</button>
              <button disabled={busy} onClick={() => setCreateOpen(false)} className="btn btn-ghost">إلغاء</button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}

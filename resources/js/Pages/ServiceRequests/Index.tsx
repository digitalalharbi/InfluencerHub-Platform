import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Kpi, ListHead, StatusBadge } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { u } from '@/lib/href';

interface RequestRow {
  id: number; number: string; title: string; client: string | null; type: string;
  priority: string; priorityLabel: string; status: string; statusLabel: string; statusTone: string;
  assignee: string | null; dueAt: string | null; sla: 'none' | 'overdue' | 'soon' | 'ok'; slaHours: number | null;
  bucket: string; blocked: string | null;
}
interface Summary {
  open: number; breached: number; unassigned: number; dueToday: number; mine: number;
  triage: number; in_progress: number; needs_info: number; resolved: number;
}
interface Filters { q?: string; priority?: string; seg?: string }
interface Options {
  clients: { id: number; name: string }[];
  brands: { id: number; name: string; clientId: number }[];
  types: Record<string, string>;
  priorities: Record<string, string>;
  platforms: Record<string, string>;
}
interface Props {
  requests: Paginated<RequestRow>; filters: Filters; priorityLabels: Record<string, string>;
  summary: Summary; canCreate: boolean; options: Options;
}

/**
 * تسجيل طلب نيابةً عن العميل.
 *
 * الطلبات تصل غالبًا من بوابة العميل، لكنها تصل أيضًا بالهاتف والبريد — وكان
 * الطابور بلا مدخل، فلا سبيل لتسجيلها. الموجز يُلتقط هنا كاملًا لأنه ينتقل
 * حرفيًّا إلى الحملة عند التحويل فلا يُعاد إدخاله.
 */
function NewRequestModal({ options, onClose }: { options: Options; onClose: () => void }) {
  const [form, setForm] = useState({
    client_id: options.clients.length === 1 ? String(options.clients[0].id) : '',
    brand_id: '', type: 'campaign', title: '', description: '', priority: 'normal',
    budget_riyals: '', preferred_start_date: '', preferred_end_date: '',
    platforms: [] as string[], scope_notes: '',
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);

  // علامات العميل المختار فقط: اختيار علامة لا تتبعه خطأ يرفضه الخادم أصلًا
  const brandsForClient = options.brands.filter((b) => String(b.clientId) === form.client_id);
  const isCampaign = form.type === 'campaign';

  const submit = () => {
    setBusy(true);
    setErrors({});
    router.post(u('/service-requests'), form, {
      onError: (e) => { setErrors(e as Record<string, string>); setBusy(false); },
      onFinish: () => setBusy(false),
    });
  };

  return (
    <div className="ih-modal-backdrop" role="dialog" aria-modal="true" aria-label="طلب جديد">
      <div className="ih-modal" style={{ maxWidth: 620 }}>
        <h3 style={{ margin: '0 0 1rem' }}>طلب جديد</h3>

        <div style={{ display: 'grid', gap: '.8rem' }}>
          <Fld label="العميل" error={errors.client_id} required>
            <select value={form.client_id} className="field" style={{ width: '100%' }}
              onChange={(e) => setForm({ ...form, client_id: e.target.value, brand_id: '' })}>
              <option value="">اختر عميلًا…</option>
              {options.clients.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
            </select>
          </Fld>

          {brandsForClient.length > 0 && (
            <Fld label="العلامة" error={errors.brand_id}>
              <select value={form.brand_id} className="field" style={{ width: '100%' }}
                onChange={(e) => setForm({ ...form, brand_id: e.target.value })}>
                <option value="">بلا علامة محدّدة</option>
                {brandsForClient.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
              </select>
            </Fld>
          )}

          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
            <Fld label="نوع الطلب" error={errors.type} required>
              <select value={form.type} className="field" style={{ width: '100%' }}
                onChange={(e) => setForm({ ...form, type: e.target.value })}>
                {Object.entries(options.types).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
              </select>
            </Fld>
            <Fld label="الأولوية" error={errors.priority} required>
              <select value={form.priority} className="field" style={{ width: '100%' }}
                onChange={(e) => setForm({ ...form, priority: e.target.value })}>
                {Object.entries(options.priorities).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
              </select>
            </Fld>
          </div>

          <Fld label="عنوان الطلب" error={errors.title} required>
            <input value={form.title} className="field" style={{ width: '100%' }}
              onChange={(e) => setForm({ ...form, title: e.target.value })} autoFocus />
          </Fld>

          <Fld label="وصف الطلب" error={errors.description}>
            <textarea value={form.description} className="field" style={{ width: '100%' }} rows={3}
              onChange={(e) => setForm({ ...form, description: e.target.value })} />
          </Fld>

          {isCampaign && (
            <>
              <div style={{ fontSize: '.78rem', color: 'var(--ih-text-muted)', borderTop: '1px solid var(--ih-border)', paddingTop: '.8rem' }}>
                موجز الحملة — ينتقل تلقائيًّا إلى الحملة عند التحويل
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '.8rem' }}>
                <Fld label="الميزانية (ر.س)" error={errors.budget_riyals}>
                  <input type="number" min="0" value={form.budget_riyals} className="field" style={{ width: '100%' }}
                    onChange={(e) => setForm({ ...form, budget_riyals: e.target.value })} />
                </Fld>
                <Fld label="البداية" error={errors.preferred_start_date}>
                  <input type="date" value={form.preferred_start_date} className="field" style={{ width: '100%' }}
                    onChange={(e) => setForm({ ...form, preferred_start_date: e.target.value })} />
                </Fld>
                <Fld label="النهاية" error={errors.preferred_end_date}>
                  <input type="date" value={form.preferred_end_date} className="field" style={{ width: '100%' }}
                    onChange={(e) => setForm({ ...form, preferred_end_date: e.target.value })} />
                </Fld>
              </div>
              <Fld label="المنصّات" error={errors.platforms}>
                <div style={{ display: 'flex', gap: '.4rem', flexWrap: 'wrap' }}>
                  {Object.entries(options.platforms).map(([k, v]) => {
                    const on = form.platforms.includes(k);
                    return (
                      <button key={k} type="button"
                        // تحديث دالّي: نقرتان متتاليتان تقرآن الحالة نفسها فتضيع الأولى
                        onClick={() => setForm((f) => ({
                          ...f,
                          platforms: f.platforms.includes(k)
                            ? f.platforms.filter((p) => p !== k)
                            : [...f.platforms, k],
                        }))}
                        className={`btn btn-xs${on ? '' : ' btn-outline'}`}>{v}</button>
                    );
                  })}
                </div>
              </Fld>
              <Fld label="ملاحظات النطاق" error={errors.scope_notes}>
                <textarea value={form.scope_notes} className="field" style={{ width: '100%' }} rows={2}
                  onChange={(e) => setForm({ ...form, scope_notes: e.target.value })} />
              </Fld>
            </>
          )}
        </div>

        <div style={{ display: 'flex', gap: '.5rem', marginTop: '1.2rem', justifyContent: 'flex-end' }}>
          <button onClick={onClose} className="btn btn-sm btn-ghost">إلغاء</button>
          <button onClick={submit} className="btn btn-sm" disabled={busy || !form.client_id || !form.title.trim()}>
            {busy ? 'جارٍ الحفظ…' : 'تسجيل الطلب'}
          </button>
        </div>
        {(!form.client_id || !form.title.trim()) && (
          <p style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', textAlign: 'end', marginTop: '.4rem' }}>
            اختر العميل واكتب عنوانًا ليُصبح الحفظ متاحًا
          </p>
        )}
      </div>
    </div>
  );
}

function Fld({ label, error, required, children }: { label: string; error?: string; required?: boolean; children: React.ReactNode }) {
  return (
    <div>
      <label style={{ fontSize: '.8rem', fontWeight: 600, display: 'block', marginBottom: '.3rem' }}>
        {label}{required && <b style={{ color: 'var(--ih-danger-ink)' }}> *</b>}
      </label>
      {children}
      {error && <em style={{ fontSize: '.75rem', color: 'var(--ih-danger-ink)', fontStyle: 'normal' }}>{error}</em>}
    </div>
  );
}

const PRIO_TONE: Record<string, { bg: string; fg: string }> = {
  urgent: { bg: 'var(--ih-danger-soft)', fg: 'var(--ih-danger-ink)' },
  high: { bg: 'var(--ih-warning-soft)', fg: 'var(--ih-warning-ink)' },
  normal: { bg: 'var(--ih-surface-sunken)', fg: 'var(--ih-text-secondary)' },
  low: { bg: 'var(--ih-surface-sunken)', fg: 'var(--ih-text-muted)' },
};

function Sla({ sla, hours }: { sla: RequestRow['sla']; hours: number | null }) {
  if (sla === 'none') return <span style={{ color: 'var(--ih-text-muted)' }}>—</span>;
  if (sla === 'overdue') return <span className="badge" style={{ background: 'var(--ih-danger-soft)', color: 'var(--ih-danger-ink)' }}>متأخر {hours != null ? `${Math.abs(hours)}س` : ''}</span>;
  if (sla === 'soon') return <span className="badge" style={{ background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)' }}>خلال {hours}س</span>;
  return <span className="badge" style={{ background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)' }}>{hours}س</span>;
}

function clean(obj: Record<string, unknown>): Record<string, string> {
  const out: Record<string, string> = {};
  for (const [k, v] of Object.entries(obj)) if (v !== '' && v !== null && v !== undefined) out[k] = String(v);
  return out;
}

export default function ServiceRequestsIndex({ requests, filters, priorityLabels, summary, canCreate, options }: Props) {
  const [q, setQ] = useState(filters.q ?? '');
  const [creating, setCreating] = useState(false);
  const first = useRef(true);
  useEffect(() => {
    if (first.current) { first.current = false; return; }
    const t = setTimeout(() => router.get(u('/service-requests'), clean({ ...filters, q }), { preserveState: true, replace: true, preserveScroll: true }), 350);
    return () => clearTimeout(t);
  }, [q]);
  const update = (patch: Filters) => router.get(u('/service-requests'), clean({ ...filters, ...patch }), { preserveState: true, replace: true, preserveScroll: true });

  const seg = filters.seg ?? '';
  const hasFilters = !!(filters.q || filters.priority || seg);
  const segments: [string, string, number][] = [
    ['', 'الكل', summary.open], ['mine', 'مسندة لي', summary.mine], ['unassigned', 'غير مسندة', summary.unassigned],
    ['breached', 'متجاوزة SLA', summary.breached], ['triage', 'قيد الفرز', summary.triage],
    ['in_progress', 'قيد التنفيذ', summary.in_progress], ['needs_info', 'بانتظار معلومة', summary.needs_info],
    ['resolved', 'مُنجزة', summary.resolved],
  ];

  return (
    <AppShell heading="الطلبات">
      <Head title="الطلبات" />

      <ListHead eyebrow="التشغيل" title="الطلبات"
        sub="طابور الطلبات الواردة: فرز، إسناد، ومتابعة مهل الاستجابة (SLA)"
        actions={canCreate ? (
          <button onClick={() => setCreating(true)} className="btn btn-sm btn-primary">
            <Icon name="inbox" size={15} /> طلب جديد
          </button>
        ) : undefined} />

      {creating && <NewRequestModal options={options} onClose={() => setCreating(false)} />}

      <div className="ih-kpis">
        <Kpi label="مفتوحة" icon="inbox" value={summary.open.toLocaleString('en-US')} sub={`${summary.triage} قيد الفرز · ${summary.in_progress} قيد التنفيذ`} />
        <Kpi label="متجاوزة SLA" icon="clipboard-check" tone={summary.breached ? 'danger' : undefined} value={summary.breached.toLocaleString('en-US')} sub="تحتاج تدخّلًا عاجلًا" />
        <Kpi label="غير مسندة" icon="user-plus" tone={summary.unassigned ? 'warning' : undefined} value={summary.unassigned.toLocaleString('en-US')} sub="بانتظار الإسناد" />
        <Kpi label="مسندة لي" icon="clipboard-check" tone="accent" value={summary.mine.toLocaleString('en-US')} sub={`${summary.dueToday} مستحقة اليوم`} />
      </div>

      <div className="ih-chips" style={{ marginBottom: '.9rem', overflowX: 'auto', paddingBottom: '.2rem', flexWrap: 'nowrap' }}>
        {segments.map(([key, label, count]) => (
          <button key={key} onClick={() => update({ seg: key })} className={`ih-chip${seg === key ? ' active' : ''}`}>{label} <span className="ih-chip__count">{count}</span></button>
        ))}
      </div>

      <div className="ih-filterbar">
        <label className="ih-search"><Icon name="search" size={16} />
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="ابحث بالعنوان أو الرقم أو العميل…" />
        </label>
        <select className="field" style={{ maxWidth: 140 }} value={filters.priority ?? ''} onChange={(e) => update({ priority: e.target.value })}>
          <option value="">كل الأولويات</option>
          {Object.entries(priorityLabels).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
        </select>
      </div>

      {requests.data.length === 0 ? (
        <div className="ih-dt-wrap"><div className="ih-empty">
          <span className="ih-empty__icon" style={{ background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)' }}><Icon name="shield-check" size={26} /></span>
          {hasFilters ? (
            <><div className="ih-empty__title">لا طلبات مطابقة</div><div className="ih-empty__text">لا نتائج للبحث أو الشريحة الحالية.</div><a href={u("/service-requests")} className="btn btn-sm btn-outline">مسح الفلاتر</a></>
          ) : (
            <><div className="ih-empty__title">لا طلبات بعد</div>
              <div className="ih-empty__text">
                تصل الطلبات من بوابة العميل، ويمكنك تسجيل طلب نيابةً عنه إن وصل بالهاتف أو البريد.
              </div>
              {canCreate && <button onClick={() => setCreating(true)} className="btn btn-sm">تسجيل طلب</button>}</>
          )}
        </div></div>
      ) : (
        <>
          {/* طابور فرز — مقسّم حسب الإلحاح، وسبب التعطل ظاهر */}
          <div className="ih-only-desktop">
            {([['overdue', 'متأخرة'], ['new', 'جديدة'], ['open', 'قيد العمل'], ['done', 'منتهية']] as [string, string][]).map(([bk, label]) => {
              const grp = requests.data.filter((s) => s.bucket === bk);
              if (grp.length === 0) return null;
              return (
                <div key={bk} style={{ marginBottom: '1.1rem' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '.5rem', marginBottom: '.5rem' }}>
                    <span style={{ fontWeight: 700, fontSize: '.88rem' }}>{label}</span>
                    <span className="ih-pipe__count">{grp.length}</span>
                  </div>
                  <div className="ih-triage">
                    {grp.map((s) => {
                      const pt = PRIO_TONE[s.priority] ?? PRIO_TONE.normal;
                      return (
                        <a key={s.id} href={u(`/service-requests/${s.id}`)}
                          className={`ih-trow ih-trow--${s.sla === 'overdue' ? 'overdue' : bk === 'new' ? 'new' : bk === 'done' ? 'done' : ''}`}>
                          <div style={{ minWidth: 0, flex: 1 }}>
                            <div style={{ fontWeight: 650, fontSize: '.88rem', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{s.title}</div>
                            <div style={{ fontSize: '.73rem', color: 'var(--ih-text-muted)' }}>
                              <span style={{ direction: 'ltr' }}>{s.number}</span>
                              {s.client ? ` · ${s.client}` : ''} · {s.type}
                              {s.assignee ? ` · ${s.assignee}` : ' · غير مُسنَد'}
                            </div>
                          </div>
                          <Sla sla={s.sla} hours={s.slaHours} />
                          {s.blocked && <span className="ih-tag" style={{ fontSize: '.64rem', background: 'var(--ih-danger-soft)', color: 'var(--ih-danger-ink)', flexShrink: 0 }}>{s.blocked}</span>}
                          <span className="badge" style={{ background: pt.bg, color: pt.fg, flexShrink: 0 }}>{s.priorityLabel}</span>
                          <StatusBadge tone={s.statusTone} label={s.statusLabel} />
                        </a>
                      );
                    })}
                  </div>
                </div>
              );
            })}
            <div className="ih-dt__foot"><span>{requests.total} طلب{hasFilters ? ' · مُرشَّح' : ''}</span><Pagination links={requests.links} /></div>
          </div>

          <div className="ih-only-mobile">
            <div className="ih-mlist">
              {requests.data.map((s) => {
                const pt = PRIO_TONE[s.priority] ?? PRIO_TONE.normal;
                return (
                  <a key={s.id} href={u(`/service-requests/${s.id}`)} className="ih-mcard">
                    <div style={{ display: 'flex', alignItems: 'flex-start', gap: '.6rem' }}>
                      <div style={{ flex: 1, minWidth: 0 }}>
                        <div className="ih-idc__name">{s.title}</div>
                        <div className="ih-idc__sub">{s.client ?? '—'} · {s.number}</div>
                      </div>
                      <StatusBadge tone={s.statusTone} label={s.statusLabel} />
                    </div>
                    <div style={{ display: 'flex', gap: '.4rem', flexWrap: 'wrap', marginTop: '.7rem', alignItems: 'center' }}>
                      <span className="badge" style={{ background: pt.bg, color: pt.fg, fontSize: '.62rem' }}>{s.priorityLabel}</span>
                      <Sla sla={s.sla} hours={s.slaHours} />
                      <span className="ih-tag" style={{ fontSize: '.66rem' }}>{s.type}</span>
                      <span style={{ marginInlineStart: 'auto', fontSize: '.74rem', color: s.assignee ? 'var(--ih-text-muted)' : 'var(--ih-warning-ink)' }}>{s.assignee ?? 'غير مسند'}</span>
                    </div>
                  </a>
                );
              })}
            </div>
            <div style={{ marginTop: '1rem' }}><Pagination links={requests.links} /></div>
          </div>
        </>
      )}
    </AppShell>
  );
}

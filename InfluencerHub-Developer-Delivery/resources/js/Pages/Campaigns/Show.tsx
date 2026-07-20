import { Head, router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Field, Sec, StatusBadge, SummaryStrip, WorkTabs, WorkspaceHeader, sarShort } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';

interface Stage { key: string; label: string; state: 'done' | 'current' | 'pending' }
interface Command {
  stages: Stage[]; current: string; current_label: string; progress: number;
  next_action: { title: string; hint: string; link: string }; is_late: boolean;
}
interface ReadyItem { label: string; done: boolean; hint: string; link: string | null }
interface Readiness { items: ReadyItem[]; done: number; total: number; percent: number }
interface TL { at: string | null; icon: string; tone: string; text: string; meta: string }
interface Row { id: number; status: string; statusLabel: string; statusTone: string }
type Deliverable = Row & { type: string; typeLabel: string; platform: string | null; quantity: number; creator: string | null };
type Collab = Row & { creator: string | null; title: string; feeMinor: number };
type Content = Row & { title: string; creator: string | null; platform: string | null };
interface SourceRequest { id: number; number: string; title: string }
interface Campaign {
  id: number; name: string; number: string; client: string | null; brand: string | null;
  clientId: number | null; brandId: number | null; sourceRequest: SourceRequest | null;
  status: string; statusLabel: string; statusTone: string;
  budgetMinor: number; committedMinor: number; currency: string;
  startDate: string | null; endDate: string | null; objective: string | null;
}
interface Metrics { progress: number; deliverables: number; creators: number; awaitingClient: number; isLate: boolean }
interface Option { value: string; label: string }
interface CampaignInvoice {
  id: number; number: string; status: string; statusLabel: string; statusTone: string;
  totalMinor: number; balanceMinor: number; dueDate: string | null;
}
interface Props {
  campaign: Campaign; metrics: Metrics; command: Command; readiness: Readiness; timeline: TL[];
  deliverables: Deliverable[]; collaborations: Collab[]; content: Content[];
  canManage: boolean; deliverableTypes: Option[]; actions: CampaignAction[];
  invoices: CampaignInvoice[]; canInvoice: boolean;
}
/** [action, label, tone, needsReason] — تأتي من الخادم حسب الحالة الفعلية. */
type CampaignAction = [string, string, string, boolean];

const ABTN: Record<string, string> = { primary: 'btn-primary', danger: 'btn-danger', ghost: 'btn-ghost' };

const LBL: React.CSSProperties = { fontSize: '.8rem', fontWeight: 600, display: 'block', marginBottom: '.3rem' };

const money = (m: number, cur: string) => (m / 100).toLocaleString('en-US', { minimumFractionDigits: 0 }) + ' ' + cur;

function DataTable({ head, children }: { head: string[]; children: ReactNode }) {
  return (
    <div className="ih-dt-wrap"><div className="ih-dt-scroll">
      <table className="ih-dt"><thead><tr>{head.map((h) => <th key={h}>{h}</th>)}</tr></thead><tbody>{children}</tbody></table>
    </div></div>
  );
}
function EmptyRow({ span, text }: { span: number; text: string }) {
  return <tr><td colSpan={span} style={{ textAlign: 'center', color: 'var(--ih-text-muted)', padding: '1.6rem' }}>{text}</td></tr>;
}

export default function CampaignShow({ campaign, metrics, command, readiness, timeline, deliverables, collaborations, content, canManage, deliverableTypes, actions, invoices, canInvoice }: Props) {
  const [actionFor, setActionFor] = useState<CampaignAction | null>(null);
  const [actionReason, setActionReason] = useState('');
  const runAction = (a: CampaignAction) => {
    if (a[3]) { setActionFor(a); setActionReason(''); return; }
    router.post(u(`/campaigns/${campaign.id}/${a[0]}`), {}, { preserveScroll: true });
  };
  const submitAction = () => {
    if (!actionFor) return;
    router.post(u(`/campaigns/${campaign.id}/${actionFor[0]}`), { reason: actionReason },
      { preserveScroll: true, onSuccess: () => setActionFor(null) });
  };

  const [tab, setTab] = useState('deliverables');
  const overBudget = campaign.committedMinor > campaign.budgetMinor && campaign.budgetMinor > 0;
  const isFinal = campaign.status === 'completed' || campaign.status === 'cancelled';
  const [delivOpen, setDelivOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  // الأتعاب والاستحقاق في النموذج: الخادم يقبلهما أصلًا، وبدونهما يبقى المخرَج
  // بلا قيمة — فتقترح الفاتورة صفرًا ولا يجد المستحق أساسًا يُبنى عليه.
  const EMPTY_DFORM = () => ({
    type: deliverableTypes[0]?.value ?? 'post', platform: '', quantity: '1',
    fee_riyals: '', due_date: '',
  });
  const [dform, setDform] = useState(EMPTY_DFORM);
  const addDeliverable = () => {
    setBusy(true);
    router.post(u(`/campaigns/${campaign.id}/deliverables`), {
      type: dform.type,
      platform: dform.platform || null,
      quantity: parseInt(dform.quantity) || 1,
      // الريالات تُحوَّل إلى وحدات صغرى عند الحدّ لا في العرض
      fee_minor: dform.fee_riyals ? Math.round(Number(dform.fee_riyals) * 100) : null,
      due_date: dform.due_date || null,
    }, {
      preserveScroll: true, onFinish: () => setBusy(false),
      onSuccess: () => { setDelivOpen(false); setDform(EMPTY_DFORM()); },
    });
  };
  const removeDeliverable = (id: number) => { setBusy(true); router.delete(u(`/campaigns/${campaign.id}/deliverables/${id}`), { preserveScroll: true, onFinish: () => setBusy(false) }); };
  const canEditDeliverables = canManage && !isFinal;
  const [editOpen, setEditOpen] = useState(false);
  const [eform, setEform] = useState({ name: '', objective: '', budget: '', start_date: '', end_date: '' });
  const openEdit = () => {
    setEform({
      name: campaign.name, objective: campaign.objective ?? '',
      budget: campaign.budgetMinor ? String(campaign.budgetMinor / 100) : '',
      start_date: campaign.startDate ?? '', end_date: campaign.endDate ?? '',
    });
    setEditOpen(true);
  };
  const saveEdit = () => {
    if (!eform.name.trim()) return;
    setBusy(true);
    router.post(u(`/campaigns/${campaign.id}`), {
      name: eform.name, objective: eform.objective || null,
      budget_minor: eform.budget ? Math.round(parseFloat(eform.budget) * 100) : null, currency: campaign.currency || 'SAR',
      start_date: eform.start_date || null, end_date: eform.end_date || null,
    }, { preserveScroll: true, onFinish: () => setBusy(false), onSuccess: () => setEditOpen(false) });
  };

  return (
    <AppShell heading="حملة">
      <Head title={campaign.name} />

      <WorkspaceHeader
        eyebrow={`حملة · ${campaign.number}`}
        title={campaign.name}
        statusTone={campaign.statusTone} statusLabel={campaign.statusLabel}
        back={u("/campaigns")} backLabel="كل الحملات"
        meta={[
          ['العميل', campaign.client ?? '—'], ['العلامة', campaign.brand ?? '—'],
          ['البداية', campaign.startDate ?? '—'], ['النهاية', campaign.endDate ?? '—'],
        ]}

        actions={
          <>
            {canManage && <button onClick={openEdit} className="btn btn-sm btn-outline"><Icon name="file-text" size={14} /> تعديل</button>}
            <a href={u(`/campaigns/${campaign.id}/shortlist`)} className="btn btn-sm">الترشيحات</a>
            {actions.map((a) => (
              <button key={a[0]} onClick={() => runAction(a)} className={`btn btn-sm ${ABTN[a[2]] ?? 'btn-outline'}`}>{a[1]}</button>
            ))}
          </>
        }
      />

      {/* السلسلة التي أنتجت هذه الحملة — مرئية لا مضمرة في قاعدة البيانات */}
      <div style={{ display: 'flex', gap: '.5rem', flexWrap: 'wrap', alignItems: 'center', marginBottom: '1rem', fontSize: '.78rem' }}>
        <span style={{ color: 'var(--ih-text-muted)' }}>متّصل بـ</span>
        {campaign.sourceRequest && (
          <a href={u(`/service-requests/${campaign.sourceRequest.id}`)} className="btn btn-xs btn-outline">
            <Icon name="inbox" size={13} /> الطلب {campaign.sourceRequest.number}
          </a>
        )}
        {campaign.clientId && (
          <a href={u(`/clients/${campaign.clientId}`)} className="btn btn-xs btn-outline">
            <Icon name="building-2" size={13} /> {campaign.client}
          </a>
        )}
        {campaign.brandId && (
          <a href={u(`/brands/${campaign.brandId}`)} className="btn btn-xs btn-outline">
            <Icon name="bookmark" size={13} /> {campaign.brand}
          </a>
        )}
        {!campaign.sourceRequest && (
          <span style={{ color: 'var(--ih-text-muted)' }}>· أُنشئت مباشرةً بلا طلب سابق</span>
        )}
      </div>

      {/* الخطوة التالية */}
      {!isFinal && (
        <div className="ih-nba">
          <span className="ih-nba__icon"><Icon name="rocket" size={22} /></span>
          <div className="ih-nba__body">
            <div className="ih-nba__eyebrow">الخطوة التالية · المرحلة: {command.current_label}</div>
            <div className="ih-nba__title">{command.next_action.title}</div>
            {command.next_action.hint && <div className="ih-nba__hint">{command.next_action.hint}</div>}
          </div>
          <a href={command.next_action.link} className="btn btn-sm">تنفيذ الآن</a>
        </div>
      )}

      {/* رحلة الحملة */}
      <div className="card" style={{ padding: '1rem 1.2rem', marginBottom: '1.1rem' }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '.6rem' }}>
          <span style={{ fontWeight: 700, fontSize: '.9rem' }}>رحلة الحملة</span>
          <span style={{ fontSize: '.78rem', color: 'var(--ih-text-muted)' }}>{command.progress}% · {command.current_label}{command.is_late && <> · <span style={{ color: 'var(--ih-danger-ink)' }}>متأخرة</span></>}</span>
        </div>
        <div className="ih-journey">
          {command.stages.map((st, i) => (
            <div key={st.key} className={`ih-journey__step ${st.state}`}>
              <span className="ih-journey__dot">{st.state === 'done' ? '✓' : i + 1}</span>
              <span className="ih-journey__label">{st.label}</span>
            </div>
          ))}
        </div>
      </div>

      <SummaryStrip items={[
        { label: 'الميزانية', value: money(campaign.budgetMinor, campaign.currency) },
        { label: 'الملتزَم', value: money(campaign.committedMinor, campaign.currency), tone: overBudget ? 'danger' : 'primary' },
        { label: 'المخرجات', value: metrics.deliverables, icon: 'image' },
        { label: 'صناع المحتوى', value: metrics.creators, icon: 'users' },
        { label: 'التقدّم', value: `${metrics.progress}%`, tone: 'primary' },
      ]} />

      <div style={{ display: 'grid', gridTemplateColumns: '1.3fr .7fr', gap: '1.1rem', alignItems: 'start' }} className="ih-overview-grid">
        <div style={{ display: 'grid', gap: '1.1rem' }}>
          <Sec title="قائمة الجاهزية" icon="clipboard-check">
            <div className="ih-sec__body">
              <div style={{ display: 'flex', alignItems: 'center', gap: '.6rem', marginBottom: '.8rem' }}>
                <div className="ih-bar" style={{ flex: 1 }}><span style={{ width: `${readiness.percent}%` }} /></div>
                <span style={{ fontWeight: 800, fontVariantNumeric: 'tabular-nums' }}>{readiness.done}/{readiness.total}</span>
              </div>
              <div style={{ display: 'grid', gap: '.4rem' }}>
                {readiness.items.map((it, i) => (
                  <a key={i} href={it.link ?? '#'} className="ih-risk" style={{ justifyContent: 'flex-start', gap: '.6rem', opacity: it.done ? 0.65 : 1 }}>
                    <span style={{ width: 22, height: 22, borderRadius: '50%', flexShrink: 0, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', background: it.done ? 'var(--ih-success-soft)' : 'var(--ih-warning-soft)', color: it.done ? 'var(--ih-success-ink)' : 'var(--ih-warning-ink)' }}>
                      {it.done ? <Icon name="shield-check" size={13} /> : '!'}
                    </span>
                    <span style={{ flex: 1, minWidth: 0 }}>
                      <span style={{ display: 'block', fontWeight: 600, textDecoration: it.done ? 'line-through' : 'none' }}>{it.label}</span>
                      {!it.done && <span style={{ display: 'block', fontSize: '.74rem', color: 'var(--ih-text-muted)', fontWeight: 500 }}>{it.hint}</span>}
                    </span>
                  </a>
                ))}
              </div>
            </div>
          </Sec>

          <div>
            <WorkTabs active={tab} onChange={setTab} tabs={[
              { key: 'deliverables', label: 'المخرجات', icon: 'image', count: deliverables.length },
              { key: 'collaborations', label: 'التعاونات', icon: 'git-merge', count: collaborations.length },
              { key: 'content', label: 'المحتوى', icon: 'image', count: content.length },
              { key: 'finance', label: 'المالية', icon: 'wallet', count: invoices.length },
            ]} />
            {tab === 'finance' && (
              <Sec title="فواتير الحملة" icon="wallet">
                {invoices.length === 0 ? (
                  <>
                    <p style={{ color: 'var(--ih-text-secondary)', fontSize: '.875rem' }}>
                      لا فواتير على هذه الحملة بعد. عند الإنشاء تُقترح البنود من مخرجاتها المسجّلة.
                    </p>
                    {canInvoice && (
                      <a href={u('/invoices')} className="btn btn-sm" style={{ marginBlockStart: '.7rem' }}>
                        إنشاء فاتورة
                      </a>
                    )}
                  </>
                ) : (
                  <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'grid', gap: '.5rem' }}>
                    {invoices.map((inv) => (
                      <li key={inv.id}>
                        <a href={u(`/invoices/${inv.id}`)} className="ih-risk" style={{ alignItems: 'center' }}>
                          <span style={{ fontWeight: 700, direction: 'ltr' }}>{inv.number}</span>
                          <StatusBadge tone={inv.statusTone} label={inv.statusLabel} />
                          <span style={{ flex: 1 }} />
                          <span style={{ direction: 'ltr', fontSize: '.85rem' }}>{sarShort(inv.totalMinor)}</span>
                          {inv.balanceMinor > 0 && (
                            <span style={{ direction: 'ltr', fontSize: '.78rem', color: 'var(--ih-warning-ink)' }}>
                              متبقٍّ {sarShort(inv.balanceMinor)}
                            </span>
                          )}
                        </a>
                      </li>
                    ))}
                  </ul>
                )}
              </Sec>
            )}

            {tab === 'deliverables' && (
              <>
                {canEditDeliverables && (
                  <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: '.7rem' }}>
                    <button onClick={() => setDelivOpen(true)} className="btn btn-sm btn-outline"><Icon name="plus" size={14} /> إضافة مخرج</button>
                  </div>
                )}
                <DataTable head={['النوع', 'المنصّة', 'الكمية', 'المبدع', 'الحالة', ...(canEditDeliverables ? ['—'] : [])]}>
                  {deliverables.length === 0 ? <EmptyRow span={canEditDeliverables ? 6 : 5} text="لا مخرجات بعد." /> :
                    deliverables.map((d) => (
                      <tr key={d.id}><td style={{ fontWeight: 600 }}>{d.typeLabel}</td><td>{d.platform ?? '—'}</td><td className="ih-dt__num">{d.quantity}</td>
                        <td>{d.creator ?? '—'}</td><td><StatusBadge tone={d.statusTone} label={d.statusLabel} /></td>
                        {canEditDeliverables && (
                          <td>
                            <span className="ih-dt__row-actions">
                              {/* المطابقة تقترح مبدعين لهذا المخرَج تحديدًا */}
                              {!d.creator && <a href={u(`/campaigns/${campaign.id}/deliverables/${d.id}/suggest`)} className="btn btn-xs btn-outline">اقترح مبدعين</a>}
                              <button disabled={busy} onClick={() => removeDeliverable(d.id)} className="btn btn-xs btn-danger">حذف</button>
                            </span>
                          </td>
                        )}</tr>
                    ))}
                </DataTable>
              </>
            )}
            {tab === 'collaborations' && (
              <DataTable head={['المبدع', 'التعاون', 'الأجر', 'الحالة']}>
                {collaborations.length === 0 ? <EmptyRow span={4} text="لا تعاونات بعد." /> :
                  collaborations.map((c) => (
                    <tr key={c.id}><td style={{ fontWeight: 600 }}>{c.creator ?? '—'}</td><td>{c.title}</td>
                      <td className="ih-dt__num" style={{ direction: 'ltr', textAlign: 'right' }}>{money(c.feeMinor, campaign.currency)}</td>
                      <td><StatusBadge tone={c.statusTone} label={c.statusLabel} /></td></tr>
                  ))}
              </DataTable>
            )}
            {tab === 'content' && (
              <DataTable head={['المحتوى', 'المبدع', 'المنصّة', 'الحالة']}>
                {content.length === 0 ? <EmptyRow span={4} text="لا محتوى بعد." /> :
                  content.map((c) => (
                    <tr key={c.id}><td style={{ fontWeight: 600 }}>{c.title}</td><td>{c.creator ?? '—'}</td><td>{c.platform ?? '—'}</td>
                      <td><StatusBadge tone={c.statusTone} label={c.statusLabel} /></td></tr>
                  ))}
              </DataTable>
            )}
          </div>
        </div>

        {/* المخطط الزمني */}
        <Sec title="المخطط الزمني" icon="bar-chart-3">
          <div className="ih-sec__body">
            {timeline.length === 0 ? (
              <div style={{ textAlign: 'center', color: 'var(--ih-text-muted)', padding: '1rem', fontSize: '.85rem' }}>لا أحداث بعد.</div>
            ) : (
              <div className="ih-tl">
                {timeline.map((e, i) => (
                  <div key={i} className={`ih-tl__item ${e.tone}`}>
                    <span className="ih-tl__dot" />
                    <div className="ih-tl__text">{e.text}</div>
                    <div className="ih-tl__meta">{[e.meta, e.at].filter(Boolean).join(' · ')}</div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </Sec>
      </div>
      {editOpen && (
        <div className="modal-backdrop" onClick={(e) => e.target === e.currentTarget && !busy && setEditOpen(false)}>
          <div className="modal" style={{ padding: '1.3rem', maxWidth: 540 }}>
            <h3 style={{ fontWeight: 800, margin: '0 0 1rem' }}>تعديل الحملة</h3>
            <div style={{ display: 'grid', gap: '.8rem' }}>
              <Field label="اسم الحملة" labelStyle={LBL}>
                <input value={eform.name} onChange={(e) => setEform({ ...eform, name: e.target.value })} className="field" style={{ width: '100%' }} />
              </Field>
              <Field label="الهدف" labelStyle={LBL}>
                <textarea value={eform.objective} onChange={(e) => setEform({ ...eform, objective: e.target.value })} className="field" rows={2} style={{ width: '100%', resize: 'vertical' }} />
              </Field>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '.8rem' }}>
                <Field label="الميزانية (ر.س)" labelStyle={LBL}>
                  <input type="number" min="0" value={eform.budget} onChange={(e) => setEform({ ...eform, budget: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} />
                </Field>
                <Field label="البداية" labelStyle={LBL}>
                  <input type="date" value={eform.start_date} onChange={(e) => setEform({ ...eform, start_date: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} />
                </Field>
                <Field label="النهاية" labelStyle={LBL}>
                  <input type="date" value={eform.end_date} onChange={(e) => setEform({ ...eform, end_date: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} />
                </Field>
              </div>
            </div>
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button disabled={busy || !eform.name.trim()} onClick={saveEdit} className="btn btn-primary">حفظ</button>
              <button disabled={busy} onClick={() => setEditOpen(false)} className="btn btn-ghost">إلغاء</button>
            </div>
          </div>
        </div>
      )}
      {delivOpen && (
        <div className="modal-backdrop" onClick={(e) => e.target === e.currentTarget && !busy && setDelivOpen(false)}>
          <div className="modal" style={{ padding: '1.3rem', maxWidth: 460 }}>
            <h3 style={{ fontWeight: 800, margin: '0 0 1rem' }}>إضافة مخرج</h3>
            <div style={{ display: 'grid', gap: '.8rem' }}>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
                <Field label="النوع" labelStyle={LBL}>
                  <select value={dform.type} onChange={(e) => setDform({ ...dform, type: e.target.value })} className="field" style={{ width: '100%' }}>
                    {deliverableTypes.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                  </select>
                </Field>
                <Field label="الكمية" labelStyle={LBL}>
                  <input type="number" min="1" value={dform.quantity} onChange={(e) => setDform({ ...dform, quantity: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} />
                </Field>
              </div>
              <Field label="المنصّة (اختياري)" labelStyle={LBL}>
                <input value={dform.platform} onChange={(e) => setDform({ ...dform, platform: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} placeholder="instagram / tiktok…" />
              </Field>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
                {/* «أتعاب الوحدة» لا «الأتعاب»: الحساب يضربها في الكمية، ولفظ
                    مبهم هنا يعني تسعير حملة كاملة بالخطأ. */}
                <Field label="أتعاب الوحدة (ر.س)" labelStyle={LBL}>
                  <input type="number" min="0" step="0.01" value={dform.fee_riyals}
                    onChange={(e) => setDform({ ...dform, fee_riyals: e.target.value })}
                    className="field" style={{ width: '100%', direction: 'ltr' }} />
                </Field>
                <Field label="تاريخ الاستحقاق" labelStyle={LBL}>
                  <input type="date" value={dform.due_date}
                    onChange={(e) => setDform({ ...dform, due_date: e.target.value })}
                    className="field" style={{ width: '100%', direction: 'ltr' }} />
                </Field>
              </div>
              <p style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', margin: 0 }}>
                {dform.fee_riyals && Number(dform.fee_riyals) > 0 ? (
                  <>
                    إجمالي هذا المخرَج{' '}
                    <b style={{ direction: 'ltr', display: 'inline-block' }}>
                      {(Number(dform.fee_riyals) * (parseInt(dform.quantity) || 1))
                        .toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ر.س
                    </b>{' '}
                    — ينتقل إلى بند الفاتورة وإلى مستحق المبدع فلا يُدخَل مرّتين.
                  </>
                ) : (
                  'أتعاب الوحدة تنتقل إلى بند الفاتورة وإلى مستحق المبدع — فلا تُدخَل مرّتين.'
                )}
              </p>
            </div>
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button disabled={busy} onClick={addDeliverable} className="btn btn-primary">إضافة</button>
              <button disabled={busy} onClick={() => setDelivOpen(false)} className="btn btn-ghost">إلغاء</button>
            </div>
          </div>
        </div>
      )}
      {actionFor && (
        <div className="modal-backdrop" onClick={(e) => e.target === e.currentTarget && setActionFor(null)}>
          <div className="modal" style={{ padding: '1.3rem', maxWidth: 460 }}>
            <h3 style={{ fontWeight: 800, margin: '0 0 1rem' }}>{actionFor[1]}</h3>
            <textarea value={actionReason} onChange={(e) => setActionReason(e.target.value)} className="field" rows={3}
              style={{ width: '100%' }} placeholder="ملاحظة (اختياري)" autoFocus />
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button onClick={submitAction} className={`btn ${ABTN[actionFor[2]] ?? 'btn-primary'}`}>تأكيد</button>
              <button onClick={() => setActionFor(null)} className="btn btn-ghost">إلغاء</button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}

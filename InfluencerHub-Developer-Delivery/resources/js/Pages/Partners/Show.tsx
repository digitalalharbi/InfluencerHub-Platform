import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Field, Sec, StatusBadge, SummaryStrip, WorkspaceHeader } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import type { SharedProps } from '@/types';
import { u } from '@/lib/href';

interface Agency {
  id: number; name: string; number: string; legalName: string | null;
  contactName: string | null; contactEmail: string | null; contactPhone: string | null;
  country: string | null; website: string | null; specialization: string | null; notes: string | null;
  status: string; statusLabel: string; statusTone: string; isActivePartner: boolean; editable: boolean;
}
type Action = [string, string, string, boolean];
interface Member { name: string; email: string | null; role: string; status: string; statusLabel: string; statusTone: string }
interface Invitation { email: string; role: string; status: string; expiresAt: string | null }
interface Link { id: number; client: string | null; brand: string | null; scopes: string[]; status: string; active: boolean }
interface History { from: string; to: string; by: string; reason: string | null; at: string | null }
interface Option { id: number; name: string }
interface BrandOption extends Option { clientId: number }
interface Props {
  agency: Agency; can: { update: boolean; manage: boolean }; actions: Action[];
  members: Member[]; invitations: Invitation[]; links: Link[]; history: History[];
  clientOptions: Option[]; brandOptions: BrandOption[]; scopeOptions: Record<string, string>;
}

const BTN: Record<string, string> = { primary: 'btn-primary', danger: 'btn-danger', ghost: 'btn-ghost' };
const LBL: React.CSSProperties = { fontSize: '.78rem', fontWeight: 600, display: 'block', marginBottom: '.25rem' };

export default function PartnerShow({ agency, can, actions, members, invitations, links, history, clientOptions, brandOptions, scopeOptions }: Props) {
  const inviteToken = usePage<SharedProps>().props.flash?.inviteToken ?? null;
  const [busy, setBusy] = useState(false);
  const [errs, setErrs] = useState<Record<string, string>>({});
  const [actionFor, setActionFor] = useState<Action | null>(null);
  const [reason, setReason] = useState('');
  const [panel, setPanel] = useState<null | 'edit' | 'invite' | 'link'>(null);
  const [form, setForm] = useState({
    name: agency.name, legal_name: agency.legalName ?? '', contact_name: agency.contactName ?? '',
    contact_email: agency.contactEmail ?? '', contact_phone: agency.contactPhone ?? '',
    country_code: agency.country ?? '', website: agency.website ?? '',
    specialization: agency.specialization ?? '', notes: agency.notes ?? '',
  });
  const [invite, setInvite] = useState({ email: '', role: 'partner_member' });
  const [link, setLink] = useState<{ client_id: string; brand_id: string; scopes: string[] }>({ client_id: '', brand_id: '', scopes: [] });

  const base = u(`/partner-agencies/${agency.id}`);
  const post = (path: string, data: Record<string, unknown>, done?: () => void) => {
    setBusy(true);
    router.post(`${base}${path}`, data as never, {
      preserveScroll: true,
      onFinish: () => setBusy(false),
      onError: (e) => setErrs(e as Record<string, string>),
      onSuccess: () => { setErrs({}); setPanel(null); setActionFor(null); done?.(); },
    });
  };
  const runAction = (a: Action) => {
    if (a[3]) { setActionFor(a); setReason(''); return; }
    post(`/${a[0]}`, {});
  };
  const Err = ({ k }: { k: string }) => errs[k] ? <div style={{ color: 'var(--ih-danger-ink)', fontSize: '.74rem', marginTop: '.25rem' }}>{errs[k]}</div> : null;

  // العلامات تُقيَّد بالعميل المختار — لا يُعرض ما لا يخصّه
  const brandsForClient = link.client_id ? brandOptions.filter((b) => String(b.clientId) === link.client_id) : [];

  return (
    <AppShell heading="وكالة شريكة">
      <Head title={agency.name} />

      <WorkspaceHeader
        eyebrow={`وكالة شريكة · ${agency.number}`}
        title={agency.name}
        statusTone={agency.statusTone} statusLabel={agency.statusLabel}
        back={u('/partner-agencies')} backLabel="كل الوكالات"
        meta={[
          ['جهة الاتصال', agency.contactName ?? '—'],
          ['التخصّص', agency.specialization ?? '—'],
          ['الدولة', agency.country ?? '—'],
          ['الأعضاء', String(members.length)],
        ]}
        actions={actions.length > 0 || (can.update && agency.editable) ? (
          <>
            {can.update && agency.editable && (
              <button onClick={() => setPanel(panel === 'edit' ? null : 'edit')} className="btn btn-sm btn-outline">
                <Icon name="file-text" size={14} /> تحرير
              </button>
            )}
            {actions.map((a) => (
              <button key={a[0]} disabled={busy} onClick={() => runAction(a)} className={`btn btn-sm ${BTN[a[2]] ?? 'btn-outline'}`}>{a[1]}</button>
            ))}
          </>
        ) : undefined}
      />

      <SummaryStrip items={[
        { label: 'الحالة', value: agency.statusLabel },
        { label: 'الأعضاء', value: members.length, icon: 'users' },
        { label: 'الروابط النشطة', value: links.filter((l) => l.active).length },
        { label: 'الدعوات المعلّقة', value: invitations.length },
      ]} />

      {errs.wf && (
        <div className="card" style={{ padding: '.8rem 1rem', marginBottom: '1rem', borderInlineStart: '3px solid var(--ih-danger)', background: 'var(--ih-danger-soft)', color: 'var(--ih-danger-ink)', fontSize: '.85rem' }}>
          {errs.wf}
        </div>
      )}

      {inviteToken && (
        <div className="card" style={{ padding: '.9rem 1rem', marginBottom: '1rem', borderInlineStart: '3px solid var(--ih-warning)', background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)' }}>
          <div style={{ fontWeight: 700, marginBottom: '.3rem' }}>رمز الدعوة — يُعرض مرة واحدة</div>
          <div style={{ fontSize: '.8rem', marginBottom: '.5rem' }}>انسخه الآن وسلّمه للعضو؛ لا يمكن استرجاعه بعد مغادرة الصفحة.</div>
          <code style={{ direction: 'ltr', display: 'block', wordBreak: 'break-all', fontSize: '.86rem', fontWeight: 700 }}>{inviteToken}</code>
        </div>
      )}

      {panel === 'edit' && (
        <Sec title="بيانات الوكالة" icon="file-text">
          <div className="ih-sec__body" style={{ display: 'grid', gap: '.8rem' }}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
              <Field label="الاسم" labelStyle={LBL}><input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} className="field" style={{ width: '100%' }} /><Err k="name" /></Field>
              <Field label="الاسم النظامي" labelStyle={LBL}><input value={form.legal_name} onChange={(e) => setForm({ ...form, legal_name: e.target.value })} className="field" style={{ width: '100%' }} /></Field>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '.8rem' }}>
              <Field label="جهة الاتصال" labelStyle={LBL}><input value={form.contact_name} onChange={(e) => setForm({ ...form, contact_name: e.target.value })} className="field" style={{ width: '100%' }} /></Field>
              <Field label="البريد" labelStyle={LBL}><input value={form.contact_email} onChange={(e) => setForm({ ...form, contact_email: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} /><Err k="contact_email" /></Field>
              <Field label="الهاتف" labelStyle={LBL}><input value={form.contact_phone} onChange={(e) => setForm({ ...form, contact_phone: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} /></Field>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '.8rem' }}>
              <Field label="التخصّص" labelStyle={LBL}><input value={form.specialization} onChange={(e) => setForm({ ...form, specialization: e.target.value })} className="field" style={{ width: '100%' }} /></Field>
              <Field label="الدولة" labelStyle={LBL}><input value={form.country_code} onChange={(e) => setForm({ ...form, country_code: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} /></Field>
              <Field label="الموقع" labelStyle={LBL}><input value={form.website} onChange={(e) => setForm({ ...form, website: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} /></Field>
            </div>
            <Field label="ملاحظات" labelStyle={LBL}><textarea value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} className="field" rows={3} style={{ width: '100%' }} /></Field>
            <div style={{ display: 'flex', gap: '.5rem' }}>
              <button disabled={busy || !form.name.trim()} onClick={() => post('', form)} className="btn btn-sm btn-primary">حفظ</button>
              <button disabled={busy} onClick={() => setPanel(null)} className="btn btn-sm btn-ghost">إلغاء</button>
            </div>
          </div>
        </Sec>
      )}

      <div className="ih-overview-grid" style={{ display: 'grid', gridTemplateColumns: '1.2fr .8fr', gap: '1.1rem', alignItems: 'start' }}>
        <Sec title="الروابط المُنطّقة" icon="handshake">
          <div className="ih-sec__body" style={{ display: 'grid', gap: '.8rem' }}>
            <div style={{ fontSize: '.78rem', color: 'var(--ih-text-muted)' }}>
              كل رابط يفتح وصولًا فعليًا لعميل واحد بنطاق محدَّد — لا وصول عام.
            </div>

            {links.length === 0 ? (
              <div style={{ color: 'var(--ih-text-muted)', fontSize: '.85rem' }}>لا روابط بعد.</div>
            ) : (
              <div style={{ display: 'grid', gap: '.5rem' }}>
                {links.map((l) => (
                  <div key={l.id} className="card" style={{ padding: '.65rem .85rem', display: 'flex', alignItems: 'center', gap: '.7rem', flexWrap: 'wrap', opacity: l.active ? 1 : .55 }}>
                    <div style={{ minWidth: 0, flex: 1 }}>
                      <div style={{ fontWeight: 600, fontSize: '.86rem' }}>{l.client ?? '—'}{l.brand ? ` · ${l.brand}` : ''}</div>
                      <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)' }}>
                        {l.scopes.length > 0 ? l.scopes.join(' · ') : 'بلا نطاق محدَّد'}
                      </div>
                    </div>
                    {l.active ? (
                      can.manage && <button disabled={busy} onClick={() => post(`/links/${l.id}/revoke`, {})} className="btn btn-xs btn-danger">إلغاء الربط</button>
                    ) : <span className="ih-tag" style={{ fontSize: '.62rem' }}>ملغى</span>}
                  </div>
                ))}
              </div>
            )}

            {can.manage && (
              agency.isActivePartner ? (
                <>
                  {panel === 'link' ? (
                    <div className="card" style={{ padding: '.9rem', display: 'grid', gap: '.7rem' }}>
                      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.7rem' }}>
                        <Field label="العميل" labelStyle={LBL}>
                          <select value={link.client_id} onChange={(e) => setLink({ ...link, client_id: e.target.value, brand_id: '' })} className="field" style={{ width: '100%' }}>
                            <option value="">— اختر —</option>
                            {clientOptions.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                          </select>
                          <Err k="client_id" />
                        </Field>
                        <Field label="العلامة (اختياري)" labelStyle={LBL}>
                          <select value={link.brand_id} onChange={(e) => setLink({ ...link, brand_id: e.target.value })} className="field" style={{ width: '100%' }} disabled={!link.client_id}>
                            <option value="">كل علامات العميل</option>
                            {brandsForClient.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
                          </select>
                        </Field>
                      </div>
                      <Field label="النطاقات" labelStyle={LBL}>
                        {(g) => (
                          <div {...g} role="group" style={{ display: 'flex', gap: '.5rem', flexWrap: 'wrap' }}>
                            {Object.entries(scopeOptions).map(([k, v]) => (
                              <label key={k} style={{ display: 'inline-flex', alignItems: 'center', gap: '.3rem', fontSize: '.8rem' }}>
                                <input type="checkbox" checked={link.scopes.includes(k)}
                                  onChange={(e) => setLink({ ...link, scopes: e.target.checked ? [...link.scopes, k] : link.scopes.filter((s) => s !== k) })} />
                                {v}
                              </label>
                            ))}
                          </div>
                        )}
                      </Field>
                      <div style={{ display: 'flex', gap: '.5rem' }}>
                        <button disabled={busy || !link.client_id} onClick={() => post('/links', link, () => setLink({ client_id: '', brand_id: '', scopes: [] }))} className="btn btn-sm btn-primary">إضافة الربط</button>
                        <button disabled={busy} onClick={() => setPanel(null)} className="btn btn-sm btn-ghost">إلغاء</button>
                      </div>
                    </div>
                  ) : (
                    <div><button onClick={() => setPanel('link')} className="btn btn-sm btn-outline"><Icon name="plus" size={14} /> ربط عميل</button></div>
                  )}
                </>
              ) : (
                <div style={{ fontSize: '.8rem', color: 'var(--ih-text-muted)' }}>
                  الربط متاح بعد اعتماد الوكالة فقط.
                </div>
              )
            )}
          </div>
        </Sec>

        <Sec title="الأعضاء والدعوات" icon="users">
          <div className="ih-sec__body" style={{ display: 'grid', gap: '.8rem' }}>
            {members.length === 0 && invitations.length === 0 && (
              <div style={{ color: 'var(--ih-text-muted)', fontSize: '.85rem' }}>لا أعضاء بعد.</div>
            )}
            {members.map((m, i) => (
              <div key={i} style={{ display: 'flex', alignItems: 'center', gap: '.6rem' }}>
                <span className="ih-idc__av" style={{ width: 32, height: 32, fontSize: '.8rem' }}>{m.name.slice(0, 1)}</span>
                <div style={{ minWidth: 0, flex: 1 }}>
                  <div style={{ fontSize: '.84rem', fontWeight: 600 }}>{m.name}</div>
                  <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', direction: 'ltr', textAlign: 'start' }}>{m.email ?? '—'}</div>
                </div>
                <StatusBadge tone={m.statusTone} label={m.statusLabel} />
              </div>
            ))}
            {invitations.map((v, i) => (
              <div key={`inv-${i}`} style={{ display: 'flex', alignItems: 'center', gap: '.6rem', fontSize: '.8rem' }}>
                <Icon name="user-plus" size={15} />
                <span style={{ direction: 'ltr', flex: 1, minWidth: 0, overflow: 'hidden', textOverflow: 'ellipsis' }}>{v.email}</span>
                <span className="ih-tag" style={{ fontSize: '.62rem' }}>دعوة معلّقة</span>
              </div>
            ))}

            {can.manage && (
              panel === 'invite' ? (
                <div className="card" style={{ padding: '.9rem', display: 'grid', gap: '.7rem' }}>
                  <Field label="البريد" labelStyle={LBL}><input value={invite.email} onChange={(e) => setInvite({ ...invite, email: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} /><Err k="email" /><Err k="member" /></Field>
                  <Field label="الدور" labelStyle={LBL}>
                    <select value={invite.role} onChange={(e) => setInvite({ ...invite, role: e.target.value })} className="field" style={{ width: '100%' }}>
                      <option value="partner_admin">مدير الوكالة الشريكة</option>
                      <option value="partner_member">عضو</option>
                    </select>
                  </Field>
                  <div style={{ display: 'flex', gap: '.5rem' }}>
                    <button disabled={busy || !invite.email.trim()} onClick={() => post('/invite', invite, () => setInvite({ email: '', role: 'partner_member' }))} className="btn btn-sm btn-primary">إرسال الدعوة</button>
                    <button disabled={busy} onClick={() => setPanel(null)} className="btn btn-sm btn-ghost">إلغاء</button>
                  </div>
                </div>
              ) : (
                <div><button onClick={() => setPanel('invite')} className="btn btn-sm btn-outline"><Icon name="user-plus" size={14} /> دعوة عضو</button></div>
              )
            )}
          </div>
        </Sec>
      </div>

      <Sec title="سجل الحالة" icon="bar-chart-3">
        <div className="ih-sec__body">
          {history.length === 0 ? (
            <div style={{ color: 'var(--ih-text-muted)', fontSize: '.85rem' }}>لا سجل بعد.</div>
          ) : (
            <div className="ih-tl">
              {history.map((h, i) => (
                <div key={i} className="ih-tl__item">
                  <div className="ih-tl__dot" />
                  <div>
                    <div style={{ fontSize: '.85rem', fontWeight: 600 }}>{h.from} ← {h.to}</div>
                    <div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>{h.by}{h.at ? ` · ${h.at}` : ''}</div>
                    {h.reason && <div style={{ fontSize: '.78rem', color: 'var(--ih-text-muted)', marginTop: '.2rem' }}>{h.reason}</div>}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </Sec>

      {actionFor && (
        <div className="modal-backdrop" onClick={(e) => e.target === e.currentTarget && !busy && setActionFor(null)}>
          <div className="modal" style={{ padding: '1.3rem', maxWidth: 460 }}>
            <h3 style={{ fontWeight: 800, margin: '0 0 1rem' }}>{actionFor[1]}</h3>
            <textarea value={reason} onChange={(e) => setReason(e.target.value)} className="field" rows={3} style={{ width: '100%' }}
              placeholder={actionFor[0] === 'request-changes' ? 'سبب طلب التعديل (إلزامي)' : 'سبب (اختياري)'} autoFocus />
            <Err k="reason" />
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button disabled={busy || (actionFor[0] === 'request-changes' && reason.trim().length < 2)}
                onClick={() => post(`/${actionFor[0]}`, { reason })} className={`btn ${BTN[actionFor[2]] ?? 'btn-primary'}`}>تأكيد</button>
              <button disabled={busy} onClick={() => setActionFor(null)} className="btn btn-ghost">إلغاء</button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}

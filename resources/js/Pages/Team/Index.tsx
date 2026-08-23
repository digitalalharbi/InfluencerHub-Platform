import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { ListHead, StatusBadge, Kpi, Sec, Bar, Field } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';

const LBL: React.CSSProperties = { fontSize: '.8rem', fontWeight: 600, display: 'block', marginBottom: '.3rem' };

interface Member {
  id: number; name: string; email: string | null; role: string; roleLabel: string;
  status: string; statusLabel: string; statusTone: string;
  open: number; breached: number; canReview: boolean; isSelf: boolean;
}
interface Option { value: string; label: string }
interface Props {
  members: Member[];
  summary: { total: number; active: number; openWork: number; breached: number; unassigned: number };
  byRole: { role: string; label: string; count: number }[];
  canManage: boolean;
  assignableRoles: Option[];
}

export default function TeamIndex({ members, summary, byRole, canManage, assignableRoles }: Props) {
  const maxOpen = Math.max(...members.map((m) => m.open), 1);
  const errors = (usePage().props.errors ?? {}) as Record<string, string>;
  const [modal, setModal] = useState(false);
  const [busy, setBusy] = useState(false);
  const [form, setForm] = useState({ email: '', role: assignableRoles[assignableRoles.length - 1]?.value ?? 'viewer' });

  const addMember = () => {
    if (!form.email.trim()) return;
    setBusy(true);
    router.post(u('/team/invite'), form, {
      preserveScroll: true,
      onFinish: () => setBusy(false),
      onSuccess: () => { setModal(false); setForm({ ...form, email: '' }); },
    });
  };
  const setRole = (id: number, role: string) => router.post(u(`/team/${id}/role`), { role }, { preserveScroll: true });
  const setStatus = (id: number, action: string) => {
    if (action === 'remove' && !confirm('إزالة هذا العضو من مساحة العمل؟ سيفقد الوصول فورًا.')) return;
    router.post(u(`/team/${id}/status`), { action }, { preserveScroll: true });
  };

  return (
    <AppShell heading="الفريق">
      <Head title="الفريق" />
      <ListHead eyebrow="الإدارة" title="الفريق" sub="أعضاء مساحة العمل وأدوارهم وحِملهم الحالي."
        actions={canManage ? <button onClick={() => setModal(true)} className="btn btn-sm btn-primary">+ إضافة عضو</button> : undefined} />

      {errors.team && (
        <div className="card" style={{ padding: '.8rem 1rem', marginBottom: '1rem', borderInlineStart: '3px solid var(--ih-danger)', background: 'var(--ih-danger-soft)', color: 'var(--ih-danger-ink)' }}>{errors.team}</div>
      )}

      <div className="ih-kpis">
        <Kpi label="الأعضاء" icon="users" value={summary.total.toLocaleString('en-US')} sub={`${summary.active} نشط`} />
        <Kpi label="عمل مفتوح" icon="inbox" value={summary.openWork.toLocaleString('en-US')} sub="طلبات مُسنَدة" />
        <Kpi label="تجاوز SLA" icon="activity" tone={summary.breached ? 'danger' : 'success'}
          value={summary.breached.toLocaleString('en-US')} sub={summary.breached ? 'يحتاج تدخّلًا' : 'لا تجاوزات'} />
        <Kpi label="غير مُسنَد" icon="user-plus" tone={summary.unassigned ? 'warning' : undefined}
          value={summary.unassigned.toLocaleString('en-US')} sub="بانتظار إسناد" href={u("/service-requests?seg=unassigned")} />
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0,1.6fr) minmax(0,1fr)', gap: '1.1rem', alignItems: 'start' }} className="ih-settings-grid">
        <Sec title="الأعضاء" icon="users">
          {members.length === 0 ? (
            <div style={{ padding: '2rem', textAlign: 'center', color: 'var(--ih-text-muted)' }}>لا أعضاء.</div>
          ) : (
            <div style={{ display: 'grid', gap: '.5rem', padding: '.7rem' }}>
              {members.map((m) => (
                <div key={m.id} className="card" style={{ display: 'flex', alignItems: 'center', gap: '.75rem', padding: '.7rem .85rem', flexWrap: 'wrap' }}>
                  <span className="ih-idc__av" style={{ width: 36, height: 36, flexShrink: 0 }}>{m.name.slice(0, 1)}</span>
                  <div style={{ minWidth: 0, flex: 1 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '.4rem', flexWrap: 'wrap' }}>
                      <Link href={u(`/team/${m.id}`)} style={{ fontWeight: 650, fontSize: '.9rem', color: 'var(--ih-primary)' }}>{m.name}</Link>
                      {m.isSelf && <span className="ih-tag" style={{ fontSize: '.6rem' }}>أنت</span>}
                    </div>
                    <div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)', direction: 'ltr', textAlign: 'start' }}>{m.email ?? '—'}</div>
                  </div>

                  {canManage && !m.isSelf ? (
                    <select value={m.role} onChange={(e) => setRole(m.id, e.target.value)} className="field" style={{ maxWidth: 150, flexShrink: 0 }} aria-label="الدور">
                      {assignableRoles.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
                      {!assignableRoles.some((r) => r.value === m.role) && <option value={m.role}>{m.roleLabel}</option>}
                    </select>
                  ) : (
                    <span className="ih-tag" style={{ flexShrink: 0 }}>{m.roleLabel}</span>
                  )}

                  <div style={{ width: 110, flexShrink: 0 }}>
                    {m.open > 0 ? (
                      <>
                        <div style={{ fontSize: '.7rem', color: 'var(--ih-text-muted)', marginBottom: 2, display: 'flex', justifyContent: 'space-between' }}>
                          <span>{m.open} مفتوح</span>
                          {m.breached > 0 && <span style={{ color: 'var(--ih-danger-ink)', fontWeight: 700 }}>{m.breached}</span>}
                        </div>
                        <Bar pct={Math.round((m.open / maxOpen) * 100)} over={m.breached > 0} />
                      </>
                    ) : (
                      <span style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)' }}>لا عمل مفتوح</span>
                    )}
                  </div>
                  <StatusBadge tone={m.statusTone} label={m.statusLabel} />

                  {canManage && !m.isSelf && (
                    <div style={{ display: 'flex', gap: '.3rem', flexShrink: 0 }}>
                      {m.status === 'active' ? (
                        <>
                          <button onClick={() => setStatus(m.id, 'suspend')} className="btn btn-xs btn-outline">تعليق</button>
                          <button onClick={() => setStatus(m.id, 'remove')} className="btn btn-xs btn-danger">إزالة</button>
                        </>
                      ) : (
                        <>
                          <button onClick={() => setStatus(m.id, 'reactivate')} className="btn btn-xs">تفعيل</button>
                          <button onClick={() => setStatus(m.id, 'remove')} className="btn btn-xs btn-danger">إزالة</button>
                        </>
                      )}
                    </div>
                  )}
                </div>
              ))}
            </div>
          )}
        </Sec>

        <div style={{ display: 'grid', gap: '1.1rem' }}>
          <Sec title="توزيع الأدوار" icon="shield-check">
            <div className="ih-sec__body" style={{ display: 'grid', gap: '.65rem' }}>
              {byRole.map((r) => {
                const max = Math.max(...byRole.map((x) => x.count), 1);
                return (
                  <div key={r.role}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '.82rem', marginBottom: '.25rem' }}>
                      <span style={{ fontWeight: 600 }}>{r.label}</span>
                      <span style={{ color: 'var(--ih-text-muted)', direction: 'ltr' }}>{r.count}</span>
                    </div>
                    <Bar pct={Math.round((r.count / max) * 100)} />
                  </div>
                );
              })}
            </div>
          </Sec>

          <div className="card" style={{ padding: '.85rem 1rem', borderInlineStart: '3px solid var(--ih-info)', background: 'var(--ih-info-soft)', color: 'var(--ih-info-ink)', fontSize: '.82rem', lineHeight: 1.6 }}>
            <Icon name="shield-check" size={14} /> الأدوار والحالة تُغيَّر من هنا وتُطبَّق من الخادم لكل طلب — لا يكفي إخفاء الأزرار. يُحمى آخر مدير نشِط من الإزالة.
          </div>
        </div>
      </div>

      {modal && (
        <div className="modal-backdrop" onClick={(e) => e.target === e.currentTarget && !busy && setModal(false)}>
          <div className="modal" style={{ padding: '1.3rem', maxWidth: 460 }}>
            <h3 style={{ fontWeight: 800, margin: '0 0 .4rem' }}>إضافة عضو إلى مساحة العمل</h3>
            <p style={{ fontSize: '.78rem', color: 'var(--ih-text-muted)', margin: '0 0 1rem', lineHeight: 1.6 }}>
              أدخِل بريد مستخدم لديه حساب على المنصّة. إن لم يكن لديه حساب بعد، اطلب منه إنشاءه أولًا.
            </p>
            {errors.team && (
              <div className="card" style={{ padding: '.6rem .8rem', marginBottom: '.8rem', borderInlineStart: '3px solid var(--ih-danger)', background: 'var(--ih-danger-soft)', color: 'var(--ih-danger-ink)', fontSize: '.8rem' }}>{errors.team}</div>
            )}
            <div style={{ display: 'grid', gap: '.8rem' }}>
              <Field label="البريد الإلكتروني" labelStyle={LBL}>
                <input value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} placeholder="name@example.com" autoFocus />
              </Field>
              <Field label="الدور" labelStyle={LBL}>
                <select value={form.role} onChange={(e) => setForm({ ...form, role: e.target.value })} className="field" style={{ width: '100%' }}>
                  {assignableRoles.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
                </select>
              </Field>
            </div>
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button disabled={busy || !form.email.trim()} onClick={addMember} className="btn btn-primary">إضافة</button>
              <button disabled={busy} onClick={() => setModal(false)} className="btn btn-ghost">إلغاء</button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}

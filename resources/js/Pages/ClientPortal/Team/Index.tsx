import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { clientNav } from '@/lib/nav';
import { ListHead, StatusBadge, Avatar, Sec, Field } from '@/Components/ui';
import type { SharedProps } from '@/types';
import { u } from '@/lib/href';
import { useT } from '@/lib/i18n';

const LBL: React.CSSProperties = { fontSize: '.8rem', fontWeight: 600, display: 'block', marginBottom: '.3rem' };

interface Member {
  id: number; name: string; email: string | null; role: string; roleLabel: string;
  status: string; statusLabel: string; statusTone: string; isMe: boolean;
}
interface Invite { id: number; email: string; role: string; roleLabel: string; expires: string | null }
interface Option { value: string; label: string }
interface Props { clientName: string; members: Member[]; invites: Invite[]; canManage: boolean; roles: Option[] }

export default function ClientTeamIndex({ clientName, members, invites, canManage, roles }: Props) {
  const t = useT();
  const inviteToken = usePage<SharedProps>().props.flash?.inviteToken ?? null;
  const [modal, setModal] = useState(false);
  const [busy, setBusy] = useState(false);
  const [form, setForm] = useState({ email: '', role: 'client_member' });

  const invite = () => {
    if (!form.email.trim()) return;
    setBusy(true);
    router.post(u('/team/invite'), form, { preserveScroll: true, onFinish: () => setBusy(false), onSuccess: () => { setModal(false); setForm({ email: '', role: 'client_member' }); } });
  };
  const setRole = (id: number, role: string) => router.post(u(`/team/${id}/role`), { role }, { preserveScroll: true });
  const setStatus = (id: number, action: string) => router.post(u(`/team/${id}/status`), { action }, { preserveScroll: true });

  return (
    <AppShell heading={t('client_portal.team_title')} nav={clientNav} portal="client" wsName={clientName} wsPlan={t('client_portal.ws_plan')}>
      <Head title={t('client_portal.team_head_title')} />
      <ListHead eyebrow={t('client_portal.eyebrow')} title={t('client_portal.team_title')} sub={t('client_portal.team_sub')}
        actions={canManage ? <button onClick={() => setModal(true)} className="btn btn-sm">{t('client_portal.invite_member')}</button> : undefined} />

      {inviteToken && (
        <div className="card" style={{ padding: '.9rem 1rem', marginBottom: '1rem', borderInlineStart: '3px solid var(--ih-warning)', background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)' }}>
          <div style={{ fontWeight: 700, marginBottom: '.3rem' }}>{t('client_portal.invite_token_title')}</div>
          <div style={{ fontSize: '.8rem', marginBottom: '.5rem' }}>{t('client_portal.invite_token_hint')}</div>
          <code style={{ direction: 'ltr', display: 'block', wordBreak: 'break-all', fontSize: '.86rem', fontWeight: 700 }}>{inviteToken}</code>
        </div>
      )}

      <Sec title={t('client_portal.sec_members')} icon="users">
        <div className="ih-dt-wrap"><div className="ih-dt-scroll">
          <table className="ih-dt">
            <thead><tr><th>{t('client_portal.th_member')}</th><th>{t('client_portal.th_role')}</th><th>{t('client_portal.th_status')}</th>{canManage && <th>{t('client_portal.th_actions')}</th>}</tr></thead>
            <tbody>
              {members.map((m) => (
                <tr key={m.id}>
                  <td>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '.5rem' }}>
                      <Avatar name={m.name} round />
                      <div>
                        <div style={{ fontWeight: 600 }}>{m.name}{m.isMe && <span style={{ fontSize: '.7rem', color: 'var(--ih-text-muted)' }}>{t('client_portal.you')}</span>}</div>
                        <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{m.email}</div>
                      </div>
                    </div>
                  </td>
                  <td>
                    {canManage && !m.isMe ? (
                      <select value={m.role} onChange={(e) => setRole(m.id, e.target.value)} className="field" style={{ maxWidth: 160 }}>
                        {roles.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
                      </select>
                    ) : m.roleLabel}
                  </td>
                  <td><StatusBadge tone={m.statusTone} label={m.statusLabel} /></td>
                  {canManage && (
                    <td>
                      {m.isMe ? <span style={{ fontSize: '.75rem', color: 'var(--ih-text-muted)' }}>—</span> : m.status === 'active' ? (
                        <div style={{ display: 'flex', gap: '.3rem' }}>
                          <button onClick={() => setStatus(m.id, 'suspend')} className="btn btn-xs btn-outline">{t('client_portal.suspend')}</button>
                          <button onClick={() => setStatus(m.id, 'revoke')} className="btn btn-xs btn-danger">{t('client_portal.remove')}</button>
                        </div>
                      ) : m.status === 'suspended' ? (
                        <button onClick={() => setStatus(m.id, 'reactivate')} className="btn btn-xs">{t('client_portal.activate')}</button>
                      ) : <span style={{ fontSize: '.75rem', color: 'var(--ih-text-muted)' }}>—</span>}
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div></div>
      </Sec>

      {invites.length > 0 && (
        <Sec title={t('client_portal.sec_pending_invites')} icon="user-plus">
          <div className="ih-dt-wrap"><div className="ih-dt-scroll">
            <table className="ih-dt">
              <thead><tr><th>{t('client_portal.th_email')}</th><th>{t('client_portal.th_role')}</th><th>{t('client_portal.th_expires')}</th></tr></thead>
              <tbody>
                {invites.map((i) => (
                  <tr key={i.id}>
                    <td style={{ direction: 'ltr' }}>{i.email}</td>
                    <td>{i.roleLabel}</td>
                    <td style={{ direction: 'ltr', fontSize: '.8rem', color: 'var(--ih-text-muted)' }}>{i.expires ?? '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div></div>
        </Sec>
      )}

      {modal && (
        <div className="modal-backdrop" onClick={(e) => e.target === e.currentTarget && !busy && setModal(false)}>
          <div className="modal" style={{ padding: '1.3rem', maxWidth: 460 }}>
            <h3 style={{ fontWeight: 800, margin: '0 0 1rem' }}>{t('client_portal.invite_modal_title')}</h3>
            <div style={{ display: 'grid', gap: '.8rem' }}>
              <Field label={t('client_portal.f_email')} labelStyle={LBL}>
                <input value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} placeholder="name@example.com" autoFocus />
              </Field>
              <Field label={t('client_portal.f_role')} labelStyle={LBL}>
                <select value={form.role} onChange={(e) => setForm({ ...form, role: e.target.value })} className="field" style={{ width: '100%' }}>
                  {roles.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
                </select>
              </Field>
            </div>
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button disabled={busy || !form.email.trim()} onClick={invite} className="btn btn-primary">{t('client_portal.send_invite')}</button>
              <button disabled={busy} onClick={() => setModal(false)} className="btn btn-ghost">{t('client_portal.cancel')}</button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}

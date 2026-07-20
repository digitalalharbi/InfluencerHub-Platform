import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { ListHead, StatusBadge, WorkTabs } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { u } from '@/lib/href';

interface Change { field: string; old: string | null; value: string }
interface CR { id: number; client: string | null; status: string; statusLabel: string; statusTone: string; changes: Change[]; at: string | null }
interface Doc { id: number; title: string; client: string | null; category: string; name: string | null; at: string | null }
interface Props { changeRequests: Paginated<CR>; documents: Paginated<Doc>; tab: string }

type Pending = { kind: 'profile-reject' | 'doc'; id: number; decision?: string };

export default function ClientReviewsIndex({ changeRequests, documents, tab: initialTab }: Props) {
  const [tab, setTab] = useState(initialTab);
  const [modal, setModal] = useState<Pending | null>(null);
  const [note, setNote] = useState('');
  const [busy, setBusy] = useState(false);

  const approveProfile = (id: number) => { setBusy(true); router.post(u(`/client-reviews/profile/${id}/approve`), {}, { preserveScroll: true, onFinish: () => setBusy(false) }); };
  const submitModal = () => {
    if (!modal) return;
    setBusy(true);
    if (modal.kind === 'profile-reject') {
      if (note.trim().length < 2) { setBusy(false); return; }
      router.post(u(`/client-reviews/profile/${modal.id}/reject`), { note }, { preserveScroll: true, onFinish: () => setBusy(false), onSuccess: () => { setModal(null); setNote(''); } });
    } else {
      const needsNote = modal.decision === 'changes_requested' || modal.decision === 'rejected';
      if (needsNote && note.trim().length < 2) { setBusy(false); return; }
      router.post(u(`/client-reviews/documents/${modal.id}/review`), { decision: modal.decision, note: note || null }, { preserveScroll: true, onFinish: () => setBusy(false), onSuccess: () => { setModal(null); setNote(''); } });
    }
  };

  return (
    <AppShell heading="مراجعات العملاء">
      <Head title="مراجعات العملاء" />
      <ListHead eyebrow="المراجعات والامتثال" title="مراجعات العملاء" sub="اعتمد تعديلات الملف القانوني وراجع مستندات العملاء." />

      <WorkTabs active={tab} onChange={setTab} tabs={[
        { key: 'profile', label: 'تعديلات الملف', icon: 'clipboard-check', count: changeRequests.total },
        { key: 'documents', label: 'المستندات', icon: 'file-text', count: documents.total },
      ]} />

      {tab === 'profile' && (
        changeRequests.data.length === 0 ? (
          <div className="card" style={{ padding: '2rem', textAlign: 'center', color: 'var(--ih-text-muted)' }}>لا طلبات تعديل معلّقة.</div>
        ) : (
          <>
            <div style={{ display: 'grid', gap: '1rem' }}>
              {changeRequests.data.map((cr) => (
                <div key={cr.id} className="ih-sec">
                  <div className="ih-sec__head">
                    <span className="ih-sec__title">{cr.client ?? '—'} <StatusBadge tone={cr.statusTone} label={cr.statusLabel} /></span>
                    <span style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{cr.at}</span>
                  </div>
                  <div className="ih-sec__body">
                    <div style={{ display: 'grid', gap: '.4rem', marginBottom: '.8rem' }}>
                      {cr.changes.map((c, i) => (
                        <div key={i} style={{ display: 'flex', justifyContent: 'space-between', gap: '1rem', fontSize: '.82rem', borderBottom: '1px solid var(--ih-border)', paddingBottom: '.35rem' }}>
                          <span style={{ color: 'var(--ih-text-muted)', direction: 'ltr' }}>{c.field}</span>
                          <span style={{ fontWeight: 600, textAlign: 'end' }}>
                            {c.old && <span style={{ textDecoration: 'line-through', color: 'var(--ih-text-muted)', fontWeight: 400, marginInlineEnd: '.4rem' }}>{c.old}</span>}
                            {c.value}
                          </span>
                        </div>
                      ))}
                    </div>
                    <div style={{ display: 'flex', gap: '.5rem' }}>
                      <button disabled={busy} onClick={() => approveProfile(cr.id)} className="btn btn-sm">اعتماد</button>
                      <button disabled={busy} onClick={() => { setModal({ kind: 'profile-reject', id: cr.id }); setNote(''); }} className="btn btn-sm btn-outline">رفض</button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
            <Pagination links={changeRequests.links} />
          </>
        )
      )}

      {tab === 'documents' && (
        documents.data.length === 0 ? (
          <div className="card" style={{ padding: '2rem', textAlign: 'center', color: 'var(--ih-text-muted)' }}>لا مستندات بانتظار المراجعة.</div>
        ) : (
          <>
            {/* طابور مراجعة مستندات — الإجراء أمام كل عنصر */}
            <div className="ih-triage" style={{ marginBottom: '1rem' }}>
              {documents.data.map((d) => (
                <div key={d.id} className="ih-trow ih-trow--new" style={{ cursor: 'default', flexWrap: 'wrap' }}>
                  <span style={{ color: 'var(--ih-gray-400)', flexShrink: 0 }}><Icon name="file-text" size={18} /></span>
                  <div style={{ minWidth: 0, flex: 1 }}>
                    <div style={{ fontWeight: 650, fontSize: '.88rem' }}>{d.title}</div>
                    <div style={{ fontSize: '.73rem', color: 'var(--ih-text-muted)' }}>
                      {d.client ?? '—'} · <span style={{ direction: 'ltr' }}>{d.category}</span> · <span style={{ direction: 'ltr' }}>{d.at}</span>
                      {d.name && <> · <span style={{ direction: 'ltr' }}>{d.name}</span></>}
                    </div>
                  </div>
                  <div style={{ display: 'flex', gap: '.3rem', flexShrink: 0 }}>
                    <button disabled={busy} onClick={() => { setModal({ kind: 'doc', id: d.id, decision: 'approved' }); setNote(''); }} className="btn btn-xs">اعتماد</button>
                    <button disabled={busy} onClick={() => { setModal({ kind: 'doc', id: d.id, decision: 'changes_requested' }); setNote(''); }} className="btn btn-xs btn-outline">تعديل</button>
                    <button disabled={busy} onClick={() => { setModal({ kind: 'doc', id: d.id, decision: 'rejected' }); setNote(''); }} className="btn btn-xs btn-danger">رفض</button>
                  </div>
                </div>
              ))}
            </div>
            <Pagination links={documents.links} />
          </>
        )
      )}

      {modal && (
        <div className="modal-backdrop" onClick={(e) => e.target === e.currentTarget && !busy && setModal(null)}>
          <div className="modal" style={{ padding: '1.3rem', maxWidth: 440 }}>
            <h3 style={{ fontWeight: 800, margin: '0 0 1rem' }}>
              {modal.kind === 'profile-reject' ? 'رفض طلب التعديل' : modal.decision === 'approved' ? 'اعتماد المستند' : modal.decision === 'changes_requested' ? 'طلب تعديل المستند' : 'رفض المستند'}
            </h3>
            <textarea value={note} onChange={(e) => setNote(e.target.value)} className="field" rows={3}
              placeholder={modal.kind === 'profile-reject' || modal.decision !== 'approved' ? 'السبب (إلزامي)' : 'ملاحظة (اختياري)'}
              style={{ width: '100%', resize: 'vertical' }} autoFocus />
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button disabled={busy || ((modal.kind === 'profile-reject' || modal.decision !== 'approved') && note.trim().length < 2)} onClick={submitModal}
                className={`btn ${modal.decision === 'rejected' || modal.kind === 'profile-reject' ? 'btn-danger' : 'btn-primary'}`}>تأكيد</button>
              <button disabled={busy} onClick={() => setModal(null)} className="btn btn-ghost">إلغاء</button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}

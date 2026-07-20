import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { clientNav } from '@/lib/nav';
import { WorkspaceHeader, Sec, StatusBadge } from '@/Components/ui';
import { u } from '@/lib/href';

interface RequestT {
  id: number; number: string; title: string; type: string; typeLabel: string;
  priority: string; priorityLabel: string; status: string; statusLabel: string; statusTone: string;
  assignee: string | null; description: string | null; brand: string | null; createdAt: string | null; dueAt: string | null; isOpen: boolean;
}
interface Comment { id: number; author: string; authorType: string; body: string; at: string | null }
interface History { to: string; tone: string; actor: string; note: string | null; at: string | null }
interface Props { clientName: string; request: RequestT; comments: Comment[]; history: History[] }

export default function ClientRequestShow({ clientName, request, comments, history }: Props) {
  const [body, setBody] = useState('');
  const [busy, setBusy] = useState(false);

  const send = () => {
    if (!body.trim()) return;
    setBusy(true);
    router.post(u(`/requests/${request.id}/comment`), { body }, {
      preserveScroll: true,
      onFinish: () => setBusy(false),
      onSuccess: () => setBody(''),
    });
  };

  return (
    <AppShell heading="طلب خدمة" nav={clientNav} portal="client" wsName={clientName} wsPlan="بوابة العميل">
      <Head title={request.title} />

      <WorkspaceHeader
        eyebrow={`طلب · ${request.number}`}
        title={request.title}
        statusTone={request.statusTone} statusLabel={request.statusLabel}
        back={u("/requests")} backLabel="الطلبات"
        meta={[
          ['النوع', request.typeLabel], ['الأولوية', request.priorityLabel],
          ['المسؤول', request.assignee ?? 'قيد الإسناد'],
          ...(request.brand ? [['العلامة', request.brand] as [string, string]] : []),
        ]}
      />

      <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0, 1.4fr) minmax(0, 1fr)', gap: '1.2rem', alignItems: 'start' }} className="ih-settings-grid">
        <div style={{ display: 'grid', gap: '1.2rem' }}>
          {request.description && (
            <Sec title="التفاصيل" icon="inbox">
              <div style={{ whiteSpace: 'pre-wrap', fontSize: '.9rem', lineHeight: 1.8 }}>{request.description}</div>
            </Sec>
          )}

          <Sec title="المحادثة" icon="clipboard-check">
            {comments.length === 0 ? (
              <div style={{ fontSize: '.84rem', color: 'var(--ih-text-muted)', marginBottom: '.9rem' }}>لا رسائل بعد — اكتب أول رسالة للوكالة.</div>
            ) : (
              <div style={{ display: 'grid', gap: '.7rem', marginBottom: '1rem' }}>
                {comments.map((cm) => {
                  const mine = cm.authorType === 'client';
                  return (
                    <div key={cm.id} style={{ background: mine ? 'var(--ih-primary-50, var(--ih-primary-100))' : 'var(--ih-surface-2, var(--ih-gray-100))', borderRadius: 10, padding: '.7rem .9rem', maxWidth: '85%', marginInlineStart: mine ? 'auto' : 0 }}>
                      <div style={{ display: 'flex', gap: '.5rem', alignItems: 'baseline', marginBottom: '.2rem' }}>
                        <span style={{ fontSize: '.76rem', fontWeight: 700 }}>{cm.author}</span>
                        {cm.at && <span style={{ fontSize: '.68rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{cm.at}</span>}
                      </div>
                      <div style={{ fontSize: '.86rem', whiteSpace: 'pre-wrap', lineHeight: 1.6 }}>{cm.body}</div>
                    </div>
                  );
                })}
              </div>
            )}
            {request.isOpen ? (
              <div style={{ display: 'flex', gap: '.5rem', alignItems: 'flex-end' }}>
                <textarea value={body} onChange={(e) => setBody(e.target.value)} className="field" rows={2}
                  placeholder="اكتب رسالة…" style={{ flex: 1, resize: 'vertical' }} />
                <button disabled={busy || !body.trim()} onClick={send} className="btn btn-sm">إرسال</button>
              </div>
            ) : (
              <div style={{ fontSize: '.82rem', color: 'var(--ih-text-muted)' }}>الطلب مُغلق — لا يمكن إضافة رسائل جديدة.</div>
            )}
          </Sec>
        </div>

        <Sec title="سجل الحالة" icon="clipboard-check">
          {history.length === 0 ? (
            <div style={{ fontSize: '.84rem', color: 'var(--ih-text-muted)' }}>لا سجل بعد.</div>
          ) : (
            <div style={{ display: 'grid', gap: '.7rem' }}>
              {history.map((h, i) => (
                <div key={i} style={{ borderInlineStart: '2px solid var(--ih-border)', paddingInlineStart: '.7rem' }}>
                  <div style={{ display: 'flex', gap: '.4rem', alignItems: 'center', flexWrap: 'wrap' }}>
                    <StatusBadge tone={h.tone} label={h.to} />
                    <span style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>{h.actor}</span>
                    {h.at && <span style={{ fontSize: '.7rem', color: 'var(--ih-text-muted)', direction: 'ltr', marginInlineStart: 'auto' }}>{h.at}</span>}
                  </div>
                  {h.note && <div style={{ fontSize: '.78rem', color: 'var(--ih-text-muted)', marginTop: '.2rem' }}>{h.note}</div>}
                </div>
              ))}
            </div>
          )}
          {request.dueAt && (
            <div style={{ marginTop: '1rem', fontSize: '.78rem', color: 'var(--ih-text-muted)', display: 'flex', justifyContent: 'space-between' }}>
              <span>الاستحقاق</span><span style={{ direction: 'ltr', fontWeight: 600 }}>{request.dueAt}</span>
            </div>
          )}
        </Sec>
      </div>
    </AppShell>
  );
}

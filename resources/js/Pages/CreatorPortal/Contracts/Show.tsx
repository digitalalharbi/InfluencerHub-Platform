import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { creatorNav } from '@/lib/nav';
import { WorkspaceHeader, Sec, StatusBadge, Field } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';

interface Contract {
  id: number; number: string; title: string; campaign: string | null; campaignName: string | null;
  valueMinor: number; currency: string; status: string; statusLabel: string; statusTone: string;
  terms: string | null; startDate: string | null; endDate: string | null; signedByName: string | null; signedAt: string | null;
}
interface History { to: string; tone: string; actor: string; note: string | null; at: string | null }
interface Props { creatorName: string; contract: Contract; history: History[]; isPending: boolean }

const money = (m: number, cur: string) => (m / 100).toLocaleString('en-US') + ' ' + cur;

export default function CreatorContractShow({ creatorName, contract, history, isPending }: Props) {
  const [modal, setModal] = useState(false);
  const [name, setName] = useState('');
  const [agree, setAgree] = useState(false);
  const [busy, setBusy] = useState(false);

  const sign = () => {
    if (!name.trim() || !agree) return;
    setBusy(true);
    router.post(u(`/contracts/${contract.id}/sign`), { signer_name: name, agree: true }, {
      preserveScroll: true, onFinish: () => setBusy(false), onSuccess: () => setModal(false),
    });
  };

  return (
    <AppShell heading="عقد" nav={creatorNav} portal="creator" wsName={creatorName} wsPlan="بوابة المبدع">
      <Head title={contract.title} />

      <WorkspaceHeader
        eyebrow={`عقد · ${contract.number}`}
        title={contract.title}
        statusTone={contract.statusTone} statusLabel={contract.statusLabel}
        back={u("/contracts")} backLabel="العقود"
        meta={[
          ['الحملة', contract.campaign ?? '—'],
          ['القيمة', contract.valueMinor ? money(contract.valueMinor, contract.currency) : '—'],
          ['البداية', contract.startDate ?? '—'], ['النهاية', contract.endDate ?? '—'],
        ]}
        actions={isPending ? <button disabled={busy} onClick={() => setModal(true)} className="btn btn-sm">مراجعة وقبول العقد</button> : undefined}
      />

      {contract.signedByName && (
        <div className="card" style={{ padding: '.8rem 1rem', marginBottom: '1.2rem', borderInlineStart: '3px solid var(--ih-success)', background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)', fontSize: '.84rem' }}>
          <Icon name="shield-check" size={14} /> قُبِل بواسطة <b>{contract.signedByName}</b>{contract.signedAt && <span style={{ direction: 'ltr' }}> · {contract.signedAt}</span>}
        </div>
      )}

      <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0, 1.4fr) minmax(0, 1fr)', gap: '1.2rem', alignItems: 'start' }} className="ih-settings-grid">
        <Sec title="بنود العقد" icon="file-text">
          <div className="card" style={{ padding: '1rem 1.1rem', whiteSpace: 'pre-wrap', fontSize: '.9rem', lineHeight: 1.8, minHeight: 100 }}>
            {contract.terms || <span style={{ color: 'var(--ih-text-muted)' }}>لا بنود مُدرجة.</span>}
          </div>
        </Sec>

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
        </Sec>
      </div>

      {modal && (
        <div className="modal-backdrop" onClick={(e) => e.target === e.currentTarget && !busy && setModal(false)}>
          <div className="modal" style={{ padding: '1.3rem', maxWidth: 480 }}>
            <h3 style={{ fontWeight: 800, margin: '0 0 .4rem' }}>قبول العقد</h3>
            <p style={{ fontSize: '.84rem', color: 'var(--ih-text-muted)', margin: '0 0 1rem' }}>بإدخال اسمك وتأكيد الموافقة، تُسجّل قبولك لهذا العقد رسميًا.</p>
            <Field label="الاسم الكامل" labelStyle={{ fontSize: '.82rem', fontWeight: 600, display: 'block', marginBottom: '.3rem' }}>
              <input value={name} onChange={(e) => setName(e.target.value)} className="field" placeholder="اسمك الكامل" style={{ width: '100%' }} autoFocus />
            </Field>
            <label style={{ display: 'flex', gap: '.5rem', alignItems: 'flex-start', marginTop: '.9rem', fontSize: '.84rem', cursor: 'pointer' }}>
              <input type="checkbox" checked={agree} onChange={(e) => setAgree(e.target.checked)} style={{ marginTop: '.2rem' }} />
              <span>أوافق على بنود هذا العقد وأقبله.</span>
            </label>
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button disabled={busy || !name.trim() || !agree} onClick={sign} className="btn btn-primary">تأكيد القبول</button>
              <button disabled={busy} onClick={() => setModal(false)} className="btn btn-ghost">إلغاء</button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}

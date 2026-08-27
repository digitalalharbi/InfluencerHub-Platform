import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { clientNav } from '@/lib/nav';
import { WorkspaceHeader, StatusBadge } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';

interface Item {
  id: number; creator: string; handle: string | null; platform: string | null; followers: number;
  isBackup: boolean; feeMinor: number; score: number; reasons: string[];
  decision: string; decisionLabel: string; decisionTone: string;
  avatar: string | null; categories: string[];
}
interface Props {
  clientName: string;
  campaign: { id: number; name: string; number: string };
  version: { version: number; status: string } | null;
  items: Item[];
}

const money = (m: number) => (m / 100).toLocaleString('en-US') + ' ر.س';
const fmt = (n: number) => n >= 1000 ? Math.round(n / 1000) + 'K' : n.toLocaleString('en-US');

function ScorePill({ score }: { score: number }) {
  const tone = score >= 75 ? 'success' : score >= 50 ? 'warning' : 'danger';
  const bg = { success: 'var(--ih-success-soft)', warning: 'var(--ih-warning-soft)', danger: 'var(--ih-danger-soft)' }[tone];
  const fg = { success: 'var(--ih-success-ink)', warning: 'var(--ih-warning-ink)', danger: 'var(--ih-danger-ink)' }[tone];
  return <span className="badge" style={{ background: bg, color: fg, direction: 'ltr' }}>{score}٪ ملاءمة</span>;
}

type ClientDecision = 'approved' | 'rejected' | 'needs_alternative';

export default function ClientShortlist({ clientName, campaign, version, items }: Props) {
  const [rejectFor, setRejectFor] = useState<number | null>(null);
  const [modalDecision, setModalDecision] = useState<'rejected' | 'needs_alternative'>('rejected');
  const [reason, setReason] = useState('');
  const [busy, setBusy] = useState(false);
  const base = u(`/campaigns/${campaign.id}/shortlist`);

  const decide = (itemId: number, decision: ClientDecision, r = '') => {
    setBusy(true);
    router.post(`${base}/items/${itemId}/decision`, { decision, reason: r }, {
      preserveScroll: true,
      onFinish: () => { setBusy(false); setRejectFor(null); setReason(''); },
    });
  };
  const openModal = (itemId: number, decision: 'rejected' | 'needs_alternative') => { setModalDecision(decision); setRejectFor(itemId); };

  const pending = items.filter((i) => i.decision === 'pending');

  return (
    <AppShell heading="قرار الترشيح" nav={clientNav} portal="client" wsName={clientName} wsPlan="بوابة العميل">
      <Head title={`ترشيح · ${campaign.name}`} />

      <WorkspaceHeader
        eyebrow={`ترشيح · حملة ${campaign.number}`}
        title={campaign.name}
        statusTone={version ? 'submitted' : 'draft'}
        statusLabel={version ? `إصدار ${version.version}` : 'لا ترشيح'}
        back={u(`/campaigns/${campaign.id}`)} backLabel="الحملة"
        meta={[['بانتظار قرارك', `${pending.length} مؤثر`]]}
      />

      {!version || items.length === 0 ? (
        <div className="card" style={{ padding: '2rem', textAlign: 'center', color: 'var(--ih-text-muted)' }}>
          <Icon name="users" size={22} /><div style={{ marginTop: '.5rem' }}>لا توجد قائمة ترشيح مُرسَلة لهذه الحملة بعد.</div>
        </div>
      ) : (
        <>
          <div className="card" style={{ padding: '.8rem 1rem', marginBottom: '1.2rem', borderInlineStart: '3px solid var(--ih-info)', background: 'var(--ih-info-soft)', color: 'var(--ih-info-ink)', fontSize: '.84rem' }}>
            <Icon name="clipboard-check" size={14} /> راجع المؤثرين المقترحين واعتمد أو ارفض كلًّا منهم. قرارك يصل فريق الوكالة فورًا.
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))', gap: '1rem' }}>
            {items.map((it) => (
              <div key={it.id} className="ih-sec" style={{ opacity: it.decision === 'rejected' ? 0.7 : 1 }}>
                <div className="ih-sec__head">
                  <span className="ih-sec__title" style={{ display: 'flex', alignItems: 'center', gap: '.55rem' }}>
                    <span className="ih-idc__av" style={{ width: 38, height: 38, flexShrink: 0, overflow: 'hidden' }} aria-hidden={!!it.avatar}>
                      {it.avatar ? <img src={it.avatar} alt="" style={{ width: '100%', height: '100%', objectFit: 'cover' }} /> : it.creator.slice(0, 1)}
                    </span>
                    {it.creator}
                    {it.isBackup && <span className="ih-tag" style={{ fontSize: '.64rem' }}>احتياط</span>}
                  </span>
                  <ScorePill score={it.score} />
                </div>
                <div className="ih-sec__body">
                  <div style={{ display: 'flex', gap: '.8rem', fontSize: '.76rem', color: 'var(--ih-text-muted)', marginBottom: '.6rem', direction: 'ltr', justifyContent: 'flex-end', alignItems: 'center' }}>
                    <span>{it.platform ?? '—'}</span>
                    <span>{fmt(it.followers)} متابع</span>
                    {it.feeMinor > 0
                      ? <span style={{ fontWeight: 600, color: 'var(--ih-text)' }}>{money(it.feeMinor)}</span>
                      : <span className="ih-tag" style={{ color: 'var(--ih-warning-ink)' }}>السعر غير مضاف</span>}
                  </div>
                  {it.categories.length > 0 && (
                    <div style={{ display: 'flex', gap: '.3rem', flexWrap: 'wrap', marginBottom: '.6rem' }}>
                      {it.categories.slice(0, 4).map((cat, i) => <span key={i} className="ih-tag" style={{ fontSize: '.64rem' }}>{cat}</span>)}
                    </div>
                  )}
                  {it.reasons.length > 0 && (
                    <div style={{ display: 'flex', gap: '.3rem', flexWrap: 'wrap', marginBottom: '.8rem' }}>
                      {it.reasons.map((r, i) => <span key={i} className="ih-tag" style={{ fontSize: '.66rem' }}>{r}</span>)}
                    </div>
                  )}
                  {it.decision === 'pending' ? (
                    <div style={{ display: 'flex', gap: '.4rem', flexWrap: 'wrap' }}>
                      <button disabled={busy} onClick={() => decide(it.id, 'approved')} className="btn btn-xs" style={{ flex: 1 }}>اعتماد</button>
                      <button disabled={busy} onClick={() => openModal(it.id, 'needs_alternative')} className="btn btn-xs btn-outline" style={{ flex: 1 }}>أحتاج بديلًا</button>
                      <button disabled={busy} onClick={() => openModal(it.id, 'rejected')} className="btn btn-xs btn-outline" style={{ flex: 1 }}>رفض</button>
                    </div>
                  ) : (
                    <StatusBadge tone={it.decisionTone} label={it.decisionLabel} />
                  )}
                </div>
              </div>
            ))}
          </div>
        </>
      )}

      {rejectFor !== null && (
        <div className="modal-backdrop" onClick={(e) => e.target === e.currentTarget && !busy && setRejectFor(null)}>
          <div className="modal" style={{ padding: '1.3rem', maxWidth: 440 }}>
            <h3 style={{ fontWeight: 800, margin: '0 0 1rem' }}>
              {modalDecision === 'needs_alternative' ? 'طلب بديل (سبب اختياري)' : 'سبب الرفض (اختياري)'}
            </h3>
            <textarea value={reason} onChange={(e) => setReason(e.target.value)} className="field" rows={3}
              placeholder={modalDecision === 'needs_alternative' ? 'ما الذي تريده في البديل؟ يساعد الفريق على اقتراح مرشّح أنسب…' : 'اذكر سبب الرفض ليساعد الفريق في بديل أنسب…'}
              style={{ width: '100%', resize: 'vertical' }} autoFocus />
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button disabled={busy} onClick={() => decide(rejectFor, modalDecision, reason)} className="btn btn-primary">
                {modalDecision === 'needs_alternative' ? 'طلب بديل' : 'تأكيد الرفض'}
              </button>
              <button disabled={busy} onClick={() => setRejectFor(null)} className="btn btn-ghost">إلغاء</button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}

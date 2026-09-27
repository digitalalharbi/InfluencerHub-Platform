import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { clientNav } from '@/lib/nav';
import { WorkspaceHeader, Sec, StatusBadge, Field } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';
import { useT } from '@/lib/i18n';

const LBL: React.CSSProperties = { fontSize: '.8rem', fontWeight: 600, display: 'block', marginBottom: '.3rem' };

interface Brand {
  id: number; name: string; sector: string | null; status: string; statusLabel: string; statusTone: string;
  website: string | null; description: string | null; toneOfVoice: string | null; targetAudience: string | null;
  preferredLanguage: string | null; changesReason: string | null;
}
interface History { to: string; tone: string; actor: string; note: string | null; at: string | null }
interface Props { clientName: string; brand: Brand; history: History[]; canManage: boolean; editable: boolean }

function ReadRow({ label, value }: { label: string; value: string | null }) {
  return (
    <div style={{ borderBottom: '1px solid var(--ih-border)', paddingBottom: '.5rem' }}>
      <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', marginBottom: '.15rem' }}>{label}</div>
      <div style={{ fontSize: '.88rem' }}>{value || <span style={{ color: 'var(--ih-text-muted)' }}>—</span>}</div>
    </div>
  );
}

export default function ClientBrandShow({ clientName, brand, history, canManage, editable }: Props) {
  const t = useT();
  const [edit, setEdit] = useState(false);
  const [busy, setBusy] = useState(false);
  const [form, setForm] = useState({
    name: brand.name, sector: brand.sector ?? '', website: brand.website ?? '', description: brand.description ?? '',
    tone_of_voice: brand.toneOfVoice ?? '', target_audience: brand.targetAudience ?? '', preferred_language: brand.preferredLanguage ?? '',
  });

  const save = () => {
    setBusy(true);
    router.post(u(`/brands/${brand.id}/update`), form, { preserveScroll: true, onFinish: () => setBusy(false), onSuccess: () => setEdit(false) });
  };
  const submit = () => {
    setBusy(true);
    router.post(u(`/brands/${brand.id}/submit`), {}, { preserveScroll: true, onFinish: () => setBusy(false) });
  };

  return (
    <AppShell heading={t('client_portal.brand_heading')} nav={clientNav} portal="client" wsName={clientName} wsPlan={t('client_portal.ws_plan')}>
      <Head title={brand.name} />

      <WorkspaceHeader
        eyebrow={t('client_portal.brand_heading')}
        title={brand.name}
        statusTone={brand.statusTone} statusLabel={brand.statusLabel}
        back={u("/brands")} backLabel={t('client_portal.my_brands')}
        meta={[[t('client_portal.f_sector'), brand.sector ?? '—'], [t('client_portal.lang_label'), brand.preferredLanguage ?? '—']]}
        actions={canManage && editable ? (
          <>
            {!edit && <button disabled={busy} onClick={() => setEdit(true)} className="btn btn-sm btn-outline">{t('client_portal.edit')}</button>}
            <button disabled={busy} onClick={submit} className="btn btn-sm">{t('client_portal.submit_review')}</button>
          </>
        ) : undefined}
      />

      {brand.status === 'changes_requested' && brand.changesReason && (
        <div className="card" style={{ padding: '.8rem 1rem', marginBottom: '1.2rem', borderInlineStart: '3px solid var(--ih-warning)', background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)', fontSize: '.84rem' }}>
          <Icon name="clipboard-check" size={14} /> {t('client_portal.changes_label')} {brand.changesReason}
        </div>
      )}

      <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0, 1.4fr) minmax(0, 1fr)', gap: '1.2rem', alignItems: 'start' }} className="ih-settings-grid">
        <Sec title={t('client_portal.sec_profile')} icon="bookmark">
          {edit ? (
            <div style={{ display: 'grid', gap: '.8rem' }}>
              {([['name', t('client_portal.f_name_short')], ['sector', t('client_portal.f_sector')], ['website', t('client_portal.f_website')], ['target_audience', t('client_portal.f_audience')], ['tone_of_voice', t('client_portal.f_tone')], ['preferred_language', t('client_portal.f_language')]] as const).map(([k, label]) => (
                <Field key={k} label={label} labelStyle={LBL}>
                  <input value={(form as Record<string, string>)[k]} onChange={(e) => setForm({ ...form, [k]: e.target.value })} className="field" style={{ width: '100%', direction: k === 'website' ? 'ltr' : undefined }} />
                </Field>
              ))}
              <Field label={t('client_portal.f_desc')} labelStyle={LBL}>
                <textarea value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} className="field" rows={4} style={{ width: '100%', resize: 'vertical' }} />
              </Field>
              <div style={{ display: 'flex', gap: '.5rem' }}>
                <button disabled={busy || !form.name.trim()} onClick={save} className="btn btn-sm btn-primary">{t('client_portal.save')}</button>
                <button disabled={busy} onClick={() => setEdit(false)} className="btn btn-sm btn-ghost">{t('client_portal.cancel')}</button>
              </div>
            </div>
          ) : (
            <div style={{ display: 'grid', gap: '.7rem' }}>
              <ReadRow label={t('client_portal.f_website')} value={brand.website} />
              <ReadRow label={t('client_portal.f_audience')} value={brand.targetAudience} />
              <ReadRow label={t('client_portal.f_tone')} value={brand.toneOfVoice} />
              <ReadRow label={t('client_portal.f_desc')} value={brand.description} />
            </div>
          )}
        </Sec>

        <Sec title={t('client_portal.sec_history')} icon="clipboard-check">
          {history.length === 0 ? (
            <div style={{ fontSize: '.84rem', color: 'var(--ih-text-muted)' }}>{t('client_portal.no_history')}</div>
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
    </AppShell>
  );
}

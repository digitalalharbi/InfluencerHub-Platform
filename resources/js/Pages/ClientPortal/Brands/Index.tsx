import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { clientNav } from '@/lib/nav';
import { ListHead, StatusBadge, Field } from '@/Components/ui';
import { u } from '@/lib/href';
import { useT } from '@/lib/i18n';

const LBL: React.CSSProperties = { fontSize: '.8rem', fontWeight: 600, display: 'block', marginBottom: '.3rem' };

interface Brand { id: number; name: string; sector: string | null; status: string; statusLabel: string; statusTone: string }
interface Props { clientName: string; brands: Brand[]; canManage: boolean }

export default function ClientBrandsIndex({ clientName, brands, canManage }: Props) {
  const t = useT();
  const [modal, setModal] = useState(false);
  const [busy, setBusy] = useState(false);
  const [form, setForm] = useState({ name: '', sector: '', website: '', description: '' });

  const submit = () => {
    if (!form.name.trim()) return;
    setBusy(true);
    router.post(u('/brands'), form, { onFinish: () => setBusy(false), onSuccess: () => setModal(false) });
  };

  return (
    <AppShell heading={t('client_portal.brands_title')} nav={clientNav} portal="client" wsName={clientName} wsPlan={t('client_portal.ws_plan')}>
      <Head title={t('client_portal.brands_title')} />
      <ListHead eyebrow={t('client_portal.eyebrow')} title={t('client_portal.brands_title')} sub={t('client_portal.brands_sub')}
        actions={canManage ? <button onClick={() => setModal(true)} className="btn btn-sm">{t('client_portal.new_brand')}</button> : undefined} />

      {brands.length === 0 ? (
        <div className="card" style={{ padding: '2rem', textAlign: 'center', color: 'var(--ih-text-muted)' }}>{t('client_portal.brands_empty')}</div>
      ) : (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: '1rem' }}>
          {brands.map((b) => (
            <Link key={b.id} href={u(`/brands/${b.id}`)} className="card" style={{ padding: '1rem 1.1rem', textDecoration: 'none', color: 'inherit', display: 'block' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '.5rem' }}>
                <div>
                  <div style={{ fontWeight: 700, fontSize: '1rem' }}>{b.name}</div>
                  {b.sector && <div style={{ fontSize: '.78rem', color: 'var(--ih-text-muted)' }}>{b.sector}</div>}
                </div>
                <StatusBadge tone={b.statusTone} label={b.statusLabel} />
              </div>
            </Link>
          ))}
        </div>
      )}

      {modal && (
        <div className="modal-backdrop" onClick={(e) => e.target === e.currentTarget && !busy && setModal(false)}>
          <div className="modal" style={{ padding: '1.3rem', maxWidth: 500 }}>
            <h3 style={{ fontWeight: 800, margin: '0 0 1rem' }}>{t('client_portal.brand_modal_title')}</h3>
            <div style={{ display: 'grid', gap: '.8rem' }}>
              <Field label={t('client_portal.f_name')} labelStyle={LBL}>
                <input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} className="field" style={{ width: '100%' }} autoFocus />
              </Field>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
                <Field label={t('client_portal.f_sector')} labelStyle={LBL}>
                  <input value={form.sector} onChange={(e) => setForm({ ...form, sector: e.target.value })} className="field" style={{ width: '100%' }} />
                </Field>
                <Field label={t('client_portal.f_website')} labelStyle={LBL}>
                  <input value={form.website} onChange={(e) => setForm({ ...form, website: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} placeholder="https://…" />
                </Field>
              </div>
              <Field label={t('client_portal.f_desc_short')} labelStyle={LBL}>
                <textarea value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} className="field" rows={3} style={{ width: '100%', resize: 'vertical' }} />
              </Field>
            </div>
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button disabled={busy || !form.name.trim()} onClick={submit} className="btn btn-primary">{t('client_portal.save_draft')}</button>
              <button disabled={busy} onClick={() => setModal(false)} className="btn btn-ghost">{t('client_portal.cancel')}</button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}

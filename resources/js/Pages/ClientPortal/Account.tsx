import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Field, Sec, StatusBadge, WorkTabs, WorkspaceHeader, type WorkTab } from '@/Components/ui';
import { clientNav } from '@/lib/nav';
import AccountSecurity from '@/Components/AccountSecurity';
import { u } from '@/lib/href';

interface ClientInfo {
  name: string; number: string; sector: string | null; website: string | null; email: string | null;
  phone: string | null; whatsapp: string | null; country: string | null; city: string | null;
  address: string | null; preferredLanguage: string | null;
  legalName: string | null; cr: string | null; crExpiry: string | null; tax: string | null;
  vatRegistered: boolean; hasLogo: boolean;
}
interface PendingChange { fields: string[]; status: string; statusLabel: string; statusTone: string; at: string | null }
interface Billing {
  billingName: string | null; billingEmail: string | null; contactName: string | null; contactPhone: string | null;
  taxNumber: string | null; vatRegistered: boolean; address: string | null; poRequired: boolean;
  currency: string | null; invoiceNotes: string | null; paymentTermsDays: number | null;
}
interface Address {
  id: number; type: string; typeLabel: string; label: string | null; recipient: string | null; phone: string | null;
  country: string | null; region: string | null; city: string | null; district: string | null; street: string | null;
  buildingNumber: string | null; postalCode: string | null; additionalNumber: string | null;
  isDefault: boolean; archived: boolean;
}
interface Pref { in_app: boolean; email: boolean; sms: boolean }
interface Session { current: boolean; ip: string | null; agent: string | null; lastActivity: string | null }
interface Props {
  client: ClientInfo; pendingChanges: PendingChange[]; billing: Billing | null; addresses: Address[];
  addressTypes: Record<string, string>; prefs: Record<string, Pref>; categories: Record<string, string>;
  sessions: Session[]; twoFactorEnabled: boolean;
  can: { editProfile: boolean; editBilling: boolean; viewBilling: boolean };
}

const LBL: React.CSSProperties = { fontSize: '.78rem', fontWeight: 600, display: 'block', marginBottom: '.25rem' };
const FIELD_LABEL: Record<string, string> = {
  legal_name: 'الاسم النظامي', commercial_registration_number: 'السجل التجاري',
  commercial_registration_expiry: 'انتهاء السجل', tax_number: 'الرقم الضريبي', vat_registered: 'مسجّل ضريبيًا',
};

export default function ClientAccount({ client, pendingChanges, billing, addresses, addressTypes, prefs, categories, sessions, twoFactorEnabled, can }: Props) {
  const TAB_KEYS = ['profile', ...(can.viewBilling ? ['billing'] : []), 'addresses', 'settings'];
  const [tab, setTab] = useState('profile');
  useEffect(() => {
    const apply = () => {
      const h = window.location.hash.replace('#', '');
      if (TAB_KEYS.includes(h)) setTab(h);
    };
    apply();
    window.addEventListener('hashchange', apply);
    return () => window.removeEventListener('hashchange', apply);
  }, []);
  const go = (k: string) => { setTab(k); window.history.replaceState(null, '', `#${k}`); };

  const [busy, setBusy] = useState(false);
  const [errs, setErrs] = useState<Record<string, string>>({});
  const post = (path: string, data: Record<string, unknown>, done?: () => void) => {
    setBusy(true);
    router.post(u(`/account${path}`), data as never, {
      preserveScroll: true,
      forceFormData: data.file instanceof File,
      onFinish: () => setBusy(false),
      onError: (e) => setErrs(e as Record<string, string>),
      onSuccess: () => { setErrs({}); done?.(); },
    });
  };
  const Err = ({ k }: { k: string }) => errs[k] ? <div style={{ color: 'var(--ih-danger-ink)', fontSize: '.74rem', marginTop: '.25rem' }}>{errs[k]}</div> : null;

  const [pf, setPf] = useState({
    display_name: client.name, sector: client.sector ?? '', website: client.website ?? '',
    email: client.email ?? '', phone: client.phone ?? '', whatsapp: client.whatsapp ?? '',
    country_code: client.country ?? '', city: client.city ?? '', address: client.address ?? '',
    legal_name: client.legalName ?? '', commercial_registration_number: client.cr ?? '',
    commercial_registration_expiry: client.crExpiry ?? '', tax_number: client.tax ?? '',
    vat_registered: client.vatRegistered,
  });
  const [bl, setBl] = useState({
    billing_name: billing?.billingName ?? '', billing_email: billing?.billingEmail ?? '',
    billing_contact_name: billing?.contactName ?? '', billing_contact_phone: billing?.contactPhone ?? '',
    tax_number: billing?.taxNumber ?? '', vat_registered: billing?.vatRegistered ?? false,
    billing_address: billing?.address ?? '', purchase_order_required: billing?.poRequired ?? false,
    default_currency: billing?.currency ?? 'SAR', invoice_notes: billing?.invoiceNotes ?? '',
    payment_terms_days: billing?.paymentTermsDays ?? '',
  });
  const emptyAddr = {
    type: 'headquarters', label: '', recipient_name: '', phone: '', country_code: 'SA',
    region: '', city: '', district: '', street: '', building_number: '', postal_code: '',
    additional_number: '', is_default: false,
  };
  const [addr, setAddr] = useState(emptyAddr);
  const [addrOpen, setAddrOpen] = useState(false);

  const tabs: WorkTab[] = [
    { key: 'profile', label: 'الملف', icon: 'building-2' },
    ...(can.viewBilling ? [{ key: 'billing', label: 'الفوترة', icon: 'wallet' as const }] : []),
    { key: 'addresses', label: 'العناوين', icon: 'file-text', count: addresses.filter((a) => !a.archived).length },
    { key: 'settings', label: 'الإعدادات', icon: 'settings' },
  ];

  return (
    <AppShell heading="حساب المنشأة" nav={clientNav} portal="client" wsName={client.name} wsPlan="بوابة العميل">
      <Head title="حساب المنشأة" />

      <WorkspaceHeader
        eyebrow={`حساب المنشأة · ${client.number}`}
        title={client.name}
        meta={[
          ['القطاع', client.sector ?? '—'], ['المدينة', client.city ?? '—'],
          ['السجل التجاري', client.cr ?? '—'], ['الرقم الضريبي', client.tax ?? '—'],
        ]}
      />

      <WorkTabs active={tab} onChange={go} tabs={tabs} />

      {tab === 'profile' && (
        <>
          {pendingChanges.length > 0 && (
            <div className="card" style={{ padding: '.9rem 1rem', marginBottom: '1rem', borderInlineStart: '3px solid var(--ih-warning)', background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)' }}>
              <div style={{ fontWeight: 700, marginBottom: '.35rem' }}>تعديلات بانتظار مراجعة الوكالة</div>
              {pendingChanges.map((p, i) => (
                <div key={i} style={{ fontSize: '.82rem', display: 'flex', alignItems: 'center', gap: '.5rem', flexWrap: 'wrap' }}>
                  <span>{p.fields.map((f) => FIELD_LABEL[f] ?? f).join('، ')}</span>
                  <StatusBadge tone={p.statusTone} label={p.statusLabel} />
                  {p.at && <span style={{ opacity: .8 }}>{p.at}</span>}
                </div>
              ))}
            </div>
          )}

          <Sec title="بيانات المنشأة" icon="building-2">
            <div className="ih-sec__body" style={{ display: 'grid', gap: '.8rem' }}>
              {!can.editProfile && (
                <div style={{ fontSize: '.8rem', color: 'var(--ih-text-muted)' }}>دورك يسمح بالاطلاع فقط.</div>
              )}
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
                <Field label="اسم المنشأة" labelStyle={LBL}><input disabled={!can.editProfile} value={pf.display_name} onChange={(e) => setPf({ ...pf, display_name: e.target.value })} className="field" style={{ width: '100%' }} /><Err k="display_name" /></Field>
                <Field label="القطاع" labelStyle={LBL}><input disabled={!can.editProfile} value={pf.sector} onChange={(e) => setPf({ ...pf, sector: e.target.value })} className="field" style={{ width: '100%' }} /></Field>
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '.8rem' }}>
                <Field label="البريد" labelStyle={LBL}><input disabled={!can.editProfile} value={pf.email} onChange={(e) => setPf({ ...pf, email: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} /><Err k="email" /></Field>
                <Field label="الهاتف" labelStyle={LBL}><input disabled={!can.editProfile} value={pf.phone} onChange={(e) => setPf({ ...pf, phone: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} /></Field>
                <Field label="واتساب" labelStyle={LBL}><input disabled={!can.editProfile} value={pf.whatsapp} onChange={(e) => setPf({ ...pf, whatsapp: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} /></Field>
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 2fr', gap: '.8rem' }}>
                <Field label="الدولة" labelStyle={LBL}><input disabled={!can.editProfile} value={pf.country_code} onChange={(e) => setPf({ ...pf, country_code: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} placeholder="SA" /><Err k="country_code" /></Field>
                <Field label="المدينة" labelStyle={LBL}><input disabled={!can.editProfile} value={pf.city} onChange={(e) => setPf({ ...pf, city: e.target.value })} className="field" style={{ width: '100%' }} /></Field>
                <Field label="الموقع" labelStyle={LBL}><input disabled={!can.editProfile} value={pf.website} onChange={(e) => setPf({ ...pf, website: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} /></Field>
              </div>

              <div style={{ borderTop: '1px solid var(--ih-border)', paddingTop: '.8rem' }}>
                <div style={{ fontWeight: 700, fontSize: '.85rem', marginBottom: '.2rem' }}>البيانات النظامية</div>
                <div style={{ fontSize: '.76rem', color: 'var(--ih-text-muted)', marginBottom: '.7rem' }}>
                  تعديل هذه الحقول يُرسَل كطلب تغيير تراجعه الوكالة — لا يُطبَّق فورًا.
                </div>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
                  <Field label="الاسم النظامي" labelStyle={LBL}><input disabled={!can.editProfile} value={pf.legal_name} onChange={(e) => setPf({ ...pf, legal_name: e.target.value })} className="field" style={{ width: '100%' }} /></Field>
                  <Field label="السجل التجاري" labelStyle={LBL}><input disabled={!can.editProfile} value={pf.commercial_registration_number} onChange={(e) => setPf({ ...pf, commercial_registration_number: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} /></Field>
                </div>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '.8rem', marginTop: '.8rem' }}>
                  <Field label="انتهاء السجل" labelStyle={LBL}><input type="date" disabled={!can.editProfile} value={pf.commercial_registration_expiry} onChange={(e) => setPf({ ...pf, commercial_registration_expiry: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} /></Field>
                  <Field label="الرقم الضريبي" labelStyle={LBL}><input disabled={!can.editProfile} value={pf.tax_number} onChange={(e) => setPf({ ...pf, tax_number: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} /></Field>
                  <div style={{ display: 'flex', alignItems: 'flex-end', paddingBottom: '.4rem' }}>
                    <label style={{ display: 'inline-flex', alignItems: 'center', gap: '.4rem', fontSize: '.82rem' }}>
                      <input type="checkbox" disabled={!can.editProfile} checked={pf.vat_registered} onChange={(e) => setPf({ ...pf, vat_registered: e.target.checked })} />
                      مسجّل ضريبيًا
                    </label>
                  </div>
                </div>
              </div>

              {errs.form && <div style={{ color: 'var(--ih-danger-ink)', fontSize: '.8rem' }}>{errs.form}</div>}
              {can.editProfile && (
                <div style={{ display: 'flex', gap: '.6rem', alignItems: 'center', flexWrap: 'wrap' }}>
                  <button disabled={busy || !pf.display_name.trim()} onClick={() => post('/profile', pf)} className="btn btn-sm btn-primary">حفظ</button>
                  <label className="btn btn-sm btn-outline" style={{ cursor: 'pointer' }}>
                    {client.hasLogo ? 'تغيير الشعار' : 'رفع شعار'}
                    <input type="file" accept="image/png,image/jpeg,image/webp" style={{ display: 'none' }}
                      onChange={(e) => { const f = e.target.files?.[0]; if (f) post('/logo', { file: f }); }} />
                  </label>
                  <Err k="file" />
                </div>
              )}
            </div>
          </Sec>
        </>
      )}

      {tab === 'billing' && can.viewBilling && (
        <Sec title="ملف الفوترة" icon="wallet">
          <div className="ih-sec__body" style={{ display: 'grid', gap: '.8rem' }}>
            {!can.editBilling && <div style={{ fontSize: '.8rem', color: 'var(--ih-text-muted)' }}>دورك يسمح بالاطلاع فقط.</div>}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
              <Field label="اسم الفوترة" labelStyle={LBL}><input disabled={!can.editBilling} value={bl.billing_name} onChange={(e) => setBl({ ...bl, billing_name: e.target.value })} className="field" style={{ width: '100%' }} /></Field>
              <Field label="بريد الفوترة" labelStyle={LBL}><input disabled={!can.editBilling} value={bl.billing_email} onChange={(e) => setBl({ ...bl, billing_email: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} /><Err k="billing_email" /></Field>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
              <Field label="مسؤول الفوترة" labelStyle={LBL}><input disabled={!can.editBilling} value={bl.billing_contact_name} onChange={(e) => setBl({ ...bl, billing_contact_name: e.target.value })} className="field" style={{ width: '100%' }} /></Field>
              <Field label="هاتفه" labelStyle={LBL}><input disabled={!can.editBilling} value={bl.billing_contact_phone} onChange={(e) => setBl({ ...bl, billing_contact_phone: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} /></Field>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '.8rem' }}>
              <Field label="الرقم الضريبي" labelStyle={LBL}><input disabled={!can.editBilling} value={bl.tax_number} onChange={(e) => setBl({ ...bl, tax_number: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} /></Field>
              <Field label="العملة" labelStyle={LBL}><input disabled={!can.editBilling} value={bl.default_currency} onChange={(e) => setBl({ ...bl, default_currency: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} /><Err k="default_currency" /></Field>
              <Field label="مهلة السداد (يوم)" labelStyle={LBL}><input type="number" min={0} max={365} disabled={!can.editBilling} value={bl.payment_terms_days} onChange={(e) => setBl({ ...bl, payment_terms_days: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} /><Err k="payment_terms_days" /></Field>
            </div>
            <Field label="عنوان الفوترة" labelStyle={LBL}><input disabled={!can.editBilling} value={bl.billing_address} onChange={(e) => setBl({ ...bl, billing_address: e.target.value })} className="field" style={{ width: '100%' }} /></Field>
            <Field label="ملاحظات الفاتورة" labelStyle={LBL}><textarea disabled={!can.editBilling} value={bl.invoice_notes} onChange={(e) => setBl({ ...bl, invoice_notes: e.target.value })} className="field" rows={3} style={{ width: '100%' }} /></Field>
            <div style={{ display: 'flex', gap: '1rem', flexWrap: 'wrap' }}>
              <label style={{ display: 'inline-flex', alignItems: 'center', gap: '.4rem', fontSize: '.82rem' }}>
                <input type="checkbox" disabled={!can.editBilling} checked={bl.vat_registered} onChange={(e) => setBl({ ...bl, vat_registered: e.target.checked })} /> مسجّل ضريبيًا
              </label>
              <label style={{ display: 'inline-flex', alignItems: 'center', gap: '.4rem', fontSize: '.82rem' }}>
                <input type="checkbox" disabled={!can.editBilling} checked={bl.purchase_order_required} onChange={(e) => setBl({ ...bl, purchase_order_required: e.target.checked })} /> يتطلّب أمر شراء
              </label>
            </div>
            {can.editBilling && <div><button disabled={busy} onClick={() => post('/billing', bl)} className="btn btn-sm btn-primary">حفظ</button></div>}
          </div>
        </Sec>
      )}

      {tab === 'addresses' && (
        <Sec title="العناوين" icon="file-text">
          <div className="ih-sec__body" style={{ display: 'grid', gap: '.8rem' }}>
            {addresses.length === 0 ? (
              <div style={{ color: 'var(--ih-text-muted)', fontSize: '.85rem' }}>لا عناوين مسجّلة.</div>
            ) : (
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: '.7rem' }}>
                {addresses.map((a) => (
                  <div key={a.id} className="card" style={{ padding: '.8rem .9rem', display: 'grid', gap: '.35rem', opacity: a.archived ? .55 : 1 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '.4rem', flexWrap: 'wrap' }}>
                      <span style={{ fontWeight: 700, fontSize: '.86rem' }}>{a.label || a.typeLabel}</span>
                      <span className="ih-tag" style={{ fontSize: '.62rem' }}>{a.typeLabel}</span>
                      {a.isDefault && <span className="badge" style={{ background: 'var(--ih-primary-soft)', color: 'var(--ih-primary-700)', fontSize: '.62rem' }}>افتراضي</span>}
                      {a.archived && <span className="ih-tag" style={{ fontSize: '.62rem' }}>مؤرشف</span>}
                    </div>
                    <div style={{ fontSize: '.78rem', color: 'var(--ih-text-muted)' }}>
                      {[a.street, a.district, a.city, a.region].filter(Boolean).join('، ') || '—'}
                    </div>
                    {a.recipient && <div style={{ fontSize: '.76rem', color: 'var(--ih-text-muted)' }}>المستلم: {a.recipient}</div>}
                    {can.editProfile && (
                      <div style={{ display: 'flex', gap: '.35rem', marginTop: '.3rem', flexWrap: 'wrap' }}>
                        {!a.archived && !a.isDefault && <button disabled={busy} onClick={() => post(`/addresses/${a.id}/default`, {})} className="btn btn-xs btn-outline">افتراضي</button>}
                        {a.archived
                          ? <button disabled={busy} onClick={() => post(`/addresses/${a.id}/restore`, {})} className="btn btn-xs btn-outline">استعادة</button>
                          : <button disabled={busy} onClick={() => post(`/addresses/${a.id}/archive`, {})} className="btn btn-xs btn-ghost">أرشفة</button>}
                      </div>
                    )}
                  </div>
                ))}
              </div>
            )}
            {errs.address && <div style={{ color: 'var(--ih-danger-ink)', fontSize: '.8rem' }}>{errs.address}</div>}

            {can.editProfile && (
              addrOpen ? (
                <div className="card" style={{ padding: '.9rem', display: 'grid', gap: '.7rem' }}>
                  <div style={{ fontWeight: 700, fontSize: '.85rem' }}>عنوان جديد</div>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.7rem' }}>
                    <Field label="النوع" labelStyle={LBL}>
                      <select value={addr.type} onChange={(e) => setAddr({ ...addr, type: e.target.value })} className="field" style={{ width: '100%' }}>
                        {Object.entries(addressTypes).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                      </select>
                    </Field>
                    <Field label="التسمية" labelStyle={LBL}><input value={addr.label} onChange={(e) => setAddr({ ...addr, label: e.target.value })} className="field" style={{ width: '100%' }} /></Field>
                  </div>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '.7rem' }}>
                    <Field label="المدينة" labelStyle={LBL}><input value={addr.city} onChange={(e) => setAddr({ ...addr, city: e.target.value })} className="field" style={{ width: '100%' }} /></Field>
                    <Field label="الحي" labelStyle={LBL}><input value={addr.district} onChange={(e) => setAddr({ ...addr, district: e.target.value })} className="field" style={{ width: '100%' }} /></Field>
                    <Field label="الشارع" labelStyle={LBL}><input value={addr.street} onChange={(e) => setAddr({ ...addr, street: e.target.value })} className="field" style={{ width: '100%' }} /></Field>
                  </div>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '.7rem' }}>
                    <Field label="المستلم" labelStyle={LBL}><input value={addr.recipient_name} onChange={(e) => setAddr({ ...addr, recipient_name: e.target.value })} className="field" style={{ width: '100%' }} /></Field>
                    <Field label="الهاتف" labelStyle={LBL}><input value={addr.phone} onChange={(e) => setAddr({ ...addr, phone: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} /></Field>
                    <Field label="الرمز البريدي" labelStyle={LBL}><input value={addr.postal_code} onChange={(e) => setAddr({ ...addr, postal_code: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} /></Field>
                  </div>
                  <label style={{ display: 'inline-flex', alignItems: 'center', gap: '.4rem', fontSize: '.82rem' }}>
                    <input type="checkbox" checked={addr.is_default} onChange={(e) => setAddr({ ...addr, is_default: e.target.checked })} /> اجعله الافتراضي
                  </label>
                  <div style={{ display: 'flex', gap: '.5rem' }}>
                    <button disabled={busy} onClick={() => post('/addresses', addr, () => { setAddr(emptyAddr); setAddrOpen(false); })} className="btn btn-sm btn-primary">إضافة</button>
                    <button disabled={busy} onClick={() => setAddrOpen(false)} className="btn btn-sm btn-ghost">إلغاء</button>
                  </div>
                </div>
              ) : (
                <div><button onClick={() => setAddrOpen(true)} className="btn btn-sm btn-outline">إضافة عنوان</button></div>
              )
            )}
          </div>
        </Sec>
      )}

      {tab === 'settings' && (
        <AccountSecurity base="/account/settings" prefs={prefs} categories={categories}
          sessions={sessions} twoFactorEnabled={twoFactorEnabled} />
      )}
    </AppShell>
  );
}

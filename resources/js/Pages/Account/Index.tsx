import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Sec, WorkspaceHeader } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import AccountSecurity, { type SecurityProps } from '@/Components/AccountSecurity';
import { u } from '@/lib/href';

interface Props extends Omit<SecurityProps, 'base'> {
  user: { name: string; email: string };
}

export default function AccountIndex({ user, prefs, categories, sessions, twoFactorEnabled }: Props) {
  const [name, setName] = useState(user.name);
  const [busy, setBusy] = useState(false);
  const saveName = () => {
    if (name.trim().length < 2 || name.trim() === user.name) return;
    setBusy(true);
    router.post(u('/account/profile'), { name: name.trim() }, { preserveScroll: true, onFinish: () => setBusy(false) });
  };

  return (
    <AppShell heading="حسابي">
      <Head title="حسابي" />

      <WorkspaceHeader
        eyebrow="حسابي"
        title={user.name}
        meta={[['البريد', user.email]]}
      />

      <Sec title="الملف الشخصي" icon="user">
        <div style={{ display: 'grid', gap: '.8rem', maxWidth: 460 }}>
          <label style={{ fontSize: '.82rem' }}>
            <span style={{ display: 'block', marginBottom: '.3rem', color: 'var(--ih-text-muted)' }}>الاسم المعروض</span>
            <input className="field" value={name} onChange={(e) => setName(e.target.value)} maxLength={120} style={{ width: '100%' }} />
          </label>
          <label style={{ fontSize: '.82rem' }}>
            <span style={{ display: 'block', marginBottom: '.3rem', color: 'var(--ih-text-muted)' }}>البريد الإلكتروني</span>
            <input className="field" value={user.email} disabled style={{ width: '100%', direction: 'ltr', textAlign: 'right', opacity: .7 }} />
          </label>
          <div>
            <button className="btn btn-sm" disabled={busy || name.trim().length < 2 || name.trim() === user.name} onClick={saveName}>
              <Icon name="check" size={14} /> حفظ الاسم
            </button>
          </div>
        </div>
      </Sec>

      <div id="security" style={{ scrollMarginTop: 80 }}>
        <AccountSecurity base="/account" prefs={prefs} categories={categories}
          sessions={sessions} twoFactorEnabled={twoFactorEnabled} />
      </div>
    </AppShell>
  );
}

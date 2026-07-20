import { Head } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { WorkspaceHeader } from '@/Components/ui';
import AccountSecurity, { type SecurityProps } from '@/Components/AccountSecurity';

interface Props extends Omit<SecurityProps, 'base'> {
  user: { name: string; email: string };
}

export default function AccountIndex({ user, prefs, categories, sessions, twoFactorEnabled }: Props) {
  return (
    <AppShell heading="حسابي">
      <Head title="حسابي" />

      <WorkspaceHeader
        eyebrow="حسابي"
        title={user.name}
        meta={[['البريد', user.email]]}
      />

      <AccountSecurity base="/account" prefs={prefs} categories={categories}
        sessions={sessions} twoFactorEnabled={twoFactorEnabled} />
    </AppShell>
  );
}

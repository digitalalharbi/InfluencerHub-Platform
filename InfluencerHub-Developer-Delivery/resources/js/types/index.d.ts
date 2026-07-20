import type { PageProps as InertiaPageProps } from '@inertiajs/core';

export interface AuthUser {
  id: number;
  name: string;
  email: string;
}

export interface SharedProps extends InertiaPageProps {
  auth: { user: AuthUser | null };
  workspace: string | null;
  showcase: boolean;
  nav: { badges: Record<string, number>; can?: Record<string, boolean> };
  flash: { ok: string | null; error: string | null; inviteToken?: string | null };
  locale: string;
  dir: 'rtl' | 'ltr';
  /** بادئة تركيب الصفحة الحالية (`/beta`, `/app`, `/beta/client`…) — انظر lib/href. */
  base: string;
}

export {};

import type { PageProps as InertiaPageProps } from '@inertiajs/core';

export interface AuthUser {
  id: number;
  name: string;
  email: string;
}

/** هوية المنتج المشتركة — مصدر واحد من الخادم (HandleInertiaRequests). */
export interface Brand {
  name: string;
  tagline: string;
  url: string;
  domain: string;
  infoUrl: string;
  infoPath: string;
  privacyPath: string;
  termsPath: string;
  helpPath: string;
  publicEmail: string;
  publicPhone: string;
  publicPhoneDisplay: string;
  year: number;
}

export interface SharedProps extends InertiaPageProps {
  auth: { user: AuthUser | null };
  workspace: string | null;
  showcase: boolean;
  nav: { badges: Record<string, number>; can?: Record<string, boolean> };
  unreadNotifications?: number;
  flash: { ok: string | null; error: string | null; inviteToken?: string | null };
  locale: string;
  dir: 'rtl' | 'ltr';
  /** بادئة تركيب الصفحة الحالية (`/beta`, `/app`, `/beta/client`…) — انظر lib/href. */
  base: string;
  brand: Brand;
}

export {};

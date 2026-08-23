import { Head, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { ListHead } from '@/Components/ui';
import { Icon, type IconName } from '@/Components/Icon';

interface Check { key: string; label: string; status: string; detail: string; metrics: Record<string, unknown> }
interface Props { checks: Check[]; overall: string; checkedAt: string }

const TONE: Record<string, { bg: string; ink: string; label: string }> = {
  ok: { bg: 'var(--ih-success-soft)', ink: 'var(--ih-success-ink)', label: 'سليم' },
  warn: { bg: 'var(--ih-warning-soft)', ink: 'var(--ih-warning-ink)', label: 'انتباه' },
  down: { bg: 'var(--ih-danger-soft)', ink: 'var(--ih-danger-ink)', label: 'متوقّف' },
  not_configured: { bg: 'var(--ih-surface-muted)', ink: 'var(--ih-text-muted)', label: 'غير مُهيّأ' },
  unknown: { bg: 'var(--ih-surface-muted)', ink: 'var(--ih-text-muted)', label: 'غير معروف' },
};

const ICON: Record<string, IconName> = {
  app: 'rocket', database: 'table', queue: 'inbox', scheduler: 'calendar-days', failed_jobs: 'alert-triangle',
  mail: 'file-text', whatsapp: 'message-circle', storage: 'grid', integrations: 'plug', webhooks: 'git-merge',
};

export default function SystemHealth({ checks, overall, checkedAt }: Props) {
  const o = TONE[overall] ?? TONE.unknown;
  return (
    <AppShell heading="صحّة النظام">
      <Head title="صحّة النظام" />
      <ListHead eyebrow="التشغيل" title="صحّة النظام" sub={`آخر فحص: ${checkedAt}`}
        actions={<button onClick={() => router.reload()} className="btn btn-sm btn-outline"><Icon name="activity" size={14} /> إعادة الفحص</button>} />

      <div className="card" style={{ padding: '1rem 1.2rem', marginBottom: '1.1rem', display: 'flex', alignItems: 'center', gap: '.8rem', borderInlineStart: `4px solid ${o.ink}`, background: o.bg }}>
        <span style={{ width: 12, height: 12, borderRadius: '50%', background: o.ink }} />
        <div><div style={{ fontWeight: 800, color: o.ink }}>الحالة العامة: {o.label}</div>
          <div style={{ fontSize: '.8rem', color: 'var(--ih-text-muted)' }}>فحوص حقيقية — لا مؤشّرات زخرفية</div></div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: '.85rem' }}>
        {checks.map((c) => {
          const t = TONE[c.status] ?? TONE.unknown;
          return (
            <div key={c.key} className="card" style={{ padding: '.9rem 1rem' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '.6rem', marginBottom: '.5rem' }}>
                <span style={{ width: 32, height: 32, borderRadius: 9, display: 'flex', alignItems: 'center', justifyContent: 'center', background: t.bg, color: t.ink, flexShrink: 0 }}>
                  <Icon name={ICON[c.key] ?? 'circle'} size={16} />
                </span>
                <span style={{ fontWeight: 700, flex: 1 }}>{c.label}</span>
                <span style={{ fontSize: '.68rem', fontWeight: 700, padding: '.15rem .5rem', borderRadius: 6, background: t.bg, color: t.ink }}>{t.label}</span>
              </div>
              <div style={{ fontSize: '.82rem', color: 'var(--ih-text-muted)', lineHeight: 1.6 }}>{c.detail}</div>
              {Object.keys(c.metrics ?? {}).length > 0 && (
                <div style={{ display: 'flex', gap: '.9rem', flexWrap: 'wrap', marginTop: '.55rem', paddingTop: '.5rem', borderTop: '1px solid var(--ih-border)' }}>
                  {Object.entries(c.metrics).map(([k, v]) => (
                    <span key={k} style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)' }}>{k}: <b style={{ color: 'var(--ih-text)', direction: 'ltr', display: 'inline-block' }}>{String(v ?? '—')}</b></span>
                  ))}
                </div>
              )}
            </div>
          );
        })}
      </div>
    </AppShell>
  );
}

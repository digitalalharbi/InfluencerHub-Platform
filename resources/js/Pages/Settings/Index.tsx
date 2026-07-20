import type { ReactNode } from 'react';
import { Head } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { WorkspaceHeader, SummaryStrip, Sec, Bar, StatusBadge } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';

interface Entitlement {
  key: string; label: string; unlimited: boolean; bool: boolean;
  enabled: boolean | null; limit: number | null; used: number | null; pct: number;
}
interface Props {
  org: { name: string; type: string; team: number; showcase: boolean };
  subscription: null | {
    status: string; statusLabel: string; plan: string; version: number;
    trialEndsAt: string | null; periodStart: string | null; periodEnd: string | null; provider: string | null;
  };
  entitlements: Entitlement[];
  teamPreview: { name: string; role: string }[];
  byRole: { label: string; count: number }[];
}

export default function SettingsIndex({ org, subscription, entitlements, teamPreview, byRole }: Props) {
  const subTone = subscription ? (subscription.status === 'active' ? 'active' : 'submitted') : 'draft';

  return (
    <AppShell heading="الإعدادات">
      <Head title="الإعدادات" />

      <WorkspaceHeader
        eyebrow="الإدارة"
        title={org.name}
        statusTone={subTone}
        statusLabel={subscription ? subscription.statusLabel : 'بدون اشتراك'}
        meta={[
          ['النوع', org.type === 'agency' ? 'وكالة' : org.type],
          ['الفريق', `${org.team.toLocaleString('en-US')} عضو`],
          ...(subscription ? [['الخطة', `${subscription.plan} · إصدار ${subscription.version}`] as [string, string]] : []),
        ]}
      />

      <SummaryStrip
        items={[
          { label: 'الخطة', value: subscription?.plan ?? '—', icon: 'shield-check', tone: subscription ? 'success' : undefined },
          { label: 'الحالة', value: subscription?.statusLabel ?? 'بدون اشتراك', icon: 'activity', tone: subscription?.status === 'active' ? 'success' : 'primary' },
          { label: 'أعضاء الفريق', value: org.team.toLocaleString('en-US'), icon: 'users' },
          { label: 'الحقوق المُقاسة', value: entitlements.length.toLocaleString('en-US'), icon: 'gauge' },
        ]}
      />

      <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0, 1fr) minmax(0, 1.4fr)', gap: '1.2rem', alignItems: 'start' }} className="ih-settings-grid">
        <Sec title="الاشتراك" icon="shield-check">
          {subscription ? (
            <dl style={{ display: 'grid', gap: '.7rem', margin: 0 }}>
              <Row k="الخطة" v={`${subscription.plan} · إصدار ${subscription.version}`} />
              <Row k="الحالة" node={<StatusBadge tone={subTone} label={subscription.statusLabel} />} />
              {subscription.trialEndsAt && <Row k="انتهاء التجربة" v={subscription.trialEndsAt} />}
              <Row k="بداية الدورة" v={subscription.periodStart ?? '—'} />
              <Row k="نهاية الدورة" v={subscription.periodEnd ?? '—'} />
              <Row k="مزوّد الفوترة" v={subscription.provider ?? 'يدوي'} />
            </dl>
          ) : (
            <div style={{ padding: '1.4rem', textAlign: 'center', color: 'var(--ih-text-muted)', fontSize: '.86rem' }}>
              <Icon name="shield-check" size={22} /><div style={{ marginTop: '.5rem' }}>لا يوجد اشتراك نشط لمساحة العمل هذه.</div>
            </div>
          )}
          <div className="card" style={{ marginTop: '1rem', padding: '.7rem .9rem', borderInlineStart: '3px solid var(--ih-info)', background: 'var(--ih-info-soft)', color: 'var(--ih-info-ink)', fontSize: '.8rem' }}>
            <Icon name="shield-check" size={14} /> تغيير الخطة أو الفوترة يُدار من فريق الحساب — عند تفعيل مزوّد الدفع تُتاح الترقية الذاتية (راجع docs/EXTERNAL-BLOCKERS.md).
          </div>
        </Sec>

        <Sec title="الحقوق والاستهلاك" icon="gauge">
          {entitlements.length === 0 ? (
            <div style={{ padding: '1.4rem', textAlign: 'center', color: 'var(--ih-text-muted)', fontSize: '.86rem' }}>لا حقوق مُعرّفة للخطة الحالية.</div>
          ) : (
            <div style={{ display: 'grid', gap: '.9rem' }}>
              {entitlements.map((e) => (
                <div key={e.key}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', marginBottom: '.35rem' }}>
                    <span style={{ fontSize: '.84rem', fontWeight: 600 }}>{e.label}</span>
                    <span style={{ fontSize: '.78rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>
                      {e.bool
                        ? (e.enabled ? 'مُفعّل' : 'غير مُفعّل')
                        : e.unlimited
                          ? '∞ غير محدود'
                          : `${(e.used ?? 0).toLocaleString('en-US')} / ${(e.limit ?? 0).toLocaleString('en-US')}`}
                    </span>
                  </div>
                  {e.bool ? (
                    <StatusBadge tone={e.enabled ? 'active' : 'draft'} label={e.enabled ? 'متاح' : 'غير متاح'} />
                  ) : e.unlimited ? (
                    <Bar pct={4} />
                  ) : (
                    <Bar pct={e.pct} over={e.pct >= 90} />
                  )}
                </div>
              ))}
            </div>
          )}
        </Sec>
      </div>

      {/* الفريق — لمحة داخل الإعدادات مع رابط للمساحة الكاملة */}
      <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0,1.4fr) minmax(0,1fr)', gap: '1.2rem', alignItems: 'start', marginTop: '1.2rem' }} className="ih-settings-grid">
        <Sec title="الفريق" icon="users" link={{ href: u('/team'), label: 'إدارة الفريق' }}>
          {teamPreview.length === 0 ? (
            <div style={{ padding: '1.5rem', textAlign: 'center', color: 'var(--ih-text-muted)', fontSize: '.86rem' }}>لا أعضاء نشطون.</div>
          ) : (
            <div style={{ padding: '.75rem .9rem', display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))', gap: '.55rem' }}>
              {teamPreview.map((m, i) => (
                <div key={i} style={{ display: 'flex', alignItems: 'center', gap: '.55rem' }}>
                  <span className="ih-idc__av" style={{ width: 30, height: 30, fontSize: '.75rem' }}>{m.name.slice(0, 1)}</span>
                  <div style={{ minWidth: 0 }}>
                    <div style={{ fontWeight: 600, fontSize: '.84rem', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{m.name}</div>
                    <div style={{ fontSize: '.7rem', color: 'var(--ih-text-muted)' }}>{m.role}</div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </Sec>

        <Sec title="توزيع الأدوار" icon="shield-check">
          <div className="ih-sec__body" style={{ display: 'grid', gap: '.6rem' }}>
            {byRole.length === 0 ? <span style={{ fontSize: '.84rem', color: 'var(--ih-text-muted)' }}>—</span> : byRole.map((r, i) => {
              const max = Math.max(...byRole.map((x) => x.count), 1);
              return (
                <div key={i}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '.82rem', marginBottom: '.22rem' }}>
                    <span style={{ fontWeight: 600 }}>{r.label}</span>
                    <span style={{ color: 'var(--ih-text-muted)', direction: 'ltr' }}>{r.count}</span>
                  </div>
                  <Bar pct={Math.round((r.count / max) * 100)} />
                </div>
              );
            })}
          </div>
        </Sec>
      </div>
    </AppShell>
  );
}

function Row({ k, v, node }: { k: string; v?: string; node?: ReactNode }) {
  return (
    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: '1rem', borderBottom: '1px solid var(--ih-border)', paddingBottom: '.6rem' }}>
      <dt style={{ fontSize: '.82rem', color: 'var(--ih-text-muted)', margin: 0 }}>{k}</dt>
      <dd style={{ fontSize: '.86rem', fontWeight: 600, margin: 0, textAlign: 'end' }}>{node ?? v}</dd>
    </div>
  );
}

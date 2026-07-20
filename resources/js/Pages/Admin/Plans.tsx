import { Head } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { adminNav } from '@/lib/nav';
import { ListHead, Sec } from '@/Components/ui';

interface Entitlement { key: string; value: string }
interface Version { version: number; active: boolean; locked: boolean; entitlements: Entitlement[] }
interface Plan { id: number; key: string; name: string; active: boolean; versions: Version[] }
interface Props { plans: Plan[] }

export default function AdminPlans({ plans }: Props) {
  return (
    <AppShell heading="الخطط" nav={adminNav} portal="admin" wsName="إدارة المنصّة" wsPlan="مدير النظام" brand="InfluencerHub">
      <Head title="الخطط" />
      <ListHead eyebrow="المنصّة" title="الخطط والإصدارات" sub="الخطط ونُسخها وحقوقها (عرض)." />

      {plans.length === 0 ? (
        <div className="card" style={{ padding: '2rem', textAlign: 'center', color: 'var(--ih-text-muted)' }}>لا خطط.</div>
      ) : (
        <div style={{ display: 'grid', gap: '1.2rem' }}>
          {plans.map((p) => (
            <Sec key={p.id} title={`${p.name}`} icon="shield-check">
              <div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)', direction: 'ltr', marginBottom: '.7rem' }}>
                {p.key} {p.active ? '· نشطة' : '· غير نشطة'}
              </div>
              {p.versions.length === 0 ? (
                <div style={{ fontSize: '.82rem', color: 'var(--ih-text-muted)' }}>لا إصدارات.</div>
              ) : (
                <div style={{ display: 'grid', gap: '.8rem' }}>
                  {p.versions.map((v) => (
                    <div key={v.version} className="card" style={{ padding: '.8rem 1rem' }}>
                      <div style={{ display: 'flex', gap: '.5rem', alignItems: 'center', marginBottom: '.5rem' }}>
                        <span style={{ fontWeight: 700 }}>إصدار {v.version}</span>
                        {v.active && <span className="ih-tag" style={{ background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)', fontSize: '.66rem' }}>نشط</span>}
                        {v.locked && <span className="ih-tag" style={{ fontSize: '.66rem' }}>مقفل</span>}
                      </div>
                      {v.entitlements.length === 0 ? (
                        <div style={{ fontSize: '.8rem', color: 'var(--ih-text-muted)' }}>لا حقوق.</div>
                      ) : (
                        <div style={{ display: 'flex', gap: '.4rem', flexWrap: 'wrap' }}>
                          {v.entitlements.map((e, i) => (
                            <span key={i} className="ih-tag" style={{ fontSize: '.7rem', direction: 'ltr' }}>{e.key}: {e.value}</span>
                          ))}
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </Sec>
          ))}
        </div>
      )}
    </AppShell>
  );
}

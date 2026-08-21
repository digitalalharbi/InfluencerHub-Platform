import { Head } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { adminNav } from '@/lib/nav';
import { ListHead, Kpi, numFmt } from '@/Components/ui';
import { Icon } from '@/Components/Icon';

interface Entitlement { key: string; value: string }
interface Version { version: number; active: boolean; locked: boolean; entitlements: Entitlement[] }
interface Plan { id: number; key: string; name: string; active: boolean; versions: Version[] }
interface Summary { total: number; active: number; versions: number; liveVersions: number }
interface Props { plans: Plan[]; summary: Summary }

export default function AdminPlans({ plans, summary }: Props) {
  return (
    <AppShell heading="الخطط" nav={adminNav} portal="admin" wsName="إدارة المنصّة" wsPlan="مدير النظام" brand="InfluencerHub">
      <Head title="الخطط" />
      <ListHead eyebrow="المنصّة · التسعير" title="الخطط والإصدارات" sub="خطط الاشتراك ونُسخها وحقوق كل نسخة (عرض فقط)." />

      <div className="ih-kpis">
        <Kpi label="إجمالي الخطط" icon="shield-check" value={numFmt(summary.total)} sub={`${summary.active} نشطة`} />
        <Kpi label="خطط نشطة" icon="clipboard-check" tone="success" value={numFmt(summary.active)} sub="متاحة للاشتراك" />
        <Kpi label="إجمالي الإصدارات" icon="layout-dashboard" tone="accent" value={numFmt(summary.versions)} sub="عبر كل الخطط" />
        <Kpi label="إصدارات فعّالة" icon="activity" tone="warning" value={numFmt(summary.liveVersions)} sub="النسخة الحيّة" />
      </div>

      {plans.length === 0 ? (
        <div className="ih-empty">
          <span className="ih-empty__icon"><Icon name="shield-check" size={26} /></span>
          <div className="ih-empty__title">لا خطط</div>
          <div className="ih-empty__text">لم تُعرّف أي خطة اشتراك بعد.</div>
        </div>
      ) : (
        <div style={{ display: 'grid', gap: '1rem' }}>
          {plans.map((p) => (
            <div key={p.id} className="card" style={{ padding: '1rem 1.15rem' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '.6rem', flexWrap: 'wrap', marginBottom: '.9rem' }}>
                <span className="ih-idc__av" style={{ background: 'var(--ih-primary-soft)', color: 'var(--ih-primary-700)' }}><Icon name="shield-check" size={18} /></span>
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '.4rem', flexWrap: 'wrap' }}>
                    <b style={{ fontSize: '1rem' }}>{p.name}</b>
                    {p.active
                      ? <span className="badge" style={{ background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)' }}>نشطة</span>
                      : <span className="badge" style={{ background: 'var(--ih-gray-100)', color: 'var(--ih-gray-600)' }}>غير نشطة</span>}
                  </div>
                  <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', direction: 'ltr', textAlign: 'start' }}>{p.key}</div>
                </div>
                <span style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>{p.versions.length} إصدار</span>
              </div>

              {p.versions.length === 0 ? (
                <div style={{ fontSize: '.82rem', color: 'var(--ih-text-muted)' }}>لا إصدارات.</div>
              ) : (
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(260px, 1fr))', gap: '.7rem' }}>
                  {p.versions.map((v) => (
                    <div key={v.version} style={{ border: '1px solid var(--ih-border)', borderRadius: 'var(--ih-radius-md, 8px)', padding: '.7rem .8rem', background: v.active ? 'var(--ih-primary-50, #F5F8FF)' : 'var(--ih-surface)' }}>
                      <div style={{ display: 'flex', gap: '.4rem', alignItems: 'center', marginBottom: '.5rem' }}>
                        <span style={{ fontWeight: 800, direction: 'ltr' }}>إصدار {v.version}</span>
                        {v.active && <span className="badge" style={{ background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)', fontSize: '.6rem' }}>فعّال</span>}
                        {v.locked && <span className="badge" style={{ background: 'var(--ih-gray-100)', color: 'var(--ih-gray-600)', fontSize: '.6rem' }}>مقفل</span>}
                      </div>
                      {v.entitlements.length === 0 ? (
                        <div style={{ fontSize: '.76rem', color: 'var(--ih-text-muted)' }}>لا حقوق.</div>
                      ) : (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '.3rem' }}>
                          {v.entitlements.map((e, i) => (
                            <div key={i} style={{ display: 'flex', justifyContent: 'space-between', gap: '.5rem', fontSize: '.74rem', borderBottom: i < v.entitlements.length - 1 ? '1px dotted var(--ih-border)' : 0, paddingBottom: '.25rem' }}>
                              <span style={{ color: 'var(--ih-text-muted)', direction: 'ltr', textAlign: 'start' }}>{e.key}</span>
                              <span style={{ fontWeight: 700, direction: 'ltr' }}>{e.value}</span>
                            </div>
                          ))}
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </AppShell>
  );
}

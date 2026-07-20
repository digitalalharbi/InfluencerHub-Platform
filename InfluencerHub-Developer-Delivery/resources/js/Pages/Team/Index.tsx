import { Head } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { ListHead, StatusBadge, Kpi, Sec, Bar } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';

interface Member {
  id: number; name: string; email: string | null; role: string; roleLabel: string;
  status: string; statusLabel: string; statusTone: string;
  open: number; breached: number; canReview: boolean; isSelf: boolean;
}
interface Props {
  members: Member[];
  summary: { total: number; active: number; openWork: number; breached: number; unassigned: number };
  byRole: { role: string; label: string; count: number }[];
}

export default function TeamIndex({ members, summary, byRole }: Props) {
  const maxOpen = Math.max(...members.map((m) => m.open), 1);

  return (
    <AppShell heading="الفريق">
      <Head title="الفريق" />
      <ListHead eyebrow="الإدارة" title="الفريق" sub="أعضاء مساحة العمل وأدوارهم وحِملهم الحالي." />

      <div className="ih-kpis">
        <Kpi label="الأعضاء" icon="users" value={summary.total.toLocaleString('en-US')} sub={`${summary.active} نشط`} />
        <Kpi label="عمل مفتوح" icon="inbox" value={summary.openWork.toLocaleString('en-US')} sub="طلبات مُسنَدة" />
        <Kpi label="تجاوز SLA" icon="activity" tone={summary.breached ? 'danger' : 'success'}
          value={summary.breached.toLocaleString('en-US')} sub={summary.breached ? 'يحتاج تدخّلًا' : 'لا تجاوزات'} />
        <Kpi label="غير مُسنَد" icon="user-plus" tone={summary.unassigned ? 'warning' : undefined}
          value={summary.unassigned.toLocaleString('en-US')} sub="بانتظار إسناد" href={u("/service-requests?seg=unassigned")} />
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0,1.6fr) minmax(0,1fr)', gap: '1.1rem', alignItems: 'start' }} className="ih-settings-grid">
        <Sec title="الأعضاء" icon="users">
          {members.length === 0 ? (
            <div style={{ padding: '2rem', textAlign: 'center', color: 'var(--ih-text-muted)' }}>لا أعضاء.</div>
          ) : (
            <div style={{ display: 'grid', gap: '.5rem', padding: '.7rem' }}>
              {members.map((m) => (
                <div key={m.id} className="card" style={{ display: 'flex', alignItems: 'center', gap: '.75rem', padding: '.7rem .85rem' }}>
                  <span className="ih-idc__av" style={{ width: 36, height: 36, flexShrink: 0 }}>{m.name.slice(0, 1)}</span>
                  <div style={{ minWidth: 0, flex: 1 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '.4rem', flexWrap: 'wrap' }}>
                      <span style={{ fontWeight: 650, fontSize: '.9rem' }}>{m.name}</span>
                      {m.isSelf && <span className="ih-tag" style={{ fontSize: '.6rem' }}>أنت</span>}
                    </div>
                    <div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)', direction: 'ltr', textAlign: 'start' }}>{m.email ?? '—'}</div>
                  </div>
                  <span className="ih-tag" style={{ flexShrink: 0 }}>{m.roleLabel}</span>
                  <div style={{ width: 110, flexShrink: 0 }}>
                    {m.open > 0 ? (
                      <>
                        <div style={{ fontSize: '.7rem', color: 'var(--ih-text-muted)', marginBottom: 2, display: 'flex', justifyContent: 'space-between' }}>
                          <span>{m.open} مفتوح</span>
                          {m.breached > 0 && <span style={{ color: 'var(--ih-danger-ink)', fontWeight: 700 }}>{m.breached}</span>}
                        </div>
                        <Bar pct={Math.round((m.open / maxOpen) * 100)} over={m.breached > 0} />
                      </>
                    ) : (
                      <span style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)' }}>لا عمل مفتوح</span>
                    )}
                  </div>
                  <StatusBadge tone={m.statusTone} label={m.statusLabel} />
                </div>
              ))}
            </div>
          )}
        </Sec>

        <div style={{ display: 'grid', gap: '1.1rem' }}>
          <Sec title="توزيع الأدوار" icon="shield-check">
            <div className="ih-sec__body" style={{ display: 'grid', gap: '.65rem' }}>
              {byRole.map((r) => {
                const max = Math.max(...byRole.map((x) => x.count), 1);
                return (
                  <div key={r.role}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '.82rem', marginBottom: '.25rem' }}>
                      <span style={{ fontWeight: 600 }}>{r.label}</span>
                      <span style={{ color: 'var(--ih-text-muted)', direction: 'ltr' }}>{r.count}</span>
                    </div>
                    <Bar pct={Math.round((r.count / max) * 100)} />
                  </div>
                );
              })}
            </div>
          </Sec>

          <div className="card" style={{ padding: '.85rem 1rem', borderInlineStart: '3px solid var(--ih-info)', background: 'var(--ih-info-soft)', color: 'var(--ih-info-ink)', fontSize: '.82rem', lineHeight: 1.6 }}>
            <Icon name="shield-check" size={14} /> الأدوار تُدار من إعدادات المؤسسة. الصلاحيات تُطبَّق من الخادم لكل طلب — لا يكفي إخفاء الأزرار.
          </div>
        </div>
      </div>
    </AppShell>
  );
}

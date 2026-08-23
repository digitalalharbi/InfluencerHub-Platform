import { Head } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { WorkspaceHeader, StatusBadge, Kpi, Sec } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';

interface Member {
  id: number; name: string; email: string | null; role: string; roleLabel: string;
  status: string; statusLabel: string; statusTone: string; isSelf: boolean; canReview: boolean;
}
interface Work {
  id: number; number: string; title: string; status: string; statusLabel: string; statusTone: string;
  priority: string; due: string | null; breached: boolean;
}
interface Props {
  member: Member;
  work: Work[];
  summary: { open: number; breached: number; resolved: number; total: number };
}

export default function TeamMember({ member, work, summary }: Props) {
  return (
    <AppShell heading="عضو الفريق">
      <Head title={member.name} />
      <WorkspaceHeader
        eyebrow={`الفريق · ${member.roleLabel}`}
        title={member.name}
        statusTone={member.statusTone}
        statusLabel={member.statusLabel}
        back={u('/team')}
        backLabel="كل الفريق"
        meta={[
          ['الدور', member.roleLabel],
          ['البريد', member.email ?? '—'],
          ['يراجع', member.canReview ? 'نعم' : 'لا'],
        ]}
      />

      <div className="ih-kpis">
        <Kpi label="عمل مفتوح" icon="inbox" value={summary.open.toLocaleString('en-US')} sub="طلبات مُسنَدة" />
        <Kpi label="متأخر (SLA)" icon="activity" tone={summary.breached ? 'danger' : 'success'}
          value={summary.breached.toLocaleString('en-US')} sub={summary.breached ? 'يحتاج تدخّلًا' : 'لا تأخّر'} />
        <Kpi label="مُنجَز" icon="clipboard-check" value={summary.resolved.toLocaleString('en-US')} sub="حُلّ/أُغلق" />
        <Kpi label="إجمالي مُسنَد" icon="users" value={summary.total.toLocaleString('en-US')} />
      </div>

      <Sec title="العمل المفتوح المُسنَد" icon="inbox">
        {work.length === 0 ? (
          <div style={{ padding: '2rem', textAlign: 'center', color: 'var(--ih-text-muted)' }}>
            <Icon name="inbox" size={22} /><div style={{ marginTop: '.5rem' }}>لا عمل مفتوح على هذا العضو.</div>
          </div>
        ) : (
          <div className="ih-dt-wrap"><div className="ih-dt-scroll">
            <table className="ih-dt">
              <thead><tr><th>الطلب</th><th>الأولوية</th><th>الاستحقاق</th><th>الحالة</th></tr></thead>
              <tbody>
                {work.map((w) => (
                  <tr key={w.id}>
                    <td>
                      <a href={u(`/service-requests/${w.id}`)} style={{ color: 'var(--ih-primary)', fontWeight: 600 }}>{w.title}</a>
                      <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{w.number}</div>
                    </td>
                    <td>{w.priority}</td>
                    <td style={{ direction: 'ltr', fontSize: '.8rem', color: w.breached ? 'var(--ih-danger-ink)' : 'var(--ih-text-muted)', fontWeight: w.breached ? 700 : 400 }}>
                      {w.due ?? '—'}{w.breached ? ' · متأخر' : ''}
                    </td>
                    <td><StatusBadge tone={w.statusTone} label={w.statusLabel} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div></div>
        )}
      </Sec>

      <div className="card" style={{ marginTop: '1.1rem', padding: '.85rem 1rem', borderInlineStart: '3px solid var(--ih-info)', background: 'var(--ih-info-soft)', color: 'var(--ih-info-ink)', fontSize: '.82rem', lineHeight: 1.6 }}>
        <Icon name="shield-check" size={14} /> الحِمل من الطلبات المُسنَدة فعليًا (بحسب الحالة والاستحقاق) — لإعادة الإسناد افتح الطلب. الدور: <b>{member.roleLabel}</b> · الحالة: <StatusBadge tone={member.statusTone} label={member.statusLabel} />
      </div>
    </AppShell>
  );
}

import { Head } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { adminNav } from '@/lib/nav';
import { ListHead, Kpi, numFmt } from '@/Components/ui';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { Icon } from '@/Components/Icon';

interface Row {
  id: number; action: string; actor: string; type: string; auditableId: number | null;
  tenantId: number | null; ip: string | null; at: string | null;
}
interface Summary { total: number; today: number; week: number; actors: number }
interface Props { logs: Paginated<Row>; summary: Summary }

export default function AdminAudit({ logs, summary }: Props) {
  return (
    <AppShell heading="سجل التدقيق" nav={adminNav} portal="admin" wsName="إدارة المنصّة" wsPlan="مدير النظام" brand="InfluencerHub">
      <Head title="سجل التدقيق" />
      <ListHead eyebrow="الإشراف" title="سجل التدقيق" sub="أحداث المنصّة عبر المستأجرين — أحدث أولًا، للقراءة فقط." />

      <div className="ih-kpis">
        <Kpi label="إجمالي الأحداث" icon="file-text" value={numFmt(summary.total)} sub="منذ البداية" />
        <Kpi label="اليوم" icon="activity" tone="accent" value={numFmt(summary.today)} sub="خلال 24 ساعة" />
        <Kpi label="آخر 7 أيام" icon="calendar-days" tone="success" value={numFmt(summary.week)} sub="هذا الأسبوع" />
        <Kpi label="المنفِّذون" icon="users" tone="warning" value={numFmt(summary.actors)} sub="جهات فاعلة مميّزة" />
      </div>

      {logs.data.length === 0 ? (
        <div className="ih-empty">
          <span className="ih-empty__icon"><Icon name="file-text" size={26} /></span>
          <div className="ih-empty__title">لا سجلات تدقيق</div>
          <div className="ih-empty__text">ستظهر هنا كل الأحداث الرقابية عبر المنصّة.</div>
        </div>
      ) : (
        <>
          <div className="ih-only-desktop">
            <div className="ih-dt-wrap"><div className="ih-dt-scroll">
              <table className="ih-dt">
                <thead><tr><th>الحدث</th><th>المنفِّذ</th><th>الكائن</th><th>المستأجر</th><th>IP</th><th>الوقت</th></tr></thead>
                <tbody>
                  {logs.data.map((a) => (
                    <tr key={a.id}>
                      <td><span className="ih-tag" style={{ background: 'var(--ih-primary-soft)', color: 'var(--ih-primary-700)', fontWeight: 700 }}>{a.action}</span></td>
                      <td>{a.actor}</td>
                      <td>{a.type}{a.auditableId ? <span style={{ direction: 'ltr', color: 'var(--ih-text-muted)' }}> #{a.auditableId}</span> : ''}</td>
                      <td className="ih-dt__num" style={{ direction: 'ltr', textAlign: 'right' }}>{a.tenantId ?? '—'}</td>
                      <td style={{ direction: 'ltr', textAlign: 'right', fontSize: '.76rem', color: 'var(--ih-text-muted)', fontFamily: 'var(--ih-font-mono, monospace)' }}>{a.ip ?? '—'}</td>
                      <td style={{ direction: 'ltr', textAlign: 'right', fontSize: '.76rem', color: 'var(--ih-text-muted)' }}>{a.at}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
              <div className="ih-dt__foot"><span>{logs.total.toLocaleString('en-US')} حدث</span><Pagination links={logs.links} /></div>
            </div>
          </div>

          <div className="ih-only-mobile">
            <div className="ih-mlist">
              {logs.data.map((a) => (
                <div key={a.id} className="ih-mcard">
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: '.5rem' }}>
                    <span className="ih-tag" style={{ background: 'var(--ih-primary-soft)', color: 'var(--ih-primary-700)', fontWeight: 700 }}>{a.action}</span>
                    <span style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{a.at}</span>
                  </div>
                  <div className="ih-mcard__grid">
                    <div><span className="ih-mcard__lbl">المنفِّذ</span><span className="ih-mcard__val">{a.actor}</span></div>
                    <div><span className="ih-mcard__lbl">الكائن</span><span className="ih-mcard__val">{a.type}{a.auditableId ? ` #${a.auditableId}` : ''}</span></div>
                    <div><span className="ih-mcard__lbl">IP</span><span className="ih-mcard__val" style={{ direction: 'ltr' }}>{a.ip ?? '—'}</span></div>
                  </div>
                </div>
              ))}
            </div>
            <Pagination links={logs.links} />
          </div>
        </>
      )}
    </AppShell>
  );
}

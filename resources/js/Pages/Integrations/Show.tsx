import { Head, router, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { WorkspaceHeader, StatusBadge } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';
import type { SharedProps } from '@/types';

interface Connection {
  status: string; environment: string; health: string; healthLabel: string; lastSync: string | null;
  lastAttempt: string | null; lastError: string | null; connected: boolean;
  account: string | null; scopes: string[]; nextSync: string | null;
}
interface Run {
  id: number; type: string; status: string; capability: string | null; started: string | null; completed: string | null;
  durationSec: number | null; fetched: number; created: number; updated: number; failed: number; retry: number; error: string | null;
}
interface Props { connection: Connection; name: string; runs: Run[]; canSync: boolean }

const RUN_TONE: Record<string, string> = { success: 'active', partial: 'under_review', running: 'submitted', queued: 'draft', failed: 'changes_requested' };

export default function IntegrationShow({ connection, name, runs, canSync }: Props) {
  const flash = usePage<SharedProps>().props.flash;
  const errors = (usePage().props.errors ?? {}) as Record<string, string>;
  const healthTone = connection.health === 'healthy' ? 'active' : connection.health === 'error' ? 'changes_requested' : 'under_review';

  return (
    <AppShell heading="تفاصيل التكامل">
      <Head title={name} />
      {flash?.ok && <div className="card" style={{ padding: '.7rem 1rem', marginBottom: '1rem', borderInlineStart: '3px solid var(--ih-success)', background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)' }}>{flash.ok}</div>}
      {errors.sync && <div className="card" style={{ padding: '.7rem 1rem', marginBottom: '1rem', borderInlineStart: '3px solid var(--ih-danger)', background: 'var(--ih-danger-soft)', color: 'var(--ih-danger-ink)' }}>{errors.sync}</div>}

      <WorkspaceHeader
        eyebrow="تكامل" title={name}
        statusTone={healthTone} statusLabel={connection.healthLabel}
        back={u('/integrations')} backLabel="كل التكاملات"
        meta={[
          ['الحالة', connection.status], ['البيئة', connection.environment],
          ['الحساب', connection.account ?? '—'], ['آخر مزامنة', connection.lastSync ?? '—'],
        ]}
        actions={canSync ? <button className="btn btn-sm btn-primary" onClick={() => router.post(u(`/integrations/${window.location.pathname.split('/').pop()}/sync`), {}, { preserveScroll: true })}><Icon name="activity" size={14} /> زامِن الآن</button> : undefined}
      />

      {connection.lastError && (
        <div className="card" style={{ padding: '.8rem 1rem', marginBottom: '1rem', borderInlineStart: '3px solid var(--ih-danger)', background: 'var(--ih-danger-soft)', color: 'var(--ih-danger-ink)' }}>
          <b>آخر خطأ:</b> {connection.lastError}
        </div>
      )}

      <div className="ih-sec">
        <div className="ih-sec__head"><span className="ih-sec__title"><Icon name="activity" size={16} /> سجلّ المزامنة</span></div>
        {runs.length === 0 ? (
          <div style={{ padding: '2rem', textAlign: 'center', color: 'var(--ih-text-muted)' }}>لا تشغيلات مزامنة بعد.</div>
        ) : (
          <div className="ih-dt-wrap"><div className="ih-dt-scroll">
            <table className="ih-dt">
              <thead><tr><th>النوع</th><th>القدرة</th><th>الحالة</th><th>البدء</th><th>المدّة</th><th>جُلب</th><th>أُنشئ</th><th>حُدّث</th><th>فشل</th><th>إعادة</th></tr></thead>
              <tbody>
                {runs.map((r) => (
                  <tr key={r.id}>
                    <td>{r.type}</td>
                    <td>{r.capability ?? '—'}</td>
                    <td><StatusBadge tone={RUN_TONE[r.status] ?? 'draft'} label={r.status} /></td>
                    <td style={{ direction: 'ltr', fontSize: '.78rem' }}>{r.started ?? '—'}</td>
                    <td style={{ direction: 'ltr' }}>{r.durationSec != null ? `${r.durationSec}s` : '—'}</td>
                    <td style={{ direction: 'ltr' }}>{r.fetched}</td>
                    <td style={{ direction: 'ltr' }}>{r.created}</td>
                    <td style={{ direction: 'ltr' }}>{r.updated}</td>
                    <td style={{ direction: 'ltr', color: r.failed ? 'var(--ih-danger-ink)' : undefined }}>{r.failed}</td>
                    <td style={{ direction: 'ltr' }}>{r.retry}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div></div>
        )}
      </div>
    </AppShell>
  );
}

import { Head, router, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { ListHead, StatusBadge } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';
import type { SharedProps } from '@/types';

interface Rule {
  id: number; name: string; key: string; trigger: string; triggerLabel: string; enabled: boolean;
  isSystem: boolean; conditions: { field: string; op: string; value: unknown }[]; actions: string[]; lastRun: string | null;
}
interface Run { id: number; trigger: string; status: string; eventKey: string | null; actions: string[]; error: string | null; at: string | null }
interface Props { rules: Rule[]; runs: Run[] }

const RUN_TONE: Record<string, string> = { executed: 'active', skipped: 'draft', failed: 'changes_requested' };
const RUN_LABEL: Record<string, string> = { executed: 'نُفِّذت', skipped: 'تُخطّيت', failed: 'فشلت' };

export default function AutomationIndex({ rules, runs }: Props) {
  const flash = usePage<SharedProps>().props.flash;
  const toggle = (id: number) => router.post(u(`/automation/${id}/toggle`), {}, { preserveScroll: true });

  return (
    <AppShell heading="الأتمتة">
      <Head title="الأتمتة" />
      {flash?.ok && <div className="card" style={{ padding: '.7rem 1rem', marginBottom: '1rem', borderInlineStart: '3px solid var(--ih-success)', background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)' }}>{flash.ok}</div>}

      <ListHead eyebrow="التشغيل الذكي" title="الأتمتة" sub="قواعد تعمل تلقائيًّا على أحداث سير العمل — إشعارات ومهام وتصعيد." />

      <div className="ih-sec" style={{ marginBottom: '1.2rem' }}>
        <div className="ih-sec__head"><span className="ih-sec__title"><Icon name="sparkles" size={16} /> القواعد</span></div>
        <div className="ih-dt-wrap"><div className="ih-dt-scroll">
          <table className="ih-dt">
            <thead><tr><th>القاعدة</th><th>المحفّز</th><th>الشروط</th><th>الإجراءات</th><th>آخر تنفيذ</th><th>الحالة</th></tr></thead>
            <tbody>
              {rules.map((r) => (
                <tr key={r.id}>
                  <td><span style={{ fontWeight: 600 }}>{r.name}</span>{r.isSystem && <span className="ih-tag" style={{ fontSize: '.58rem', marginInlineStart: '.4rem' }}>نظام</span>}</td>
                  <td>{r.triggerLabel}</td>
                  <td style={{ fontSize: '.78rem', color: 'var(--ih-text-muted)' }}>{r.conditions.length ? r.conditions.map((c) => `${c.field} ${c.op} ${String(c.value)}`).join('، ') : 'بلا شرط'}</td>
                  <td>{r.actions.map((a, i) => <span key={i} className="ih-tag" style={{ fontSize: '.62rem', marginInlineEnd: '.25rem' }}>{a}</span>)}</td>
                  <td style={{ direction: 'ltr', fontSize: '.78rem', color: 'var(--ih-text-muted)' }}>{r.lastRun ?? '—'}</td>
                  <td>
                    <button onClick={() => toggle(r.id)} className={`btn btn-xs ${r.enabled ? 'btn-outline' : 'btn-primary'}`}>
                      {r.enabled ? 'تعطيل' : 'تفعيل'}
                    </button>
                    <StatusBadge tone={r.enabled ? 'active' : 'draft'} label={r.enabled ? 'مُفعّلة' : 'معطّلة'} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div></div>
      </div>

      <div className="ih-sec">
        <div className="ih-sec__head"><span className="ih-sec__title"><Icon name="activity" size={16} /> سجلّ التشغيل</span></div>
        {runs.length === 0 ? (
          <div style={{ padding: '2rem', textAlign: 'center', color: 'var(--ih-text-muted)' }}>لا تشغيلات بعد.</div>
        ) : (
          <div className="ih-dt-wrap"><div className="ih-dt-scroll">
            <table className="ih-dt">
              <thead><tr><th>الحدث</th><th>الحالة</th><th>الإجراءات</th><th>الوقت</th><th>خطأ</th></tr></thead>
              <tbody>
                {runs.map((x) => (
                  <tr key={x.id}>
                    <td>{x.trigger}<div style={{ fontSize: '.66rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{x.eventKey}</div></td>
                    <td><StatusBadge tone={RUN_TONE[x.status] ?? 'draft'} label={RUN_LABEL[x.status] ?? x.status} /></td>
                    <td>{x.actions.map((a, i) => <span key={i} className="ih-tag" style={{ fontSize: '.6rem', marginInlineEnd: '.2rem' }}>{a}</span>)}</td>
                    <td style={{ direction: 'ltr', fontSize: '.78rem', color: 'var(--ih-text-muted)' }}>{x.at}</td>
                    <td style={{ color: 'var(--ih-danger-ink)', fontSize: '.76rem' }}>{x.error ?? ''}</td>
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

import { Head, router, usePage } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { ListHead, StatusBadge } from '@/Components/ui';
import { Donut, Legend } from '@/Components/Charts';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';
import type { SharedProps } from '@/types';

interface Rule {
  id: number; name: string; key: string; trigger: string; triggerLabel: string; description: string; enabled: boolean;
  isSystem: boolean; conditions: { field: string; op: string; value: unknown }[]; actions: string[];
  lastRun: string | null; runCount: number; failures: number;
}
interface Run { id: number; trigger: string; status: string; eventKey: string | null; actions: string[]; error: string | null; at: string | null }
interface ScheduledReminder { key: string; description: string; schedule: string; count: number; lastRun: string | null }
interface Props { rules: Rule[]; runs: Run[]; scheduledReminders: ScheduledReminder[] }

const RUN_TONE: Record<string, string> = { executed: 'active', skipped: 'draft', failed: 'changes_requested' };
const RUN_LABEL: Record<string, string> = { executed: 'نُفِّذت', skipped: 'تُخطّيت', failed: 'فشلت' };

export default function AutomationIndex({ rules, runs, scheduledReminders }: Props) {
  const flash = usePage<SharedProps>().props.flash;
  const toggle = (id: number) => router.post(u(`/automation/${id}/toggle`), {}, { preserveScroll: true });

  return (
    <AppShell heading="الأتمتة">
      <Head title="الأتمتة" />
      {flash?.ok && <div className="card" style={{ padding: '.7rem 1rem', marginBottom: '1rem', borderInlineStart: '3px solid var(--ih-success)', background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)' }}>{flash.ok}</div>}

      <ListHead eyebrow="التشغيل الذكي" title="الأتمتة" sub="قواعد تعمل تلقائيًّا على أحداث سير العمل — إشعارات ومهام وتصعيد." />

      {/* مركز صحّة الأتمتة — يُجيب فورًا: كم قاعدة تعمل، وهل التشغيلات الأخيرة سليمة */}
      {(() => {
        const enabled = rules.filter((r) => r.enabled).length;
        const failed = runs.filter((x) => x.status === 'failed').length;
        const outcomeSegs = [
          { label: 'نُفِّذت', value: runs.filter((x) => x.status === 'executed').length, color: 'var(--ih-success-700, #067647)' },
          { label: 'تُخطّيت', value: runs.filter((x) => x.status === 'skipped').length, color: 'var(--ih-gray-400, #98A2B3)' },
          { label: 'فشلت', value: failed, color: 'var(--ih-danger-ink, #B42318)' },
        ];
        return (
          <div className="card" style={{ padding: '1.1rem 1.3rem', marginBottom: '1.2rem', display: 'flex', alignItems: 'center', gap: '1.6rem', flexWrap: 'wrap' }}>
            {runs.length > 0 && (
              <Donut segments={outcomeSegs} size={120} centerValue={runs.length} centerLabel="آخر التشغيلات" ariaLabel={outcomeSegs.map((s) => `${s.label}: ${s.value}`).join('، ')} />
            )}
            <div style={{ flex: 1, minWidth: 200, display: 'grid', gap: '.7rem' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '.6rem', flexWrap: 'wrap' }}>
                <span style={{ fontWeight: 800, fontSize: '.98rem' }}>{enabled} من {rules.length} قاعدة مُفعّلة</span>
                {failed > 0
                  ? <span className="badge" style={{ background: 'var(--ih-danger-soft, #FEF3F2)', color: 'var(--ih-danger-ink, #B42318)', fontWeight: 700 }}>{failed} تشغيلة فاشلة تحتاج مراجعة</span>
                  : runs.length > 0 && <span className="badge" style={{ background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)', fontWeight: 700 }}>لا أعطال في آخر التشغيلات</span>}
              </div>
              {runs.length > 0
                ? <Legend segments={outcomeSegs} />
                : <div style={{ fontSize: '.84rem', color: 'var(--ih-text-muted)' }}>لا تشغيلات بعد — ستظهر صحّة الأتمتة هنا بمجرّد وقوع أوّل حدث.</div>}
            </div>
          </div>
        );
      })()}

      {/* القواعد — بطاقات مقروءة للإنسان: «متى ← ماذا» + إحصاء التشغيل، بلا مصطلحات محفّز/حدث. */}
      <div className="ih-sec" style={{ marginBottom: '1.2rem' }}>
        <div className="ih-sec__head"><span className="ih-sec__title"><Icon name="sparkles" size={16} /> القواعد</span></div>
        <div style={{ display: 'grid', gap: '.6rem', padding: '.6rem' }}>
          {rules.map((r) => (
            <div key={r.id} className="card" style={{ padding: '.85rem 1rem', display: 'flex', alignItems: 'center', gap: '1rem', flexWrap: 'wrap', opacity: r.enabled ? 1 : 0.7 }}>
              <div style={{ flex: 1, minWidth: 220 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '.45rem', flexWrap: 'wrap' }}>
                  <span style={{ fontWeight: 800, fontSize: '.9rem' }}>{r.name}</span>
                  {r.isSystem && <span className="ih-tag" style={{ fontSize: '.58rem' }}>نظام</span>}
                  <StatusBadge tone={r.enabled ? 'active' : 'draft'} label={r.enabled ? 'مُفعّلة' : 'معطّلة'} />
                </div>
                <div style={{ fontSize: '.82rem', color: 'var(--ih-text-secondary)', marginTop: '.3rem' }}>{r.description}</div>
                <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', marginTop: '.35rem', display: 'flex', gap: '.8rem', flexWrap: 'wrap' }}>
                  <span>نُفِّذت <b style={{ color: 'var(--ih-text)' }}>{r.runCount.toLocaleString('en-US')}</b> مرة</span>
                  <span>آخر تنفيذ: <span style={{ direction: 'ltr', display: 'inline-block' }}>{r.lastRun ?? '—'}</span></span>
                  {r.failures > 0
                    ? <span style={{ color: 'var(--ih-danger-ink)', fontWeight: 700 }}>{r.failures.toLocaleString('en-US')} فشل</span>
                    : <span style={{ color: 'var(--ih-success-ink)' }}>بلا أعطال</span>}
                </div>
              </div>
              <button onClick={() => toggle(r.id)} className={`btn btn-xs ${r.enabled ? 'btn-outline' : 'btn-primary'}`} style={{ flexShrink: 0 }}>
                {r.enabled ? 'تعطيل' : 'تفعيل'}
              </button>
            </div>
          ))}
        </div>
      </div>

      {/* التذكيرات المجدولة — أوامر زمنيّة تعمل دوريًّا (لا قواعد أحداث): «متى ← ماذا» + الجدولة
          والعدّ وآخر تنفيذ من سجلّ التدقيق. تجعل الأتمتة الزمنيّة مرئيّة وموثوقة. */}
      <div className="ih-sec" style={{ marginBottom: '1.2rem' }}>
        <div className="ih-sec__head"><span className="ih-sec__title"><Icon name="calendar-days" size={16} /> التذكيرات المجدولة</span></div>
        <div style={{ display: 'grid', gap: '.6rem', padding: '.6rem' }}>
          {scheduledReminders.map((rm) => (
            <div key={rm.key} className="card" style={{ padding: '.85rem 1rem', display: 'flex', alignItems: 'center', gap: '1rem', flexWrap: 'wrap' }}>
              <div style={{ flex: 1, minWidth: 220 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '.45rem', flexWrap: 'wrap' }}>
                  <span className="ih-tag" style={{ background: 'var(--ih-primary-soft)', color: 'var(--ih-primary-700)', fontSize: '.62rem' }}>{rm.schedule}</span>
                  <StatusBadge tone="active" label="مُفعّلة" />
                </div>
                <div style={{ fontSize: '.82rem', color: 'var(--ih-text-secondary)', marginTop: '.3rem' }}>{rm.description}</div>
                <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', marginTop: '.35rem', display: 'flex', gap: '.8rem', flexWrap: 'wrap' }}>
                  <span>أُطلِقت <b style={{ color: 'var(--ih-text)' }}>{rm.count.toLocaleString('en-US')}</b> مرة</span>
                  <span>آخر إطلاق: <span style={{ direction: 'ltr', display: 'inline-block' }}>{rm.lastRun ?? '—'}</span></span>
                </div>
              </div>
            </div>
          ))}
        </div>
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

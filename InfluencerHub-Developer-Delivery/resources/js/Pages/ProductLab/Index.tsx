import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Sec, WorkTabs, WorkspaceHeader, type WorkTab } from '@/Components/ui';
import { Icon } from '@/Components/Icon';

interface Step {
  label: string; done: boolean; count: number | null;
  link: string | null; note?: string; blocker?: string;
}
interface Journey { key: string; title: string; subtitle: string; start: string; steps: Step[] }
interface ModuleGroup { group: string; items: { label: string; href: string }[] }
interface Account { role: string; email: string; login: string; lands: string; exists: boolean }
interface Integration { key: string; name: string; status: string; label: string; available: boolean; note?: string }
interface Blocker { title: string; impact: string; needs: string }
interface Services {
  database: { ok: boolean; driver: string; error: string | null };
  queue: { driver: string; pending: number | null; failed: number | null };
  cache: { driver: string }; session: { driver: string; active: number | null };
  mail: { driver: string }; env: string; debug: boolean;
}
interface Dataset { exists: boolean; tenant?: string; counts?: Record<string, number> }
interface Props {
  journeys: Journey[]; modules: ModuleGroup[]; accounts: Account[];
  integrations: Integration[]; blockers: Blocker[]; services: Services;
  dataset: Dataset; changelog: { hash: string; date: string; subject: string }[];
}

const TONE: Record<string, { bg: string; fg: string }> = {
  ok: { bg: 'var(--ih-success-soft)', fg: 'var(--ih-success-ink)' },
  warn: { bg: 'var(--ih-warning-soft)', fg: 'var(--ih-warning-ink)' },
  bad: { bg: 'var(--ih-danger-soft)', fg: 'var(--ih-danger-ink)' },
  mute: { bg: 'var(--ih-surface-sunken)', fg: 'var(--ih-text-muted)' },
};
function Pill({ tone, children }: { tone: keyof typeof TONE; children: React.ReactNode }) {
  const t = TONE[tone];
  return <span className="badge" style={{ background: t.bg, color: t.fg, fontSize: '.66rem', whiteSpace: 'nowrap' }}>{children}</span>;
}

const statusTone = (i: Integration): keyof typeof TONE =>
  i.status === 'connected' ? 'ok' : i.available ? 'ok'
    : i.status === 'unavailable' || i.status === 'disconnected' ? 'bad'
    : i.status === 'draft' ? 'mute' : 'warn';

export default function ProductLab({ journeys, modules, accounts, integrations, blockers, services, dataset, changelog }: Props) {
  const [tab, setTab] = useState('journeys');
  const [busy, setBusy] = useState(false);

  const tabs: WorkTab[] = [
    { key: 'journeys', label: 'الرحلات', icon: 'git-merge', count: journeys.length },
    { key: 'modules', label: 'الوحدات', icon: 'layout-dashboard' },
    { key: 'accounts', label: 'حسابات التجربة', icon: 'users', count: accounts.length },
    { key: 'integrations', label: 'التكاملات', icon: 'plug', count: integrations.length },
    { key: 'health', label: 'الخدمات والبيانات', icon: 'activity' },
  ];

  const totalSteps = journeys.reduce((n, j) => n + j.steps.length, 0);
  const doneSteps = journeys.reduce((n, j) => n + j.steps.filter((s) => s.done).length, 0);
  const blocked = journeys.reduce((n, j) => n + j.steps.filter((s) => s.blocker).length, 0);

  return (
    <AppShell heading="مختبر رحلات المنتَج">
      <Head title="مختبر رحلات المنتَج" />

      <WorkspaceHeader
        eyebrow="بيئة تطوير · محجوبة في الإنتاج"
        title="مختبر رحلات المنتَج"
        meta={[
          ['الخطوات المنجزة', `${doneSteps}/${totalSteps}`],
          ['خطوات معطّلة بعائق', String(blocked)],
          ['البيئة', services.env],
          ['التنقيح', services.debug ? 'مُفعَّل' : 'مُطفأ'],
        ]}
      />

      <div className="card" style={{ padding: '.8rem 1rem', marginBottom: '1.1rem', borderInlineStart: '3px solid var(--ih-primary)', fontSize: '.83rem' }}>
        كل خطوة أدناه تُقاس من قاعدة البيانات: «منجزة» تعني وجود سجلّ فعلي يثبت أنها تُنفَّذ —
        لا وجود صفحة أو زر. الخطوات المعطّلة تُظهر سبب التعطّل لا تُخفيه.
      </div>

      <WorkTabs active={tab} onChange={setTab} tabs={tabs} />

      {tab === 'journeys' && (
        <div style={{ display: 'grid', gap: '1.1rem' }}>
          {journeys.map((j) => {
            const done = j.steps.filter((s) => s.done).length;
            const pct = Math.round((done / j.steps.length) * 100);
            return (
              <Sec key={j.key} title={j.title} icon="git-merge">
                <div className="ih-sec__body" style={{ display: 'grid', gap: '.8rem' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '.7rem', flexWrap: 'wrap' }}>
                    <span style={{ fontSize: '.82rem', color: 'var(--ih-text-muted)', flex: 1, minWidth: 200 }}>{j.subtitle}</span>
                    <Pill tone={pct === 100 ? 'ok' : pct >= 60 ? 'warn' : 'bad'}>{done}/{j.steps.length} · {pct}%</Pill>
                    <a href={j.start} className="btn btn-xs btn-primary">ابدأ الرحلة</a>
                  </div>

                  <div style={{ display: 'grid', gap: '.35rem' }}>
                    {j.steps.map((s, i) => (
                      <div key={i} style={{
                        display: 'flex', alignItems: 'center', gap: '.6rem', padding: '.5rem .7rem',
                        borderRadius: 'var(--ih-radius-sm)', flexWrap: 'wrap',
                        background: s.blocker ? 'var(--ih-danger-soft)' : s.done ? 'transparent' : 'var(--ih-surface-sunken)',
                      }}>
                        <span style={{ width: 22, textAlign: 'center', flexShrink: 0 }}>
                          {s.blocker ? <Icon name="activity" size={14} /> : s.done ? <Icon name="shield-check" size={14} /> : <Icon name="circle" size={12} />}
                        </span>
                        <span style={{ fontSize: '.85rem', fontWeight: s.done ? 500 : 600, flex: 1, minWidth: 160 }}>
                          {i + 1}. {s.label}
                        </span>
                        {s.count !== null && s.count > 0 && (
                          <span style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}><bdi>{s.count}</bdi> سجل</span>
                        )}
                        {s.note && <span style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', flexBasis: '100%' }}>{s.note}</span>}
                        {s.blocker && <span style={{ fontSize: '.74rem', color: 'var(--ih-danger-ink)', flexBasis: '100%' }}>عائق: {s.blocker}</span>}
                        {s.link && <a href={s.link} className="btn btn-xs btn-outline">افتح</a>}
                      </div>
                    ))}
                  </div>
                </div>
              </Sec>
            );
          })}
        </div>
      )}

      {tab === 'modules' && (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: '1rem' }}>
          {modules.map((g) => (
            <Sec key={g.group} title={g.group} icon="layout-dashboard">
              <div className="ih-sec__body" style={{ display: 'grid', gap: '.3rem' }}>
                {g.items.map((m) => (
                  <a key={m.href} href={m.href} style={{
                    display: 'flex', justifyContent: 'space-between', gap: '.5rem', padding: '.35rem .5rem',
                    borderRadius: 'var(--ih-radius-sm)', textDecoration: 'none', color: 'inherit', fontSize: '.84rem',
                  }}>
                    <span>{m.label}</span>
                    <code style={{ direction: 'ltr', fontSize: '.72rem', color: 'var(--ih-text-muted)' }}>{m.href}</code>
                  </a>
                ))}
              </div>
            </Sec>
          ))}
        </div>
      )}

      {tab === 'accounts' && (
        <Sec title="حسابات التجربة بحسب الدور" icon="users">
          <div className="ih-sec__body" style={{ display: 'grid', gap: '.5rem' }}>
            <div style={{ fontSize: '.8rem', color: 'var(--ih-text-muted)' }}>
              كلمات المرور غير معروضة هنا عمدًا — تُقرأ من ملف الاعتمادات المحلي غير المتتبَّع في Git.
            </div>
            {accounts.map((a) => (
              <div key={a.email} style={{ display: 'flex', alignItems: 'center', gap: '.6rem', flexWrap: 'wrap', padding: '.45rem .6rem', borderRadius: 'var(--ih-radius-sm)', background: 'var(--ih-surface-sunken)' }}>
                <span style={{ fontWeight: 600, fontSize: '.85rem', minWidth: 150 }}>{a.role}</span>
                <code style={{ direction: 'ltr', fontSize: '.76rem', flex: 1, minWidth: 180 }}>{a.email}</code>
                {a.exists ? <Pill tone="ok">موجود</Pill> : <Pill tone="bad">غير موجود</Pill>}
                <a href={a.login} className="btn btn-xs btn-outline">صفحة الدخول</a>
                <a href={a.lands} className="btn btn-xs btn-ghost">وجهته</a>
              </div>
            ))}
          </div>
        </Sec>
      )}

      {tab === 'integrations' && (
        <div style={{ display: 'grid', gap: '1.1rem' }}>
          <Sec title="حالات التكاملات — كما هي فعلًا" icon="plug">
            <div className="ih-sec__body" style={{ display: 'grid', gap: '.4rem' }}>
              {integrations.map((i) => (
                <div key={i.key} style={{ display: 'flex', alignItems: 'center', gap: '.6rem', flexWrap: 'wrap' }}>
                  <span style={{ fontSize: '.85rem', fontWeight: 600, minWidth: 140 }}>{i.name}</span>
                  <Pill tone={statusTone(i)}>{i.label}</Pill>
                  {i.note && <span style={{ fontSize: '.76rem', color: 'var(--ih-text-muted)' }}>{i.note}</span>}
                </div>
              ))}
            </div>
          </Sec>

          <Sec title="العوائق الخارجية" icon="activity">
            <div className="ih-sec__body" style={{ display: 'grid', gap: '.7rem' }}>
              {blockers.map((b) => (
                <div key={b.title} className="card" style={{ padding: '.7rem .9rem' }}>
                  <div style={{ fontWeight: 700, fontSize: '.86rem' }}>{b.title}</div>
                  <div style={{ fontSize: '.79rem', color: 'var(--ih-text-muted)', marginTop: '.2rem' }}>الأثر: {b.impact}</div>
                  <div style={{ fontSize: '.79rem', marginTop: '.2rem' }}>المطلوب: {b.needs}</div>
                </div>
              ))}
            </div>
          </Sec>
        </div>
      )}

      {tab === 'health' && (
        <div style={{ display: 'grid', gap: '1.1rem' }}>
          <Sec title="الخدمات" icon="activity">
            <div className="ih-sec__body" style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))', gap: '.7rem' }}>
              <div><div style={{ fontSize: '.75rem', color: 'var(--ih-text-muted)' }}>قاعدة البيانات</div>
                <div style={{ display: 'flex', gap: '.4rem', alignItems: 'center', marginTop: '.2rem' }}>
                  {services.database.ok ? <Pill tone="ok">تعمل</Pill> : <Pill tone="bad">متعثّرة</Pill>}
                  <span style={{ fontSize: '.8rem' }}>{services.database.driver}</span>
                </div></div>
              <div><div style={{ fontSize: '.75rem', color: 'var(--ih-text-muted)' }}>الطابور</div>
                <div style={{ fontSize: '.84rem', marginTop: '.2rem' }}>{services.queue.driver} · معلّق <bdi>{services.queue.pending ?? '—'}</bdi> · فاشل <bdi>{services.queue.failed ?? '—'}</bdi></div></div>
              <div><div style={{ fontSize: '.75rem', color: 'var(--ih-text-muted)' }}>الذاكرة المؤقتة</div>
                <div style={{ fontSize: '.84rem', marginTop: '.2rem' }}>{services.cache.driver}</div></div>
              <div><div style={{ fontSize: '.75rem', color: 'var(--ih-text-muted)' }}>الجلسات</div>
                <div style={{ fontSize: '.84rem', marginTop: '.2rem' }}>{services.session.driver} · نشطة <bdi>{services.session.active ?? '—'}</bdi></div></div>
              <div><div style={{ fontSize: '.75rem', color: 'var(--ih-text-muted)' }}>البريد</div>
                <div style={{ fontSize: '.84rem', marginTop: '.2rem' }}>{services.mail.driver}</div></div>
            </div>
          </Sec>

          <Sec title="بيانات العرض التجريبية" icon="bar-chart-3">
            <div className="ih-sec__body" style={{ display: 'grid', gap: '.7rem' }}>
              {dataset.exists ? (
                <>
                  <div style={{ display: 'flex', gap: '.5rem', flexWrap: 'wrap' }}>
                    {Object.entries(dataset.counts ?? {}).map(([k, v]) => (
                      <span key={k} className="ih-tag" style={{ fontSize: '.72rem' }}>{k}: <bdi>{v}</bdi></span>
                    ))}
                  </div>
                  <div style={{ fontSize: '.78rem', color: 'var(--ih-text-muted)' }}>
                    المستأجر: {dataset.tenant} — إعادة البناء تحذف بيانات العرض وتولّدها من جديد، ولا تمسّ أي مستأجر آخر.
                  </div>
                </>
              ) : (
                <div style={{ fontSize: '.85rem', color: 'var(--ih-text-muted)' }}>لا توجد بيانات عرض بعد.</div>
              )}
              <div>
                <button disabled={busy}
                  onClick={() => { setBusy(true); router.post('/product-lab/reseed', {}, { onFinish: () => setBusy(false) }); }}
                  className="btn btn-sm btn-outline">
                  {busy ? 'جارٍ إعادة البناء…' : 'إعادة بناء بيانات العرض'}
                </button>
              </div>
            </div>
          </Sec>

          <Sec title="آخر التحسينات" icon="file-text">
            <div className="ih-sec__body" style={{ display: 'grid', gap: '.35rem' }}>
              {changelog.length === 0 ? (
                <div style={{ fontSize: '.85rem', color: 'var(--ih-text-muted)' }}>لا سجل متاح.</div>
              ) : changelog.map((c) => (
                <div key={c.hash} style={{ display: 'flex', gap: '.6rem', fontSize: '.8rem', alignItems: 'baseline' }}>
                  <code style={{ direction: 'ltr', color: 'var(--ih-text-muted)', flexShrink: 0 }}>{c.date}</code>
                  <span style={{ flex: 1, minWidth: 0 }}>{c.subject}</span>
                </div>
              ))}
            </div>
          </Sec>
        </div>
      )}
    </AppShell>
  );
}

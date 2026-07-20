import { Head } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { Kpi, ListHead, StatusBadge } from '@/Components/ui';
import { Icon } from '@/Components/Icon';

interface Platform {
  key: string; name: string; nameEn: string; status: string; statusLabel: string; statusTone: string;
  statusNote: string; available: boolean; capabilities: string[];
}
interface MatrixRow { key: string; label: string; platforms: string[]; count: number }
interface Props { platforms: Platform[]; summary: { total: number; available: number; soon: number }; matrix: MatrixRow[] }

export default function IntegrationsIndex({ platforms, summary, matrix }: Props) {
  return (
    <AppShell heading="التكاملات">
      <Head title="التكاملات" />

      <ListHead eyebrow="الإدارة" title="التكاملات"
        sub="سجل قدرات المنصّات بحالات صادقة — لا تكامل وهمي؛ ما هو يدوي يُعرض يدويًا" />

      <div className="ih-kpis">
        <Kpi label="المنصّات" icon="plug" value={summary.total.toLocaleString('en-US')} sub="في السجل" />
        <Kpi label="متاحة" icon="shield-check" tone="success" value={summary.available.toLocaleString('en-US')} sub="قابلة للاستخدام الآن" />
        <Kpi label="قريبًا" icon="clipboard-check" value={summary.soon.toLocaleString('en-US')} sub="غير متاحة بعد" />
      </div>

      <div className="card" style={{ padding: '.8rem 1rem', marginBottom: '1.2rem', borderInlineStart: '3px solid var(--ih-info)', background: 'var(--ih-info-soft)', color: 'var(--ih-info-ink)', fontSize: '.84rem' }}>
        <Icon name="shield-check" size={15} /> التكاملات الحيّة (API) غير مفعّلة — البيانات تُدخَل يدويًا والتفاعل تقديري. عند توفّر بيانات الاعتماد تُرفَّع الحالة تلقائيًا (راجع docs/EXTERNAL-BLOCKERS.md).
      </div>

      {/* مصفوفة تغطية القدرات — ما تدعمه كل منصّة فعلًا */}
      <div className="ih-sec" style={{ marginBottom: '1.2rem' }}>
        <div className="ih-sec__head"><span className="ih-sec__title"><Icon name="bar-chart-3" size={16} /> تغطية القدرات</span>
          <span style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)' }}>عدد المنصّات الداعمة لكل قدرة</span>
        </div>
        <div className="ih-sec__body" style={{ display: 'grid', gap: '.6rem' }}>
          {matrix.map((m) => (
            <div key={m.key}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', fontSize: '.82rem', marginBottom: '.25rem', gap: '.5rem' }}>
                <span style={{ fontWeight: 600 }}>{m.label}</span>
                <span style={{ display: 'flex', gap: '.25rem', alignItems: 'center', flexWrap: 'wrap', justifyContent: 'flex-end' }}>
                  {m.platforms.map((k) => {
                    const p = platforms.find((x) => x.key === k);
                    return <span key={k} className="ih-tag" style={{ fontSize: '.62rem' }} title={p?.name}>{p?.nameEn ?? k}</span>;
                  })}
                  <span style={{ fontWeight: 700, direction: 'ltr', marginInlineStart: '.3rem' }}>{m.count}/{summary.total}</span>
                </span>
              </div>
              <div className="ih-bar"><span style={{ width: `${Math.round((m.count / Math.max(summary.total, 1)) * 100)}%` }} /></div>
            </div>
          ))}
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))', gap: '1rem' }}>
        {platforms.map((p) => (
          <div key={p.key} className="ih-sec" style={{ opacity: p.available ? 1 : 0.72 }}>
            <div className="ih-sec__head">
              <span className="ih-sec__title">
                <span className="ih-plat" style={{ width: 30, height: 30 }}>{p.nameEn.slice(0, 1)}</span>
                {p.name} <span style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{p.nameEn}</span>
              </span>
              <StatusBadge tone={p.statusTone} label={p.statusLabel} />
            </div>
            <div className="ih-sec__body">
              <div style={{ fontSize: '.8rem', color: 'var(--ih-text-muted)', marginBottom: '.7rem' }}>{p.statusNote}</div>
              {p.capabilities.length > 0 ? (
                <div style={{ display: 'flex', gap: '.35rem', flexWrap: 'wrap' }}>
                  {p.capabilities.map((c, i) => <span key={i} className="ih-tag" style={{ fontSize: '.68rem' }}>{c}</span>)}
                </div>
              ) : (
                <div style={{ fontSize: '.78rem', color: 'var(--ih-text-muted)' }}>لا قدرات مفعّلة بعد.</div>
              )}
            </div>
          </div>
        ))}
      </div>
    </AppShell>
  );
}

import { Head, router } from '@inertiajs/react';
import AppShell from '@/Layouts/AppShell';
import { adminNav } from '@/lib/nav';
import { ListHead, StatusBadge, Kpi, numFmt } from '@/Components/ui';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';

interface Row {
  id: number; org: string; plan: string; version: number; status: string; statusLabel: string; statusTone: string;
  provider: string | null; trialEndsAt: string | null; periodEnd: string | null;
}
interface Summary {
  total: number; active: number; trialing: number; attention: number;
  byStatus: { status: string; label: string; count: number }[];
}
interface Props { subs: Paginated<Row>; summary: Summary; filters: { status: string | null } }

export default function AdminSubscriptions({ subs, summary, filters }: Props) {
  const apply = (status?: string) =>
    router.get(u('/subscriptions'), status ? { status } : {}, { preserveState: true, replace: true, preserveScroll: true });

  return (
    <AppShell heading="الاشتراكات" nav={adminNav} portal="admin" wsName="إدارة المنصّة" wsPlan="مدير النظام" brand="InfluencerHub">
      <Head title="الاشتراكات" />
      <ListHead eyebrow="المنصّة · الفوترة" title="الاشتراكات" sub="اشتراكات المؤسسات وخططها ودورات فوترتها عبر المنصّة." />

      <div className="ih-kpis">
        <Kpi label="إجمالي الاشتراكات" icon="wallet" value={numFmt(summary.total)} sub="كل الحالات" />
        <Kpi label="نشطة" icon="shield-check" tone="success" value={numFmt(summary.active)} sub="مدفوعة وجارية" />
        <Kpi label="تجريبية" icon="clipboard-check" tone="accent" value={numFmt(summary.trialing)} sub="فترة تجربة" />
        <Kpi label="تحتاج انتباهًا" icon="alert-triangle" tone={summary.attention ? 'danger' : 'success'} value={numFmt(summary.attention)} sub="متأخّرة/منتهية" />
      </div>

      {/* شرائح الحالة */}
      <div className="ih-chips" style={{ marginBottom: '.9rem', overflowX: 'auto', paddingBottom: '.2rem', flexWrap: 'nowrap' }}>
        <button onClick={() => apply()} className={`ih-chip${!filters.status ? ' active' : ''}`}>الكل <span className="ih-chip__count">{summary.total}</span></button>
        {summary.byStatus.map((s) => (
          <button key={s.status} onClick={() => apply(filters.status === s.status ? undefined : s.status)}
            className={`ih-chip${filters.status === s.status ? ' active' : ''}`}>{s.label} <span className="ih-chip__count">{s.count}</span></button>
        ))}
      </div>

      {subs.data.length === 0 ? (
        <div className="ih-empty">
          <span className="ih-empty__icon"><Icon name="wallet" size={26} /></span>
          <div className="ih-empty__title">لا اشتراكات</div>
          <div className="ih-empty__text">لا نتائج بهذه الحالة.</div>
        </div>
      ) : (
        <>
          <div className="ih-only-desktop">
            <div className="ih-dt-wrap"><div className="ih-dt-scroll">
              <table className="ih-dt">
                <thead><tr><th>المؤسسة</th><th>الخطة</th><th>المزوّد</th><th>انتهاء التجربة</th><th>نهاية الدورة</th><th>الحالة</th></tr></thead>
                <tbody>
                  {subs.data.map((s) => (
                    <tr key={s.id}>
                      <td>
                        <div className="ih-idc">
                          <span className="ih-idc__av">{s.org.slice(0, 1)}</span>
                          <span className="ih-idc__main"><span className="ih-idc__name">{s.org}</span></span>
                        </div>
                      </td>
                      <td>{s.plan} <span style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>v{s.version}</span></td>
                      <td><span className="ih-tag">{s.provider ?? '—'}</span></td>
                      <td style={{ direction: 'ltr', textAlign: 'right', fontSize: '.8rem', color: 'var(--ih-text-muted)' }}>{s.trialEndsAt ?? '—'}</td>
                      <td style={{ direction: 'ltr', textAlign: 'right', fontSize: '.8rem', color: 'var(--ih-text-muted)' }}>{s.periodEnd ?? '—'}</td>
                      <td><StatusBadge tone={s.statusTone} label={s.statusLabel} /></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
              <div className="ih-dt__foot"><span>{subs.total.toLocaleString('en-US')} اشتراك</span><Pagination links={subs.links} /></div>
            </div>
          </div>

          <div className="ih-only-mobile">
            <div className="ih-mlist">
              {subs.data.map((s) => (
                <div key={s.id} className="ih-mcard">
                  <div className="ih-mcard__top">
                    <span className="ih-idc__av" style={{ width: 42, height: 42 }}>{s.org.slice(0, 1)}</span>
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div className="ih-idc__name">{s.org} <StatusBadge tone={s.statusTone} label={s.statusLabel} /></div>
                      <div className="ih-idc__sub">{s.plan} · <span style={{ direction: 'ltr' }}>v{s.version}</span></div>
                    </div>
                  </div>
                  <div className="ih-mcard__grid">
                    <div><span className="ih-mcard__lbl">المزوّد</span><span className="ih-mcard__val">{s.provider ?? '—'}</span></div>
                    <div><span className="ih-mcard__lbl">التجربة</span><span className="ih-mcard__val" style={{ direction: 'ltr' }}>{s.trialEndsAt ?? '—'}</span></div>
                    <div><span className="ih-mcard__lbl">نهاية الدورة</span><span className="ih-mcard__val" style={{ direction: 'ltr' }}>{s.periodEnd ?? '—'}</span></div>
                  </div>
                </div>
              ))}
            </div>
            <Pagination links={subs.links} />
          </div>
        </>
      )}
    </AppShell>
  );
}

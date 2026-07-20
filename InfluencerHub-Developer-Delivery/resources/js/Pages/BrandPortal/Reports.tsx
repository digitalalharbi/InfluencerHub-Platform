import AppShell from '@/Layouts/AppShell'
import { brandNav } from '@/lib/nav'

const money = (m: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(m / 100) + ' ر.س'

/**
 * التقارير.
 *
 * الأرقام تصل محسوبةً من `FinancialMetrics` — مصدر الحساب الوحيد. لا معادلة
 * هنا: حسابٌ ثانٍ في الواجهة يُنتج رقمًا يخالف بقيّة النظام بلا أن يلاحظ أحد.
 */
export default function Reports({
  brand, finance, campaigns,
}: {
  brand: { name: string }
  finance: { revenueMinor: number; costMinor: number; profitMinor: number; margin: number }
  campaigns: { id: number; name: string; budgetMinor: number }[]
}) {
  return (
    <AppShell heading="التقارير" nav={brandNav} portal="brand" wsName={brand.name} wsPlan="علامة تجارية">
      <section className="ih-sec">
        <h2>الأداء المالي</h2>
        <div className="ih-kpis">
          <div className="ih-kpi"><span className="label">الإيراد المعترَف به</span><strong>{money(finance.revenueMinor)}</strong></div>
          <div className="ih-kpi"><span className="label">التكلفة الملتزَم بها</span><strong>{money(finance.costMinor)}</strong></div>
          <div className="ih-kpi"><span className="label">الربح</span><strong>{money(finance.profitMinor)}</strong></div>
          <div className="ih-kpi"><span className="label">الهامش</span><strong>{finance.margin}%</strong></div>
        </div>
        <p className="ih-mline">
          الإيراد من الفواتير الصادرة بلا ضريبة، والتكلفة من المستحقّات الملتزَم بها.
          الربح = الإيراد − التكلفة، والهامش = الربح ÷ الإيراد × 100.
        </p>
      </section>

      <section className="ih-sec">
        <h2>الحملات المكتملة</h2>
        {campaigns.length === 0 ? (
          <div className="ih-empty">
            <strong>لا حملة مكتملة بعد</strong>
            <p>يظهر هنا أداء كل حملة بعد إغلاقها.</p>
          </div>
        ) : (
          <div className="ih-table-wrap">
            <table className="table">
              <thead><tr><th scope="col">الحملة</th><th scope="col">الميزانية</th></tr></thead>
              <tbody>
                {campaigns.map((c) => (
                  <tr key={c.id}><td>{c.name}</td><td>{money(c.budgetMinor)}</td></tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </AppShell>
  )
}

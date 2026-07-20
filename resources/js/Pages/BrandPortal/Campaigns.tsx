import AppShell from '@/Layouts/AppShell'
import { brandNav } from '@/lib/nav'

type Item = { id: number; number: string; name: string; status: string; statusLabel: string; statusTone: string; budgetMinor: number }

const money = (m: number) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(m / 100) + ' ر.س'

export default function Campaigns({ brand, items }: { brand: { name: string }; items: Item[] }) {
  return (
    <AppShell heading="الحملات" nav={brandNav} portal="brand" wsName={brand.name} wsPlan="علامة تجارية">
      <section className="ih-sec">
        {items.length === 0 ? (
          <div className="ih-empty">
            <strong>لا حملات بعد</strong>
            <p>الحملة تُشتقّ من طلب — ابدأ بطلب، ثم حوّله حملةً بضغطة.</p>
          </div>
        ) : (
          <div className="ih-table-wrap">
            <table className="table">
              <thead>
                <tr><th scope="col">الرقم</th><th scope="col">الاسم</th><th scope="col">الميزانية</th><th scope="col">الحالة</th></tr>
              </thead>
              <tbody>
                {items.map((x) => (
                  <tr key={x.id}>
                    <td style={{ direction: 'ltr' }}>{x.number}</td>
                    <td>{x.name}</td>
                    <td>{money(x.budgetMinor)}</td>
                    <td><span className={`badge ih-status-${x.statusTone}`}>{x.statusLabel}</span></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </AppShell>
  )
}

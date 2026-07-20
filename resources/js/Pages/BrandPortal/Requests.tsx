import AppShell from '@/Layouts/AppShell'
import { brandNav } from '@/lib/nav'

type Item = { id: number; number: string; title: string; status: string; statusLabel: string; statusTone: string; dueAt: string | null }

export default function Requests({ brand, items }: { brand: { name: string }; items: Item[] }) {
  return (
    <AppShell heading="الطلبات" nav={brandNav} portal="brand" wsName={brand.name} wsPlan="علامة تجارية">
      <section className="ih-sec">
        {items.length === 0 ? (
          <div className="ih-empty">
            <strong>لا طلبات بعد</strong>
            <p>الطلب هو مدخل الحملة: تصفه مرّة واحدة، ثم تُشتقّ منه الحملة بلا إعادة كتابة.</p>
          </div>
        ) : (
          <div className="ih-table-wrap">
            <table className="table">
              <thead>
                <tr><th scope="col">الرقم</th><th scope="col">العنوان</th><th scope="col">الاستحقاق</th><th scope="col">الحالة</th></tr>
              </thead>
              <tbody>
                {items.map((x) => (
                  <tr key={x.id}>
                    <td style={{ direction: 'ltr' }}>{x.number}</td>
                    <td>{x.title}</td>
                    <td>{x.dueAt ?? '—'}</td>
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

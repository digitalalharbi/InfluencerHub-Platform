import AppShell from '@/Layouts/AppShell'
import { brandNav } from '@/lib/nav'

type Item = {
  id: number
  title: string
  meta: string
  status: string
  statusLabel: string
  statusTone: string
}

/**
 * قائمة قسم.
 *
 * الفراغ يقول **لماذا** هو فارغ وما الذي يملؤه — جدولٌ بلا صفوف يترك القارئ
 * يظنّ أن شيئًا تعطّل، وهو الفرق بين واجهة تشرح وأخرى تصمت.
 */
export default function Listing({
  brand,
  title,
  items,
  emptyTitle,
  emptyHint,
}: {
  brand: { name: string }
  title: string
  items: Item[]
  emptyTitle: string
  emptyHint: string
}) {
  return (
    <AppShell heading={title} nav={brandNav} portal="brand" wsName={brand.name} wsPlan="علامة تجارية">
      <section className="ih-sec">
        {items.length === 0 ? (
          <div className="ih-empty">
            <strong>{emptyTitle}</strong>
            <p>{emptyHint}</p>
          </div>
        ) : (
          <div className="ih-table-wrap">
            <table className="table">
              <thead>
                <tr>
                  <th scope="col">العنوان</th>
                  <th scope="col">التفصيل</th>
                  <th scope="col">الحالة</th>
                </tr>
              </thead>
              <tbody>
                {items.map((item) => (
                  <tr key={item.id}>
                    <td>{item.title}</td>
                    <td>{item.meta}</td>
                    <td>
                      <span className={`badge ih-status-${item.statusTone}`}>{item.statusLabel}</span>
                    </td>
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

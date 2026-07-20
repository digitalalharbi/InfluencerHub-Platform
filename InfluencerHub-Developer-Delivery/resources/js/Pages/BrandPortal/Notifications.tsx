import AppShell from '@/Layouts/AppShell'
import { brandNav } from '@/lib/nav'

type Item = { id: number; title: string; body: string | null; actionUrl: string | null; read: boolean; at: string | null }

export default function Notifications({ brand, items }: { brand: { name: string }; items: Item[] }) {
  return (
    <AppShell heading="الإشعارات" nav={brandNav} portal="brand" wsName={brand.name} wsPlan="علامة تجارية">
      <section className="ih-sec">
        {items.length === 0 ? (
          <div className="ih-empty">
            <strong>لا إشعارات</strong>
            <p>تصلك هنا تحديثات حملاتك وعقودك ومدفوعاتك.</p>
          </div>
        ) : (
          <ul className="ih-mlist">
            {items.map((n) => (
              <li key={n.id} className="ih-mcard">
                <strong>{n.title}</strong>
                {n.body && <p className="ih-mline">{n.body}</p>}
                <p className="ih-delta">{n.at ?? ''}</p>
              </li>
            ))}
          </ul>
        )}
      </section>
    </AppShell>
  )
}

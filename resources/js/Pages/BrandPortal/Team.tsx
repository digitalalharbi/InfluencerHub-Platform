import AppShell from '@/Layouts/AppShell'
import { brandNav } from '@/lib/nav'

type Member = { id: number; name: string; email: string; role: string; roleLabel: string; status: string }

export default function Team({ brand, members, canManage }: { brand: { name: string }; members: Member[]; canManage: boolean }) {
  return (
    <AppShell heading="الفريق" nav={brandNav} portal="brand" wsName={brand.name} wsPlan="علامة تجارية">
      <section className="ih-sec">
        <div className="ih-listhead">
          <h2>أعضاء المساحة</h2>
          {canManage && <span className="ih-chip">تُدار الدعوات من هنا</span>}
        </div>

        {members.length <= 1 && (
          <div className="ih-empty">
            <strong>أنت وحدك في المساحة</strong>
            <p>مساحة بعضو واحد تتعطّل بغيابه — ادعُ زميلًا يشاركك مراجعة المحتوى والعقود.</p>
          </div>
        )}

        <div className="ih-table-wrap">
          <table className="table">
            <thead>
              <tr><th scope="col">الاسم</th><th scope="col">البريد</th><th scope="col">الدور</th><th scope="col">الحالة</th></tr>
            </thead>
            <tbody>
              {members.map((m) => (
                <tr key={m.id}>
                  <td>{m.name}</td>
                  <td style={{ direction: 'ltr' }}>{m.email}</td>
                  <td>{m.roleLabel}</td>
                  <td><span className="badge">{m.status}</span></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </AppShell>
  )
}

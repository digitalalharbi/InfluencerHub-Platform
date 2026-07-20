import AppShell from '@/Layouts/AppShell'
import { brandNav } from '@/lib/nav'

export default function Settings({
  brand, socialAccounts,
}: {
  brand: { name: string; sector: string | null; website: string | null; description: string | null; status: string }
  socialAccounts: { id: number; platform: string; handle: string }[]
  canManage: boolean
}) {
  const missing = [
    !brand.sector && 'القطاع',
    !brand.website && 'الموقع',
    !brand.description && 'الوصف',
  ].filter(Boolean) as string[]

  return (
    <AppShell heading="الإعدادات" nav={brandNav} portal="brand" wsName={brand.name} wsPlan="علامة تجارية">
      <section className="ih-sec">
        <h2>هوية العلامة</h2>

        {missing.length > 0 && (
          <div className="ih-empty">
            <strong>بيانات ناقصة: {missing.join('، ')}</strong>
            <p>هذه ما يقرؤه المبدع قبل قبول التعاون — نقصُها يُبطئ الردود.</p>
          </div>
        )}

        <dl className="ih-summary">
          <dt>الاسم</dt><dd>{brand.name}</dd>
          <dt>القطاع</dt><dd>{brand.sector ?? '—'}</dd>
          <dt>الموقع</dt><dd style={{ direction: 'ltr' }}>{brand.website ?? '—'}</dd>
          <dt>الوصف</dt><dd>{brand.description ?? '—'}</dd>
        </dl>
      </section>

      <section className="ih-sec">
        <h2>حسابات التواصل</h2>
        {socialAccounts.length === 0 ? (
          <div className="ih-empty">
            <strong>لا حسابات مرتبطة</strong>
            <p>حسابات علامتك تُعرض للمبدعين وتساعد في إثبات الهوية.</p>
          </div>
        ) : (
          <ul className="ih-chips">
            {socialAccounts.map((s) => (
              <li key={s.id} className="ih-chip">{s.platform} · <span style={{ direction: 'ltr' }}>@{s.handle}</span></li>
            ))}
          </ul>
        )}
      </section>
    </AppShell>
  )
}

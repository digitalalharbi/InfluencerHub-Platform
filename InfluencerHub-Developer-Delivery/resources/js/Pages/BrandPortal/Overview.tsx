import { Link } from '@inertiajs/react'
import AppShell from '@/Layouts/AppShell'
import { brandNav } from '@/lib/nav'
import { u } from '@/lib/href'

type Step = {
  key: string
  title: string
  hint: string
  done: boolean
  progress: string
  href: string | null
  action: string | null
  optional: boolean
  blocked?: boolean
  blockedReason?: string
}

type Props = {
  brand: { id: number; name: string; sector: string | null; status: string }
  counts: Record<string, number>
  finance: { revenueMinor: number; costMinor: number; profitMinor: number; margin: number }
  onboarding: {
    steps: Step[]
    doneCount: number
    total: number
    requiredDone: number
    requiredTotal: number
    isSettingUp: boolean
  }
  nextAction: { label: string; href: string; why: string }
}

const money = (minor: number) =>
  new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(minor / 100) + ' ر.س'

/**
 * نظرة عامة على مساحة العلامة.
 *
 * قائمة التهيئة تُخفى تلقائيًّا متى اكتملت خطواتها الإلزامية — التهيئة مرحلة
 * لا واجهة دائمة. وكل خطوة تُقاس بعدّ سجلّات، فتعود «غير مكتملة» متى زال ما
 * أكملها؛ لا مربّع اختيار يُعلَّم ثم يكذب.
 */
export default function Overview({ brand, counts, finance, onboarding, nextAction }: Props) {
  return (
    <AppShell heading="نظرة عامة" nav={brandNav} portal="brand" wsName={brand.name} wsPlan="علامة تجارية">
      {onboarding.isSettingUp && (
        <section className="ih-sec">
          <div className="ih-listhead">
            <h2>لنُجهّز مساحتك</h2>
            <span className="ih-tag">
              {onboarding.requiredDone} من {onboarding.requiredTotal} خطوة أساسية
            </span>
          </div>

          {/* قائمة رأسية لا شريط أفقي: هذه خطوات تُقرأ وتُنفَّذ، لا مؤشّر تقدّم */}
          <ol className="ih-mlist">
            {onboarding.steps.map((step) => (
              <li key={step.key} className="ih-mcard">
                <div className="ih-mcard__top" style={{ justifyContent: 'space-between' }}>
                  <strong>
                    {step.done ? '✓ ' : ''}
                    {step.title}
                    {step.optional && <span className="ih-chip"> اختياري</span>}
                  </strong>

                  {step.blocked ? (
                    <span className="ih-chip" title={step.blockedReason}>
                      {step.blockedReason}
                    </span>
                  ) : step.done ? (
                    <span className="ih-chip">{step.progress}</span>
                  ) : (
                    step.href && (
                      <Link
                        href={u(step.href.replace('/brand', ''))}
                        className="btn btn-secondary btn-sm"
                      >
                        {step.action}
                      </Link>
                    )
                  )}
                </div>

                <p className="ih-mline">{step.hint}</p>
                {! step.done && ! step.blocked && <p className="ih-delta">{step.progress}</p>}
              </li>
            ))}
          </ol>
        </section>
      )}

      <section className="ih-sec">
        <div className="ih-nba">
          <div>
            <strong>الخطوة التالية: {nextAction.label}</strong>
            <p className="ih-mline">{nextAction.why}</p>
          </div>
          <Link href={u(nextAction.href.replace('/brand', ''))} className="btn btn-primary">
            {nextAction.label}
          </Link>
        </div>
      </section>

      <section className="ih-sec">
        <h2>مساحتك بالأرقام</h2>
        <div className="ih-kpis">
          <Kpi label="الطلبات" value={counts.requests} href="/requests" />
          <Kpi label="الحملات" value={counts.campaigns} href="/campaigns" />
          <Kpi label="الترشيحات" value={counts.shortlists} href="/shortlists" />
          <Kpi label="المحتوى" value={counts.content} href="/content" />
          <Kpi label="العقود" value={counts.contracts} href="/contracts" />
          <Kpi label="الفواتير" value={counts.invoices} href="/invoices" />
          <Kpi label="الفريق" value={counts.team} href="/team" />
          <Kpi label="الوكالات المفوَّضة" value={counts.agencies} href="/agencies" />
        </div>
      </section>

      <section className="ih-sec">
        <div className="ih-listhead">
          <h2>الملخّص المالي</h2>
          <Link href={u('/reports')} className="btn btn-ghost btn-sm">
            التقارير
          </Link>
        </div>

        <div className="ih-kpis">
          <div className="ih-kpi">
            <span className="label">الإيراد المعترَف به</span>
            <strong>{money(finance.revenueMinor)}</strong>
          </div>
          <div className="ih-kpi">
            <span className="label">التكلفة الملتزَم بها</span>
            <strong>{money(finance.costMinor)}</strong>
          </div>
          <div className="ih-kpi">
            <span className="label">الربح</span>
            <strong>{money(finance.profitMinor)}</strong>
          </div>
          <div className="ih-kpi">
            <span className="label">الهامش</span>
            <strong>{finance.margin}%</strong>
          </div>
        </div>

        <p className="ih-mline">
          الإيراد من الفواتير الصادرة بلا ضريبة، والتكلفة من المستحقّات الملتزَم بها.
          الربح = الإيراد − التكلفة، والهامش = الربح ÷ الإيراد.
        </p>
      </section>
    </AppShell>
  )
}

function Kpi({ label, value, href }: { label: string; value: number; href: string }) {
  return (
    <Link href={u(href)} className="ih-kpi ih-row-link">
      <span className="label">{label}</span>
      <strong>{value}</strong>
    </Link>
  )
}

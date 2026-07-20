/**
 * شريط خطوات رحلة العلامة.
 *
 * يعيد استعمال أصناف `pub-stepper` القائمة — لا طبقة تنسيق موازية لشيء موجود.
 *
 * والخطوات **ثابتة** لا تتغيّر بنتيجة المطابقة: لو ظهرت خطوة «إثبات ملكية»
 * فجأةً لمن طابق سجلًّا قائمًا، لكان الشريط نفسه يكشف أن علامته موجودة عندنا.
 */
const STEPS = [
  { key: 'email', label: 'البريد' },
  { key: 'phone', label: 'الجوال' },
  { key: 'details', label: 'البيانات' },
  { key: 'owner', label: 'الحساب' },
] as const

export type StepKey = (typeof STEPS)[number]['key']

export default function Steps({ current }: { current: StepKey }) {
  const currentIndex = STEPS.findIndex((s) => s.key === current)

  return (
    <ol className="pub-stepper" aria-label="خطوات التسجيل">
      {STEPS.map((step, i) => {
        const done = i < currentIndex
        const isCurrent = i === currentIndex

        return (
          <li
            key={step.key}
            className={`pub-stepper-item${done ? ' is-done' : ''}${isCurrent ? ' is-current' : ''}`}
            aria-current={isCurrent ? 'step' : undefined}
          >
            <span className="pub-stepper-dot">{done ? '✓' : i + 1}</span>
            <span className="pub-stepper-label">{step.label}</span>
          </li>
        )
      })}
    </ol>
  )
}

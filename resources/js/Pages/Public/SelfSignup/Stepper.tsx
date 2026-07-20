/**
 * شريط خطوات التسجيل — يقول للمستخدم أين هو وكم بقي.
 * بلا هذا يصبح المسار متعدّد الصفحات نفقًا مظلمًا.
 */
export default function Stepper({
  steps,
  status,
  completedSteps,
}: {
  steps: Record<string, string>
  status: string
  completedSteps: string[]
}) {
  const keys = Object.keys(steps)
  const currentIndex = keys.indexOf(status)

  return (
    <ol className="pub-stepper" aria-label="خطوات التسجيل">
      {keys.map((key, i) => {
        const done = completedSteps.includes(key) || i < currentIndex
        const current = key === status
        return (
          <li
            key={key}
            className={`pub-stepper-item${done ? ' is-done' : ''}${current ? ' is-current' : ''}`}
            aria-current={current ? 'step' : undefined}
          >
            <span className="pub-stepper-dot">{done ? '✓' : i + 1}</span>
            <span className="pub-stepper-label">{steps[key]}</span>
          </li>
        )
      })}
    </ol>
  )
}

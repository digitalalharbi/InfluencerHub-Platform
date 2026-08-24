import { usePage } from '@inertiajs/react'
import type { SharedProps } from '@/types'

/**
 * سطر تواصل عام موحّد — بريد وهاتف حقيقيان من الهوية المشتركة (Brand)، لا قيم
 * مكتوبة يدويًّا. يُستعمل في الصفحات العامّة (عن/الخصوصية/الشروط/المساعدة).
 */
export default function PublicContact({ inline = false }: { inline?: boolean }) {
  const { brand } = usePage<SharedProps>().props
  const email = <a href={`mailto:${brand.publicEmail}`} dir="ltr">{brand.publicEmail}</a>
  const phone = <a href={`tel:${brand.publicPhone}`} dir="ltr">{brand.publicPhoneDisplay}</a>

  if (inline) {
    return <>البريد {email} · الهاتف {phone}</>
  }
  return (
    <ul className="pub-contact-list">
      <li>البريد الإلكتروني: {email}</li>
      <li>الهاتف: {phone}</li>
    </ul>
  )
}

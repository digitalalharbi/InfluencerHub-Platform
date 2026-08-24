import { Link } from '@inertiajs/react'
import PublicLayout from '@/Layouts/PublicLayout'

interface Brand { name: string; tagline: string; url: string; domain: string }

/** القدرات الفعلية — لا نُعلن تكاملًا حيًّا غير مُفعَّل. */
const CAPS: { title: string; body: string }[] = [
  { title: 'إدارة الحملات', body: 'دورة حياة الحملة من الطلب إلى الإغلاق: المخرجات والميزانية والالتزامات والجاهزية والمخطط الزمني في مركز قيادة واحد.' },
  { title: 'صنّاع المحتوى', body: 'قاعدة صنّاع المحتوى وحساباتهم ومنصّاتهم وأداؤهم، مع ترشيحهم للحملات وإدارة تعاوناتهم.' },
  { title: 'العملاء والعلامات', body: 'ملفّات العملاء وعلاماتهم التجارية، ومراجعتها واعتمادها، وربطها بالحملات والفواتير.' },
  { title: 'الترشيحات', body: 'بناء قوائم الترشيح داخليًّا ثم مشاركة مقترح آمن للعميل (بلا تكلفة المبدع أو الملاحظات الداخلية) لاتخاذ القرار.' },
  { title: 'المحتوى والمراجعات', body: 'استلام المحتوى ومراجعته واعتماده وطلب التعديلات وتتبّع النشر والإثبات.' },
  { title: 'العقود', body: 'إنشاء العقود وإرسالها وتتبّع حالتها ومعاينتها وتنزيلها كـPDF — مع بقاء أطراف العقد أطرافًا حقيقية.' },
  { title: 'الفواتير والمستحقات', body: 'مطالبة العملاء بالفواتير وتسجيل التحصيل، وإدارة مستحقات صنّاع المحتوى واعتمادها وصرفها بضوابط صلاحيات.' },
  { title: 'التقارير', body: 'نظرة تجميعية على الأداء المالي والتشغيلي، وتصدير Excel/CSV ومعاينة PDF لنفس البيانات.' },
  { title: 'الأتمتة والتنبيهات', body: 'قواعد أتمتة على أحداث حقيقية في سير العمل، ومركز إشعارات بتفضيلات لكل قناة.' },
  { title: 'التكاملات', body: 'حالات صادقة لكل منصّة: ما هو يدويّ يُعرَض يدويًّا، ولا يُعلَن تكامل حيّ (API) إلا عند تفعيله فعلًا.' },
  { title: 'الأمن والخصوصية', body: 'عزل كامل بين المستأجرين، وصلاحيات دقيقة لكل إجراء، ومستندات خاصّة تُنزَّل بترخيص لا بروابط عامّة.' },
]

export default function Info({ brand }: { brand: Brand }) {
  return (
    <PublicLayout
      title="عن InfluencerHub"
      description="InfluencerHub — منصة لإدارة وتشغيل حملات المؤثرين وصنّاع المحتوى: العملاء والحملات والترشيحات والتعاونات والمحتوى والعقود والمستحقات والتقارير."
    >
      <section className="pub-hero">
        <div className="pub-wrap">
          <div style={{ direction: 'ltr', fontWeight: 800, fontSize: '1.1rem', color: 'var(--ih-primary, #6252e5)' }}>{brand.name}</div>
          <h1 className="pub-hero-title">عن {brand.name}</h1>
          <p className="pub-hero-lede">
            {brand.name} منصة لإدارة وتشغيل حملات المؤثرين وصنّاع المحتوى، بما يشمل إدارة العملاء
            والحملات والترشيحات والتعاونات والمحتوى والعقود والمستحقات والتقارير — في مكان واحد.
          </p>
          <p style={{ direction: 'ltr' }}>
            <a href={`${brand.url}/`} style={{ fontWeight: 800 }}>{brand.domain}</a>
          </p>
          <div style={{ marginTop: '1rem', display: 'flex', gap: '.6rem', flexWrap: 'wrap' }}>
            <a href="/login" className="btn btn-primary">تسجيل الدخول</a>
            <Link href="/register" className="btn btn-outline">إنشاء حساب</Link>
          </div>
        </div>
      </section>

      <section className="pub-wrap pub-section">
        <h2 style={{ marginBottom: '1rem' }}>أهم قدرات النظام</h2>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '1rem' }}>
          {CAPS.map((c) => (
            <div key={c.title} className="card" style={{ padding: '1.1rem 1.25rem' }}>
              <h3 style={{ margin: '0 0 .5rem', fontSize: '1.02rem' }}>{c.title}</h3>
              <p className="pub-muted" style={{ margin: 0, lineHeight: 1.8 }}>{c.body}</p>
            </div>
          ))}
        </div>
      </section>

      <section className="pub-wrap pub-section" style={{ textAlign: 'center' }}>
        <p className="pub-muted">
          للاطّلاع على المنصّة أو طلب عرض توضيحي، زُر{' '}
          <a href={`${brand.url}/`} style={{ direction: 'ltr', fontWeight: 700 }}>{brand.domain}</a>.
        </p>
      </section>
    </PublicLayout>
  )
}

import { Head } from '@inertiajs/react';

/** رابط دعوة لا يصلح — السبب يُقال بدل «رابط غير صالح» المبهمة. */
export default function InvitationInvalid({ reason }: { reason: string }) {
  return (
    <div className="pub" style={{ minHeight: '100vh', display: 'grid', placeItems: 'center', padding: '1.5rem' }}>
      <Head title="دعوة غير صالحة — إنفلونسر هَب" />
      <div className="card" style={{ maxWidth: 460, padding: '2rem', textAlign: 'center' }}>
        <h1 style={{ fontSize: 'var(--ih-fs-section)', fontWeight: 700, margin: '0 0 .6rem' }}>الدعوة غير صالحة</h1>
        <p style={{ margin: '0 0 1.4rem', lineHeight: 1.9, color: 'var(--ih-text-secondary)' }}>{reason}</p>
        <a href="/creator/login" className="btn btn-primary">الذهاب لتسجيل الدخول</a>
      </div>
    </div>
  );
}

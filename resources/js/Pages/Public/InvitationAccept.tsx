import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { SharedProps } from '@/types';

interface Props {
  token: string;
  creatorName: string | null;
  email: string;
  phone: string | null;
  emailVerified: boolean;
  phoneVerified: boolean;
  needsPhone: boolean;
}

/** خطوة واحدة ظاهرة في كل مرّة — لا نموذج طويل أمام من يُنشئ حسابه أوّل مرّة. */
function Step({ n, title, done, active, children }: {
  n: number; title: string; done: boolean; active: boolean; children?: React.ReactNode;
}) {
  return (
    <div style={{ display: 'flex', gap: '.9rem', opacity: active || done ? 1 : .5 }}>
      <span aria-hidden style={{
        flexShrink: 0, width: 30, height: 30, borderRadius: '50%',
        display: 'grid', placeItems: 'center', fontWeight: 700, fontSize: '.82rem',
        background: done ? 'var(--ih-success-soft)' : active ? 'var(--ih-primary-soft)' : 'var(--ih-gray-100)',
        color: done ? 'var(--ih-success-ink)' : active ? 'var(--ih-primary)' : 'var(--ih-text-muted)',
      }}>{done ? '✓' : n}</span>
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ fontWeight: 600, fontSize: '.9rem', marginBottom: active ? '.6rem' : 0 }}>{title}</div>
        {active && children}
      </div>
    </div>
  );
}

export default function InvitationAccept({ token, creatorName, email, phone, emailVerified, phoneVerified, needsPhone }: Props) {
  const { props } = usePage<SharedProps>();
  const [code, setCode] = useState('');
  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [busy, setBusy] = useState(false);

  const phoneDone = !needsPhone || phoneVerified;
  const stage = !emailVerified ? 'email' : !phoneDone ? 'phone' : 'password';

  const verify = (channel: 'email' | 'phone') => {
    setBusy(true);
    router.post(`/creator/invitation/${token}/verify-${channel}`, { code },
      { preserveScroll: true, onFinish: () => { setBusy(false); setCode(''); } });
  };
  const submit = () => {
    setBusy(true);
    router.post(`/creator/invitation/${token}/accept`,
      { password, password_confirmation: confirm },
      { preserveScroll: true, onFinish: () => setBusy(false) });
  };

  const err = props.errors ?? {};

  return (
    <div className="pub" style={{ minHeight: '100vh', display: 'grid', placeItems: 'center', padding: '1.5rem' }}>
      <Head title="تفعيل بوابتك — إنفلونسر هَب" />
      <div className="card" style={{ maxWidth: 480, width: '100%', padding: '1.8rem' }}>
        <h1 style={{ fontSize: 'var(--ih-fs-section)', fontWeight: 700, margin: '0 0 .3rem' }}>
          أهلًا {creatorName ?? ''}
        </h1>
        <p style={{ margin: '0 0 1.6rem', lineHeight: 1.8, color: 'var(--ih-text-secondary)', fontSize: '.88rem' }}>
          دُعيت لتفعيل بوابتك: تتابع تعاوناتك وتوقّع عقودك وترفع محتواك وتتابع مستحقاتك.
        </p>

        {props.flash?.ok && (
          <div style={{ padding: '.7rem 1rem', marginBottom: '1rem', borderRadius: 'var(--ih-radius-sm)',
            background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)', fontSize: '.85rem' }}>{props.flash.ok}</div>
        )}

        <div style={{ display: 'grid', gap: '1.3rem' }}>
          <Step n={1} title={`تحقّق البريد — ${email}`} done={emailVerified} active={stage === 'email'}>
            <div style={{ display: 'flex', gap: '.5rem' }}>
              <label htmlFor="code-email" className="sr-only">رمز البريد</label>
              <input id="code-email" className="field" style={{ flex: 1, direction: 'ltr' }} inputMode="numeric"
                value={code} onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))} placeholder="رمز من 6 خانات" />
              <button className="btn btn-primary" disabled={busy || code.length < 4} onClick={() => verify('email')}>تحقّق</button>
            </div>
            {err.code && <div style={{ color: 'var(--ih-danger)', fontSize: '.8rem', marginTop: '.4rem' }}>{err.code}</div>}
          </Step>

          {needsPhone && (
            <Step n={2} title={`تحقّق الجوال — ${phone ?? ''}`} done={phoneVerified} active={stage === 'phone'}>
              <div style={{ display: 'flex', gap: '.5rem' }}>
                <label htmlFor="code-phone" className="sr-only">رمز الجوال</label>
                <input id="code-phone" className="field" style={{ flex: 1, direction: 'ltr' }} inputMode="numeric"
                  value={code} onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))} placeholder="رمز من 6 خانات" />
                <button className="btn btn-primary" disabled={busy || code.length < 4} onClick={() => verify('phone')}>تحقّق</button>
              </div>
              {err.code && <div style={{ color: 'var(--ih-danger)', fontSize: '.8rem', marginTop: '.4rem' }}>{err.code}</div>}
            </Step>
          )}

          <Step n={needsPhone ? 3 : 2} title="كلمة المرور" done={false} active={stage === 'password'}>
            <div style={{ display: 'grid', gap: '.6rem' }}>
              <div>
                <label htmlFor="pw" style={{ display: 'block', fontSize: '.8rem', fontWeight: 500, marginBottom: '.25rem' }}>كلمة المرور</label>
                <input id="pw" type="password" className="field" style={{ width: '100%' }} value={password}
                  onChange={(e) => setPassword(e.target.value)} autoComplete="new-password" />
              </div>
              <div>
                <label htmlFor="pw2" style={{ display: 'block', fontSize: '.8rem', fontWeight: 500, marginBottom: '.25rem' }}>تأكيد كلمة المرور</label>
                <input id="pw2" type="password" className="field" style={{ width: '100%' }} value={confirm}
                  onChange={(e) => setConfirm(e.target.value)} autoComplete="new-password" />
              </div>
              {err.password && <div style={{ color: 'var(--ih-danger)', fontSize: '.8rem' }}>{err.password}</div>}
              <button className="btn btn-primary" disabled={busy || password.length < 8 || password !== confirm} onClick={submit}>
                فعّل بوابتي
              </button>
            </div>
          </Step>
        </div>
      </div>
    </div>
  );
}

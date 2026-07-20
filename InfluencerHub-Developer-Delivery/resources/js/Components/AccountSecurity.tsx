import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Sec } from '@/Components/ui';
import { u } from '@/lib/href';

export interface Pref { in_app: boolean; email: boolean; sms: boolean }
export interface Session { current: boolean; ip: string | null; agent: string | null; lastActivity: string | null }
export interface SecurityProps {
  prefs: Record<string, Pref>;
  categories: Record<string, string>;
  sessions: Session[];
  twoFactorEnabled: boolean;
  /** جذر مسارات الأمان داخل البوابة، مثل `/account/settings` أو `/account`. */
  base: string;
}

const LBL: React.CSSProperties = { fontSize: '.78rem', fontWeight: 600, display: 'block', marginBottom: '.25rem' };

/**
 * أمان الحساب — مشترك بين الوكالة والعميل والمبدع والشريك.
 * مكوّن واحد لأن السلوك واحد: تغيير كلمة المرور يُنهي الجلسات الأخرى،
 * والجلسة الحالية تُميَّز ولا يمكن إنهاؤها من هنا.
 */
export default function AccountSecurity({ prefs, categories, sessions, twoFactorEnabled, base }: SecurityProps) {
  const [busy, setBusy] = useState(false);
  const [errs, setErrs] = useState<Record<string, string>>({});
  const [pr, setPr] = useState<Record<string, Pref>>(prefs);
  const [pw, setPw] = useState({ current_password: '', password: '', password_confirmation: '' });

  const post = (path: string, data: Record<string, unknown>, done?: () => void) => {
    setBusy(true);
    router.post(u(`${base}${path}`), data as never, {
      preserveScroll: true,
      onFinish: () => setBusy(false),
      onError: (e) => setErrs(e as Record<string, string>),
      onSuccess: () => { setErrs({}); done?.(); },
    });
  };
  const Err = ({ k }: { k: string }) => errs[k]
    ? <div style={{ color: 'var(--ih-danger-ink)', fontSize: '.74rem', marginTop: '.25rem' }}>{errs[k]}</div>
    : null;

  return (
    <div style={{ display: 'grid', gap: '1.1rem' }}>
      <Sec title="تفضيلات الإشعارات" icon="inbox">
        <div className="ih-sec__body" style={{ display: 'grid', gap: '.6rem' }}>
          {Object.entries(categories).map(([key, label]) => (
            <div key={key} style={{ display: 'flex', alignItems: 'center', gap: '.8rem', flexWrap: 'wrap' }}>
              <span style={{ fontSize: '.84rem', minWidth: 140 }}>{label}</span>
              {(['in_app', 'email', 'sms'] as const).map((ch) => (
                <label key={ch} style={{ display: 'inline-flex', alignItems: 'center', gap: '.3rem', fontSize: '.78rem' }}>
                  <input type="checkbox" checked={pr[key]?.[ch] ?? false}
                    onChange={(e) => setPr({ ...pr, [key]: { ...pr[key], [ch]: e.target.checked } })} />
                  {ch === 'in_app' ? 'داخل المنصّة' : ch === 'email' ? 'بريد' : 'رسالة نصية'}
                </label>
              ))}
            </div>
          ))}
          <div>
            <button disabled={busy} onClick={() => post('/notifications', { prefs: pr })} className="btn btn-sm btn-primary">
              حفظ التفضيلات
            </button>
          </div>
        </div>
      </Sec>

      <Sec title="كلمة المرور" icon="shield-check">
        <div className="ih-sec__body" style={{ display: 'grid', gap: '.7rem', maxWidth: 460 }}>
          <div style={{ fontSize: '.78rem', color: 'var(--ih-text-muted)' }}>
            تغيير كلمة المرور يُنهي جلساتك الأخرى تلقائيًا.
          </div>
          <div>
            <label style={LBL}>كلمة المرور الحالية</label>
            <input type="password" value={pw.current_password} onChange={(e) => setPw({ ...pw, current_password: e.target.value })}
              className="field" style={{ width: '100%', direction: 'ltr' }} autoComplete="current-password" />
            <Err k="current_password" />
          </div>
          <div>
            <label style={LBL}>الجديدة</label>
            <input type="password" value={pw.password} onChange={(e) => setPw({ ...pw, password: e.target.value })}
              className="field" style={{ width: '100%', direction: 'ltr' }} autoComplete="new-password" />
            <Err k="password" />
          </div>
          <div>
            <label style={LBL}>تأكيد الجديدة</label>
            <input type="password" value={pw.password_confirmation} onChange={(e) => setPw({ ...pw, password_confirmation: e.target.value })}
              className="field" style={{ width: '100%', direction: 'ltr' }} autoComplete="new-password" />
          </div>
          <div>
            <button disabled={busy || !pw.current_password || !pw.password}
              onClick={() => post('/password', pw, () => setPw({ current_password: '', password: '', password_confirmation: '' }))}
              className="btn btn-sm btn-primary">تحديث كلمة المرور</button>
          </div>
        </div>
      </Sec>

      <Sec title="الجلسات النشطة" icon="activity">
        <div className="ih-sec__body" style={{ display: 'grid', gap: '.6rem' }}>
          <div style={{ fontSize: '.78rem', color: 'var(--ih-text-muted)' }}>
            التحقّق بخطوتين: {twoFactorEnabled ? 'مُفعَّل' : 'غير مُفعَّل'}
          </div>
          {sessions.length === 0 ? (
            <div style={{ color: 'var(--ih-text-muted)', fontSize: '.85rem' }}>لا جلسات مسجّلة.</div>
          ) : sessions.map((s, i) => (
            <div key={i} style={{ display: 'flex', alignItems: 'center', gap: '.6rem', fontSize: '.8rem', flexWrap: 'wrap' }}>
              <span style={{ direction: 'ltr' }}>{s.ip ?? '—'}</span>
              <span style={{ color: 'var(--ih-text-muted)', flex: 1, minWidth: 0, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', direction: 'ltr', textAlign: 'start' }}>{s.agent ?? '—'}</span>
              <span style={{ color: 'var(--ih-text-muted)' }}>{s.lastActivity ?? '—'}</span>
              {s.current && <span className="badge" style={{ background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)', fontSize: '.62rem' }}>الحالية</span>}
            </div>
          ))}
          {sessions.length > 1 && (
            <div>
              <button disabled={busy} onClick={() => post('/sessions/revoke-others', {})} className="btn btn-sm btn-outline">
                إنهاء الجلسات الأخرى
              </button>
            </div>
          )}
        </div>
      </Sec>
    </div>
  );
}

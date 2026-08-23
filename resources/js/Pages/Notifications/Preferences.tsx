import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { ListHead } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';
import type { SharedProps } from '@/types';

type ChannelKey = 'in_app' | 'email' | 'whatsapp' | 'sms';
interface Category { key: string; label: string; in_app: boolean; email: boolean; whatsapp: boolean; sms: boolean }
interface Channel { key: ChannelKey; label: string; always?: boolean; available?: boolean }
interface Props { categories: Category[]; channels: Channel[] }

export default function NotificationsPreferences({ categories, channels }: Props) {
  const flash = usePage<SharedProps>().props.flash;
  const [rows, setRows] = useState(categories);

  const toggle = (catKey: string, channel: ChannelKey, value: boolean) => {
    const next = rows.map((r) => (r.key === catKey ? { ...r, [channel]: value } : r));
    setRows(next);
    const row = next.find((r) => r.key === catKey)!;
    router.post(u('/notifications/preferences'), {
      category: catKey, in_app: row.in_app, email: row.email, whatsapp: row.whatsapp, sms: row.sms,
    }, { preserveScroll: true, preserveState: true });
  };

  return (
    <AppShell heading="تفضيلات الإشعارات">
      <Head title="تفضيلات الإشعارات" />
      {flash?.ok && <div className="card" style={{ padding: '.7rem 1rem', marginBottom: '1rem', borderInlineStart: '3px solid var(--ih-success)', background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)' }}>{flash.ok}</div>}

      <ListHead eyebrow="التواصل" title="تفضيلات الإشعارات" sub="اختر قنوات كل فئة — يُطبَّق فورًا على التسليم."
        actions={<Link href={u('/notifications')} className="btn btn-sm btn-outline"><Icon name="message-circle" size={14} /> الإشعارات</Link>} />

      <div className="ih-dt-wrap"><div className="ih-dt-scroll">
        <table className="ih-dt">
          <thead>
            <tr>
              <th>الفئة</th>
              {channels.map((c) => (
                <th key={c.key} style={{ textAlign: 'center' }}>
                  {c.label}
                  {c.available === false && <div style={{ fontSize: '.6rem', color: 'var(--ih-warning-ink)', fontWeight: 400 }}>غير مهيّأة</div>}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.key}>
                <td style={{ fontWeight: 600 }}>{r.label}</td>
                {channels.map((c) => {
                  const disabled = c.available === false && !r[c.key];
                  return (
                    <td key={c.key} style={{ textAlign: 'center' }}>
                      <input type="checkbox" checked={r[c.key]} disabled={disabled}
                        onChange={(e) => toggle(r.key, c.key, e.target.checked)}
                        style={{ width: 17, height: 17, cursor: disabled ? 'not-allowed' : 'pointer', accentColor: 'var(--ih-primary)' }}
                        title={disabled ? 'القناة غير مهيّأة بعد' : undefined} />
                    </td>
                  );
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div></div>

      <div className="card" style={{ marginTop: '1.1rem', padding: '.85rem 1rem', borderInlineStart: '3px solid var(--ih-info)', background: 'var(--ih-info-soft)', color: 'var(--ih-info-ink)', fontSize: '.82rem', lineHeight: 1.6 }}>
        <Icon name="shield-check" size={14} /> «داخل التطبيق» متاح دائمًا. القنوات الخارجية تُرسِل فعليًّا عند تهيئتها؛ قبلها تُسجَّل محاولة بحالة «بانتظار التهيئة» بلا تسليم وهمي.
      </div>
    </AppShell>
  );
}

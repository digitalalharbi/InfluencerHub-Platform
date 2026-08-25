import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { platformNav } from '@/lib/nav';
import { ListHead, StatusBadge } from '@/Components/ui';
import { Icon } from '@/Components/Icon';

interface TenantRow { id: number; name: string; slug: string; type: string; status: string; statusLabel: string; statusTone: string; orgs: number; href: string }
interface Paginator<T> { data: T[]; links: { url: string | null; label: string; active: boolean }[] }
interface Props { tenants: Paginator<TenantRow>; total: number; filters: { q: string | null; status: string | null } }

export default function PlatformTenants({ tenants, total, filters }: Props) {
  const [q, setQ] = useState(filters.q ?? '');
  const search = (e: React.FormEvent) => { e.preventDefault(); router.get('/platform/tenants', { q: q || undefined }, { preserveState: true, replace: true }); };

  return (
    <AppShell heading="المستأجرون" nav={platformNav} portal="platform">
      <Head title="المستأجرون · المنصّة" />
      <ListHead eyebrow="المنصّة" title="المستأجرون" sub={`${total.toLocaleString('en-US')} مستأجرًا — اختر واحدًا لمعاينته، أو اضغط ⌘K للبحث الشامل.`} />

      <form onSubmit={search} style={{ display: 'flex', gap: '.5rem', marginBottom: '1rem', maxWidth: 480 }}>
        <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="ابحث بالاسم أو المعرّف…"
          style={{ flex: 1, padding: '.5rem .7rem', border: '1px solid var(--ih-border)', borderRadius: 8, background: 'var(--ih-surface, #fff)', color: 'var(--ih-text)', fontSize: '.88rem' }} />
        <button className="btn btn-sm btn-outline" type="submit"><Icon name="radar" size={14} /> بحث</button>
      </form>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))', gap: '.8rem' }}>
        {tenants.data.length === 0 ? <p className="pub-muted">لا مستأجرين مطابقين.</p> :
          tenants.data.map((t) => (
            <Link key={t.id} href={t.href} className="card" style={{ padding: '.9rem 1rem', textDecoration: 'none', color: 'inherit', display: 'flex', flexDirection: 'column', gap: '.4rem' }}>
              <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '.5rem' }}>
                <span style={{ fontWeight: 700, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{t.name}</span>
                <StatusBadge tone={t.statusTone} label={t.statusLabel} />
              </div>
              <div style={{ fontSize: '.74rem', color: 'var(--ih-text-muted)', display: 'flex', gap: '.6rem' }}>
                <span style={{ direction: 'ltr' }}>{t.slug}</span><span>· {t.type}</span><span>· {t.orgs.toLocaleString('en-US')} مؤسسة</span>
              </div>
            </Link>
          ))}
      </div>

      {tenants.links.length > 3 && (
        <div style={{ display: 'flex', gap: '.3rem', flexWrap: 'wrap', marginTop: '1rem', justifyContent: 'center' }}>
          {tenants.links.map((l, i) => (
            l.url
              ? <Link key={i} href={l.url} preserveState className={`btn btn-xs ${l.active ? 'btn-primary' : 'btn-ghost'}`} dangerouslySetInnerHTML={{ __html: l.label }} />
              : <span key={i} className="btn btn-xs btn-ghost" style={{ opacity: .4 }} dangerouslySetInnerHTML={{ __html: l.label }} />
          ))}
        </div>
      )}
    </AppShell>
  );
}

import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { adminNav } from '@/lib/nav';
import { ListHead, StatusBadge } from '@/Components/ui';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';

interface Row { id: number; name: string; slug: string; mode: string; status: string; statusLabel: string; statusTone: string; orgs: number; members: number; sub: boolean }
interface Props { tenants: Paginated<Row>; filters: { q: string | null; status: string | null } }


export default function AdminTenants({ tenants, filters }: Props) {
  const [q, setQ] = useState(filters.q ?? '');
  const [status, setStatus] = useState(filters.status ?? '');
  const search = () => router.get(u('/tenants'), { q: q || undefined, status: status || undefined }, { preserveState: true, replace: true });

  return (
    <AppShell heading="المستأجرون" nav={adminNav} portal="admin" wsName="إدارة المنصّة" wsPlan="مدير النظام" brand="InfluencerHub">
      <Head title="المستأجرون" />
      <ListHead eyebrow="المنصّة" title="المستأجرون" sub={`${tenants.total.toLocaleString('en-US')} مستأجر`} />

      <div className="ih-filterbar" style={{ marginBottom: '1rem' }}>
        <div className="ih-search">
          <Icon name="search" size={15} />
          <input value={q} onChange={(e) => setQ(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && search()} placeholder="ابحث بالاسم أو المعرّف…" />
        </div>
        <select value={status} onChange={(e) => setStatus(e.target.value)} className="field" style={{ maxWidth: 150 }}>
          <option value="">كل الحالات</option>
          <option value="active">active</option>
          <option value="suspended">suspended</option>
          <option value="pending">pending</option>
          <option value="archived">archived</option>
        </select>
        <button onClick={search} className="btn btn-sm">بحث</button>
      </div>

      <div className="ih-dt-wrap"><div className="ih-dt-scroll">
        <table className="ih-dt">
          <thead><tr><th>المستأجر</th><th>النمط</th><th>المؤسسات</th><th>الأعضاء</th><th>اشتراك</th><th>الحالة</th></tr></thead>
          <tbody>
            {tenants.data.map((t) => (
              <tr key={t.id}>
                <td>
                  <div style={{ fontWeight: 600 }}>{t.name}</div>
                  <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', direction: 'ltr' }}>{t.slug}</div>
                </td>
                <td style={{ direction: 'ltr' }}>{t.mode}</td>
                <td style={{ direction: 'ltr' }}>{t.orgs.toLocaleString('en-US')}</td>
                <td style={{ direction: 'ltr' }}>{t.members.toLocaleString('en-US')}</td>
                <td>{t.sub ? <span className="ih-tag" style={{ background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)' }}>نشط</span> : <span style={{ color: 'var(--ih-text-muted)', fontSize: '.8rem' }}>—</span>}</td>
                <td><StatusBadge tone={t.statusTone} label={t.statusLabel} /></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div></div>
      <Pagination links={tenants.links} />
    </AppShell>
  );
}

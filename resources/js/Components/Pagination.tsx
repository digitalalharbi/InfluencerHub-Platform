import { router } from '@inertiajs/react';

export interface PaginatorLink { url: string | null; label: string; active: boolean }
export interface Paginated<T> {
  data: T[];
  links: PaginatorLink[];
  from: number | null;
  to: number | null;
  total: number;
  current_page: number;
  last_page: number;
}

export function Pagination({ links }: { links: PaginatorLink[] }) {
  if (links.length <= 3) return null;
  const go = (url: string | null) => {
    if (url) router.visit(url, { preserveScroll: true, preserveState: true });
  };
  return (
    <div style={{ display: 'flex', gap: '.25rem', flexWrap: 'wrap' }}>
      {links.map((l, i) => (
        <button
          key={i}
          disabled={!l.url}
          onClick={() => go(l.url)}
          className={`btn btn-xs ${l.active ? 'btn-primary' : 'btn-ghost'}`}
          style={{ minWidth: 32, opacity: l.url ? 1 : 0.4, cursor: l.url ? 'pointer' : 'default' }}
          dangerouslySetInnerHTML={{ __html: l.label }}
        />
      ))}
    </div>
  );
}

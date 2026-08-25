import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Icon } from '@/Components/Icon';

interface Result {
  entityType: string; typeLabel: string; entityId: number; name: string;
  sub: string | null; tenantId: number | null; tenant: string; organizationId: number | null;
  portalHint: string | null; status: string | null; contextHref: string;
}

/**
 * لوحة أوامر مالك المنصّة (⌘K / Ctrl+K) — بحث شامل حيّ عبر الخادم (/platform/search)
 * في المستأجرين/المؤسسات/المستخدمين/العملاء/العلامات/الحملات/صنّاع المحتوى/العقود/
 * الفواتير/المستحقات. اختيار نتيجة يدخل سياق المستأجر الصحيح. لا يبحث في أسرار.
 */
export default function PlatformCommandPalette() {
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState('');
  const [results, setResults] = useState<Result[]>([]);
  const [loading, setLoading] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); setOpen((v) => !v); }
      if (e.key === 'Escape') setOpen(false);
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  useEffect(() => { if (open) setTimeout(() => inputRef.current?.focus(), 30); else { setQ(''); setResults([]); } }, [open]);

  useEffect(() => {
    if (!open) return;
    const term = q.trim();
    if (term.length < 2) { setResults([]); return; }
    setLoading(true);
    const ctrl = new AbortController();
    const t = setTimeout(async () => {
      try {
        const res = await fetch(`/platform/search?q=${encodeURIComponent(term)}`, {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: ctrl.signal,
        });
        if (res.ok) { const data = await res.json(); setResults(data.results ?? []); }
      } catch { /* aborted */ }
      finally { setLoading(false); }
    }, 250);
    return () => { clearTimeout(t); ctrl.abort(); };
  }, [q, open]);

  const go = (href: string) => { if (href) { setOpen(false); router.visit(href); } };

  if (!open) return null;
  return (
    <div role="dialog" aria-modal="true" aria-label="بحث المنصّة"
      onClick={(e) => { if (e.target === e.currentTarget) setOpen(false); }}
      style={{ position: 'fixed', inset: 0, zIndex: 1000, background: 'rgba(9,13,24,.55)', display: 'flex', alignItems: 'flex-start', justifyContent: 'center', paddingTop: '12vh' }}>
      <div style={{ width: 'min(680px, 92vw)', background: 'var(--ih-surface, #fff)', borderRadius: 14, boxShadow: '0 20px 60px rgba(0,0,0,.35)', overflow: 'hidden' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '.6rem', padding: '.85rem 1rem', borderBottom: '1px solid var(--ih-border)' }}>
          <Icon name="radar" size={18} className="ih-icon" />
          <input ref={inputRef} value={q} onChange={(e) => setQ(e.target.value)} placeholder="ابحث في كل المستأجرين والكيانات…"
            style={{ flex: 1, border: 0, outline: 'none', background: 'transparent', fontSize: '.95rem', color: 'var(--ih-text)' }} />
          {loading && <span style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)' }}>…</span>}
          <kbd style={{ fontSize: '.62rem', color: 'var(--ih-text-muted)', border: '1px solid var(--ih-border)', borderRadius: 4, padding: '.05rem .3rem' }}>Esc</kbd>
        </div>
        <div style={{ maxHeight: '52vh', overflowY: 'auto' }}>
          {q.trim().length < 2 ? (
            <p style={{ padding: '1.2rem', textAlign: 'center', color: 'var(--ih-text-muted)', fontSize: '.82rem' }}>اكتب حرفين على الأقل للبحث.</p>
          ) : results.length === 0 && !loading ? (
            <p style={{ padding: '1.2rem', textAlign: 'center', color: 'var(--ih-text-muted)', fontSize: '.82rem' }}>لا نتائج.</p>
          ) : (
            <ul style={{ listStyle: 'none', margin: 0, padding: '.35rem' }}>
              {results.map((r, idx) => (
                <li key={`${r.entityType}-${r.entityId}-${idx}`}>
                  <button type="button" onClick={() => go(r.contextHref)} disabled={!r.contextHref}
                    style={{ width: '100%', display: 'flex', alignItems: 'center', gap: '.7rem', padding: '.6rem .7rem', border: 0, background: 'none', cursor: r.contextHref ? 'pointer' : 'default', textAlign: 'start', borderRadius: 8 }}
                    onMouseEnter={(e) => (e.currentTarget.style.background = 'var(--ih-surface-sunken, #F2F4F7)')}
                    onMouseLeave={(e) => (e.currentTarget.style.background = 'none')}>
                    <span style={{ fontSize: '.62rem', fontWeight: 700, padding: '.12rem .45rem', borderRadius: 999, background: 'var(--ih-primary-soft)', color: 'var(--ih-primary-700)', flexShrink: 0 }}>{r.typeLabel}</span>
                    <span style={{ minWidth: 0, flex: 1 }}>
                      <span style={{ display: 'block', fontWeight: 600, fontSize: '.88rem', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{r.name}</span>
                      <span style={{ display: 'block', fontSize: '.72rem', color: 'var(--ih-text-muted)' }}>{[r.sub, r.tenant, r.portalHint].filter(Boolean).join(' · ')}</span>
                    </span>
                    {r.contextHref && <Icon name="chevron-left" size={15} />}
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>
    </div>
  );
}

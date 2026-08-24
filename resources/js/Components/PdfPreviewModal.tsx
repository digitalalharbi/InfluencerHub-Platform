import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';

/** بيانات مستند قابل للمعاينة — المعاينة والتنزيل يبثّان نفس الأثر (نفس البايتات). */
export interface PreviewDoc {
  title: string;
  hasArtifact: boolean;
  generatedAt: string | null;
  stale: boolean;
  previewUrl: string;
  downloadUrl: string;
  regenerateUrl: string | null;
}

/**
 * معاينة PDF أولًا ثم تنزيل نفس الملفّ — لا تجديد صامت. عند تغيّر المصدر تظهر لافتة
 * «توجد تحديثات» مع خيار إنشاء نسخة محدّثة صريح. ESC/نقر خارجي/تراكب يغلق.
 */
export function PdfPreviewModal({ doc, open, onClose }: { doc: PreviewDoc; open: boolean; onClose: () => void }) {
  const [nonce, setNonce] = useState(0);            // لكسر التخزين المؤقت بعد التجديد
  const [busy, setBusy] = useState(false);
  const [downloadMsg, setDownloadMsg] = useState<string | null>(null);
  const closeRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
    document.addEventListener('keydown', onKey);
    closeRef.current?.focus();
    return () => document.removeEventListener('keydown', onKey);
  }, [open, onClose]);

  if (!open) return null;

  const previewSrc = u(doc.previewUrl) + (nonce ? `?v=${nonce}` : '');
  const regenerate = () => {
    if (!doc.regenerateUrl) return;
    setBusy(true);
    router.post(u(doc.regenerateUrl), {}, {
      preserveScroll: true, preserveState: true,
      onFinish: () => { setBusy(false); setNonce((n) => n + 1); },
    });
  };
  const onDownload = () => {
    setDownloadMsg('جارٍ تجهيز الملف…');
    window.setTimeout(() => setDownloadMsg('بدأ التنزيل'), 900);
    window.setTimeout(() => setDownloadMsg(null), 3000);
  };

  return (
    <div className="ih-modal-backdrop" role="dialog" aria-modal="true" aria-label={doc.title}
      onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="ih-modal" style={{ maxWidth: 940, width: '100%', maxHeight: '92dvh', display: 'flex', flexDirection: 'column', padding: 0 }}>
        {/* رأس + شريط أدوات */}
        <div style={{ display: 'flex', alignItems: 'center', gap: '.6rem', padding: '.85rem 1.1rem', borderBottom: '1px solid var(--ih-border)', flexWrap: 'wrap' }}>
          <Icon name="file-text" size={17} />
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ fontWeight: 800, fontSize: '.95rem' }}>{doc.title}</div>
            {doc.generatedAt && <div style={{ fontSize: '.7rem', color: 'var(--ih-text-muted)' }}>أُنشئت: {doc.generatedAt}</div>}
          </div>
          <a href={u(doc.downloadUrl)} className="btn btn-sm btn-primary" download onClick={onDownload}>
            <Icon name="file-text" size={14} /> تحميل
          </a>
          <a href={u(doc.previewUrl)} target="_blank" rel="noopener noreferrer" className="btn btn-sm btn-outline">
            <Icon name="external-link" size={14} /> تبويب جديد
          </a>
          {doc.regenerateUrl && (
            <button onClick={regenerate} disabled={busy} className="btn btn-sm btn-outline">
              <Icon name="git-merge" size={14} /> {busy ? 'يُنشئ…' : 'إعادة إنشاء'}
            </button>
          )}
          <button ref={closeRef} onClick={onClose} className="btn btn-sm btn-ghost" aria-label="إغلاق">✕</button>
        </div>

        {/* لافتة القِدَم — تغيّر المصدر منذ إنشاء النسخة */}
        {doc.stale && (
          <div style={{ padding: '.6rem 1.1rem', background: 'var(--ih-warning-soft, #FFFAEB)', color: 'var(--ih-warning-ink, #B54708)', fontSize: '.8rem', display: 'flex', alignItems: 'center', gap: '.6rem', flexWrap: 'wrap' }}>
            <Icon name="alert-triangle" size={15} />
            <span style={{ flex: 1 }}>تغيّرت بيانات الحملة منذ إنشاء هذه النسخة. المعاينة تعرض النسخة المحفوظة.</span>
            {doc.regenerateUrl && <button onClick={regenerate} disabled={busy} className="btn btn-xs btn-primary">إنشاء نسخة محدّثة</button>}
          </div>
        )}
        {downloadMsg && (
          <div style={{ padding: '.45rem 1.1rem', background: 'var(--ih-success-soft, #ECFDF3)', color: 'var(--ih-success-700, #067647)', fontSize: '.78rem' }}>{downloadMsg}</div>
        )}

        {/* المعاينة — عارض PDF الأصلي للمتصفّح، نفس الأثر الذي يُنزَّل */}
        <div style={{ flex: 1, minHeight: 420, background: 'var(--ih-surface-sunken, #F2F4F7)' }}>
          {busy ? (
            <div style={{ display: 'grid', placeItems: 'center', height: '100%', minHeight: 420, color: 'var(--ih-text-muted)', fontSize: '.85rem' }}>يُنشئ النسخة…</div>
          ) : (
            <iframe key={nonce} title={doc.title} src={previewSrc} style={{ width: '100%', height: '68dvh', minHeight: 420, border: 0, display: 'block' }} />
          )}
        </div>
      </div>
    </div>
  );
}

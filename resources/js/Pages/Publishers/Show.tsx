import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { WorkspaceHeader, SummaryStrip, Sec, StatusBadge } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';

interface Publisher {
  id: number; number: string; name: string; handle: string; platform: string; platformLabel: string;
  followers: number; engagement: number | null; growth: number | null; contentTypes: string[]; categories: string[];
  city: string | null; language: string | null; quality: number | null;
  source: string; sourceLabel: string; sourceTone: string; lastSynced: string | null; saved: boolean; converted: boolean;
  audienceNote: string | null; brands: string[]; convertedCreatorId: number | null;
}
interface Props { publisher: Publisher; canConvert: boolean }

const fmt = (n: number) => n >= 1000 ? Math.round(n / 1000).toLocaleString('en-US') + 'K' : n.toLocaleString('en-US');

export default function PublisherShow({ publisher: p, canConvert }: Props) {
  const [busy, setBusy] = useState(false);
  const save = () => { setBusy(true); router.post(u(`/publishers/${p.id}/save`), {}, { preserveScroll: true, onFinish: () => setBusy(false) }); };
  const convert = () => { setBusy(true); router.post(u(`/publishers/${p.id}/convert`), { type: 'influencer' }, { onFinish: () => setBusy(false) }); };

  return (
    <AppShell heading="ناشر">
      <Head title={p.name} />

      <WorkspaceHeader
        eyebrow={`ناشر · ${p.platformLabel}`}
        title={p.name}
        statusTone={p.sourceTone} statusLabel={`المصدر: ${p.sourceLabel}`}
        back={u("/publishers")} backLabel="الناشرون"
        meta={[
          ['الحساب', p.handle], ['المدينة', p.city ?? '—'], ['اللغة', p.language ?? '—'],
          ...(p.lastSynced ? [['آخر تحديث', p.lastSynced] as [string, string]] : []),
        ]}
        actions={
          <>
            <button disabled={busy} onClick={save} className={`btn btn-sm ${p.saved ? 'btn-primary' : 'btn-outline'}`}><Icon name="bookmark" size={14} /> {p.saved ? 'محفوظ' : 'حفظ في قائمتي'}</button>
            {p.converted ? (
              <Link href={u(`/creators/${p.convertedCreatorId}`)} className="btn btn-sm">فتح ملف المؤثر</Link>
            ) : canConvert ? (
              <button disabled={busy} onClick={convert} className="btn btn-sm"><Icon name="users" size={14} /> تحويل إلى مؤثر</button>
            ) : undefined}
          </>
        }
      />

      <SummaryStrip
        items={[
          { label: 'المتابعون', value: fmt(p.followers), icon: 'users' },
          { label: 'التفاعل', value: p.engagement != null ? `${p.engagement}%` : '—', icon: 'activity' },
          { label: 'النمو (30ي)', value: p.growth != null ? `${p.growth > 0 ? '+' : ''}${p.growth}%` : '—', icon: 'trending-up', tone: p.growth != null && p.growth >= 0 ? 'success' : 'danger' },
          { label: 'الجودة', value: p.quality != null ? `${p.quality}/100` : '—', icon: 'shield-check', tone: p.quality != null && p.quality >= 70 ? 'success' : undefined },
        ]}
      />

      <div className="card" style={{ padding: '.8rem 1rem', marginBottom: '1.2rem', borderInlineStart: '3px solid var(--ih-info)', background: 'var(--ih-info-soft)', color: 'var(--ih-info-ink)', fontSize: '.82rem' }}>
        <Icon name="shield-check" size={14} /> مصدر البيانات: <b>{p.sourceLabel}</b>{p.lastSynced && <> · آخر تحديث <span style={{ direction: 'ltr' }}>{p.lastSynced}</span></>}. لا اكتشاف حيّ عبر API بعد؛ الأرقام مُدخلة/مستوردة بصدق.
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0, 1.4fr) minmax(0, 1fr)', gap: '1.2rem', alignItems: 'start' }} className="ih-settings-grid">
        <Sec title="الهوية والجمهور" icon="radar">
          <div style={{ display: 'grid', gap: '.7rem' }}>
            <Row k="المنصّة" v={p.platformLabel} />
            <Row k="الحساب" v={p.handle} ltr />
            <Row k="المدينة" v={p.city ?? '—'} />
            <Row k="اللغة" v={p.language ?? '—'} />
            {p.audienceNote && <Row k="ملاحظة الجمهور" v={p.audienceNote} />}
          </div>
          {p.categories.length > 0 && (
            <div style={{ marginTop: '.9rem' }}>
              <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', marginBottom: '.3rem' }}>فئات المحتوى</div>
              <div style={{ display: 'flex', gap: '.3rem', flexWrap: 'wrap' }}>{p.categories.map((c, i) => <span key={i} className="ih-tag" style={{ fontSize: '.7rem' }}>{c}</span>)}</div>
            </div>
          )}
          {p.contentTypes.length > 0 && (
            <div style={{ marginTop: '.7rem' }}>
              <div style={{ fontSize: '.72rem', color: 'var(--ih-text-muted)', marginBottom: '.3rem' }}>أنواع المحتوى</div>
              <div style={{ display: 'flex', gap: '.3rem', flexWrap: 'wrap' }}>{p.contentTypes.map((c, i) => <span key={i} className="ih-tag" style={{ fontSize: '.7rem', direction: 'ltr' }}>{c}</span>)}</div>
            </div>
          )}
        </Sec>

        <Sec title="العلامات والشراكات" icon="bookmark">
          {p.brands.length === 0 ? (
            <div style={{ fontSize: '.82rem', color: 'var(--ih-text-muted)' }}>لا بيانات شراكات رسمية متاحة بعد لهذا الناشر.</div>
          ) : (
            <div style={{ display: 'flex', gap: '.3rem', flexWrap: 'wrap' }}>
              {p.brands.map((b, i) => <span key={i} className="ih-tag" style={{ fontSize: '.72rem' }}>{b}</span>)}
            </div>
          )}
          <div style={{ marginTop: '1rem', fontSize: '.76rem', color: 'var(--ih-text-muted)', lineHeight: 1.6 }}>
            تظهر العلامات/الشراكات فقط عند توفّر البيانات رسميًا من المنصّة. عند التحويل إلى مؤثر يُربط السجل دون تكرار.
          </div>
          {p.converted && <div style={{ marginTop: '.8rem' }}><StatusBadge tone="approved" label="مُحوَّل إلى مؤثر" /></div>}
        </Sec>
      </div>
    </AppShell>
  );
}

function Row({ k, v, ltr }: { k: string; v: string; ltr?: boolean }) {
  return (
    <div style={{ display: 'flex', justifyContent: 'space-between', gap: '1rem', borderBottom: '1px solid var(--ih-border)', paddingBottom: '.45rem' }}>
      <span style={{ fontSize: '.8rem', color: 'var(--ih-text-muted)' }}>{k}</span>
      <span style={{ fontSize: '.86rem', fontWeight: 600, direction: ltr ? 'ltr' : undefined }}>{v}</span>
    </div>
  );
}

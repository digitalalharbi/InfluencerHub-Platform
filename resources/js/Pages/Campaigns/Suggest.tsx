import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Sec, WorkspaceHeader } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { u } from '@/lib/href';

interface Suggestion {
  creatorId: number; name: string; handle: string | null; platform: string | null;
  followers: number; score: number; reasons: string[]; alreadyOffered: boolean; clientApproved: boolean;
}
interface Props {
  campaign: { id: number; name: string };
  deliverable: { id: number; type: string; platform: string | null; quantity: number };
  suggestions: Suggestion[];
  canOffer: boolean;
}

function fnum(n: number): string {
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'M';
  if (n >= 1000) return Math.round(n / 1000) + 'K';
  return n.toLocaleString('en-US');
}

/** درجة المطابقة تأتي من CreatorMatchingService — تُعرض كما هي بلا إعادة حساب. */
function scoreTone(score: number): { bg: string; fg: string; label: string } {
  if (score >= 80) return { bg: 'var(--ih-success-soft)', fg: 'var(--ih-success-ink)', label: 'مطابقة قوية' };
  if (score >= 50) return { bg: 'var(--ih-primary-soft)', fg: 'var(--ih-primary-700)', label: 'مطابقة جيدة' };
  if (score > 0) return { bg: 'var(--ih-warning-soft)', fg: 'var(--ih-warning-ink)', label: 'مطابقة جزئية' };
  return { bg: 'var(--ih-surface-sunken)', fg: 'var(--ih-text-muted)', label: 'مطابقة عامة' };
}

export default function CampaignSuggest({ campaign, deliverable, suggestions, canOffer }: Props) {
  const [busy, setBusy] = useState<number | null>(null);

  const offer = (creatorId: number) => {
    setBusy(creatorId);
    router.post(u(`/campaigns/${campaign.id}/deliverables/${deliverable.id}/offer`), { creator_id: creatorId }, {
      onFinish: () => setBusy(null),
    });
  };

  return (
    <AppShell heading="اقتراح مبدعين">
      <Head title="اقتراح مبدعين" />

      <WorkspaceHeader
        eyebrow="التنفيذ"
        title="اقتراح مبدعين للمخرَج"
        back={u(`/campaigns/${campaign.id}`)} backLabel="رجوع للحملة"
        meta={[
          ['الحملة', campaign.name],
          ['المخرَج', deliverable.type],
          ['المنصّة', deliverable.platform ?? '—'],
          ['الكمية', String(deliverable.quantity)],
        ]}
      />

      <Sec title="المبدعون المقترَحون" icon="users">
        {suggestions.length === 0 ? (
          <div style={{ padding: '2.4rem 1.5rem', textAlign: 'center' }}>
            <span className="ih-empty__icon" style={{ width: 48, height: 48 }}><Icon name="users" size={22} /></span>
            <div style={{ marginTop: '.6rem', fontWeight: 700 }}>لا مبدعين نشِطين للاقتراح</div>
            <div style={{ fontSize: '.84rem', color: 'var(--ih-text-muted)', marginTop: '.2rem' }}>
              تظهر هنا مطابقات المنصّة والفئات لمبدعي المستأجر النشطين.
            </div>
          </div>
        ) : (
          <div style={{ display: 'grid', gap: '.7rem', padding: '.8rem' }}>
            {suggestions.map((s) => {
              const tone = scoreTone(s.score);
              return (
                <div key={s.creatorId} className="card" style={{ padding: '.85rem 1rem', display: 'flex', alignItems: 'center', gap: '.9rem', flexWrap: 'wrap' }}>
                  <span className="ih-idc__av" style={{ width: 40, height: 40, flexShrink: 0 }}>{s.name.slice(0, 1)}</span>

                  <div style={{ minWidth: 180, flex: 1 }}>
                    <a href={u(`/creators/${s.creatorId}`)} style={{ fontWeight: 700, fontSize: '.92rem', color: 'inherit', textDecoration: 'none' }}>{s.name}</a>
                    {/* قرار العميل يسبق درجة المطابقة: المعتمَد لا يُختار بالدرجة بل بقرار سبقه */}
                    {s.clientApproved && (
                      <span className="ih-tag" style={{ fontSize: '.62rem', marginInlineStart: '.4rem', background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)' }}>
                        اعتمده العميل
                      </span>
                    )}
                    <div style={{ fontSize: '.75rem', color: 'var(--ih-text-muted)' }}>
                      {s.handle && <span style={{ direction: 'ltr' }}>@{s.handle.replace(/^@+/, '')}</span>}
                      {s.platform && <> · <span className="ih-tag" style={{ fontSize: '.62rem' }}>{s.platform}</span></>}
                      {s.followers > 0 && <> · <bdi>{fnum(s.followers)}</bdi> متابع</>}
                    </div>
                  </div>

                  <div style={{ minWidth: 160, flex: 1.2 }}>
                    <span className="badge" style={{ background: tone.bg, color: tone.fg, fontSize: '.66rem' }}>
                      {tone.label} · <bdi>{s.score}</bdi>
                    </span>
                    <div style={{ fontSize: '.75rem', color: 'var(--ih-text-muted)', marginTop: '.25rem' }}>
                      {s.reasons.length > 0 ? s.reasons.join(' · ') : 'لا سبب مطابقة محدّد'}
                    </div>
                  </div>

                  <div style={{ marginInlineStart: 'auto' }}>
                    {s.alreadyOffered ? (
                      <span className="ih-tag" style={{ fontSize: '.68rem', background: 'var(--ih-success-soft)', color: 'var(--ih-success-ink)' }}>عُرض عليه</span>
                    ) : canOffer ? (
                      <button disabled={busy !== null} onClick={() => offer(s.creatorId)} className="btn btn-sm btn-primary">
                        {busy === s.creatorId ? 'جارٍ…' : 'عرض تعاون'}
                      </button>
                    ) : null}
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </Sec>
    </AppShell>
  );
}

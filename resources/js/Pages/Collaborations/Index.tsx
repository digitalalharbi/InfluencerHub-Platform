import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AppShell from '@/Layouts/AppShell';
import { Field, Kpi, ListHead, StatusBadge } from '@/Components/ui';
import { Icon } from '@/Components/Icon';
import { Pagination, type Paginated } from '@/Components/Pagination';
import { u } from '@/lib/href';
import { useT } from '@/lib/i18n';

interface CollabRow {
  id: number; number: string; title: string; creator: string | null; campaign: string | null;
  feeMinor: number; currency: string; dueDate: string | null; status: string; statusLabel: string; statusTone: string; needsApproval: boolean;
  overdue: boolean; stage: string;
}
interface Summary {
  total: number; active: number; offered: number; submitted: number;
  approved: number; completed: number; declined: number; committedMinor: number;
}
interface Filters { q?: string; seg?: string }
interface CreatorOption { id: number; name: string }
interface Props {
  collaborations: Paginated<CollabRow>; filters: Filters; summary: Summary;
  canCreate: boolean; creatorOptions: CreatorOption[];
}

const LBL: React.CSSProperties = { fontSize: '.8rem', fontWeight: 600, display: 'block', marginBottom: '.3rem' };

function kfmt(minor: number): string {
  const v = minor / 100;
  if (v >= 1_000_000) return (v / 1_000_000).toFixed(1) + 'M';
  if (v >= 1000) return Math.round(v / 1000) + 'K';
  return v.toLocaleString('en-US');
}
function clean(obj: Record<string, unknown>): Record<string, string> {
  const out: Record<string, string> = {};
  for (const [k, v] of Object.entries(obj)) if (v !== '' && v !== null && v !== undefined) out[k] = String(v);
  return out;
}

export default function CollaborationsIndex({ collaborations, filters, summary, canCreate, creatorOptions }: Props) {
  const t = useT();
  const [createOpen, setCreateOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [form, setForm] = useState({ creator_id: '', title: '', brief: '', fee: '', due_date: '' });

  const submitCreate = () => {
    if (!form.creator_id || !form.title.trim()) return;
    const riyals = Number(form.fee || 0);
    setBusy(true);
    router.post(u('/collaborations'), {
      creator_id: form.creator_id, title: form.title, brief: form.brief,
      fee_minor: Number.isFinite(riyals) ? Math.round(riyals * 100) : 0,
      due_date: form.due_date,
    }, {
      onFinish: () => setBusy(false),
      onError: (e) => setErrors(e as Record<string, string>),
      onSuccess: () => { setCreateOpen(false); setErrors({}); },
    });
  };
  const [q, setQ] = useState(filters.q ?? '');
  const first = useRef(true);
  useEffect(() => {
    if (first.current) { first.current = false; return; }
    const t = setTimeout(() => router.get(u('/collaborations'), clean({ ...filters, q }), { preserveState: true, replace: true, preserveScroll: true }), 350);
    return () => clearTimeout(t);
  }, [q]);
  const update = (patch: Filters) => router.get(u('/collaborations'), clean({ ...filters, ...patch }), { preserveState: true, replace: true, preserveScroll: true });

  const seg = filters.seg ?? '';
  const hasFilters = !!(filters.q || seg);
  const segments: [string, string, number][] = [
    ['', t('collaborations.seg_all'), summary.total], ['active', t('collaborations.seg_active'), summary.active], ['offered', t('collaborations.seg_offered'), summary.offered],
    ['submitted', t('collaborations.seg_submitted'), summary.submitted], ['approved', t('collaborations.seg_approved'), summary.approved],
    ['completed', t('collaborations.seg_completed'), summary.completed], ['declined', t('collaborations.seg_declined'), summary.declined],
  ];

  return (
    <AppShell heading={t('collaborations.idx_title')}>
      <Head title={t('collaborations.idx_title')} />

      <ListHead eyebrow={t('collaborations.idx_eyebrow')} title={t('collaborations.idx_title')}
        sub={t('collaborations.idx_sub')}
        actions={canCreate ? <button onClick={() => setCreateOpen(true)} className="btn btn-sm btn-primary"><Icon name="plus" size={15} /> {t('collaborations.idx_offer')}</button> : undefined} />

      <div className="ih-kpis">
        <Kpi label={t('collaborations.kpi_active')} icon="handshake" tone="accent" value={summary.active.toLocaleString('en-US')} sub={t('collaborations.kpi_active_sub', { n: summary.offered })} />
        <Kpi label={t('collaborations.kpi_pending')} icon="clipboard-check" tone={summary.submitted ? 'warning' : undefined} value={summary.submitted.toLocaleString('en-US')} sub={t('collaborations.kpi_pending_sub')} />
        <Kpi label={t('collaborations.kpi_committed')} icon="wallet" tone="success" value={<>{kfmt(summary.committedMinor)} <small>{t('common.currency_sar')}</small></>} sub={t('collaborations.kpi_committed_sub')} />
        <Kpi label={t('collaborations.kpi_completed')} icon="shield-check" value={summary.completed.toLocaleString('en-US')} sub={t('collaborations.kpi_completed_sub', { n: summary.declined })} />
      </div>

      <div className="ih-chips" style={{ marginBottom: '.9rem', overflowX: 'auto', paddingBottom: '.2rem', flexWrap: 'nowrap' }}>
        {segments.map(([key, label, count]) => (
          <button key={key} onClick={() => update({ seg: key })} className={`ih-chip${seg === key ? ' active' : ''}`}>{label} <span className="ih-chip__count">{count}</span></button>
        ))}
      </div>

      <div className="ih-filterbar">
        <label className="ih-search"><Icon name="search" size={16} />
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('collaborations.search_placeholder')} />
        </label>
      </div>

      {collaborations.data.length === 0 ? (
        <div className="ih-dt-wrap"><div className="ih-empty">
          <span className="ih-empty__icon"><Icon name="handshake" size={26} /></span>
          {hasFilters ? (
            <><div className="ih-empty__title">{t('collaborations.empty_filtered_title')}</div><div className="ih-empty__text">{t('collaborations.empty_filtered_text')}</div><a href={u("/collaborations")} className="btn btn-sm btn-outline">{t('collaborations.clear_filters')}</a></>
          ) : (
            <><div className="ih-empty__title">{t('collaborations.empty_title')}</div><div className="ih-empty__text">{t('collaborations.empty_text')}</div></>
          )}
        </div></div>
      ) : (
        <>
          {/* دورة التعاون — لوحة مراحل ببطاقات */}
          <div className="ih-only-desktop">
            <div className="ih-pipe">
              {([['offered', t('collaborations.stage_offered')], ['progress', t('collaborations.stage_progress')], ['done', t('collaborations.stage_done')], ['closed', t('collaborations.stage_closed')]] as [string, string][]).map(([stage, label]) => {
                const col = collaborations.data.filter((c) => c.stage === stage);
                return (
                  <div key={stage} className="ih-pipe__col">
                    <div className="ih-pipe__head"><span>{label}</span><span className="ih-pipe__count">{col.length}</span></div>
                    <div className="ih-pipe__body">
                      {col.length === 0 ? <div className="ih-pipe__empty">{t('collaborations.pipe_empty')}</div> : col.map((c) => (
                        <a key={c.id} href={u(`/collaborations/${c.id}`)} className="ih-wcard">
                          <div style={{ display: 'flex', justifyContent: 'space-between', gap: '.5rem', alignItems: 'flex-start' }}>
                            <span className="ih-wcard__title">{c.title}</span>
                            <StatusBadge tone={c.statusTone} label={c.statusLabel} />
                          </div>
                          <div className="ih-wcard__meta">{c.creator ?? '—'}{c.campaign ? ` · ${c.campaign}` : ''}</div>
                          <div className="ih-wcard__row">
                            <span style={{ fontWeight: 700, direction: 'ltr', fontSize: '.84rem' }}>{kfmt(c.feeMinor)} {t('common.currency_sar')}</span>
                            <span style={{ fontSize: '.72rem', color: c.overdue ? 'var(--ih-danger-ink)' : 'var(--ih-text-muted)', direction: 'ltr', fontWeight: c.overdue ? 700 : 400 }}>
                              {c.dueDate ?? '—'}
                            </span>
                          </div>
                          {c.needsApproval && <div className="ih-wcard__risk" style={{ background: 'var(--ih-warning-soft)', color: 'var(--ih-warning-ink)' }}>{t('collaborations.needs_your_approval')}</div>}
                          {c.overdue && <div className="ih-wcard__risk">{t('collaborations.overdue')}</div>}
                        </a>
                      ))}
                    </div>
                  </div>
                );
              })}
            </div>
            <div className="ih-dt__foot" style={{ marginTop: '1rem' }}><span>{t('collaborations.count_item', { n: collaborations.total })}</span><Pagination links={collaborations.links} /></div>
          </div>

          <div className="ih-only-mobile">
            <div className="ih-mlist">
              {collaborations.data.map((c) => (
                <a key={c.id} href={u(`/collaborations/${c.id}`)} className="ih-mcard">
                  <div style={{ display: 'flex', alignItems: 'flex-start', gap: '.6rem' }}>
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div className="ih-idc__name">{c.title}</div>
                      <div className="ih-idc__sub">{c.creator ?? '—'} · {c.number}</div>
                    </div>
                    <StatusBadge tone={c.statusTone} label={c.statusLabel} />
                  </div>
                  <div style={{ display: 'flex', gap: '.6rem', marginTop: '.7rem', fontSize: '.82rem' }}>
                    <span style={{ fontWeight: 700, direction: 'ltr', display: 'inline-block' }}>{kfmt(c.feeMinor)} {c.currency}</span>
                    <span style={{ marginInlineStart: 'auto', color: 'var(--ih-text-muted)' }}>{c.campaign ?? ''}</span>
                  </div>
                </a>
              ))}
            </div>
            <div style={{ marginTop: '1rem' }}><Pagination links={collaborations.links} /></div>
          </div>
        </>
      )}
      {createOpen && (
        <div className="modal-backdrop" onClick={(e) => e.target === e.currentTarget && !busy && setCreateOpen(false)}>
          <div className="modal" style={{ padding: '1.3rem', maxWidth: 560 }}>
            <h3 style={{ fontWeight: 800, margin: '0 0 1rem' }}>{t('collaborations.create_title')}</h3>
            <div style={{ display: 'grid', gap: '.8rem' }}>
              <Field label={t('collaborations.f_creator')} labelStyle={LBL}>
                <select value={form.creator_id} onChange={(e) => setForm({ ...form, creator_id: e.target.value })} className="field" style={{ width: '100%' }} autoFocus>
                  <option value="">{t('collaborations.choose')}</option>
                  {creatorOptions.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
                {errors.creator_id && <div style={{ color: 'var(--ih-danger-ink)', fontSize: '.76rem', marginTop: '.3rem' }}>{errors.creator_id}</div>}
              </Field>
              <Field label={t('collaborations.f_title')} labelStyle={LBL}>
                <input value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} className="field" style={{ width: '100%' }} />
                {errors.title && <div style={{ color: 'var(--ih-danger-ink)', fontSize: '.76rem', marginTop: '.3rem' }}>{errors.title}</div>}
              </Field>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '.8rem' }}>
                <Field label={t('collaborations.f_fee')} labelStyle={LBL}>
                  <input type="number" min={0} step="0.01" value={form.fee} onChange={(e) => setForm({ ...form, fee: e.target.value })}
                    className="field" style={{ width: '100%', direction: 'ltr' }} placeholder="0" />
                </Field>
                <Field label={t('collaborations.f_due')} labelStyle={LBL}>
                  <input type="date" value={form.due_date} onChange={(e) => setForm({ ...form, due_date: e.target.value })} className="field" style={{ width: '100%', direction: 'ltr' }} />
                </Field>
              </div>
              <Field label={t('collaborations.f_brief')} labelStyle={LBL}>
                <textarea value={form.brief} onChange={(e) => setForm({ ...form, brief: e.target.value })} className="field" rows={3} style={{ width: '100%' }} />
              </Field>
              {errors.offer && <div style={{ color: 'var(--ih-danger-ink)', fontSize: '.8rem' }}>{errors.offer}</div>}
            </div>
            <div style={{ marginTop: '1rem', display: 'flex', gap: '.5rem' }}>
              <button disabled={busy || !form.creator_id || !form.title.trim()} onClick={submitCreate} className="btn btn-primary">{t('collaborations.submit_offer')}</button>
              <button disabled={busy} onClick={() => setCreateOpen(false)} className="btn btn-ghost">{t('collaborations.cancel')}</button>
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}

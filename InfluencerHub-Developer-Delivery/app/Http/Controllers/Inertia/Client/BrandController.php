<?php

namespace App\Http\Controllers\Inertia\Client;

use App\Domain\CRM\Models\Brand;
use App\Domain\CRM\Services\BrandWorkflowService;
use App\Domain\CRM\Support\ClientPortalAbilities;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use App\Support\Http\MountPrefix;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * علامات العميل التجارية (React/Inertia) — عرض + إنشاء/تعديل مسودة/إرسال للمراجعة عبر BrandWorkflowService.
 * الإدارة لأدوار العميل المخوّلة (client_admin/client_campaign_manager). معزول على العميل النشِط.
 */
class BrandController extends Controller
{
    private function canManage(Request $r): bool
    {
        return ClientPortalAbilities::can($r->attributes->get('clientMembership')->role, ClientPortalAbilities::MANAGE_BRANDS);
    }

    private function rules(): array
    {
        return [
            'name' => 'required|string|max:160', 'sector' => 'nullable|string|max:120', 'website' => 'nullable|string|max:200',
            'description' => 'nullable|string|max:2000', 'tone_of_voice' => 'nullable|string|max:500', 'target_audience' => 'nullable|string|max:500',
            'preferred_language' => 'nullable|string|max:10',
        ];
    }

    public function index(Request $r): Response
    {
        $c = $r->attributes->get('activeClient');
        $brands = Brand::where('client_id', $c->id)->latest()->get()->map(fn (Brand $b) => $this->row($b));

        return Inertia::render('ClientPortal/Brands/Index', [
            'clientName' => $c->display_name,
            'brands' => $brands,
            'canManage' => $this->canManage($r),
        ]);
    }

    public function show(Request $r, int $brand): Response
    {
        $b = $this->brandOf($r, $brand);
        $b->load('statusHistory');
        $actorIds = $b->statusHistory->pluck('actor_id')->filter()->unique();
        $actors = User::whereIn('id', $actorIds)->pluck('name', 'id');
        $history = $b->statusHistory->sortByDesc('occurred_at')->take(12)->map(fn ($h) => [
            'to' => __("statuses.{$h->to_status}"), 'tone' => __("statuses.tone.{$h->to_status}"),
            'actor' => $actors[$h->actor_id] ?? 'النظام', 'note' => $h->reason,
            'at' => $h->occurred_at?->format('Y-m-d H:i'),
        ])->values();

        return Inertia::render('ClientPortal/Brands/Show', [
            'clientName' => $r->attributes->get('activeClient')->display_name,
            'brand' => $this->row($b) + [
                'website' => $b->website, 'description' => $b->description,
                'toneOfVoice' => $b->tone_of_voice, 'targetAudience' => $b->target_audience,
                'preferredLanguage' => $b->preferred_language, 'changesReason' => $b->changes_reason,
            ],
            'history' => $history,
            'canManage' => $this->canManage($r),
            'editable' => in_array($b->status, ['draft', 'changes_requested'], true),
        ]);
    }

    public function store(Request $r, BrandWorkflowService $wf)
    {
        abort_unless($this->canManage($r), 403);
        $c = $r->attributes->get('activeClient');
        $data = $r->validate($this->rules());
        $b = $wf->createDraft($c->tenant_id, $c->id, $data, $r->user()->id);
        return redirect(MountPrefix::path($r, "/brands/{$b->id}"))->with('ok', 'أُنشئت مسودة العلامة.');
    }

    public function update(Request $r, int $brand, BrandWorkflowService $wf)
    {
        abort_unless($this->canManage($r), 403);
        try { $wf->updateDraft($this->brandOf($r, $brand), $r->validate($this->rules()), $r->user()->id); }
        catch (\RuntimeException $e) { return back()->withErrors(['form' => $e->getMessage()]); }
        return back()->with('ok', 'حُفظت التعديلات.');
    }

    public function submit(Request $r, int $brand, BrandWorkflowService $wf)
    {
        abort_unless($this->canManage($r), 403);
        try { $wf->submit($this->brandOf($r, $brand), $r->user()->id); }
        catch (\RuntimeException $e) { return back()->withErrors(['form' => $e->getMessage()]); }
        return back()->with('ok', 'أُرسلت العلامة للمراجعة.');
    }

    private function brandOf(Request $r, int $id): Brand
    {
        $c = $r->attributes->get('activeClient');
        $b = Brand::where('id', $id)->where('client_id', $c->id)->first();
        abort_unless($b, 404);
        return $b;
    }

    private function row(Brand $b): array
    {
        return [
            'id' => $b->id, 'name' => $b->name, 'sector' => $b->sector,
            'status' => $b->status, 'statusLabel' => __("statuses.{$b->status}"), 'statusTone' => __("statuses.tone.{$b->status}"),
        ];
    }
}

<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\CRM\Models\Brand;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * قائمة العلامات (React/Inertia) — موجّهة للاعتماد: طابور مراجعة + شرائح حالة.
 * Policy(viewAny على Brand)، معزولة بالمستأجر.
 */
class BrandsController extends Controller
{
    public function index(Request $r): Response
    {
        $this->authorize('viewAny', Brand::class);

        $q = Brand::query()->with('client')->latest();
        if ($s = trim((string) $r->query('q'))) {
            $q->where(fn ($w) => $w->where('name', 'ilike', "%{$s}%")
                ->orWhereHas('client', fn ($c) => $c->where('display_name', 'ilike', "%{$s}%")));
        }
        match ($r->query('seg')) {
            'needs_review' => $q->whereIn('status', ['submitted', 'under_review']),
            'draft', 'submitted', 'under_review', 'changes_requested', 'approved', 'suspended', 'archived' => $q->where('status', $r->query('seg')),
            default => null,
        };

        $brands = $q->paginate(15)->withQueryString();
        $brands->through(fn (Brand $b) => [
            'id' => $b->id,
            'name' => $b->name,
            'client' => $b->client?->display_name,
            'sector' => $b->sector,
            'version' => (int) $b->current_version,
            'status' => $b->status,
            'statusLabel' => __('statuses.' . $b->status),
            'statusTone' => __('statuses.tone.' . $b->status),
            'submittedAt' => $b->submitted_at?->format('Y-m-d'),
            'needsReview' => in_array($b->status, ['submitted', 'under_review'], true),
        ]);

        $count = fn (array|string $st) => Brand::query()->whereIn('status', (array) $st)->count();

        // العلامة تُنشأ من صفحة العميل لأنها تابعة له. هذه الصفحة كانت طريقًا
        // مسدودًا: حالة فارغة تَعِد بعلامات دون أن تقول من أين تُنشأ.
        $clientsCount = \App\Domain\CRM\Models\Client::query()->count();
        $soleClient = $clientsCount === 1 ? \App\Domain\CRM\Models\Client::query()->first() : null;

        return Inertia::render('Brands/Index', [
            'createHint' => [
                'clientsCount' => $clientsCount,
                // عميل واحد فقط ⇒ نأخذه مباشرةً إلى تبويب علاماته بلا اختيار وسيط
                'href' => $soleClient
                    ? \App\Support\Http\MountPrefix::path($r, "/clients/{$soleClient->id}?tab=brands")
                    : \App\Support\Http\MountPrefix::path($r, '/clients'),
                'label' => $soleClient ? "أضِف علامة لـ{$soleClient->display_name}" : 'اختر عميلًا',
            ],
            'brands' => $brands,
            'filters' => $r->only('q', 'seg'),
            'summary' => [
                'total' => Brand::query()->count(),
                'needs_review' => $count(['submitted', 'under_review']),
                'submitted' => $count('submitted'),
                'under_review' => $count('under_review'),
                'changes_requested' => $count('changes_requested'),
                'approved' => $count('approved'),
                'suspended' => $count('suspended'),
                'draft' => $count('draft'),
            ],
        ]);
    }
}

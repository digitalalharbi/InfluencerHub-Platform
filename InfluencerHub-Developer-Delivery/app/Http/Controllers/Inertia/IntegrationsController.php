<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\CRM\Models\Client;
use App\Http\Controllers\Controller;
use App\Support\Platforms\PlatformRegistry;
use Inertia\Inertia;
use Inertia\Response;

/**
 * التكاملات ومنصّات النشر (React/Inertia) — سجل قدرات صادق (لا تكامل وهمي).
 * الحالات صريحة (available_manual/draft/…)؛ راجع docs/EXTERNAL-BLOCKERS.md. عرض فقط.
 */
class IntegrationsController extends Controller
{
    private const STATUS = [
        'available_manual' => ['يدوي — متاح', 'submitted', 'إدخال البيانات يدويًا؛ لا جلب تلقائي من المنصّة.'],
        'available_import' => ['استيراد — متاح', 'approved', 'استيراد دفعات.'],
        'available_api' => ['API — متاح', 'active', 'تكامل API فعّال.'],
        'connected' => ['متصل', 'completed', 'اتصال حيّ.'],
        'sandbox' => ['تجريبي (Sandbox)', 'under_review', 'بيئة اختبار — ليست إنتاجًا.'],
        'waiting_for_credentials' => ['بانتظار بيانات اعتماد', 'changes_requested', 'يلزم مفاتيح API/اعتماد.'],
        'waiting_for_platform_approval' => ['بانتظار موافقة المنصّة', 'changes_requested', 'قيد مراجعة المنصّة.'],
        'configured' => ['مُهيّأ', 'submitted', 'مُهيّأ ولم يُفعّل.'],
        'draft' => ['قريبًا', 'draft', 'غير متاح بعد.'],
    ];
    private const CAP = [
        'creator_profile' => 'ملف المبدع', 'creator_application' => 'انضمام المبدعين',
        'ugc_creator_application' => 'انضمام صنّاع UGC', 'influencer_campaign' => 'حملات المؤثرين',
        'ugc_campaign' => 'حملات UGC', 'audience_data' => 'بيانات الجمهور',
        'content_publishing' => 'نشر المحتوى', 'publishing_verification' => 'التحقق من النشر',
    ];

    public function index(): Response
    {
        $this->authorize('viewAny', Client::class);
        $registry = PlatformRegistry::all();

        $platforms = [];
        foreach ($registry as $key => $p) {
            $status = $p['status'] ?? 'draft';
            [$label, $tone, $note] = self::STATUS[$status] ?? [$status, 'draft', ''];
            $platforms[] = [
                'key' => $key,
                'name' => $p['label_ar'] ?? $key,
                'nameEn' => $p['label_en'] ?? $key,
                'status' => $status,
                'statusLabel' => $label,
                'statusTone' => $tone,
                'statusNote' => $note,
                'available' => in_array($status, config('platforms.available_statuses', []), true),
                'capabilities' => collect($p['capabilities'] ?? [])->map(fn ($c) => self::CAP[$c] ?? $c)->values(),
                'capabilityKeys' => array_values($p['capabilities'] ?? []),
            ];
        }

        // مصفوفة تغطية القدرات — أي منصّة تدعم أي قدرة فعلًا (من السجل، لا افتراضات)
        $capKeys = collect($registry)->flatMap(fn ($p) => $p['capabilities'] ?? [])->unique()->values();
        $matrix = $capKeys->map(fn ($cap) => [
            'key' => $cap,
            'label' => self::CAP[$cap] ?? $cap,
            'platforms' => collect($platforms)->filter(fn ($p) => in_array($cap, $p['capabilityKeys'], true))->pluck('key')->values(),
            'count' => collect($platforms)->filter(fn ($p) => in_array($cap, $p['capabilityKeys'], true))->count(),
        ])->sortByDesc('count')->values();

        $available = collect($platforms)->where('available', true)->count();
        return Inertia::render('Integrations/Index', [
            'platforms' => $platforms,
            'summary' => ['total' => count($platforms), 'available' => $available, 'soon' => count($platforms) - $available],
            'matrix' => $matrix,
        ]);
    }
}

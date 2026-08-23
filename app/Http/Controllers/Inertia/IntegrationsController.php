<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\CRM\Models\Client;
use App\Domain\Integrations\Jobs\SyncProviderJob;
use App\Domain\Integrations\Models\{IntegrationConnection, IntegrationSyncRun};
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use App\Support\Platforms\PlatformRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * التكاملات ومنصّات النشر (React/Inertia) — سجل قدرات صادق (لا تكامل وهمي).
 * الحالات صريحة (available_manual/draft/…). عرض فقط.
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

    private const HEALTH_LABEL = ['healthy' => 'سليم', 'degraded' => 'متدهور', 'error' => 'خطأ', 'unknown' => 'غير معروف'];

    public function index(): Response
    {
        $this->authorize('viewAny', Client::class);
        $registry = PlatformRegistry::all();

        // اتّصالات المستأجر الفعلية لكل مزوّد (حالة/صحّة/آخر مزامنة) — تُدمَج مع السجلّ الثابت.
        $connections = IntegrationConnection::whereIn('provider', array_keys($registry))->get()->keyBy('provider');

        $platforms = [];
        foreach ($registry as $key => $p) {
            $status = $p['status'] ?? 'draft';
            [$label, $tone, $note] = self::STATUS[$status] ?? [$status, 'draft', ''];
            $conn = $connections->get($key);
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
                // حالة الاتّصال الفعلية (أو null إن لم يُهيّأ بعد — لا ادّعاء)
                'connection' => $conn ? $this->connectionDto($conn) : null,
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

    /** لوحة اتّصال مزوّد واحد + سجلّ مزامناته الفعلي. */
    public function show(Request $r, string $provider): Response
    {
        $this->authorize('viewAny', Client::class);
        $conn = IntegrationConnection::where('provider', $provider)->firstOrFail();
        $runs = IntegrationSyncRun::where('connection_id', $conn->id)->latest('id')->limit(30)->get()
            ->map(fn (IntegrationSyncRun $s) => [
                'id' => $s->id, 'type' => $s->type, 'status' => $s->status, 'capability' => $s->capability,
                'started' => $s->started_at?->format('Y-m-d H:i'), 'completed' => $s->completed_at?->format('Y-m-d H:i'),
                'durationSec' => ($s->started_at && $s->completed_at) ? $s->completed_at->diffInSeconds($s->started_at) : null,
                'fetched' => $s->fetched, 'created' => $s->created, 'updated' => $s->updated, 'failed' => $s->failed,
                'retry' => $s->retry_count, 'error' => $s->error,
            ]);

        return Inertia::render('Integrations/Show', [
            'connection' => $this->connectionDto($conn) + [
                'account' => $conn->external_account_name ?? $conn->external_account_id,
                'scopes' => $conn->scopes ?? [],
                'nextSync' => $conn->next_sync_at?->format('Y-m-d H:i'),
            ],
            'name' => PlatformRegistry::all()[$provider]['label_ar'] ?? $provider,
            'runs' => $runs,
            'canSync' => $conn->isConnected(),
        ]);
    }

    /** «زامن الآن» — يُدرِج وظيفة مزامنة بالطابور (لا مزامنة متزامنة في الطلب)، ويمنع التكرار. */
    public function syncNow(Request $r, string $provider): RedirectResponse
    {
        $this->authorize('create', Client::class); // إجراء إداري
        $conn = IntegrationConnection::where('provider', $provider)->firstOrFail();

        if (! $conn->isConnected()) {
            return back()->withErrors(['sync' => 'المزوّد غير موصول — لا يمكن المزامنة.']);
        }
        // منع تكرار مزامنة متزامنة
        $running = IntegrationSyncRun::where('connection_id', $conn->id)->whereIn('status', ['queued', 'running'])->exists();
        if ($running) {
            return back()->with('ok', 'مزامنة جارية بالفعل.');
        }

        SyncProviderJob::dispatch($conn->id, 'manual');
        $conn->update(['next_sync_at' => now()]);

        return back()->with('ok', 'بدأت المزامنة — ستظهر النتيجة في السجلّ.');
    }

    private function connectionDto(IntegrationConnection $conn): array
    {
        return [
            'status' => $conn->status,
            'environment' => $conn->environment,
            'health' => $conn->health,
            'healthLabel' => self::HEALTH_LABEL[$conn->health] ?? $conn->health,
            'lastSync' => $conn->last_success_sync_at?->format('Y-m-d H:i'),
            'lastAttempt' => $conn->last_attempt_sync_at?->format('Y-m-d H:i'),
            'lastError' => $conn->last_error,   // آمن، بلا أسرار
            'connected' => $conn->isConnected(),
        ];
    }
}

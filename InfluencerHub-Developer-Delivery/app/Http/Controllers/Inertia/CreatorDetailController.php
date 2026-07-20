<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Contracts\Models\Contract;
use App\Domain\Creators\Models\{Creator, CreatorInvitation};
use App\Domain\Creators\Services\CreatorInvitationService;
use App\Domain\Identity\Models\User;
use App\Domain\Finance\Models\Payout;
use App\Http\Controllers\Controller;
use App\Support\Analytics\CreatorAnalytics;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * تفاصيل المبدع (React/Inertia) — مساحة عمل ذكاء المبدع: درجة محسوبة آليًا + درجات فرعية +
 * تبويبات (المنصّات/الحملات/المحتوى/العقود/المستحقات). بيانات PostgreSQL حقيقية، Policy(view)، معزولة.
 */
class CreatorDetailController extends Controller
{
    private const CREATOR_STATUS = ['prospect' => ['مبدئي', 'submitted'], 'active' => ['نشط', 'active'], 'paused' => ['موقوف', 'paused'], 'blocked' => ['محظور', 'rejected']];
    private const SUB_LABEL = [
        'audience' => 'حجم الجمهور', 'engagement' => 'التفاعل', 'reliability' => 'الالتزام',
        'content_quality' => 'جودة المحتوى', 'commercial' => 'الأداء التجاري', 'profile' => 'اكتمال الملف', 'trust' => 'الموثوقية',
    ];

    public function show(Request $r, Creator $creator): Response
    {
        $this->authorize('view', $creator);
        $creator->load('platforms', 'services', 'capabilities');
        $intel = CreatorAnalytics::intelligence($creator);

        $collabs = Collaboration::where('creator_id', $creator->id)->with('campaign')->latest()->get()
            ->map(fn ($c) => [
                'id' => $c->id, 'title' => $c->title, 'campaign' => $c->campaign?->name,
                'status' => $c->status, 'statusLabel' => __('statuses.' . $c->status), 'statusTone' => __('statuses.tone.' . $c->status),
                'feeMinor' => (int) $c->fee_minor,
            ]);
        $content = ContentItem::where('creator_id', $creator->id)->latest()->get()
            ->map(fn ($c) => [
                'id' => $c->id, 'title' => $c->title, 'type' => $c->type, 'platform' => $c->platform,
                'mediaUrl' => $c->media_url, 'version' => (int) $c->version,
                'publishedAt' => $c->published_at?->format('Y-m-d'),
                'needsAction' => in_array($c->status, ['agency_review', 'client_review', 'changes_requested'], true),
                'status' => $c->status, 'statusLabel' => __('statuses.' . $c->status), 'statusTone' => __('statuses.tone.' . $c->status),
            ]);
        $contracts = Contract::where('creator_id', $creator->id)->latest()->get()
            ->map(fn ($c) => [
                'id' => $c->id, 'title' => $c->title, 'number' => $c->contract_number,
                'status' => $c->status, 'statusLabel' => __('statuses.' . $c->status), 'statusTone' => __('statuses.tone.' . $c->status),
                'valueMinor' => (int) $c->value_minor,
            ]);
        $payouts = Payout::where('creator_id', $creator->id)->latest()->get()
            ->map(fn ($p) => [
                'id' => $p->id, 'number' => $p->payout_number,
                'status' => $p->status, 'statusLabel' => __('statuses.' . $p->status), 'statusTone' => __('statuses.tone.' . $p->status),
                'amountMinor' => (int) $p->amount_minor,
            ]);

        [$stLabel, $stTone] = self::CREATOR_STATUS[$creator->status] ?? [$creator->status, 'draft'];
        $subscores = [];
        foreach (self::SUB_LABEL as $key => $label) {
            $subscores[] = ['key' => $key, 'label' => $label, 'value' => (int) ($intel['subscores'][$key] ?? 0)];
        }

        return Inertia::render('Creators/Show', [
            'creator' => [
                'id' => $creator->id,
                'name' => $creator->display_name,
                'handle' => $creator->handle,
                'number' => $creator->creator_number,
                'type' => $creator->type, // للتوافق فقط — العرض يعتمد capabilities
                'capabilities' => array_map(
                    fn (string $k) => \App\Domain\Creators\Models\CreatorCapability::label($k),
                    $creator->capabilityKeys(),
                ),
                'status' => $creator->status,
                'statusLabel' => $stLabel,
                'statusTone' => $stTone,
                'platform' => $creator->primary_platform,
                'followers' => (int) $creator->followers_count,
                'city' => $creator->city,
                'email' => $creator->email,
                'phone' => $creator->phone,
                'rateMinor' => $creator->rate_per_post_minor,
                'verified' => $creator->mowthooq_status === 'verified',
                'bio' => $creator->bio,
                'categories' => $creator->content_categories ?? [],
            ],
            'intel' => [
                'score' => $intel['score'],
                'tier' => $intel['tier'],
                'tierLabel' => $intel['tier'] === 'under_review' ? 'قيد المراجعة' : $intel['tier'],
                'risk' => $intel['risk'],
                'reasons' => $intel['reasons'],
                'metrics' => $intel['metrics'],
                'subscores' => $subscores,
            ],
            'platforms' => $creator->platforms->map(fn ($p) => [
                'platform' => $p->platform, 'handle' => $p->handle, 'followers' => (int) $p->followers_count,
            ])->values(),
            'collaborations' => $collabs,
            'content' => $content,
            'contracts' => $contracts,
            'payouts' => $payouts,
            'access' => $this->access($creator, $r),
        ]);
    }

    /**
     * حالة وصول صانع المحتوى إلى بوابته — سؤال واحد بجواب واحد.
     *
     * 165 من 168 سجلًّا بلا حساب، وكانت الصفحة لا تقول ذلك ولا تعرض سبيلًا
     * لإصلاحه. الحالة تُشتقّ هنا لا في الواجهة: الواجهة تعرض ما يُقال لها.
     */
    private function access(Creator $creator, Request $r): array
    {
        $inv = CreatorInvitation::where('creator_id', $creator->id)->latest('id')->first();
        $canInvite = $r->user()->can('update', $creator);

        // مرتبط بحساب = نشط، ولا دعوة تُرسَل بعده
        if ($creator->user_id) {
            $user = User::find($creator->user_id);

            return [
                'state' => 'active',
                'label' => 'البوابة نشطة',
                'tone' => 'approved',
                'email' => $user?->email ?? $creator->email,
                'phone' => $creator->phone,
                'canInvite' => false,
                'blockedReason' => 'الحساب مرتبط بالفعل — لا حاجة لدعوة.',
                'invitation' => null,
            ];
        }

        // لا بريد ⇒ لا دعوة. يُقال السبب والحلّ بدل زرّ يفشل عند الضغط.
        $missing = ! $creator->email && ! $inv;

        $base = [
            'email' => $creator->email,
            'phone' => $creator->phone,
            'canInvite' => $canInvite && ! $missing,
            'blockedReason' => $missing
                ? 'أضف بريد صانع المحتوى أوّلًا — الدعوة تُرسَل إليه.'
                : (! $canInvite ? 'لا تملك صلاحية دعوة صانع محتوى.' : null),
        ];

        if (! $inv || $inv->accepted_at) {
            return $base + ['state' => 'unlinked', 'label' => 'غير مرتبط', 'tone' => 'draft', 'invitation' => null];
        }

        [$state, $label, $tone] = match (true) {
            (bool) $inv->revoked_at => ['revoked', 'دعوة مُلغاة', 'rejected'],
            $inv->expires_at && $inv->expires_at->isPast() => ['expired', 'دعوة منتهية', 'rejected'],
            (bool) $inv->phone_verified_at => ['phone_verified', 'الجوال متحقّق — بانتظار كلمة المرور', 'submitted'],
            (bool) $inv->email_verified_at => ['email_verified', 'البريد متحقّق', 'submitted'],
            default => ['pending', 'دعوة معلّقة', 'submitted'],
        };

        return $base + [
            'state' => $state,
            'label' => $label,
            'tone' => $tone,
            'invitation' => [
                'id' => $inv->id,
                'email' => $inv->email,
                'phone' => $inv->phone,
                'expiresAt' => $inv->expires_at?->format('Y-m-d H:i'),
                'lastSentAt' => $inv->last_sent_at?->format('Y-m-d H:i'),
                'sentCount' => (int) $inv->sent_count,
                'maxSends' => CreatorInvitationService::MAX_SENDS,
                'emailVerified' => (bool) $inv->email_verified_at,
                'phoneVerified' => (bool) $inv->phone_verified_at,
                'canResend' => $canInvite && ! $inv->revoked_at && $inv->sent_count < CreatorInvitationService::MAX_SENDS,
                'canRevoke' => $canInvite && ! $inv->revoked_at,
            ],
        ];
    }
}

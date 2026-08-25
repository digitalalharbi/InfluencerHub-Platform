<?php

namespace Tests\Feature;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\CRM\Models\{Client, ClientMember};
use App\Domain\Creators\Models\Creator;
use App\Domain\Creators\Services\CreatorEntitlementService;
use App\Domain\Finance\Models\Payout;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Support\PlatformPreviewToken;
use App\Domain\Tenancy\Models\{Organization, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * تسرّب الخصوصية/المال أثناء معاينة مالك المنصّة (§P3-hardening §4). القيم الحسّاسة
 * **مزروعة فعلًا** في الحقول التي تُحمّلها الصفحات (لا اختبار ضعيف بحقل فارغ): تكلفة/أجر
 * المبدع وأتعابه وحسابه البنكيّ في جانب، وسعر بيع العميل في الجانب الآخر. نمرّ كل مسارات
 * البوّابة في المعاينة ونؤكّد ألّا يتسرّب أيّ سِنتينل للطرف الآخر، ولا أيّ سِرّ اعتماد.
 */
class PlatformPreviewPrivacyTest extends TestCase
{
    use RefreshDatabase;

    // سِنتينلات فريدة يقينيّة الوجود في القاعدة.
    private const CREATOR_FEE = 333444001;      // أجر/تكلفة المبدع (collaboration.fee_minor)
    private const CREATOR_PAYOUT = 777888003;   // مبلغ مستحقّ المبدع (payout.amount_minor)
    private const CLIENT_PRICE = 888999002;     // سعر بيع العميل (campaign.budget_minor)
    private const BANK_SENTINEL = 'SENTINEL-BANK-Z9Q7';   // بيانات بنكيّة خاصّة بالمبدع
    private const REMEMBER = 'SENTINEL-REMEMBER-TOKEN-Z9Q7';

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::reset();
    }

    private function owner(): User
    {
        $u = User::create(['name' => 'Owner', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $u->forceFill(['is_system_admin' => true, 'is_platform_owner' => true])->save();
        return $u;
    }

    private function token(User $owner, User $target, Tenant $t, string $portal, int $entity): string
    {
        return PlatformPreviewToken::issue((int) $owner->id, (int) $target->id, (int) $t->id, $portal, $entity, null, now()->timestamp);
    }

    public function test_no_cross_side_financial_or_credential_leakage_in_preview(): void
    {
        // بوّابة المبدع مفعّلة (القاعدة القانونية الوحيدة) — بلا سقالة خطة.
        $this->mock(CreatorEntitlementService::class, function ($m) {
            $m->shouldReceive('portalEligible')->andReturn(true);
        });

        $owner = $this->owner();
        $t = Tenant::create(['name' => 'T', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);

        [$clientUser, $client, $creatorUser, $creator] = TenantContext::withBypass(function () use ($t) {
            $org = Organization::create(['tenant_id' => $t->id, 'name' => 'Org', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);

            $clientUser = User::create(['name' => 'Client User', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('client-secret-xyz'), 'is_active' => true]);
            $clientUser->forceFill(['remember_token' => self::REMEMBER])->save();
            $client = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-1', 'display_name' => 'Client One', 'status' => 'active']);
            ClientMember::create(['tenant_id' => $t->id, 'client_id' => $client->id, 'user_id' => $clientUser->id, 'role' => 'client_admin', 'status' => 'active', 'accepted_at' => now()]);

            $creatorUser = User::create(['name' => 'Creator User', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('creator-secret-xyz'), 'is_active' => true]);
            $creatorUser->forceFill(['remember_token' => self::REMEMBER])->save();
            $creator = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-1', 'type' => 'influencer', 'display_name' => 'Creator One',
                'status' => 'active', 'user_id' => $creatorUser->id, 'bank_name' => self::BANK_SENTINEL]);

            // سعر بيع العميل = ميزانية الحملة (يراها العميل، لا المبدع).
            $campaign = Campaign::create(['tenant_id' => $t->id, 'campaign_number' => 'CM-1', 'client_id' => $client->id,
                'name' => 'Summer', 'status' => 'active', 'budget_minor' => self::CLIENT_PRICE, 'currency' => 'SAR']);

            // أجر/تكلفة المبدع (يراها المبدع، لا العميل).
            Collaboration::create(['tenant_id' => $t->id, 'collaboration_number' => 'CO-1', 'creator_id' => $creator->id,
                'campaign_id' => $campaign->id, 'client_id' => $client->id, 'title' => 'Deliver', 'fee_minor' => self::CREATOR_FEE, 'status' => 'accepted']);

            // مستحقّ المبدع (يراه المبدع، لا العميل).
            Payout::create(['tenant_id' => $t->id, 'payout_number' => 'PO-1', 'creator_id' => $creator->id, 'campaign_id' => $campaign->id,
                'amount_minor' => self::CREATOR_PAYOUT, 'currency' => 'SAR', 'status' => 'pending', 'iban_last4' => '4242']);

            return [$clientUser, $client, $creatorUser, $creator];
        });

        $creatorHash = User::withoutGlobalScopes()->find($creatorUser->id)->password;
        $clientHash = User::withoutGlobalScopes()->find($clientUser->id)->password;

        // ── معاينة العميل: لا تكلفة/أجر/مستحقّ/بنك المبدع، ولا أسرار اعتماد ────────
        $clientToken = $this->token($owner, $clientUser, $t, 'client', $client->id);
        $clientForbidden = [
            (string) self::CREATOR_FEE, (string) self::CREATOR_PAYOUT, self::BANK_SENTINEL,
            $creatorHash, self::REMEMBER,
        ];
        foreach (['/client', '/client/campaigns', "/client/campaigns/{$this->campaignId($t)}", '/client/contracts', '/client/account', '/client/notifications'] as $path) {
            $body = $this->actingAs($owner)->get("{$path}?_pv={$clientToken}")->getContent();
            foreach ($clientForbidden as $needle) {
                $this->assertStringNotContainsString($needle, $body, "تسرّب في معاينة العميل عبر {$path}: {$needle}");
            }
        }

        // ── معاينة المبدع: لا سعر بيع العميل، ولا أسرار اعتماد ─────────────────────
        $creatorToken = $this->token($owner, $creatorUser, $t, 'creator', $creator->id);
        $creatorForbidden = [(string) self::CLIENT_PRICE, $clientHash, self::REMEMBER];
        foreach (['/creator', '/creator/payouts', '/creator/collaborations', '/creator/content', '/creator/account', '/creator/notifications'] as $path) {
            $body = $this->actingAs($owner)->get("{$path}?_pv={$creatorToken}")->getContent();
            foreach ($creatorForbidden as $needle) {
                $this->assertStringNotContainsString($needle, $body, "تسرّب في معاينة المبدع عبر {$path}: {$needle}");
            }
        }

        // حصانة إيجابية: المبدع يرى مستحقّه فعلًا في معاينته (يثبت أن السِنتينل مزروع
        // وقابل للظهور — فغيابُه في معاينة العميل نتيجةٌ حقيقيّة لا حقلٌ فارغ).
        $payoutBody = $this->actingAs($owner)->get("/creator/payouts?_pv={$creatorToken}")->getContent();
        $this->assertStringContainsString((string) self::CREATOR_PAYOUT, $payoutBody);
    }

    private function campaignId(Tenant $t): int
    {
        return (int) TenantContext::withBypass(fn () => Campaign::where('tenant_id', $t->id)->value('id'));
    }
}

<?php

namespace Tests\Feature;

use App\Domain\Communications\Models\Notification;
use App\Domain\Contracts\Models\Contract;
use App\Domain\Contracts\Services\ContractSignatureReminderService;
use App\Domain\Creators\Models\Creator;
use App\Domain\CRM\Models\Client;
use App\Domain\CRM\Models\ClientMember;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * تذكير توقيع العقد: تذكير مرّة واحدة بعد مضيّ المهلة بلا توقيع (للمبدع أو العميل بحسب
 * الطرف)، بلا تكرار عند إعادة المسح، ولا تذكير لعقد حديث أو مُوقَّع. مبنيّ على sent_at.
 */
class ContractSignatureReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    /** @return array{0:Tenant,1:User} */
    private function world(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        $owner = User::create(['name' => 'منسّق', 'email' => Str::random(6).'@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);

        return [$t, $owner];
    }

    private function creatorContract(Tenant $t, User $owner, string $status, ?string $sentAt, ?string $signedAt = null): Contract
    {
        return TenantContext::withBypass(function () use ($t, $owner, $status, $sentAt, $signedAt) {
            $cu = User::create(['name' => 'مؤثر', 'email' => Str::random(6).'@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
            $creator = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-'.Str::random(4), 'type' => 'influencer', 'display_name' => 'نجم', 'status' => 'active', 'user_id' => $cu->id]);

            return Contract::create([
                'tenant_id' => $t->id, 'contract_number' => 'CO-'.Str::random(4), 'party_type' => 'creator',
                'creator_id' => $creator->id, 'title' => 'عقد تعاون', 'status' => $status,
                'sent_at' => $sentAt, 'signed_at' => $signedAt, 'created_by' => $owner->id,
            ]);
        });
    }

    private function reminderNotifs(int $tenantId): int
    {
        return TenantContext::withBypass(fn () => Notification::where('tenant_id', $tenantId)->where('type', 'contract.signature_reminder')->count());
    }

    public function test_creator_contract_pending_beyond_window_reminds_once(): void
    {
        [$t, $owner] = $this->world();
        $c = $this->creatorContract($t, $owner, 'sent', now()->subHours(80)->toDateTimeString());

        $r = app(ContractSignatureReminderService::class)->scan();

        $this->assertSame(1, $r['notified']);
        // المبدع + صاحب العقد
        $this->assertSame(2, $this->reminderNotifs($t->id));
        $this->assertNotNull($c->fresh()->pending_reminded_at, 'العلامة تُضبط (منع التكرار)');
    }

    public function test_client_contract_notifies_client_members(): void
    {
        [$t, $owner] = $this->world();
        TenantContext::withBypass(function () use ($t, $owner) {
            $clUser = User::create(['name' => 'عميل', 'email' => Str::random(6).'@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
            $cl = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-'.$t->id, 'display_name' => 'شركة نماء', 'type' => 'company', 'status' => 'active']);
            ClientMember::create(['tenant_id' => $t->id, 'client_id' => $cl->id, 'user_id' => $clUser->id, 'role' => 'client_admin', 'status' => 'active', 'accepted_at' => now()]);
            Contract::create([
                'tenant_id' => $t->id, 'contract_number' => 'CO-'.Str::random(4), 'party_type' => 'client',
                'client_id' => $cl->id, 'title' => 'عقد عميل', 'status' => 'sent',
                'sent_at' => now()->subHours(80)->toDateTimeString(), 'created_by' => $owner->id,
            ]);
        });

        $r = app(ContractSignatureReminderService::class)->scan();

        $this->assertSame(1, $r['notified']);
        // عضو العميل + صاحب العقد
        $this->assertSame(2, $this->reminderNotifs($t->id));
    }

    public function test_rescan_is_idempotent_no_duplicate(): void
    {
        [$t, $owner] = $this->world();
        $this->creatorContract($t, $owner, 'sent', now()->subHours(80)->toDateTimeString());

        app(ContractSignatureReminderService::class)->scan();
        $after1 = $this->reminderNotifs($t->id);
        $r2 = app(ContractSignatureReminderService::class)->scan();
        $after2 = $this->reminderNotifs($t->id);

        $this->assertSame(0, $r2['notified'], 'إعادة المسح لا تُنتج تذكيرًا ثانيًا');
        $this->assertSame($after1, $after2, 'لا إشعارات مكرّرة');
    }

    public function test_recent_contract_within_window_is_not_reminded(): void
    {
        [$t, $owner] = $this->world();
        $this->creatorContract($t, $owner, 'sent', now()->subHours(10)->toDateTimeString());

        $r = app(ContractSignatureReminderService::class)->scan();

        $this->assertSame(0, $r['notified']);
        $this->assertSame(0, $this->reminderNotifs($t->id));
    }

    public function test_signed_contract_is_not_reminded_even_if_old(): void
    {
        [$t, $owner] = $this->world();
        $this->creatorContract($t, $owner, 'signed', now()->subDays(5)->toDateTimeString(), now()->subDays(4)->toDateTimeString());

        $r = app(ContractSignatureReminderService::class)->scan();

        $this->assertSame(0, $r['notified']);
        $this->assertSame(0, $this->reminderNotifs($t->id));
    }
}

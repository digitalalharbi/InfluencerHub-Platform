<?php
namespace App\Domain\CRM\Services;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\CRM\Models\{Client, ClientProfileChangeRequest, ClientProfileStatusHistory};
use App\Domain\CRM\Support\ClientPortalAbilities as A;
use App\Domain\Tenancy\Support\TenantContext;
use RuntimeException;

class ClientProfileService {
    /** حقول قابلة للتعديل المباشر من client_admin. */
    private const DIRECT = ['display_name', 'sector', 'website', 'email', 'phone', 'whatsapp', 'country_code', 'city', 'address', 'preferred_language', 'billing_email', 'billing_contact_name', 'billing_contact_phone'];

    /**
     * يطبّق تعديلات الملف: المباشر فورًا، والحساس عبر طلب مراجعة.
     * يعيد رسالة توضيحية. يرمي إن لم يملك صلاحية.
     */
    public function applyProfileUpdate(Client $client, array $input, string $role, int $actorId): string {
        if (! A::can($role, A::EDIT_PROFILE)) throw new RuntimeException('لا تملك صلاحية تعديل ملف العميل.');
        $msg = TenantContext::withTenant($client->tenant_id, function () use ($actorId, $client, $input) {
    
            // 1) الحقول المباشرة
            $direct = array_intersect_key($input, array_flip(self::DIRECT));
            $direct = array_diff_key($direct, array_flip(A::FORBIDDEN)); // حماية إضافية
            if ($direct) {
                $client->update($direct);
                AuditLogger::log('client_profile.updated', $client, array_keys($direct), $client->tenant_id, $actorId);
            }
    
            // 2) الحقول الحساسة → طلب مراجعة (لا تُطبَّق مباشرة)
            $changes = [];
            foreach (A::SENSITIVE as $f) {
                if (array_key_exists($f, $input) && (string) $input[$f] !== (string) $client->$f) {
                    $changes[$f] = ['old' => $client->$f, 'new' => $input[$f]];
                }
            }
            $msg = $direct ? 'حُفظت التعديلات.' : '';
            if ($changes) {
                $cr = ClientProfileChangeRequest::create(['tenant_id' => $client->tenant_id, 'client_id' => $client->id,
                    'requested_by' => $actorId, 'changes' => $changes, 'status' => 'submitted']);
                ClientProfileStatusHistory::create(['tenant_id' => $client->tenant_id, 'change_request_id' => $cr->id,
                    'from_status' => null, 'to_status' => 'submitted', 'actor_id' => $actorId, 'reason' => 'طلب تعديل بيانات قانونية', 'occurred_at' => now()]);
                AuditLogger::log('client_profile.change_requested', $cr, array_keys($changes), $client->tenant_id, $actorId);
                $msg = trim($msg . ' التعديلات القانونية أُرسلت لمراجعة الوكالة.');
            }
            return $msg;
        });
        return $msg ?: 'لا تغييرات.';
    }

    /** اعتماد طلب تعديل (من الوكالة) — يطبّق التغييرات ويسجّل. */
    public function approveChangeRequest(ClientProfileChangeRequest $cr, int $reviewerId, string $decision, ?string $note = null): void {
        TenantContext::withTenant($cr->tenant_id, function () use ($cr, $decision, $note, $reviewerId) {
            $client = Client::findOrFail($cr->client_id);
            $from = $cr->status;
            if ($decision === 'approved') {
                $apply = [];
                foreach ($cr->changes as $f => $vals) { $apply[$f] = $vals['new']; }
                $client->update($apply);
            }
            $cr->update(['status' => $decision, 'reviewer_note' => $note, 'reviewed_by' => $reviewerId, 'reviewed_at' => now()]);
            ClientProfileStatusHistory::create(['tenant_id' => $cr->tenant_id, 'change_request_id' => $cr->id,
                'from_status' => $from, 'to_status' => $decision, 'actor_id' => $reviewerId, 'reason' => $note, 'occurred_at' => now()]);
            AuditLogger::log("client_profile.$decision", $cr, [], $cr->tenant_id, $reviewerId);
        });
    }
}

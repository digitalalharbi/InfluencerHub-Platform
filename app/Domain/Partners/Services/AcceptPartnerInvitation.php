<?php

namespace App\Domain\Partners\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Partners\Models\{ExternalAgency, ExternalAgencyInvitation, ExternalAgencyMember};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\{DB, Hash};
use RuntimeException;

/**
 * قبول دعوة شريك (تدفّق عام مُحصّن): البحث بالـ hash فقط، تحقّق صلاحية،
 * إنشاء/إرفاق حساب المستخدم بالبريد المدعو، تفعيل العضوية — كله في معاملة واحدة.
 */
class AcceptPartnerInvitation
{
    /** يعيد الدعوة إن كانت صالحة (لعرض النموذج)، وإلا يرمي. */
    public function resolve(string $rawToken): ExternalAgencyInvitation
    {
        return TenantContext::withBypass(function () use ($rawToken) {
            $inv = ExternalAgencyInvitation::where('token_hash', hash('sha256', $rawToken))->first();
            if (! $inv || ! $inv->isPending()) {
                throw new RuntimeException('الدعوة غير صالحة (منتهية أو مستخدمة أو ملغاة).');
            }
            // الوكالة يجب أن تكون معتمدة لقبول الأعضاء
            $agency = ExternalAgency::withoutGlobalScopes()->find($inv->external_agency_id);
            if (! $agency || $agency->status !== 'approved') {
                throw new RuntimeException('الوكالة الشريكة غير معتمدة حاليًا.');
            }
            return $inv;
        });
    }

    /**
     * يقبل الدعوة: يُنشئ حساب المستخدم بالبريد المدعو (إن لم يوجد) بكلمة المرور المُختارة،
     * ثم يُفعّل عضوية الشريك. يعيد [user, member]. يرمي إن كان البريد مملوكًا لحساب آخر موجود.
     */
    public function accept(string $rawToken, string $name, string $password): array
    {
        return DB::transaction(function () use ($rawToken, $name, $password) {
            return TenantContext::withBypass(function () use ($rawToken, $name, $password) {
                $inv = ExternalAgencyInvitation::where('token_hash', hash('sha256', $rawToken))->lockForUpdate()->first();
                if (! $inv || ! $inv->isPending()) {
                    throw new RuntimeException('الدعوة غير صالحة (منتهية أو مستخدمة أو ملغاة).');
                }
                $agency = ExternalAgency::withoutGlobalScopes()->find($inv->external_agency_id);
                if (! $agency || $agency->status !== 'approved') {
                    throw new RuntimeException('الوكالة الشريكة غير معتمدة حاليًا.');
                }

                // حساب المستخدم: إن وُجد بريد مطابق مسبقًا، لا نغيّر كلمته — نرفقه فقط (يجب أن يسجّل دخوله)
                $existing = User::where('email', $inv->email)->first();
                if ($existing) {
                    throw new RuntimeException('لهذا البريد حساب بالفعل. سجّل الدخول ثم اقبل الدعوة من داخل حسابك.');
                }
                $user = User::create([
                    'name' => $name, 'email' => $inv->email, 'password' => Hash::make($password), 'is_active' => true,
                ]);

                $member = ExternalAgencyMember::firstOrCreate(
                    ['external_agency_id' => $inv->external_agency_id, 'user_id' => $user->id],
                    ['tenant_id' => $inv->tenant_id, 'role' => $inv->role, 'status' => 'active', 'invited_by' => $inv->invited_by, 'accepted_at' => now()]
                );
                if ($member->status !== 'active') { $member->update(['status' => 'active', 'accepted_at' => now()]); }
                $inv->update(['accepted_at' => now()]);
                AuditLogger::log('external_agency_member.accepted', $member, [], $inv->tenant_id, $user->id);

                return [$user, $member];
            });
        });
    }
}

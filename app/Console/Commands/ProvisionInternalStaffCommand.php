<?php

namespace App\Console\Commands;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Creators\Models\Creator;
use App\Domain\CRM\Models\ClientMember;
use App\Domain\Identity\Models\User;
use App\Domain\Partners\Models\ExternalAgencyMember;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Models\OrganizationMembership;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * توفير حسابات التشغيل الداخلية الحقيقية لـInfluencerHub بأمان (§18/§19).
 *
 * مبادئ الأمان:
 *  - قائمة بيضاء ثابتة من 7 حسابات @influencerhub.io فقط؛ لا يُمسّ أي مستخدم آخر إطلاقًا.
 *  - كلمة مرور عشوائية قوية «لكل حساب»، تُطبع «مرّة واحدة» على طرفية المشغّل، ولا تُكتب
 *    في ملف ولا سجلّ ولا تدقيق ولا Git. الحساب القائم لا تُغيَّر كلمته إلا بـ--rotate صراحةً.
 *  - لا يُرقّى مستخدم مرتبط بمستأجر إلى حساب منصّة (owner/admin) — يُرفض بوضوح.
 *  - الأدوار المرتبطة بمستأجر (operations/campaigns/creators/finance/content) تتطلّب
 *    --tenant=<slug> لتحديد مستأجر وكالة InfluencerHub صراحةً (قرار طوبولوجيا للمشغّل).
 *  - --inventory: جرد وتصنيف للقراءة فقط، بلا أي تعديل.
 *  - في الإنتاج يلزم تأكيد (--yes أو تفاعليّ) قبل أي كتابة.
 */
class ProvisionInternalStaffCommand extends Command
{
    protected $signature = 'identity:provision-staff
        {--tenant= : slug مستأجر وكالة InfluencerHub للأدوار المرتبطة بمستأجر}
        {--inventory : جرد وتصنيف المستخدمين فقط (لا تعديل)}
        {--rotate= : تدوير كلمة مرور حساب داخلي واحد (بالبريد) — الحساب يجب أن يكون ضمن القائمة البيضاء}
        {--yes : تأكيد الكتابة في الإنتاج بلا سؤال}';

    protected $description = 'ينشئ/يطبّع حسابات التشغيل الداخلية (@influencerhub.io) بكلمات مرور عشوائية تُطبع مرّة واحدة. لا يمسّ حسابات العملاء/المبدعين الحقيقيين.';

    /** القائمة البيضاء الوحيدة — لا يُنشأ/يُعدّل أي بريد خارجها. */
    private const STAFF = [
        ['email' => 'owner@influencerhub.io',      'name' => 'Platform Owner',    'kind' => 'platform_owner'],
        ['email' => 'admin@influencerhub.io',      'name' => 'System Admin',       'kind' => 'system_admin'],
        ['email' => 'operations@influencerhub.io', 'name' => 'Operations Manager', 'kind' => 'agency', 'role' => 'operations_manager'],
        ['email' => 'campaigns@influencerhub.io',  'name' => 'Campaigns Manager',  'kind' => 'agency', 'role' => 'campaign_manager'],
        ['email' => 'creators@influencerhub.io',   'name' => 'Creators Manager',   'kind' => 'agency', 'role' => 'creator_manager'],
        ['email' => 'finance@influencerhub.io',    'name' => 'Finance',            'kind' => 'agency', 'role' => 'finance'],
        ['email' => 'content@influencerhub.io',    'name' => 'Content Reviewer',   'kind' => 'agency', 'role' => 'content_reviewer'],
    ];

    public function handle(): int
    {
        if ($this->option('inventory')) {
            return $this->inventory();
        }
        if ($rotate = trim((string) $this->option('rotate'))) {
            return $this->rotateOne($rotate);
        }

        return $this->provision();
    }

    /** جرد وتصنيف كل المستخدمين — للقراءة فقط. */
    private function inventory(): int
    {
        $rows = [];
        $tally = ['REAL_INTERNAL' => 0, 'REAL_EXTERNAL' => 0, 'TEST_OR_SHOWCASE' => 0, 'UNKNOWN' => 0];
        TenantContext::withBypass(function () use (&$rows, &$tally) {
            foreach (User::query()->orderBy('id')->get() as $u) {
                $class = $this->classify($u);
                $tally[$class]++;
                $rows[] = [$u->id, $u->email, $class, $this->linksLabel($u->id) ?: '—',
                    ($u->is_platform_owner ? 'owner ' : '').($u->is_system_admin ? 'sysadmin' : '')];
            }
        });
        $this->table(['id', 'email', 'classification', 'tenant links', 'platform flags'], $rows);
        $this->line('');
        foreach ($tally as $k => $n) {
            $this->line("  {$k}: {$n}");
        }
        $this->warn('جرد للقراءة فقط — لم يُعدَّل أي سجلّ. لا يُحذف أي حساب آليًّا.');

        return self::SUCCESS;
    }

    private function classify(User $u): string
    {
        $email = strtolower((string) $u->email);
        if (preg_match('/(\.test$|@.*\.test|showcase|@demo\.|\bdemo\b|e2e|preview)/', $email)) {
            return 'TEST_OR_SHOWCASE';
        }
        if (str_ends_with($email, '@influencerhub.io')) {
            return 'REAL_INTERNAL';
        }
        if ($this->linksLabel($u->id) !== '') {
            return 'REAL_EXTERNAL'; // عميل/مبدع/شريك حقيقي — لا يُمسّ إطلاقًا
        }

        return 'UNKNOWN';
    }

    private function linksLabel(int $userId): string
    {
        $l = [];
        if (ClientMember::withoutGlobalScopes()->where('user_id', $userId)->exists()) {
            $l[] = 'client';
        }
        if (Creator::withoutGlobalScopes()->where('user_id', $userId)->exists()) {
            $l[] = 'creator';
        }
        if (ExternalAgencyMember::withoutGlobalScopes()->where('user_id', $userId)->exists()) {
            $l[] = 'partner';
        }
        if (OrganizationMembership::withoutGlobalScopes()->where('user_id', $userId)->exists()) {
            $l[] = 'agency';
        }

        return implode('+', $l);
    }

    private function provision(): int
    {
        if (! $this->confirmProduction()) {
            return self::FAILURE;
        }
        $tenantSlug = trim((string) $this->option('tenant'));
        [$tenant, $org] = $tenantSlug ? $this->resolveAgencyOrg($tenantSlug) : [null, null];
        if ($tenantSlug && ! $org) {
            return self::FAILURE;
        }
        if (! $tenantSlug) {
            $this->warn('بلا --tenant: سيُوفَّر حسابا المنصّة (owner/admin) فقط. الأدوار المرتبطة بمستأجر تُتخطّى — أعِد التشغيل بـ--tenant=<slug>.');
        }

        $created = [];
        foreach (self::STAFF as $spec) {
            if ($spec['kind'] === 'agency' && ! $org) {
                continue; // بلا مستأجر لا تُوفَّر أدوار الوكالة
            }
            $result = TenantContext::withBypass(fn () => $this->upsertOne($spec, $tenant, $org));
            if ($result !== null) {
                $created[] = $result; // [email, password] لحساب أُنشئ حديثًا فقط
            }
        }

        $this->line('');
        if ($created === []) {
            $this->info('لا حسابات جديدة — الحسابات الداخلية موجودة (كلمات المرور دون تغيير). لتدوير واحدة: --rotate=<email>.');

            return self::SUCCESS;
        }
        $this->warn('كلمات المرور التالية تُعرض «مرّة واحدة» — انسخها الآن. لا تُخزَّن في أي مكان.');
        foreach ($created as [$email, $pw]) {
            $this->line("  {$email}    {$pw}");
        }

        return self::SUCCESS;
    }

    /** ينشئ أو يطبّع حسابًا واحدًا. يعيد [email,password] عند الإنشاء الجديد فقط، وإلا null. */
    private function upsertOne(array $spec, ?Tenant $tenant, ?Organization $org): ?array
    {
        $existing = User::withoutGlobalScopes()->where('email', $spec['email'])->first();

        // حماية حسابات المنصّة: لا تُرقّى فوق مستخدم مرتبط بمستأجر.
        if ($existing && in_array($spec['kind'], ['platform_owner', 'system_admin'], true)) {
            $links = $this->linksLabel($existing->id);
            if ($links !== '') {
                $this->error("تخطٍّ: {$spec['email']} مرتبط بمستأجر ({$links}) — لا يُرقّى لحساب منصّة. عالِجه يدويًّا.");

                return null;
            }
        }

        $new = $existing === null;
        $user = $existing ?? new User(['email' => $spec['email'], 'name' => $spec['name'], 'locale' => 'ar']);
        $password = null;
        if ($new) {
            $password = Str::password(20);
            $user->password = $password; // cast=hashed
        }
        $user->is_active = true;
        if ($spec['kind'] === 'platform_owner') {
            $user->forceFill(['is_system_admin' => true, 'is_platform_owner' => true]);
        } elseif ($spec['kind'] === 'system_admin') {
            $user->forceFill(['is_system_admin' => true]);
        }
        $user->save();

        if ($spec['kind'] === 'agency' && $org && $tenant) {
            OrganizationMembership::updateOrCreate(
                ['tenant_id' => $tenant->id, 'organization_id' => $org->id, 'user_id' => $user->id],
                ['role' => $spec['role'], 'status' => 'active']
            );
        }

        AuditLogger::log($new ? 'staff.provisioned' : 'staff.normalized', $user,
            ['kind' => $spec['kind'], 'role' => $spec['role'] ?? null], $tenant?->id, $user->id);
        $this->line(($new ? '✚ أُنشئ ' : '• طُبِّع ').$spec['email'].' ('.($spec['role'] ?? $spec['kind']).')');

        return $new ? [$spec['email'], $password] : null;
    }

    private function rotateOne(string $email): int
    {
        $email = strtolower(trim($email));
        if (! collect(self::STAFF)->contains(fn ($s) => $s['email'] === $email)) {
            $this->error('التدوير مسموح لحسابات القائمة البيضاء الداخلية فقط.');

            return self::FAILURE;
        }
        if (! $this->confirmProduction()) {
            return self::FAILURE;
        }
        $pw = TenantContext::withBypass(function () use ($email) {
            $u = User::withoutGlobalScopes()->where('email', $email)->first();
            if (! $u) {
                return null;
            }
            $p = Str::password(20);
            $u->password = $p;
            $u->save();
            AuditLogger::log('staff.password_rotated', $u, [], null, $u->id);

            return $p;
        });
        if ($pw === null) {
            $this->error("الحساب غير موجود: {$email} — وفّره أوّلًا.");

            return self::FAILURE;
        }
        $this->warn('كلمة المرور الجديدة (مرّة واحدة):');
        $this->line("  {$email}    {$pw}");

        return self::SUCCESS;
    }

    /** يستأنس بوجود مستأجر وكالة نشِط ومنظّمة وكالة تحته. */
    private function resolveAgencyOrg(string $slug): array
    {
        return TenantContext::withBypass(function () use ($slug) {
            $tenant = Tenant::where('slug', $slug)->first();
            if (! $tenant) {
                $this->error("مستأجر بالمعرّف «{$slug}» غير موجود.");

                return [null, null];
            }
            $org = Organization::where('tenant_id', $tenant->id)->where('type', 'agency')->orderBy('id')->first();
            if (! $org) {
                $this->error("لا توجد منظّمة وكالة (type=agency) تحت المستأجر «{$slug}».");

                return [$tenant, null];
            }

            return [$tenant, $org];
        });
    }

    private function confirmProduction(): bool
    {
        if (! app()->environment('production') || $this->option('yes')) {
            return true;
        }
        if (! $this->input->isInteractive()) {
            $this->error('في الإنتاج: أضِف --yes للتأكيد (أو شغّل تفاعليًّا).');

            return false;
        }

        return $this->confirm('أنت في الإنتاج. متابعة توفير الحسابات الداخلية؟');
    }
}

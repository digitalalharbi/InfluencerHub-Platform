<?php

namespace App\Console\Commands;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use Illuminate\Console\Command;

/**
 * أمر QA ضيّق النطاق: يدوّر **كلمة مرور مدير بيئة العرض فقط** (showcase_admin@showcase.test)
 * لتمكين فحص الدخان المُصادَق على الإنتاج. آمن في الإنتاج بحكم تقييده الصارم:
 *
 *  - لا ينشئ/يحذف/يعيد بذر أو تهيئة أي سجلّ. لا يستدعي ShowcaseBuilder إطلاقًا.
 *  - التغيير الوحيد المسموح: حقل password لهذا المستخدم بالذات.
 *  - مربوط بالهوية الثابتة لبيئة العرض (لا يقبل بريدًا/مستأجرًا من المُدخلات).
 *  - يرفض التنفيذ ما لم تتحقّق كل الثوابت + وجود --force + سرّ التشغيل.
 *
 * كلمة المرور الجديدة تُقرأ من متغيّر البيئة PRODUCTION_SMOKE_PASSWORD (يُمرَّر لكل تشغيل)،
 * ولا تُطبع ولا تُسجَّل ولا تُكتب في أي ملفّ أو سياق تدقيق.
 */
class RotateSmokeCredentialCommand extends Command
{
    protected $signature = 'preview:rotate-smoke-credential {--force : تأكيد صريح مطلوب}';

    protected $description = 'يدوّر كلمة مرور مدير بيئة العرض فقط (Showcase) لفحص الدخان المُصادَق — لا يمسّ أي بيانات أخرى.';

    /** الهوية المُصرَّح بها حصريًّا — لا تأتي من المُدخلات أبدًا. */
    private const TENANT_SLUG = 'showcase';
    private const ADMIN_EMAIL = 'showcase_admin@showcase.test';
    private const ADMIN_ROLE = 'agency_admin';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('يتطلّب --force صراحةً.');
            return self::FAILURE;
        }

        // السرّ يُقرأ من بيئة العملية مباشرةً (getenv) فلا يتأثّر بتخزين config المؤقّت.
        $newPassword = (string) (getenv('PRODUCTION_SMOKE_PASSWORD') ?: '');
        if (strlen($newPassword) < 32) {
            $this->error('PRODUCTION_SMOKE_PASSWORD غير مضبوط أو أقصر من 32 محرفًا.');
            return self::FAILURE;
        }

        // كل الاستعلامات بلا نطاق مستأجر (CLI بلا TenantContext) — قراءة صريحة ومقيّدة.
        $tenant = Tenant::withoutGlobalScopes()->where('slug', self::TENANT_SLUG)->first();
        if (! $tenant) {
            $this->error('مستأجر بيئة العرض (slug=showcase) غير موجود.');
            return self::FAILURE;
        }

        $user = User::withoutGlobalScopes()->where('email', self::ADMIN_EMAIL)->first();
        if (! $user) {
            $this->error('مدير بيئة العرض غير موجود.');
            return self::FAILURE;
        }

        $membership = OrganizationMembership::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('role', self::ADMIN_ROLE)
            ->where('status', 'active')
            ->first();
        if (! $membership) {
            $this->error('عضوية مدير بيئة العرض غير صالحة (المستأجر/الدور/الحالة).');
            return self::FAILURE;
        }

        // العضوية يجب أن تكون في منظّمة تتبع مستأجر بيئة العرض بالذات.
        $org = Organization::withoutGlobalScopes()
            ->where('id', $membership->organization_id)
            ->where('tenant_id', $tenant->id)
            ->first();
        if (! $org) {
            $this->error('منظّمة العضوية لا تتبع مستأجر بيئة العرض.');
            return self::FAILURE;
        }

        // التغيير الوحيد المسموح: كلمة مرور هذا المستخدم (cast=hashed يطبّق تجزئة Laravel).
        $user->password = $newPassword;
        $user->save();

        $this->info('دُوّرت كلمة مرور مدير بيئة العرض بنجاح.');

        return self::SUCCESS;
    }
}

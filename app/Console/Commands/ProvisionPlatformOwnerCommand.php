<?php

namespace App\Console\Commands;

use App\Domain\Identity\Models\User;
use Illuminate\Console\Command;

/**
 * إنشاء/ترقية حساب «مالك المنصّة» بأمان (§18) — لا كلمة مرور في الكود ولا Seeder
 * ولا مستودع Git. كلمة المرور تُقرأ من:
 *   1) متغيّر البيئة PLATFORM_OWNER_PASSWORD (للنشر غير التفاعلي)، أو
 *   2) إدخال سرّي تفاعلي (secret) لا يُطبع.
 * تُفرض قوّة دنيا (≥16 محرفًا). الحساب ثابت، مستقلّ عن حساب Production Smoke (§19).
 * لا يُطبع سرٌّ أبدًا؛ ويُدقَّق الإجراء.
 */
class ProvisionPlatformOwnerCommand extends Command
{
    protected $signature = 'platform:provision-owner {email : بريد مالك المنصّة} {--name= : الاسم المعروض}';

    protected $description = 'ينشئ أو يرقّي حساب مالك المنصّة (is_system_admin) بكلمة مرور تُقرأ من البيئة/إدخال سرّي — لا شيء في الكود.';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('بريد غير صالح.');
            return self::FAILURE;
        }

        // المصدر: البيئة أوّلًا (نشر آليّ)، ثم إدخال سرّيّ تفاعليّ.
        $password = (string) (getenv('PLATFORM_OWNER_PASSWORD') ?: '');
        if ($password === '' && $this->input->isInteractive()) {
            $password = (string) $this->secret('كلمة مرور مالك المنصّة (لن تُطبع)');
        }
        if (strlen($password) < 16) {
            $this->error('كلمة المرور مفقودة أو أقصر من 16 محرفًا (اضبط PLATFORM_OWNER_PASSWORD أو أدخِلها).');
            return self::FAILURE;
        }

        $name = (string) ($this->option('name') ?: 'Platform Owner');

        $user = User::withoutGlobalScopes()->where('email', $email)->first();
        $creating = $user === null;
        if ($creating) {
            $user = new User();
            $user->email = $email;
            $user->name = $name;
        } elseif ($this->option('name')) {
            $user->name = $name;
        }

        $user->password = $password;          // cast=hashed يجزّئها
        $user->is_active = true;
        $user->forceFill(['is_system_admin' => true]);   // خارج $fillable عمدًا
        $user->save();

        \App\Domain\Audit\Services\AuditLogger::log(
            $creating ? 'platform.owner.provisioned' : 'platform.owner.updated',
            $user, [], null, $user->id
        );

        $this->info(($creating ? 'أُنشئ' : 'حُدّث') . " حساب مالك المنصّة: {$email} (is_system_admin=true).");
        $this->line('لم تُطبع كلمة المرور. الحساب مستقلّ عن حساب Production Smoke.');

        return self::SUCCESS;
    }
}

<?php
namespace App\Console\Commands;

use App\Domain\Billing\Actions\CreateSubscription;
use App\Domain\Billing\Models\{Plan, PlanVersion, PlanEntitlement};
use App\Domain\Creators\Services\CreatorCapabilityService;
use App\Domain\CRM\Actions\CreateClient;
use App\Domain\CRM\Enums\ClientMemberRole;
use App\Domain\CRM\Models\{Client, ClientMember};
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Partners\Models\{ExternalAgency, ExternalAgencyMember};
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * حسابات معاينة محلية لكل دور. ممنوع في الإنتاج، وتُكتب بيانات الدخول في ملف غير متتبع.
 */
class SeedPreviewAccountsCommand extends Command
{
    protected $signature = 'preview:seed {--fresh : أعد إنشاء بيانات المعاينة}';
    protected $description = 'حسابات معاينة محلية لكل دور وصلاحية (غير إنتاجي)';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('ممنوع في الإنتاج.');
            return self::FAILURE;
        }

        $password = env('PREVIEW_PASSWORD') ?: Str::password(16);
        $passwordHash = Hash::make($password);

        $tenant = Tenant::firstOrCreate(
            ['slug' => 'demo'],
            ['name' => 'وكالة تجريبية', 'deployment_mode' => 'saas', 'status' => 'active'],
        );

        [$agencyOrg, $brandOrg, $client, $partner, $rows] = TenantContext::withBypass(function () use ($tenant, $passwordHash) {
            $agencyOrg = Organization::firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => 'demo-org'],
                ['name' => 'الوكالة التجريبية', 'type' => 'agency', 'status' => 'active'],
            );

            $brandOrg = Organization::firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => 'demo-brand-org'],
                ['name' => 'علامة تجريبية', 'type' => 'brand', 'status' => 'active'],
            );

            $this->ensurePreviewSubscription($agencyOrg);

            $rows = [];
            $agencyRoles = [
                Role::SystemAdmin,
                Role::SuperAdmin,
                Role::AgencyAdmin,
                Role::AgencyEmployee,
                Role::OperationsManager,
                Role::CampaignManager,
                Role::CreatorManager,
                Role::Finance,
                Role::ContentReviewer,
                Role::Viewer,
            ];

            foreach ($agencyRoles as $role) {
                $user = $this->user($role->value . '@demo.test', $this->label($role->value), $passwordHash);
                if ($role === Role::SystemAdmin) {
                    $user->forceFill(['is_system_admin' => true])->save();
                }
                OrganizationMembership::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'organization_id' => $agencyOrg->id, 'user_id' => $user->id],
                    ['role' => $role->value, 'status' => 'active'],
                );
                $rows[] = ['agency', $role->value, $user->email];
            }

            foreach ([Role::BrandAdmin, Role::BrandMember] as $role) {
                $user = $this->user($role->value . '@demo.test', $this->label($role->value), $passwordHash);
                OrganizationMembership::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'organization_id' => $brandOrg->id, 'user_id' => $user->id],
                    ['role' => $role->value, 'status' => 'active'],
                );
                $rows[] = ['brand', $role->value, $user->email];
            }

            $client = $this->ensureClient($tenant, $agencyOrg);
            foreach (ClientMemberRole::cases() as $role) {
                $user = $this->user($role->value . '@demo.test', $this->label($role->value), $passwordHash);
                ClientMember::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'client_id' => $client->id, 'user_id' => $user->id],
                    ['role' => $role->value, 'status' => 'active', 'accepted_at' => now()],
                );
                $rows[] = ['client', $role->value, $user->email];
            }

            $partner = ExternalAgency::updateOrCreate(
                ['tenant_id' => $tenant->id, 'contact_email' => 'partner_admin@demo.test'],
                [
                    'name' => 'وكالة شريكة تجريبية',
                    'legal_name' => 'Demo Partner Agency',
                    'status' => 'approved',
                    'contact_name' => 'Partner Admin',
                    'contact_phone' => '+201000000000',
                    'country_code' => 'EG',
                    'specialization' => 'production',
                    'reviewed_at' => now(),
                ],
            );

            foreach ([Role::ExternalAgencyAdmin, Role::ExternalAgencyMember] as $role) {
                $user = $this->user($role->value . '@demo.test', $this->label($role->value), $passwordHash);
                ExternalAgencyMember::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'external_agency_id' => $partner->id, 'user_id' => $user->id],
                    ['role' => $role->value, 'status' => 'active', 'accepted_at' => now()],
                );
                $rows[] = ['partner', $role->value, $user->email];
            }

            foreach ([Role::Influencer, Role::UgcCreator, Role::InfluencerAndUgc] as $role) {
                $user = $this->user($role->value . '@demo.test', $this->label($role->value), $passwordHash);
                OrganizationMembership::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'organization_id' => $agencyOrg->id, 'user_id' => $user->id],
                    ['role' => $role->value, 'status' => 'active'],
                );
                $creator = \App\Domain\Creators\Models\Creator::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('user_id', $user->id)
                    ->first();

                if (! $creator) {
                    $creator = app(\App\Domain\Creators\Actions\CreateCreator::class)->handle($agencyOrg, [
                        'display_name' => $this->label($role->value),
                        'professional_name' => $this->label($role->value),
                        'type' => $this->creatorType($role),
                        'capabilities' => $this->creatorCapabilities($role),
                        'handle' => str_replace('_', '.', $role->value),
                        'email' => $user->email,
                        'phone' => '+2010' . random_int(10000000, 99999999),
                        'primary_platform' => 'instagram',
                        'followers_count' => 120000,
                        'status' => 'active',
                    ], User::where('email', Role::AgencyAdmin->value . '@demo.test')->first());
                }

                $creator->forceFill([
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'status' => 'active',
                ])->save();
                CreatorCapabilityService::sync($creator, $this->creatorCapabilities($role), 'preview');
                $rows[] = ['creator', $role->value, $user->email];
            }

            return [$agencyOrg, $brandOrg, $client, $partner, $rows];
        });

        TenantContext::withTenant($tenant->id, function () use ($tenant, $agencyOrg) {
            $this->ensureDemoData($tenant, $agencyOrg);
        }, $agencyOrg->id);

        $this->writeCredentials($password, $rows);
        $this->auditPreviewAccounts($tenant->id, $rows);

        $this->info('حسابات المعاينة جاهزة (' . count($rows) . ' حسابًا).');
        $this->line('البيانات في storage/app/private/preview-credentials.txt');

        return self::SUCCESS;
    }

    private function user(string $email, string $name, string $passwordHash): User
    {
        return tap(User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'is_active' => true, 'password' => $passwordHash, 'email_verified_at' => now()],
        ), function (User $user) use ($passwordHash) {
            if (! str_starts_with($user->email, Role::SystemAdmin->value . '@')) {
                $user->forceFill(['is_system_admin' => false])->save();
            }
            $user->forceFill(['password' => $passwordHash, 'email_verified_at' => $user->email_verified_at ?: now()])->save();
        });
    }

    private function ensurePreviewSubscription(Organization $org): void
    {
        if (\App\Domain\Billing\Models\Subscription::where('organization_id', $org->id)->exists()) {
            return;
        }

        $plan = Plan::firstOrCreate(['key' => 'preview'], ['name' => 'معاينة', 'is_active' => true]);
        $version = PlanVersion::firstOrCreate(['plan_id' => $plan->id, 'version' => 1], ['is_active' => true]);
        foreach ([
            'customers.max' => 100,
            'creators.max' => 100,
            'creator_applications.monthly.max' => 50,
            'creator_storage.gb' => 10,
            'creator_portal.enabled' => 1,
            'ugc_creator.enabled' => 1,
            'social_integrations.max' => 10,
        ] as $feature => $value) {
            PlanEntitlement::firstOrCreate(['plan_version_id' => $version->id, 'feature_key' => $feature], ['value' => $value]);
        }
        (new CreateSubscription)->handle($org, $version);
    }

    private function ensureClient(Tenant $tenant, Organization $org): Client
    {
        return TenantContext::withTenant($tenant->id, function () use ($org) {
            $client = Client::where('email', 'client@demo.test')->first();
            if ($client) {
                return $client;
            }

            return app(CreateClient::class)->handle($org, [
                'display_name' => 'عميل تجريبي',
                'legal_name' => 'Demo Client',
                'status' => 'active',
                'type' => 'company',
                'sector' => 'تجربة',
                'email' => 'client@demo.test',
                'phone' => '+201000000000',
            ], User::where('email', Role::AgencyAdmin->value . '@demo.test')->first());
        }, $org->id);
    }

    private function ensureDemoData(Tenant $tenant, Organization $org): void
    {
        if (Client::count() === 0) {
            $admin = User::where('email', Role::AgencyAdmin->value . '@demo.test')->first();
            foreach ([['نايك السعودية', 'active', 'رياضة'], ['stc', 'active', 'اتصالات'], ['مطاعم البيك', 'qualified', 'أغذية'], ['نون', 'lead', 'تجارة']] as [$name, $status, $sector]) {
                app(CreateClient::class)->handle($org, ['display_name' => $name, 'status' => $status, 'type' => 'company', 'sector' => $sector], $admin);
            }
        }
    }

    private function writeCredentials(string $password, array $rows): void
    {
        $dir = storage_path('app/private');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        $lines = [
            '# حسابات معاينة محلية - لا ترفع إلى Git',
            '# كلمة المرور للجميع: ' . $password,
            '',
            sprintf('%-12s %-28s %s', 'portal', 'role', 'email'),
            str_repeat('-', 72),
        ];

        foreach ($rows as [$portal, $role, $email]) {
            $lines[] = sprintf('%-12s %-28s %s', $portal, $role, $email);
        }

        file_put_contents($dir . '/preview-credentials.txt', implode("\n", $lines) . "\n");
        @chmod($dir . '/preview-credentials.txt', 0600);
    }

    private function auditPreviewAccounts(int $tenantId, array $rows): void
    {
        $errors = [];
        foreach ($rows as [$portal, $role, $email]) {
            $user = User::where('email', $email)->first();
            if (! $user || ! $user->is_active) {
                $errors[] = "$email user missing/inactive";
                continue;
            }

            $ok = match ($portal) {
                'agency' => $role === Role::SystemAdmin->value
                    ? (bool) $user->is_system_admin
                    : OrganizationMembership::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('user_id', $user->id)->where('role', $role)->where('status', 'active')->exists(),
                'brand' => OrganizationMembership::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('user_id', $user->id)->where('role', $role)->where('status', 'active')->exists(),
                'client' => ClientMember::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('user_id', $user->id)->where('role', $role)->where('status', 'active')->exists(),
                'partner' => ExternalAgencyMember::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('user_id', $user->id)->where('role', $role)->where('status', 'active')->exists(),
                'creator' => OrganizationMembership::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('user_id', $user->id)->where('role', $role)->where('status', 'active')->exists()
                    && \App\Domain\Creators\Models\Creator::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('user_id', $user->id)->exists(),
                default => false,
            };

            if (! $ok) {
                $errors[] = "$email missing active $portal membership for $role";
            }
        }

        if ($errors) {
            throw new \RuntimeException('Preview auth audit failed: ' . implode('; ', $errors));
        }
    }

    private function creatorType(Role $role): string
    {
        return match ($role) {
            Role::UgcCreator => 'ugc_creator',
            Role::InfluencerAndUgc => 'both',
            default => 'influencer',
        };
    }

    private function creatorCapabilities(Role $role): array
    {
        return match ($role) {
            Role::UgcCreator => ['ugc_creator'],
            Role::InfluencerAndUgc => ['influencer', 'ugc_creator'],
            default => ['influencer'],
        };
    }

    private function label(string $role): string
    {
        return Str::headline(str_replace('_', ' ', $role));
    }
}
<?php

namespace App\Domain\Integrations\Google\Services;

use App\Domain\Creators\Actions\CreateCreator;
use App\Domain\Creators\Models\Creator;
use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Google\GoogleClient;
use App\Domain\Integrations\Google\GoogleScopes;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * استيراد المبدعين من Google Sheets (GOOGLE-01): اقتراح ربط الأعمدة → معاينة → تحقّق →
 * كشف تكرار → معالجة تعارض → تثبيت. المنطق صافٍ على الصفوف (قابل للاختبار بلا Google)؛
 * القراءة من الورقة تمرّ عبر GoogleClient. التثبيت يعيد استعمال CreateCreator (ترقيم/قدرات/تدقيق)
 * ولا يخترع بيانات — حقل غير مربوط = فارغ، لا تخمين قيمة.
 */
class GoogleSheetsImporter
{
    /** الحقول المستهدَفة القابلة للاستيراد (مجموعة آمنة فقط — لا ماليّات/هوية حسّاسة). */
    public const FIELDS = [
        'display_name', 'handle', 'email', 'phone', 'primary_platform', 'followers_count', 'city', 'country_code', 'bio',
    ];

    /** مرادفات ترويسة الأعمدة (عربي/إنجليزي) لاقتراح الربط. */
    private const SYNONYMS = [
        'display_name' => ['name', 'full name', 'display name', 'اسم', 'الاسم', 'الاسم الكامل', 'اسم العرض', 'الاسم بالكامل'],
        'handle' => ['handle', 'username', 'user name', 'account', 'معرف', 'المعرف', 'اسم المستخدم', 'الحساب', 'يوزر'],
        'email' => ['email', 'e-mail', 'mail', 'بريد', 'البريد', 'الايميل', 'البريد الالكتروني'],
        'phone' => ['phone', 'mobile', 'tel', 'whatsapp', 'جوال', 'الجوال', 'هاتف', 'رقم', 'الرقم', 'واتساب'],
        'primary_platform' => ['platform', 'channel', 'network', 'منصة', 'المنصة', 'القناة', 'الشبكة'],
        'followers_count' => ['followers', 'follower', 'audience', 'متابعين', 'المتابعين', 'عدد المتابعين', 'الجمهور'],
        'city' => ['city', 'مدينة', 'المدينة'],
        'country_code' => ['country', 'country code', 'دولة', 'الدولة', 'بلد', 'البلد', 'رمز الدولة'],
        'bio' => ['bio', 'biography', 'about', 'description', 'نبذة', 'وصف', 'السيرة', 'عن'],
    ];

    public function __construct(
        private GoogleClient $client,
        private GoogleConnectionService $connections,
        private CreateCreator $createCreator,
    ) {}

    /** يجلب (ترويسة، صفوف) من ورقة؛ السطر الأوّل ترويسة. @return array{headers:array<int,string>,rows:array<int,array<int,string>>} */
    public function fetch(IntegrationConnection $conn, string $spreadsheetId, string $range = 'Sheet1'): array
    {
        $conn = $this->connections->ensureFresh($conn);
        $this->connections->assertCapability($conn, GoogleScopes::CAP_SHEETS);
        $values = $this->client->sheetsGetValues($conn->access_token, $spreadsheetId, $range);
        $headers = array_map(fn ($h) => (string) $h, array_shift($values) ?? []);

        return ['headers' => $headers, 'rows' => array_values($values)];
    }

    /**
     * يقترح ربط كل عمود بحقل (أو null) من الترويسة. مطابقة غير حسّاسة لحالة الأحرف،
     * تساوي ثمّ احتواء. لا يُربط عمودان بالحقل نفسه (الأوّل يفوز).
     *
     * @param  array<int,string>  $headers
     * @return array<int,?string> index العمود => الحقل
     */
    public function suggestMapping(array $headers): array
    {
        $map = [];
        $used = [];
        foreach ($headers as $i => $header) {
            $norm = $this->norm($header);
            $field = null;
            foreach (self::SYNONYMS as $f => $alts) {
                if (isset($used[$f])) {
                    continue;
                }
                foreach ($alts as $alt) {
                    $a = $this->norm($alt);
                    if ($norm === $a || ($norm !== '' && (str_contains($norm, $a) || str_contains($a, $norm)))) {
                        $field = $f;
                        break 2;
                    }
                }
            }
            if ($field) {
                $used[$field] = true;
            }
            $map[$i] = $field;
        }

        return $map;
    }

    /**
     * يبني سمات مبدع من صفّ وفق الربط. حقل غير مربوط = غير موجود (لا قيمة مخترَعة).
     *
     * @param  array<int,string>  $row
     * @param  array<int,?string>  $mapping
     * @return array<string,mixed>
     */
    public function rowToAttrs(array $row, array $mapping): array
    {
        $attrs = [];
        foreach ($mapping as $i => $field) {
            if (! $field || ! in_array($field, self::FIELDS, true)) {
                continue;
            }
            $val = trim((string) ($row[$i] ?? ''));
            if ($val === '') {
                continue;
            }
            $attrs[$field] = $field === 'followers_count' ? (int) preg_replace('/\D+/', '', $val) : $val;
        }

        return $attrs;
    }

    /**
     * يتحقّق من سمات صفّ. يُعيد قائمة أخطاء (فارغة = صالح).
     *
     * @param  array<string,mixed>  $attrs
     * @return array<int,string>
     */
    public function validateRow(array $attrs): array
    {
        $errors = [];
        if (empty($attrs['display_name'])) {
            $errors[] = 'display_name_required';
        }
        if (! empty($attrs['email']) && ! filter_var($attrs['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'email_invalid';
        }
        if (isset($attrs['followers_count']) && $attrs['followers_count'] < 0) {
            $errors[] = 'followers_negative';
        }
        if (! empty($attrs['country_code']) && mb_strlen((string) $attrs['country_code']) > 3 && ! preg_match('/^[A-Za-z]{2,3}$/', (string) $attrs['country_code'])) {
            // نقبل اسم دولة أو رمزًا — نحذّر فقط لا نرفض
        }

        return $errors;
    }

    /**
     * يصنّف كل صفّ: invalid|duplicate|conflict|new مع تفاصيل. لا يكتب شيئًا (معاينة فقط).
     * dedup على البريد (lowercase) ثمّ المعرّف. التعارض = مطابقة موجودة بقيم مختلفة.
     *
     * @param  array<int,string>  $headers
     * @param  array<int,array<int,string>>  $rows
     * @param  array<int,?string>  $mapping
     * @return array{summary:array<string,int>,rows:array<int,array<string,mixed>>}
     */
    public function preview(int $tenantId, array $headers, array $rows, array $mapping): array
    {
        return TenantContext::withTenant($tenantId, function () use ($tenantId, $rows, $mapping) {
            $existing = $this->existingIndex($tenantId);
            $summary = ['total' => count($rows), 'new' => 0, 'duplicate' => 0, 'conflict' => 0, 'invalid' => 0];
            $out = [];

            foreach ($rows as $n => $row) {
                $attrs = $this->rowToAttrs($row, $mapping);
                $errors = $this->validateRow($attrs);
                if ($errors) {
                    $summary['invalid']++;
                    $out[] = ['line' => $n + 2, 'status' => 'invalid', 'errors' => $errors, 'attrs' => $attrs];

                    continue;
                }
                $match = $this->matchExisting($attrs, $existing);
                if (! $match) {
                    $summary['new']++;
                    $out[] = ['line' => $n + 2, 'status' => 'new', 'attrs' => $attrs];

                    continue;
                }
                $diff = $this->diff($match, $attrs);
                if ($diff === []) {
                    $summary['duplicate']++;
                    $out[] = ['line' => $n + 2, 'status' => 'duplicate', 'creator_id' => $match->id, 'attrs' => $attrs];
                } else {
                    $summary['conflict']++;
                    $out[] = ['line' => $n + 2, 'status' => 'conflict', 'creator_id' => $match->id, 'diff' => $diff, 'attrs' => $attrs];
                }
            }

            return ['summary' => $summary, 'rows' => $out];
        });
    }

    /**
     * يثبّت الاستيراد. استراتيجيّة التعارض: skip (تجاهل) | update (تحديث الموجود) | create_new.
     * الصفوف غير الصالحة تُتخطّى دائمًا. يعيد استعمال CreateCreator للإنشاء.
     *
     * @param  array<int,string>  $headers
     * @param  array<int,array<int,string>>  $rows
     * @param  array<int,?string>  $mapping
     * @return array{created:int,updated:int,skipped:int,invalid:int,conflicts:int}
     */
    public function commit(int $tenantId, array $headers, array $rows, array $mapping, string $conflictStrategy, User $actor, array $capabilities = ['influencer']): array
    {
        if (! in_array($conflictStrategy, ['skip', 'update', 'create_new'], true)) {
            throw new RuntimeException('invalid_conflict_strategy');
        }

        return TenantContext::withTenant($tenantId, function () use ($tenantId, $rows, $mapping, $conflictStrategy, $actor, $capabilities) {
            $org = Organization::where('tenant_id', $tenantId)->firstOrFail();
            $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'invalid' => 0, 'conflicts' => 0];

            foreach ($rows as $row) {
                $attrs = $this->rowToAttrs($row, $mapping);
                if ($this->validateRow($attrs)) {
                    $counts['invalid']++;

                    continue;
                }
                // إعادة فهرسة لكل صفّ تضمن رؤية ما أُنشئ خلال هذه الدفعة (منع تكرار داخل نفس الملفّ).
                $match = $this->matchExisting($attrs, $this->existingIndex($tenantId));
                if (! $match) {
                    $this->createCreator->handle($org, $attrs + ['capabilities' => $capabilities], $actor);
                    $counts['created']++;

                    continue;
                }
                if ($this->diff($match, $attrs) === []) {
                    $counts['skipped']++;

                    continue;
                }
                // تعارض
                $counts['conflicts']++;
                if ($conflictStrategy === 'update') {
                    $match->fill(array_intersect_key($attrs, array_flip(self::FIELDS)))->save();
                    $counts['updated']++;
                } elseif ($conflictStrategy === 'create_new') {
                    $this->createCreator->handle($org, $attrs + ['capabilities' => $capabilities], $actor);
                    $counts['created']++;
                } else {
                    $counts['skipped']++;
                }
            }

            return $counts;
        });
    }

    /** @return Collection<int,Creator> */
    private function existingIndex(int $tenantId)
    {
        return Creator::where('tenant_id', $tenantId)->get(['id', 'display_name', 'handle', 'email', 'phone', 'primary_platform', 'followers_count', 'city', 'country_code', 'bio']);
    }

    /** @param Collection<int,Creator> $existing */
    private function matchExisting(array $attrs, $existing): ?Creator
    {
        $email = isset($attrs['email']) ? mb_strtolower((string) $attrs['email']) : null;
        $handle = isset($attrs['handle']) ? mb_strtolower(ltrim((string) $attrs['handle'], '@')) : null;

        return $existing->first(function (Creator $c) use ($email, $handle) {
            if ($email && $c->email && mb_strtolower($c->email) === $email) {
                return true;
            }

            return $handle && $c->handle && mb_strtolower(ltrim($c->handle, '@')) === $handle;
        });
    }

    /** @param array<string,mixed> $attrs @return array<string,array{old:mixed,new:mixed}> الفروق فقط */
    private function diff(Creator $c, array $attrs): array
    {
        $diff = [];
        foreach ($attrs as $field => $new) {
            if (! in_array($field, self::FIELDS, true)) {
                continue;
            }
            $old = $c->{$field};
            $same = $field === 'followers_count' ? ((int) $old === (int) $new) : (trim((string) $old) === trim((string) $new));
            if (! $same) {
                $diff[$field] = ['old' => $old, 'new' => $new];
            }
        }

        return $diff;
    }

    private function norm(string $s): string
    {
        $s = mb_strtolower(trim($s));

        return preg_replace('/[\s_\-]+/u', ' ', $s) ?? $s;
    }
}

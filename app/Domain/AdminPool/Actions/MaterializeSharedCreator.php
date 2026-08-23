<?php

namespace App\Domain\AdminPool\Actions;

use App\Domain\AdminPool\Models\PoolCreator;
use App\Domain\Creators\Actions\CreateCreator;
use App\Domain\Creators\Models\Creator;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Support\TenantContext;

/**
 * جسر حتمي (idempotent): مبدع قاعدة المؤثرين العالمي → علاقة مبدع للمستأجر.
 *
 * أوّل استخدام لمبدع مشترك من قِبل مؤسسة يُنشئ علاقة مبدع واحدة للمستأجر (عبر
 * CreateCreator نفسه — لا محرّك موازٍ). الاستخدام المتكرّر يعيد العلاقة نفسها بلا
 * تكرار (قيد فريد tenant_id+pool_creator_id).
 *
 * لا يُنقل أيّ مصدر/متجر/تكلفة إلى علاقة المستأجر — تُنسَخ الحقول المفيدة فقط.
 * الهوية العالمية تبقى عالمية؛ ما يخصّ المستأجر يصبح مملوكًا له.
 */
class MaterializeSharedCreator
{
    public function __construct(private CreateCreator $create) {}

    public function handle(PoolCreator $pool, Organization $org, User $actor): Creator
    {
        return TenantContext::withTenant($org->tenant_id, function () use ($pool, $org, $actor) {
            $existing = Creator::where('pool_creator_id', $pool->id)->first();
            if ($existing) {
                return $existing; // حتمي — لا يُنشأ مبدع ثانٍ لنفس المبدع المشترك
            }

            $type = $pool->source_type === 'ugc' ? 'ugc_creator' : 'influencer';

            return $this->create->handle($org, [
                'type' => $type,
                'display_name' => $pool->name,
                'handle' => $this->handleFrom($pool->account_url),
                'primary_platform' => $pool->platform,
                'followers_count' => $pool->followers,
                'content_categories' => $pool->categories ?? [],
                'phone' => $pool->phone ? \App\Domain\AdminPool\Support\CreatorNormalizer::phone($pool->phone) : null,
                'city' => $pool->city,
                'gender' => $pool->gender,
                'status' => 'prospect',
                // رابط تقني داخلي فقط — لا يُعرَض للمستأجر كمصدر
                'pool_creator_id' => $pool->id,
            ], $actor);
        });
    }

    private function handleFrom(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        if (preg_match('~/@([A-Za-z0-9_.]+)~', $url, $m)) {
            return '@' . $m[1];
        }
        if (preg_match('~([A-Za-z0-9_.]+)$~', rtrim($url, '/'), $m) && ! str_contains($m[1], '.')) {
            return '@' . $m[1];
        }

        return null;
    }
}

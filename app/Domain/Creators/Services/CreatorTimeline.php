<?php

namespace App\Domain\Creators\Services;

use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Contracts\Models\Contract;
use App\Domain\Creators\Models\Creator;
use App\Domain\Creators\Models\CreatorInvitation;
use App\Domain\Creators\Models\CreatorNote;
use App\Domain\Creators\Models\CreatorTask;
use App\Domain\Finance\Models\Payout;

/**
 * الخطّ الزمني الموحّد لعلاقة المبدع (CREATOR-01 · §4) — مصدر واحد يجمع الأحداث الحقيقية
 * المسجَّلة فعلًا (تعاونات/محتوى/عقود/مستحقات/ملاحظات/مهام/دعوات) مرتّبةً زمنيًّا تنازليًّا.
 * لا خطّ منفصل لكل قناة، ولا أحداث مُختلَقة — كل سطر من صفّ حقيقي بتاريخه الفعليّ.
 * عدد الاستعلامات ثابت (استعلام مُجمَّع لكل مصدر) — لا N+1.
 */
class CreatorTimeline
{
    /** @return list<array{at:string,ts:int,type:string,icon:string,tone:string,text:string,meta:?string}> */
    public static function for(Creator $creator, int $limit = 50): array
    {
        $id = $creator->id;
        $ev = [];
        $push = function (?\DateTimeInterface $at, string $type, string $icon, string $tone, string $text, ?string $meta = null) use (&$ev) {
            if (! $at) {
                return;
            }
            $ev[] = ['at' => $at->format('Y-m-d H:i'), 'ts' => $at->getTimestamp(), 'type' => $type, 'icon' => $icon, 'tone' => $tone, 'text' => $text, 'meta' => $meta];
        };

        foreach (Collaboration::where('creator_id', $id)->with('campaign')->latest()->limit($limit)->get() as $c) {
            $push($c->created_at, 'collaboration', 'git-merge', 'info',
                trans('creators.tl_collaboration', ['status' => __('statuses.'.$c->status)]), $c->campaign?->name);
        }
        foreach (ContentItem::where('creator_id', $id)->latest()->limit($limit)->get() as $c) {
            $push($c->created_at, 'content', 'image', 'accent',
                trans('creators.tl_content', ['status' => __('statuses.'.$c->status)]), $c->title);
        }
        foreach (Contract::where('creator_id', $id)->latest()->limit($limit)->get() as $c) {
            $push($c->created_at, 'contract', 'file-text', 'primary',
                trans('creators.tl_contract', ['status' => __('statuses.'.$c->status)]), $c->contract_number);
        }
        foreach (Payout::where('creator_id', $id)->latest()->limit($limit)->get() as $p) {
            $push($p->paid_at ?? $p->created_at, 'payout', 'wallet', 'success',
                trans('creators.tl_payout', ['status' => __('statuses.'.$p->status)]), number_format(((int) $p->amount_minor) / 100, 0).' '.($p->currency ?: 'SAR'));
        }
        foreach (CreatorNote::where('creator_id', $id)->with('author')->latest()->limit($limit)->get() as $n) {
            $push($n->created_at, 'note', 'pencil', 'neutral', $n->body, $n->author?->name);
        }
        foreach (CreatorTask::where('creator_id', $id)->latest()->limit($limit)->get() as $t) {
            $done = $t->status === CreatorTask::DONE;
            $push($done ? ($t->completed_at ?? $t->created_at) : $t->created_at, 'task', 'clipboard-check', $done ? 'success' : 'warning',
                trans($done ? 'creators.tl_task_done' : 'creators.tl_task_open', ['title' => $t->title]),
                $t->due_at ? trans('creators.tl_task_due', ['date' => $t->due_at->format('Y-m-d')]) : null);
        }
        foreach (CreatorInvitation::where('creator_id', $id)->latest()->limit($limit)->get() as $i) {
            $push($i->created_at, 'invitation', 'user-plus', 'info',
                trans($i->accepted_at ? 'creators.tl_invite_accepted' : 'creators.tl_invite_sent'), $i->email ?: $i->phone);
        }

        usort($ev, fn ($a, $b) => $b['ts'] <=> $a['ts']);

        return array_slice($ev, 0, $limit);
    }
}

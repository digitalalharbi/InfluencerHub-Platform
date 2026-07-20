<?php
namespace App\Domain\Campaigns\Enums;
enum DeliverableType: string {
    case Post='post'; case Story='story'; case Reel='reel'; case Video='video'; case Ugc='ugc';
    public static function values(): array { return array_map(fn($c)=>$c->value, self::cases()); }
    public static function labels(): array { return ['post'=>'منشور','story'=>'ستوري','reel'=>'ريل','video'=>'فيديو','ugc'=>'UGC']; }
}

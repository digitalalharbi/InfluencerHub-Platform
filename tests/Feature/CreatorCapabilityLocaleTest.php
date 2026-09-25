<?php

namespace Tests\Feature;

use App\Domain\Creators\Models\CreatorCapability;
use App\Domain\Creators\Services\CreatorCapabilityService;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * تسميات القدرات تُعرَّب حسب لغة الطلب دون مسّ المفتاح الداخلي.
 *
 * المفتاح (influencer، ugc…) هو المعنى القانونيّ ولا يُترجَم أبدًا؛ التسمية
 * وحدها تتبدّل. واللغة الافتراضية (العربية) يجب أن تبقى مطابقةً حرفيًّا للثابت
 * حتى لا يغيّر هذا التعريب أيّ سطح قائم — يحرس ذلك اختبار الانحراف أدناه.
 */
class CreatorCapabilityLocaleTest extends TestCase
{
    /** العربية = الثابت حرفيًّا لكل مفتاح (حارس الانحراف). */
    public function test_arabic_labels_match_the_canonical_const_exactly(): void
    {
        App::setLocale('ar');

        foreach (CreatorCapability::LABELS as $key => $arabic) {
            $this->assertSame($arabic, CreatorCapability::label($key), "درجت التسمية العربية عن الثابت للمفتاح: {$key}");
        }
    }

    /** الإنجليزية: تسمية حقيقية (لا مفتاح خام) ومغايرة للعربية. */
    public function test_english_labels_are_translated_not_raw_keys(): void
    {
        App::setLocale('en');

        foreach (array_keys(CreatorCapability::LABELS) as $key) {
            $label = CreatorCapability::label($key);
            $this->assertNotSame($key, $label, "بقي المفتاح خامًا في الإنجليزية: {$key}");
            $this->assertNotSame("creator_capability.$key", $label);
        }

        $this->assertSame('Influencer', CreatorCapability::label('influencer'));
        $this->assertSame('User-Generated Content (UGC)', CreatorCapability::label('ugc'));
    }

    /** مفتاح غير معروف يعود كما هو، لا استثناء ولا سلسلة ترجمة خام. */
    public function test_unknown_key_falls_back_to_itself(): void
    {
        App::setLocale('en');
        $this->assertSame('__nope__', CreatorCapability::label('__nope__'));
    }

    /** options() تحفظ ترتيب المفاتيح وتتبع لغة الطلب. */
    public function test_options_preserve_key_order_and_follow_locale(): void
    {
        App::setLocale('ar');
        $ar = CreatorCapabilityService::options();
        $this->assertSame(array_keys(CreatorCapability::LABELS), array_keys($ar));
        $this->assertSame(CreatorCapability::LABELS, $ar);

        App::setLocale('en');
        $en = CreatorCapabilityService::options();
        $this->assertSame(array_keys(CreatorCapability::LABELS), array_keys($en));
        $this->assertSame('Influencer', $en['influencer']);
    }
}

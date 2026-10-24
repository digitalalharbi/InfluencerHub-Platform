<?php

namespace App\Http\Middleware;

use App\Support\I18n\PageTranslations;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * حقن ترجمات السطح (PERF-01) — يعمل بعد Inertia. الوسيط المشترك يرسل CORE فقط؛
 * هنا نضيف مجموعات المكوّن الحالي (المعروف فقط بعد التصيير) إلى props.translations،
 * في استجابة Inertia (JSON عبر X-Inertia) وفي HTML الأوّلي (data-page) على السواء.
 *
 * إعادة التحميل الجزئية (only=…) لا تحمل translations ⇒ لا حقن (يبقى العقد كما هو).
 */
class InjectPageTranslations
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $isInertiaJson = $response->headers->get('X-Inertia') === 'true';
        $isHtml = str_contains((string) $response->headers->get('Content-Type'), 'text/html');
        if (! $isInertiaJson && ! $isHtml) {
            return $response;
        }

        $content = (string) $response->getContent();
        if ($content === '') {
            return $response;
        }

        if ($isInertiaJson) {
            $page = json_decode($content, true);
            $merged = $this->augment($page);
            if ($merged === null) {
                return $response;
            }
            $this->rewrite($response, json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $response;
        }

        if (! str_contains($content, 'data-page=')) {
            return $response;
        }
        // Inertia v3 يضع حمولة الصفحة في جسم <script data-page="app"
        // type="application/json">…JSON…</script> بترميز json_encode الافتراضي.
        $new = preg_replace_callback(
            '#(<script\b[^>]*\bdata-page="[^"]*"[^>]*>)(.*?)(</script>)#s',
            function (array $m): string {
                $merged = $this->augment(json_decode($m[2], true));

                return $merged === null ? $m[0] : $m[1].json_encode($merged).$m[3];
            },
            $content,
            1
        );

        if (is_string($new) && $new !== $content) {
            $this->rewrite($response, $new);
        }

        return $response;
    }

    /**
     * يستبدل جسم الاستجابة مع الحفاظ على `original` (View/مصفوفة) — لأن
     * Response::setContent يُحلّ محلّه السلسلة، فتفشل مساعدات الاختبار
     * (assertViewHas/viewData/assertInertia). الإنتاج لا يستعمل `original`.
     */
    private function rewrite(Response $response, string $content): void
    {
        $original = method_exists($response, 'getOriginalContent') ? $response->getOriginalContent() : null;
        $response->setContent($content);
        if ($original !== null && property_exists($response, 'original')) {
            $response->original = $original;
        }
        $response->headers->remove('Content-Length');
    }

    /**
     * يدمج مجموعات المكوّن في props.translations، أو null إن لا شيء يتغيّر.
     *
     * @param  mixed  $page
     * @return array<string,mixed>|null
     */
    private function augment($page): ?array
    {
        if (! is_array($page) || ! isset($page['component'])) {
            return null;
        }
        $extra = PageTranslations::namespacesFor($page['component']);
        if (! $extra) {
            return null;
        }
        if (! isset($page['props']['translations']) || ! is_array($page['props']['translations'])) {
            return null;
        }
        // لا تُعِد حلّ مجموعة مُرسَلة أصلًا (CORE) — أضف الناقص فقط.
        $missing = array_values(array_filter($extra, fn ($ns) => ! isset($page['props']['translations'][$ns])));
        if (! $missing) {
            return null;
        }
        $page['props']['translations'] = array_merge($page['props']['translations'], PageTranslations::resolve($missing));

        return $page;
    }
}

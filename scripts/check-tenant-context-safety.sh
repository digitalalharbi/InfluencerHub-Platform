#!/usr/bin/env bash
#
# حارس سلامة سياق المستأجر — صارم.
#
# كان سقّاطة (ratchet) تقبل الدَّين القائم وتمنع الزيادة، لأن الاستدعاءات اليدوية
# كانت بالمئات. بلغت الآن **صفرًا** في كود الإنتاج كلّه، فلم يعد للسقّاطة معنى:
# قائمة استثناءات قصيرة بالاسم والسبب تقول ما تسمح به بالضبط، والسقّاطة تقول
# «ما كان موجودًا مقبول» — وهو ختم على الوضع القائم لا حراسة.
#
# القاعدة: `TenantContext::set/reset/bypass` ممنوعة في `app/` كلّه إلا المواضع
# المذكورة أدناه. النمط المعتمَد: `withTenant()` / `withBypass()` — تستعيد ما
# كان حتّى عند الاستثناء. انظر docs/TENANT-CONTEXT-SAFETY.md.
#
# الاختبارات وSeeders خارج `app/` أصلًا، فهي غير معنيّة بهذا الحارس.
set -euo pipefail

cd "$(dirname "$0")/.."

PATTERN='TenantContext::(set|reset|bypass)\('

# ─────────────────────────────────────────────────────────────────────────────
# الاستثناءات النهائية — بالاسم والسبب. أي إضافة هنا تحتاج تبريرًا في الوثيقة.
#
#  TenantContext.php        يُعرّف النمط الآمن نفسه (withTenant/withBypass فوقه)
#  SetTenantContext.php     Bootstrap داخلي: يُنشئ سياق الطلب من المستخدم
#  Middleware/SetTenantContext  وسيط الطلب الأساسي — نقطة الدخول الوحيدة للسياق
#  Middleware/EnsureClientMember    يُنشئ سياق بوابة العميل لمدى الطلب
#  Middleware/EnsureCreator         يُنشئ سياق بوابة صانع المحتوى لمدى الطلب
#  Middleware/EnsurePartnerMember   يُنشئ سياق بوابة الشريك لمدى الطلب
#  Middleware/EnsureBrandMember     يُنشئ سياق مساحة العلامة لمدى الطلب
#
# قيدٌ على وسائط البوّابات: استثناؤها مقصور على **إنشاء** السياق (استدعاء `set`
# واحد قرب نهايتها). وسيطٌ يضبط ثم يُعيد داخل نفسه يعود إلى العيب الأصلي — وهو
# ما وقع فعلًا وكلّف 157 تعويضًا في المتحكّمات.
# ─────────────────────────────────────────────────────────────────────────────
EXEMPT_RE='^app/Domain/Tenancy/Support/(TenantContext|SetTenantContext)\.php$'
EXEMPT_RE+='|^app/Http/Middleware/(SetTenantContext|EnsureClientMember|EnsureCreator|EnsurePartnerMember|EnsureBrandMember)\.php$'

hits=$(grep -rEn "$PATTERN" app/ 2>/dev/null | grep -Ev "^($(echo "$EXEMPT_RE" | sed 's/\^//g;s/\$//g')):" || true)

if [[ -n "$hits" ]]; then
  echo "✗ استدعاء يدوي لسياق المستأجر في كود الإنتاج:"
  echo "$hits" | sed 's/^/    /'
  echo
  echo "  استعمل TenantContext::withTenant() أو withBypass() — تستعيدان ما كان"
  echo "  حتّى عند الاستثناء. و`reset()` لا يستعيد شيئًا: يمسح المستأجر والمؤسسة"
  echo "  وورشة العمل والتجاوز معًا، فيعود الاستعلام التالي فارغًا **بلا خطأ**."
  echo
  echo "  إن كان الموضع يستحقّ استثناءً فعلًا: أضفه بالاسم والسبب في هذا الملفّ"
  echo "  وفي docs/TENANT-CONTEXT-SAFETY.md — لا سقّاطة ولا أساس واسع."
  exit 1
fi

exempt_count=$(grep -rEln "$PATTERN" app/ 2>/dev/null | wc -l | tr -d ' ')
echo "✓ سلامة السياق: صفر استدعاء يدوي في كود الإنتاج (${exempt_count} ملفات مستثناة بالاسم)."

# Page Quality Gate — InfluencerHub (موحّدة لكل صفحة React)

لا تُعتمد صفحة ولا يُنتقل لغيرها حتى ✅ كل البنود:

## المنتج والوظيفة
- [ ] تحقّق الهدف التجاري/التشغيلي للوحدة.
- [ ] تجيب أسئلة الصفحة (أين/الحالة/أهم المعلومات/ما يحتاج انتباهي/المخاطر/الإجراء/الخطوة التالية/المسؤول/الموعد/ما تغيّر/العلاقات).
- [ ] ليست CRUD/جدول بدائي — طبقات فهم+تنفيذ+ذكاء تشغيلي حسب الوحدة.
- [ ] معلومات رئيسية وثانوية واضحة + حالات + مخاطر + نواقص.
- [ ] إجراءات مباشرة + Next Best Action عند الملاءمة + روابط للكيانات المرتبطة.
- [ ] لا مؤشر/رقم زخرفي بلا معلومة أو إجراء.

## البيانات والأمان
- [ ] البيانات من PostgreSQL (لا أرقام ثابتة/وهمية).
- [ ] الصلاحيات من Laravel Policies/Middleware (لا إخفاء CSS فقط).
- [ ] Tenant Isolation + IDOR (كيان مستأجر آخر ⇒ 404).
- [ ] لا N+1 (تجميعات/Eager Loading).
- [ ] لا تكامل/دفع وهمي — حالات صريحة (Manual/Sandbox/Waiting/Connected/Degraded/Unavailable).

## الحالات والتجربة
- [ ] Loading (Skeleton) + Empty (غنية) + Error states.
- [ ] Desktop احترافي + Mobile احترافي (بطاقات لا جداول عريضة).
- [ ] RTL وLTR ناجحان.
- [ ] لا Horizontal Overflow (scrollWidth == clientWidth عند 390).
- [ ] لا Console Errors. لا Broken Actions.

## التحقق والإغلاق
- [ ] `npx tsc --noEmit` نظيف.
- [ ] `npm run build` ناجح.
- [ ] اختبار Inertia (assertInertia: component+props + عزل + IDOR/صلاحية).
- [ ] كل Backend tests ناجحة (لا Regression).
- [ ] لقطة Desktop + لقطة Mobile عبر `scripts/dev-screenshots.mjs` (URL `/beta/...`).
- [ ] مراجعة بصرية بعد اللقطات + إصلاح العيوب + إعادة تصوير.
- [ ] Commit مستقل واضح + Git نظيف + تحديث CONTINUATION-STATE + MATRIX.

## جولات المراجعة الذاتية (لا تعتمد أول نسخة)
Product completeness · UX · UI · IA · Workflow · Data usefulness · Permissions · Security · Performance · Mobile · RTL/LTR · Accessibility · Empty/Error · Originality · Visual quality · Operational usefulness.

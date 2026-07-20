# تصميم وحدة المبدعين (Phase 4)

المبدعون = المؤثّرون + صنّاع UGC، ضمن دومين `app/Domain/Creators`. نموذج واحد `Creator` بحقل `type` (influencer|ugc_creator|both) لتفادي التكرار، مع تصفية بالنوع في الواجهة (`both` يظهر في القائمتين).

## المكوّنات
- **Models:** Creator, CreatorPlatform, CreatorService, CreatorPortfolio, CreatorCategory, CreatorApplication (+9 جداول فرعية).
- **Enums:** CreatorType, CreatorStatus (prospect/active/paused/blocked), Platform (instagram/tiktok/youtube/snapchat/x).
- **Actions:** CreateCreator (ترقيم CR-{tenant}-{seq})، ApproveCreatorApplication (معاملة قبول ذرّية)، RecalculateCreatorsUsage.
- **Services:** CreatorApplicationService (مرجع/مسودة/OTP/تحوّلات حالة).
- **Policies:** CreatorPolicy، CreatorApplicationPolicy (مصفوفة CreatorAbilities).

## المسارات
- الوكالة: `/app/creators` (+ `?type=`)، `/app/creators/{id}`، `/app/creator-applications`، `/app/creator-applications/{id}`.
- عامة: `/join`, `/join/creator`, `/join/creator/{ref}/status`.
- المبدع: `/creator/*`.

## مبادئ
- عزل مستأجر fail-closed عبر TenantScope.
- أموال بوحدات صغرى (price_minor). IBAN مشفّر (Crypt) — يُعرض آخر 4 فقط.
- تصنيفات قابلة للإدارة (جدول creator_categories، لا تثبيت في الواجهة).

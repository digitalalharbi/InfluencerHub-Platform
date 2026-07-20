# Phase 8 — التعاونات والمطابقة (Collaborations & Matching) — بوابة القبول

الحالة: **PHASE COMPLETE** (Feature Complete + مُتحقَّق بالمتصفّح + اختبارات خلفية). التالي: Phase 9.

## ما أُنجز
- **البنية** `app/Domain/Collaborations`: `collaborations` + `collaboration_status_history`؛ `CollaborationWorkflowService` (دورة حياة بالأحداث) + `CreatorMatchingService` (مطابقة شفّافة قابلة للتفسير).
- **دورة الحياة**: offered→accepted/declined/cancelled؛ accepted→in_progress؛ in_progress→submitted؛ submitted→approved/in_progress(مراجعة)؛ approved→completed. سجل append-only يميّز الفاعل (وكالة/مبدع).
- **الوكالة تعرض / المبدع يستجيب**: عرض من مخرَج حملة (يرث الأجر/الحملة/العميل)؛ المبدع يقبل/يرفض/يبدأ/يسلّم؛ الوكالة تعتمد/تطلب تعديلًا/تُكمل.
- **المطابقة**: ترشيح المبدعين النشِطين وترتيبهم بمعيار مفسَّر (تطابق المنصّة + تقاطع الفئات + الوصول) — ليست صندوقًا أسود.
- **الوكالة** `/app/collaborations` (قائمة/تفصيل/دورة حياة) + `/app/campaigns/{id}/deliverables/{d}/suggest` (اقتراح + عرض بنقرة).
- **المبدع** `/creator/collaborations` (تفعيل stub سابق): قائمة + تفصيل + قبول/رفض/بدء/تسليم.
- **إشعارات**: عرض جديد → المبدع؛ استجابة المبدع → الوكالة (عبر NotificationService).

## الأمان (اختبارات + حيًّا)
- عزل مستأجر + IDOR-safe (المبدع يتصرّف على تعاوناته فقط).
- آلة حالة صارمة؛ العرض يرفض مبدعًا غير موجود.
- بوابة المبدع fail-closed (EnsureCreator + creator_portal.enabled).

## الاختبارات
- **خلفي: 300 إجمالًا**. `CollaborationTest(11)`: عرض+إشعار، دورة كاملة، رفض بسبب، طلب تعديل، منع انتقال غير صالح، رفض مبدع مجهول، وراثة الأجر من المخرَج، تصرّف المبدع عبر HTTP، منع IDOR، ترتيب المطابقة، عزل مستأجر.
- **حيًّا**: الوكالة تعرض CO-1-1 (Renad، 4,500) → المبدع يراه في بوابته → يقبل (مقبول، سجل يميّز الفاعل، إجراء «بدء التنفيذ»).

## التالي
Phase 9 (المحتوى والموافقات) وما بعدها.

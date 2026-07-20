# طلبات انضمام المبدعين

## التدفّق
بوابة عامة (بلا دخول) → مسودة بمرجع عشوائي `CA-`+20 حرفًا (لا ID متسلسل) → تأكيد بريد (OTP) → استكمال (تصنيفات/نبذة/منصات...) → إرسال → مراجعة الوكالة → قبول/رفض/استكمال → عند القبول يُنشأ الحساب والمبدع.

## الحالات
draft · email_verification_pending · phone_verification_pending · submitted · under_review · completion_required · approved · rejected · suspended · withdrawn · archived. كل تحوّل يُسجَّل Append-only (from/to/actor/reason/internal_notes/applicant_message/request_id/occurred_at).

## الأمان
- OTP: يُخزَّن sha256 فقط، صلاحية 10 دقائق، حدّ 5 محاولات، Rate limiting على النقاط.
- منع الطلبات المكرّرة النشِطة لنفس البريد.
- رفع الملفات خاصّ (قرص private).

## القبول (معاملة واحدة — Idempotency: creator-application:approve:{id})
منع القبول المزدوج (lockForUpdate) → منع تكرار مبدع → استهلاك creators.max → إنشاء User+Creator+Membership → نقل platforms/services/portfolio → ربط الطلب → حالة approved → Audit. أي فشل ⇒ Rollback كامل (لا Usage يتيم، لا User بلا Creator).

## تصلّب Phase 4 (fix/harden)
- **الوصول:** المرجع وحده لا يمنح وصولًا. رمز وصول منفصل (`access_token`، sha256، انتهاء 30 يومًا، إلغاء/تدوير) + جلسة متقدّم. كل عملية حسّاسة محروسة؛ محاولات فاشلة تُسجَّل؛ استعادة عبر البريد (رسالة موحّدة، محدودة المعدّل، تدوير الرمز).
- **حلّ المستأجر:** صريح `?a={slug}` (SaaS)، fail-closed، لا "أول مستأجر". راجع `TENANCY-DESIGN.md`.
- **نقل الملفات:** post-commit عبر `FinalizeCreatorFilesJob` (pending→copying→completed/failed، checksum، الأصل يبقى، idempotent، `creators:reconcile-files`). ليس "Fully Atomic" بل معاملة قاعدة بيانات + إتمام idempotent بعد Commit.
- **Rate limiting مركّب:** IP + email + reference لكل عملية.

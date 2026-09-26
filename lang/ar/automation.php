<?php

/**
 * الأتمتة (صفحة القائمة + تسميات المحفّزات/الإجراءات/التذكيرات) — عربي، المصدر الافتراضي.
 * القيم مطابقة حرفيًّا للنصوص التي كانت مضمّنة في الصفحة/المتحكّم حتى لا تتغيّر
 * اللغة الافتراضية؛ الإنجليزية مرآةٌ في en/automation.php.
 */
return [
    // تسميات المحفّزات (TRIGGER_LABEL) — النقاط في المفتاح تُستبدل بشرطة سفلية
    'trig_service_request_created' => 'إنشاء طلب خدمة',
    'trig_service_request_assigned' => 'إسناد طلب',
    'trig_content_approved' => 'اعتماد محتوى',
    'trig_content_submitted' => 'تقديم محتوى',
    'trig_content_revision_requested' => 'طلب تعديل محتوى',
    'trig_creator_declined' => 'اعتذار مبدع',

    // تسميات الإجراءات (ACTION_LABEL)
    'act_notify' => 'إشعار',
    'act_create_task' => 'إنشاء مهمة',
    'act_escalate' => 'تصعيد',

    // وصف المحفّزات (TRIGGER_DESC) — «متى»
    'trigdesc_service_request_created' => 'عند إنشاء طلب خدمة جديد',
    'trigdesc_service_request_assigned' => 'عند إسناد طلب إلى عضو',
    'trigdesc_content_approved' => 'عند اعتماد محتوى',
    'trigdesc_content_submitted' => 'عند تقديم محتوى للمراجعة',
    'trigdesc_content_revision_requested' => 'عند طلب تعديل على محتوى',
    'trigdesc_creator_declined' => 'عند اعتذار مبدع عن التعاون',

    // وصف الإجراءات (ACTION_DESC) — «ماذا»
    'actdesc_notify' => 'يُرسَل إشعار للمعنيّ',
    'actdesc_create_task' => 'تُنشأ مهمة متابعة',
    'actdesc_escalate' => 'يُصعَّد الأمر للمسؤول',

    // جدولة التذكيرات (SCHEDULED_REMINDERS.schedule)
    'sched_daily' => 'يوميًّا',
    'sched_hourly' => 'كل ساعة',

    // وصف التذكيرات المجدولة (SCHEDULED_REMINDERS.desc) — مفتاح كلٍّ حسب key التذكير
    'rmdesc_invoice_overdue' => 'إذا تأخّرت فاتورة مُصدَرة عن موعد استحقاقها ← تذكير المسؤولين لمتابعة التحصيل',
    'rmdesc_content_publishing' => 'إذا اقترب موعد نشر محتوى مُجدوَل أو فات دون نشر ← تذكير المبدع وصاحب الحملة',
    'rmdesc_creator_response' => 'إذا لم يردّ المؤثر على عرض التعاون خلال ٤٨ ساعة ← تذكيره وصاحب العرض',
    'rmdesc_client_decision' => 'إذا لم يبتّ العميل في الترشيح خلال ٧٢ ساعة ← تذكير العميل والوكالة',
    'rmdesc_contract_signature' => 'إذا لم يوقّع الطرف العقد المُرسَل خلال ٧٢ ساعة ← تذكير الطرف وصاحب العقد',
    'rmdesc_sla' => 'إذا تجاوز طلب خدمة موعد استحقاقه ← رصد التجاوز وإشعار المسؤولين',

    // أسماء قواعد النظام (AutomationRule.name) — مفتاح كلٍّ حسب key القاعدة (النقاط ← شرطة سفلية)
    'rule_sys_request_created_confirm' => 'تأكيد استلام الطلب',
    'rule_sys_content_approved_notify_owner' => 'إبلاغ صاحب الحملة باعتماد المحتوى',
    'rule_sys_creator_declined_alert' => 'تنبيه اعتذار مبدع',

    // ===== واجهة الصفحة (Index) =====
    'heading' => 'الأتمتة',
    'eyebrow' => 'التشغيل الذكي',
    'sub' => 'قواعد تعمل تلقائيًّا على أحداث سير العمل — إشعارات ومهام وتصعيد.',

    // تسميات حالة التشغيل (RUN_LABEL + شرائح الدونات)
    'run_executed' => 'نُفِّذت',
    'run_skipped' => 'تُخطّيت',
    'run_failed' => 'فشلت',

    // مركز صحّة الأتمتة
    'last_runs' => 'آخر التشغيلات',
    'rules_enabled' => ':enabled من :total قاعدة مُفعّلة',
    'failed_runs_review' => ':n تشغيلة فاشلة تحتاج مراجعة',
    'no_failures_recent' => 'لا أعطال في آخر التشغيلات',
    'no_runs_health' => 'لا تشغيلات بعد — ستظهر صحّة الأتمتة هنا بمجرّد وقوع أوّل حدث.',

    // قسم القواعد
    'rules' => 'القواعد',
    'system' => 'نظام',
    'enabled_badge' => 'مُفعّلة',
    'disabled_badge' => 'معطّلة',
    'ran_prefix' => 'نُفِّذت',
    'times_suffix' => 'مرة',
    'last_run_label' => 'آخر تنفيذ:',
    'failures_n' => ':n فشل',
    'no_failures' => 'بلا أعطال',
    'disable' => 'تعطيل',
    'enable' => 'تفعيل',

    // قسم التذكيرات المجدولة
    'scheduled_reminders' => 'التذكيرات المجدولة',
    'fired_prefix' => 'أُطلِقت',
    'last_fired_label' => 'آخر إطلاق:',

    // سجلّ التشغيل
    'run_log' => 'سجلّ التشغيل',
    'no_runs' => 'لا تشغيلات بعد.',
    'th_event' => 'الحدث',
    'th_status' => 'الحالة',
    'th_actions' => 'الإجراءات',
    'th_time' => 'الوقت',
    'th_error' => 'خطأ',

    // فاصل نصّ التعريف الصوتيّ (aria) لشرائح الدونات
    'aria_sep' => '، ',
];

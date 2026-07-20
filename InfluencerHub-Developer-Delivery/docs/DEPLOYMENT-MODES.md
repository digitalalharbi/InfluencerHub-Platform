# Deployment Modes (Phase 2)

`DEPLOYMENT_MODE=saas|dedicated|self_hosted` (config/influencerhub.php). لا نطاق ثابت في الكود.

- **saas**: يتطلب اشتراكًا + entitlements. لا اشتراك = لا ميزات مدفوعة.
- **dedicated**: مؤسسة مخصّصة بخطة/License واضحة (اشتراك + overrides).
- **self_hosted**: entitlements محلية من الإعداد (فارغ = unlimited)، بلا افتراض مزود SaaS.

بلا bypass غير موثّق للقيود. الاختبارات تغطّي الأنماط الثلاثة.

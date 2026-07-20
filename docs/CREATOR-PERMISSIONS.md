# صلاحيات المبدعين

## مصفوفة CreatorAbilities (أدوار المؤسسة)
- **VIEW:** super_admin, agency_admin, operations_manager, campaign_manager, creator_manager, agency_employee, viewer.
- **WRITE (إنشاء/تعديل/حذف/قبول):** super_admin, agency_admin, operations_manager, creator_manager.
- دور `influencer`/`ugc_creator` (كمستخدم مؤسسة) لا يرى وحدة إدارة المبدعين — لهم بوابتهم المستقلة.

## بوابة المبدع
المبدع يصل لملفه فقط عبر `EnsureCreator` (السياق من creator.user_id). لا وصول لملفات مبدعين آخرين (IDOR مُختبَر).

## القبول
`approve` يتطلب WRITE + عدم تجاوز creators.max. system_admin يتجاوز عبر Gate::before (مُدقّق).

# مخطّط قاعدة البيانات — InfluencerHub

> مولَّد من قاعدة البيانات الفعلية `influencerhub` بتاريخ 20 يوليو 2026.
> كان هذا الملفّ متوقّفًا عند «Phase 2» ويذكر نحو 30 جدولًا، والقاعدة تحوي
> **116**. فأُعيد توليده من `information_schema` لا من الذاكرة.

## الأرقام

| العنصر | العدد |
|---|---|
| الجداول | **116** |
| مُنطَّقة بـ`tenant_id` | **86** |
| غير مُنطَّقة (كتالوج/نظام) | **30** |
| ملفّات الهجرة | 66 |

## المبادئ

- **كل بيانات تشغيلية تحمل `tenant_id`** ويحرسها `TenantScope` بنمط
  fail-closed: بلا سياق يعود الاستعلام **فارغًا** لا خطأً. انظر
  `TENANT-CONTEXT-SAFETY.md` — الصمت هو العطل.
- **المال بالوحدة الصغرى**: `amount_minor` من نوع bigint، والعملة عمود لا
  ثابت. لا أرقام عشرية في المال.
- **الكتالوج غير مُنطَّق**: `plans` و`features` و`roles` مشتركة بين الجميع.
- **`brand_workspace_relationships` بلا `tenant_id` عمدًا**: الصفّ يربط
  مستأجرَين (مالك العلامة والوكالة المفوَّضة)، فتنطيقه يُعمي أحد الطرفين.
- **جداول التسجيل بلا `tenant_id`**: `brand_signups` و`self_signups` تسبق
  وجود المستأجر.

## الجداول


### التعدّدية والهوية

| الجدول | الأعمدة | مُنطَّق |
|---|---|---|
| `brand_workspace_relationships` | 13 | ✅ |
| `cache` | 3 | — |
| `cache_locks` | 3 | — |
| `client_member_invitations` | 12 | ✅ |
| `client_notes` | 7 | ✅ |
| `creator_invitations` | 18 | ✅ |
| `external_agency_invitations` | 12 | ✅ |
| `failed_jobs` | 7 | — |
| `invitations` | 13 | ✅ |
| `job_batches` | 10 | — |
| `jobs` | 7 | — |
| `migrations` | 3 | — |
| `notes` | 7 | ✅ |
| `organization_add_ons` | 8 | ✅ |
| `organization_memberships` | 9 | ✅ |
| `organizations` | 11 | ✅ |
| `password_reset_tokens` | 3 | — |
| `permission_role` | 2 | — |
| `permissions` | 5 | — |
| `personal_access_tokens` | 10 | — |
| `roles` | 5 | — |
| `sessions` | 6 | — |
| `tenants` | 10 | — |
| `users` | 16 | — |
| `workspaces` | 9 | ✅ |

### الاشتراكات والفوترة

| الجدول | الأعمدة | مُنطَّق |
|---|---|---|
| `add_ons` | 8 | — |
| `coupon_redemptions` | 7 | ✅ |
| `coupons` | 11 | — |
| `features` | 6 | — |
| `plan_entitlements` | 7 | — |
| `plan_prices` | 8 | — |
| `plan_versions` | 8 | — |
| `plans` | 7 | — |
| `subscription_events` | 7 | — |
| `subscription_items` | 6 | — |
| `subscriptions` | 13 | ✅ |
| `usage_aggregates` | 9 | ✅ |
| `usage_records` | 11 | ✅ |

### العملاء والعلامات

| الجدول | الأعمدة | مُنطَّق |
|---|---|---|
| `brand_claim_documents` | 10 | — |
| `brand_claim_requests` | 19 | — |
| `brand_review_decisions` | 8 | ✅ |
| `brand_signups` | 26 | — |
| `brand_social_accounts` | 8 | ✅ |
| `brand_status_history` | 9 | ✅ |
| `brand_versions` | 7 | ✅ |
| `brands` | 32 | ✅ |
| `client_addresses` | 23 | ✅ |
| `client_billing_profiles` | 16 | ✅ |
| `client_contacts` | 15 | ✅ |
| `client_document_access_logs` | 9 | ✅ |
| `client_document_reviews` | 7 | ✅ |
| `client_document_versions` | 9 | ✅ |
| `client_documents` | 24 | ✅ |
| `client_member_status_history` | 7 | ✅ |
| `client_members` | 13 | ✅ |
| `client_profile_change_requests` | 11 | ✅ |
| `client_profile_status_history` | 8 | ✅ |
| `client_status_history` | 7 | ✅ |
| `clients` | 33 | ✅ |
| `partner_client_links` | 10 | ✅ |

### المبدعون

| الجدول | الأعمدة | مُنطَّق |
|---|---|---|
| `creator_application_access_attempts` | 6 | — |
| `creator_application_document_access_logs` | 8 | ✅ |
| `creator_application_document_versions` | 9 | ✅ |
| `creator_application_documents` | 21 | ✅ |
| `creator_application_messages` | 7 | ✅ |
| `creator_application_platforms` | 16 | ✅ |
| `creator_application_portfolios` | 13 | ✅ |
| `creator_application_reviews` | 7 | ✅ |
| `creator_application_services` | 13 | ✅ |
| `creator_application_status_history` | 11 | ✅ |
| `creator_application_verifications` | 9 | ✅ |
| `creator_applications` | 50 | ✅ |
| `creator_capabilities` | 13 | ✅ |
| `creator_categories` | 9 | ✅ |
| `creator_platforms` | 9 | ✅ |
| `creator_portfolios` | 15 | ✅ |
| `creator_services` | 13 | ✅ |
| `creators` | 37 | ✅ |
| `publishers` | 24 | ✅ |

### الطلبات والحملات

| الجدول | الأعمدة | مُنطَّق |
|---|---|---|
| `campaign_deliverables` | 14 | ✅ |
| `campaign_shortlist_items` | 12 | ✅ |
| `campaign_shortlist_versions` | 9 | ✅ |
| `campaign_shortlists` | 8 | ✅ |
| `campaign_status_history` | 8 | ✅ |
| `campaigns` | 17 | ✅ |
| `collaboration_status_history` | 9 | ✅ |
| `collaborations` | 22 | ✅ |
| `demo_requests` | 18 | — |
| `service_request_comments` | 8 | ✅ |
| `service_request_status_history` | 8 | ✅ |
| `service_requests` | 28 | ✅ |
| `signup_requests` | 20 | — |

### المحتوى

| الجدول | الأعمدة | مُنطَّق |
|---|---|---|
| `content_approvals` | 10 | ✅ |
| `content_items` | 30 | ✅ |
| `content_status_history` | 9 | ✅ |

### العقود والمستندات

| الجدول | الأعمدة | مُنطَّق |
|---|---|---|
| `contract_status_history` | 9 | ✅ |
| `contracts` | 23 | ✅ |

### المالية

| الجدول | الأعمدة | مُنطَّق |
|---|---|---|
| `invoice_items` | 11 | ✅ |
| `invoice_payments` | 13 | ✅ |
| `invoice_status_history` | 10 | ✅ |
| `invoices` | 24 | ✅ |
| `payout_status_history` | 8 | ✅ |
| `payouts` | 19 | ✅ |

### الشركاء

| الجدول | الأعمدة | مُنطَّق |
|---|---|---|
| `external_agency_members` | 13 | ✅ |
| `external_agency_status_history` | 8 | ✅ |

### التدقيق والإشعارات

| الجدول | الأعمدة | مُنطَّق |
|---|---|---|
| `audit_logs` | 15 | ✅ |
| `import_batches` | 9 | ✅ |
| `notification_delivery_attempts` | 7 | ✅ |
| `notification_preferences` | 9 | ✅ |
| `notifications` | 14 | ✅ |

### أخرى

| الجدول | الأعمدة | مُنطَّق |
|---|---|---|
| `automation_log` | 7 | ✅ |
| `custom_field_definitions` | 11 | ✅ |
| `custom_field_options` | 8 | ✅ |
| `custom_field_values` | 8 | ✅ |
| `external_agencies` | 20 | ✅ |
| `self_signups` | 15 | — |

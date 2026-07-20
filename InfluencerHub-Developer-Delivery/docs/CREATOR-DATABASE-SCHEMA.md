# مخطط قاعدة بيانات المبدعين (Phase 4)

## المبدع
- **creators:** id, tenant_id, creator_number(CR-{t}-{seq}), type, display_name, professional_name, handle, email, phone, whatsapp, city, country_code, gender, languages(json), primary_platform, followers_count, content_categories(json), status, rate_per_post_minor, bio, user_id, mowthooq_*(license/expires/status), beneficiary_name, bank_name, **iban_encrypted**, iban_last4, financial_verification_status, created_by, timestamps, softDeletes.
- **creator_platforms / creator_services / creator_portfolios:** فرعية (tenant_id, creator_id, ...). أموال بـprice_minor.
- **creator_categories:** tenant_id(null=عام), slug, name_ar, name_en, sort_order, is_active.

## الطلبات
- **creator_applications:** id, tenant_id, **reference(unique, عشوائي)**, status, account_type, بيانات أساسية، categories(json), current_step, email/phone_verified_at, terms/privacy_accepted_at, assigned_reviewer_id, submitted_at/reviewed_at, rejection_reason, creator_id/user_id(بعد القبول), mowthooq_*, financial_*(iban_encrypted+last4), timestamps, softDeletes.
- **creator_application_platforms / services / portfolios / documents:** فرعية.
- **creator_application_messages / reviews:** created_at فقط.
- **creator_application_status_history:** Append-only (from_status, to_status, actor_id, reason, internal_notes, applicant_message, request_id, occurred_at).
- **creator_application_verifications:** channel, **code_hash(sha256)**, expires_at, attempts, verified_at.

## ملاحظات نزاهة
- IBAN لا يُخزَّن خامًا (Crypt). OTP لا يُخزَّن خامًا (sha256).
- سجل الحالة Append-only على مستوى التطبيق.

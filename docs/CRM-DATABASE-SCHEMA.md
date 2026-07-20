# CRM Database Schema (Phase 3)

- **clients**: tenant_id, client_number(unique/tenant), type, legal_name, display_name, status, sector, website, email, phone, whatsapp, country_code, city, address, commercial_registration_number, commercial_registration_expiry, tax_number, vat_registered, preferred_language, acquisition_source, account_manager_id, created_by, updated_by, archived_at, timestamps, soft_deletes. Index (tenant_id,status).
- **brands**: tenant_id, client_id, name, slug(unique/tenant), logo_path, sector, website, description, tone_of_voice, target_audience, brand_guidelines_path, status, soft_deletes.
- **client_contacts**: tenant_id, client_id, name, job_title, department, email, phone, whatsapp, is_primary, preferred_channel, notes, soft_deletes.
- **client_notes**: tenant_id, client_id, author_id, body (داخلية).
- **client_status_history**: tenant_id, client_id, from_status, to_status, changed_by, created_at (append-only).

**قيود:** unique(tenant_id,client_number)، unique(tenant_id,slug)، FK قابلة للتراجع، TenantScope fail-closed على الكل، tenant_id صريح.
**متبقٍّ (موثّق):** client_members, client_addresses, client_documents (خاصة/موقّعة), client_custom_fields (definitions+values).

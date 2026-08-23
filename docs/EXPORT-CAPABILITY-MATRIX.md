# Export Capability Matrix

Reusable export engine — one `TabularData` (title, columns, rows, meta, workspace, generatedAt) rendered to CSV / XLSX / PDF. Authorized streaming (no public URLs); every export audit-logged (`export.generated`: actor, tenant, type, format, rows).

## Formats (engine — PRODUCTION_VERIFIED via generated files)

| Format | Writer | Library | Proven |
|---|---|---|---|
| CSV | `CsvWriter` | native (streamed) | UTF-8 **BOM** so Excel reads Arabic; streamed for large data |
| XLSX | `XlsxWriter` | `phpoffice/phpspreadsheet ^5.9` | **real ZIP** (not renamed CSV), RTL sheet, bold branded header, autosize, frozen header, autofilter; re-read & asserted in tests |
| PDF | `PdfWriter` | `mpdf/mpdf ^8.3` | **Arabic RTL** table, branded header/footer, page numbering; visually inspected — Arabic readable, RTL correct, numbers LTR |

Verification: `ExportEngineTest` (3) generates all three from Arabic data — CSV BOM+Arabic+rows; XLSX real ZIP re-loaded (RTL + Arabic cells); PDF `%PDF-` header + non-trivial size. Sample PDF rendered and visually confirmed.

## Security
- No public predictable URLs — streamed through authorized controllers only.
- Bulk exports (contacts) audit-logged with row counts.
- Large exports should be queued (SyncRun-style) — the writers stream from a temp file / generator; queued-export delivery is a follow-up unit.

## Module wiring (next units — each: filtered list → export in the same filters)

| Module | CSV | XLSX | PDF | Status |
|---|---|---|---|---|
| Clients | ✓ | ✓ | ✓ | wiring pending (engine ready) |
| Creators (permission-scoped) | ✓ | ✓ | — | wiring pending |
| Campaigns (report) | — | ✓ | ✓ | wiring pending |
| Shortlist (client PDF / internal XLSX) | — | ✓ | ✓ | wiring pending |
| Invoices / Payouts (statement PDF) | — | ✓ | ✓ | wiring pending |
| Reports | ✓ | ✓ | ✓ | wiring pending |

Export filters must mirror the on-screen list (same status/date/client/platform) — enforced when each module is wired.

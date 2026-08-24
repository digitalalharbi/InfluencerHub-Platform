# PRODUCT-COMPLEXITY-AUDIT

Baseline complexity of InfluencerHub captured **before** the simplification mission, from
the real navigation source (`resources/js/lib/nav.ts`), controllers, and a live logged-in
walkthrough (agency admin, local server). This is the measuring stick: every simplification
PR updates the matching row's *After* + *Implemented?* + *Production verified?* columns.

> Method: destinations counted from `nav.ts` per role (gated items counted for the role that
> sees them); live agency count confirmed in-browser = **26** for an agency admin
> (`creator_database` not entitled on that tenant; +1 when entitled = 27, +dev preview = 28).

## 1. Sidebar destinations by role (BEFORE)

| Role | Nav source | Groups | Primary destinations (flat, all always visible) |
|---|---|---:|---:|
| Agency — admin | `agencyNav` | 6 | **26** (live) — work 3, relationships 5, execution 5, finance 2, intelligence 4, admin 7 |
| Agency — plain operator (no admin/reviews) | `agencyNav` filtered | 5 | ~18 |
| Client portal | `clientNav` | 3 | 9 — overview 1, work 5, account 3 |
| Creator portal | `creatorNav` | 3 | 7 — overview 1, work 3, finance 3 |
| Partner agency | `partnerNav` | 1 | 3 |
| System admin | `adminNav` | 4 | 8 |
| Brand workspace | `brandNav` | 4 | 13 |

**Core problem:** the agency operator faces ~26 flat destinations with no primary/secondary
distinction — every module (execution steps, finance, admin tooling) sits at the same depth.
Target (mission §13): ~5–8 primary destinations for a normal operator; the rest under
*More / Administration / Settings* or contextual to an entity.

## 2. Campaign journey (BEFORE)

The canonical lifecycle is **13 stages** (`CampaignLifecycleService::STAGES`) spanning **7
separate sidebar modules**: Campaigns, Shortlisting, Collaborations, Content, Contracts,
Invoices, Payouts. To operate one campaign end-to-end today a user leaves the campaign to
separate top-level modules repeatedly.

- Campaign detail (`Campaigns/Show.tsx`) **already** has an in-page tab strip (`WorkTabs`)
  with 4 tabs: deliverables, collaborations, content, finance(invoices).
- **Not yet in the campaign page** (require leaving to a separate module): Shortlist
  (link-out today), Contracts (separate `/contracts`), Payouts (separate `/payouts`).
- Context switches to run a full campaign (create → nominate → client decision → contract →
  booking → content → publish → invoice → payout → close): **≈7 module switches** minimum,
  several with repeated re-orientation (find the campaign again inside each module's flat list).

*After target:* one Campaign Workspace; the 3 missing steps become tabs; context switches for
the core loop drop toward **1** (stay on the campaign).

## 3. Duplicate / overlapping surfaces (BEFORE)

| Concept | Surfaces today | Overlap finding (evidence) |
|---|---|---|
| "My work" | `/my-tasks` page **and** Dashboard `myWork` block | `/my-tasks` (MyTasksController) is a strict subset of `OperationalDashboard::myWork()` (7 sources, priority-ranked, role-gated). Redundant copy. |
| Shortlist | global `/shortlisting`, per-campaign `/campaigns/{id}/shortlist`, client proposal | Same domain (`CampaignShortlist`); the campaign-scoped one is the operational surface. Global list = cross-campaign index. |
| Brands | top-level `/brands` **and** Client detail → brands tab | `BrandsController::index` already redirects create/empty to `/clients/{sole}?tab=brands`. Brand is a client sub-entity. |
| Content | global `/content`, Campaign content tab, Creator content tab, Client approvals | One `ContentItem` model; context-scoped views. Global page = cross-workspace review queue. |
| Contracts / Invoices / Payouts | global modules **and** Campaign/Client/Creator tabs | All carry `campaign_id`; entity tabs are the natural home, globals are search/admin indexes. |
| Collaborations | global `/collaborations`, Campaign tab, Creator tab | `Collaboration = Campaign × Creator`; belongs inside those entities. |
| Requests vs Campaign | `/service-requests` + `/campaigns` | **Not** duplication — request is intake, converts 1:1 to campaign via `source_request_id` (idempotent `convertToCampaign`). Keep as intake. |

## 4. Status/enum surface (BEFORE)

Technical status values shown to users (single map: `lang/ar/statuses.php`, with a partial
`tone` collapse already): Campaign 13-stage + 6-status, Content 9, Collaboration 8, Contract 7,
Payout 7, Invoice 6, ServiceRequest 7. Simplification target: map to ≤5 human states
(مسودة / بانتظار إجراء / قيد التنفيذ / محظور / مكتمل) with the technical value available on detail.

## 5. Terminology drift (BEFORE)

`nav.ts` is migrated to `docs/PRODUCT-TERMINOLOGY.md`, but `lang/ar/navigation.php` +
`lang/ar/entities.php` still ship banned long forms (العلامات التجارية، طلبات الخدمة،
المحتوى والموافقات) and the المبدعون/المؤثرون/صناع المحتوى overlap; creator-portal nav
mislabels payouts (المدفوعات = payments) as المستحقات. 5 of 7 audited concepts INCONSISTENT.

---

## Complexity matrix (living — update as PRs land)

| Role | Journey | Current pages | Current clicks/switches | Duplicate concepts | Problem | Proposed simplification | Implemented? | Prod verified? |
|---|---|---|---|---|---|---|---|---|
| Agency op | daily entry | Dashboard + `/my-tasks` | 2 surfaces | My Tasks ⊂ Dashboard myWork | two "what's mine" views | one **عملي** (My Work) built on `OperationalDashboard::myWork` | ⏳ | ⏳ |
| Agency op | run a campaign | 7 modules | ≈7 switches | shortlist/content/contract/finance split | leaves campaign repeatedly | **Campaign Workspace** (Overview + 8 tabs) | ⏳ | ⏳ |
| Agency op | navigate | 26 flat items | — | execution+finance+admin flat | no primary/secondary | role-aware nav: ~7 primary + Administration | ⏳ | ⏳ |
| Agency | manage a client | `/clients` + `/brands` + `/campaigns` + `/invoices` | ≥4 modules | brands/campaigns/finance also top-level | client data scattered | Client workspace primary (already tabbed); demote globals | partial (page exists) | ⏳ |
| Agency | manage a creator | `/creators` + `/collaborations` + `/contracts` + `/payouts` | ≥4 modules | same | creator data scattered | Creator workspace primary (already tabbed) | partial (page exists) | ⏳ |
| Client | decide | client portal 9 items | — | — | mostly OK, can tighten to decisions | client home = "needs my decision" | ⏳ | ⏳ |
| Creator | deliver | creator portal 7 items | — | payouts mislabeled | terminology | fix label + tighten | ⏳ | ⏳ |
| All | terminology | — | — | 5/7 concepts inconsistent | drift in lang files | reconcile `navigation.php`/`entities.php` to doc | ⏳ | ⏳ |

## Complexity targets (mission §53 — directional, not vanity)

- Agency primary navigation: **26 → ~7** primary + Administration (authorized only).
- Core campaign loop context switches: **≈7 → ≈1**.
- Duplicate primary destinations removed (only after actions proven elsewhere, §64).
- Statuses shown by default: technical enums → ≤5 human states with detail on demand.
- One Arabic name per concept (0 banned forms in shipped lang files).

*No capability is removed; every "demoted" module keeps a route (global index/search) and
its actions remain reachable from the entity workspace before it leaves primary nav (§64).*

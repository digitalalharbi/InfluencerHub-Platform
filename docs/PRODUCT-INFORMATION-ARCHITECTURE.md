# PRODUCT-INFORMATION-ARCHITECTURE

How InfluencerHub's surfaces are organized today, the problems, and the target
information architecture. Companion to `PRODUCT-COMPLEXITY-AUDIT.md` (numbers) and
`PRODUCT-JOURNEYS.md` (flows). Source of truth for nav labels remains
`docs/PRODUCT-TERMINOLOGY.md`.

## Principle

Move from **Module → Module → Module** to **Entity → Complete Workspace**. Key entities —
Campaign, Client, Creator, Brand — each become a complete operational surface; the workflow
steps that were separate sidebar modules (shortlist, collaboration, content, contract,
finance) become **tabs inside the entity** where they're actually used. Standalone module
pages survive as cross-entity index/search/admin views, not as the primary way to operate.

## Current IA (agency, `agencyNav` — 6 groups, ~26 items)

```
العمل            لوحة التحكم · مهامي · الطلبات
العلاقات         العملاء · العلامات · صناع المحتوى · [قاعدة المؤثرين] · الناشرون · طلبات الانضمام
التنفيذ          الحملات · الترشيحات · التعاونات · المحتوى · العقود
المالية          الفواتير · المستحقات
الذكاء           التقارير · الأتمتة · مركز التصدير · التكاملات
الإدارة          مراجعة العلامات · مراجعات العملاء · الوكالات الشريكة · الفريق · الإعدادات · صحّة النظام · حسابي
```

### Problems
1. **No primary/secondary hierarchy** — a daily execution step (المحتوى) and a rare admin
   tool (صحّة النظام) sit at equal depth. 26 flat items.
2. **التنفيذ group is the campaign, exploded** — الترشيحات/التعاونات/المحتوى/العقود are all
   facets of a campaign, presented as if independent products. This is the biggest driver of
   context-switching (7 modules to run one campaign).
3. **Finance split from where it's decided** — الفواتير/المستحقات live away from the campaign
   and client that generate them.
4. **العلامات is a child of العميل** presented as a sibling (BrandsController already redirects
   to the client when there's a single client).
5. **الذكاء mixes analysis with operator tooling** — التقارير (analysis) next to الأتمتة/التكاملات
   (admin configuration) and مركز التصدير (download history, not a product).
6. **Admin + personal + platform tooling flattened** into الإدارة (reviews, partners, team,
   settings, system health, account) with no separation of *my settings* vs *workspace admin*
   vs *platform health*.

## Proposed IA (agency)

Role-aware. A **normal operator** sees ~7 primary destinations; authorized roles additionally
see an **Administration** section (collapsible / secondary). Nothing is deleted — demoted
modules keep routes and become index/search views reachable from *More* or the entity.

```
PRIMARY (everyone in the workspace, permission-filtered)
  الرئيسية        (Dashboard — business overview)
  عملي            (My Work — my actionable items; unifies /my-tasks + dashboard myWork)
  الطلبات         (Intake; converts to campaigns)
  الحملات         (Campaign Workspace: Overview · Creators · Deliverables · Content ·
                   Schedule · Client Decisions · Contracts · Finance · Documents · Activity)
  العملاء         (Client Workspace: Brands · Campaigns · Content · Contracts · Finance · Team)
  صناع المحتوى     (Creator Workspace: Platforms · Campaigns · Content · Contracts · Finance;
                   قاعدة المؤثرين = Discovery tab/entry; الناشرون + طلبات الانضمام as sources)
  المالية          (Collection from clients · Creator payouts · Financial overview — kept separate)
  التقارير         (Analysis; Export history lives here, not primary)

ADMINISTRATION (authorized roles only — secondary/collapsible)
  المراجعات        (brand reviews + client reviews — a review queue)
  الوكالات الشريكة
  الفريق · الإعدادات (Workspace · Team · Permissions · Notifications · Integrations · Automation · Billing)
  الأتمتة · التكاملات
  صحّة النظام       (or moved to System Admin area)

PERSONAL
  حسابي · تسجيل الخروج (already in the shell footer/menu)
```

### Contextual (moved INTO entities, demoted from primary)
| Was top-level module | Primary home now | Standalone page kept as |
|---|---|---|
| الترشيحات | Campaign → Creators/Shortlist tab | cross-campaign shortlist index |
| التعاونات | Campaign → Creators tab / Creator → Campaigns | cross-campaign collaboration index |
| المحتوى | Campaign → Content / Creator → Content | global content review queue |
| العقود | Campaign/Client/Creator → Contracts tab | global contract search/admin |
| الفواتير / المستحقات | Campaign/Client → Finance | finance module (kept — real separation) |
| العلامات | Client → Brands tab | cross-client brand review list |
| مركز التصدير | Reports → Export history | (not primary) |

### Merged / clarified concepts
- **My Tasks + Dashboard myWork → عملي** (one engine: `OperationalDashboard::myWork`).
- **قاعدة المؤثرين = Discovery**, bridging into tenant Creator (materialize on nominate);
  not a parallel creator-management system.
- **Publishers + Applications = ingress sources** into Creators, not separate destinations.

## Role visibility (who sees what — enforced by existing `nav.can` + policies)

| Destination | Agency op | Agency admin | Client | Creator | Partner | Sysadmin |
|---|---|---|---|---|---|---|
| الرئيسية / عملي | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| الحملات (workspace) | ✓ | ✓ | read (their campaigns) | read (their collabs) | scoped | — |
| العملاء | ✓ | ✓ | — | — | scoped | — |
| صناع المحتوى | ✓ | ✓ | — | — | — | — |
| المالية | finance role | ✓ | own invoices | own payouts | — | — |
| Administration | — | ✓ | — | — | — | platform |
| صحّة النظام / platform | — | ✓ (workspace) | — | — | — | ✓ |

RBAC is unchanged — simplification only *reorganizes* what a role already may see; the server
policies and tenant scope remain the enforcement layer (§60). Client-/creator-safe serializers
(`PoolCreator::toSharedArray`, portal controllers) are untouched (§61).

## Retained standalone modules (not everything nests)

- **الطلبات** (intake, converts to campaigns) — its own primary destination.
- **المالية** — kept distinct (collection ≠ payout ≠ profit; §31/§62), even though also shown
  as entity Finance tabs.
- **التقارير** — cross-entity analysis.
- Global index/search pages for shortlist/content/contracts remain reachable (search, cross-
  campaign management) but are demoted out of primary nav.

## Migration & safety
- **URL compatibility (§59):** demoting a module from nav does **not** remove its route;
  email deep links, bookmarks, notification `action_url`s keep working. New consolidated tabs
  use query/hash (`?tab=`) on the entity route; old module routes redirect where merged.
- **No schema merges for UI consolidation (§57):** Shortlist/Collaboration/Content/Contract
  stay distinct domain models; consolidation is UI/workflow only.
- **Order (§65/§66):** Campaign Workspace first (benchmark), then Client & Creator apply the
  same pattern, then navigation simplification (only after entity tabs prove the actions),
  then finance/reports/settings, then mobile.

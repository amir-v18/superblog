# Superblog Architecture Blueprint

## 1. Purpose and decision status

Superblog is one Laravel application that centrally manages multiple public content websites. Public sites are server-rendered with Blade, and the central application manages their content and settings.

This document is the source of truth for the intended architecture and product rules. A status of **planned** means the decision is intentional but the feature is not implemented. A **confirmed** rule is an agreed product or architecture decision. An **open** item has not been decided. A difference between the current code and a planned or confirmed rule is an implementation gap; it does not mean the rule was accidental or outdated.

Decisions confirmed during the architecture review on 10 November 2026 are incorporated below. Open items are listed in the relevant sections and in Section 15.

## 2. Technology and deployment shape

* Laravel 13
* PHP 8.5+
* Blade
* MySQL 8.4 for the application database
* Vite, Tailwind and vanilla JavaScript/Alpine where appropriate
* Git and GitHub Actions

React and Next.js are not part of V1. Public Superblogs remain server-rendered HTML; primary content must not depend on JavaScript to render.

The application is one centrally managed Laravel installation serving the admin application and multiple public domains. The admin and login routes are served on the central application host only. A Superblog's configured domain serves that Superblog's public pages, not the admin interface.

Production runs MySQL. The automated test suite uses SQLite in memory, so SQLite tests do not prove MySQL-specific migration or locking behavior. Test schema changes against a disposable MySQL database before production deployment. Use forward migrations, back up production data before destructive data changes, and never use database-wiping setup commands against a real environment.

## 3. Core content and source rules

**RSS supplies source material. AI transforms it. Laravel validates it. MySQL preserves the source and application state. Templates render it.**

AI is a processing mechanism, never an article source type. Valid article source types are:

* `rss`
* `manual`

An RSS-derived article originates from an RSS item and can be traced through `Article → RSS Item → RSS Source → Original URL` while those source records exist. Each RSS item can create at most one article. A manual article has no RSS item. Deleting an RSS item or source may remove that traceability; preserving it is not required for now.

The original RSS item is an immutable first-seen snapshot while it exists. AI processing and staff edits change the article, never the original RSS record. Deleting an RSS item or source must preserve the linked article, but may detach it from the deleted source record. Do not introduce an `ai` source type or create RSS-derived articles without an RSS item.

## 4. Main product domains

### Superblogs and domains

A Superblog represents one public website and has a name, a selected template, site settings, publishing settings, RSS sources, articles, and one current public domain.

The request host/domain identifies the Superblog. There is no Superblog slug in public URLs. A published Superblog is resolved at the root of its exact configured domain. Article pages use `/articles/{article-slug}` on that domain. Only the currently configured domain works; changing the domain does not preserve the old domain as an alias or redirect.

A domain may be empty while the Superblog is being created or edited. It is required before publishing and must have a valid domain format. DNS/hosting verification is not part of the initial publishing check. Until a domain is configured and points to the application, the editor's authenticated preview is the local way to view the Superblog.

Domain resolution must not make admin routes available on customer domains. Public requests must only render Superblogs whose site status is published. Unpublished Superblogs and their articles remain available in the authorized admin preview but return 404 on public domains.

Status: host-based public routing and central-host-only admin routing are confirmed targets, not yet implemented.

### Templates

Templates are a fixed code-backed catalog. Admins cannot create or delete templates. An admin may rename an existing template and change its active checkbox. The name is unique. Inactive templates are not offered to new Superblogs, but existing Superblogs keep their selected template and remain editable.

Each template has an immutable internal key that is not displayed or editable in the admin UI. Code uses this key to select the homepage view, article view, theme defaults, and seed record. The editable name must never identify implementation behavior.

The intended persisted template data is an internal key, unique name, active state, ID, and timestamps. Template presentation behavior belongs in the code registry. The unused editable slug, description, generic public layout, and settings fields should be retired through a safe migration after rendering and seeding use the internal key. Existing rows must be explicitly mapped to keys; do not guess identity from editable names or slugs. Preserve inactive templates already assigned to existing Superblogs.

The template default remains code-controlled (`template-modern`).

A Superblog's template is selected during creation and cannot be changed afterward. The editor displays the assigned template as read-only information. Updates may omit `template_id`; if a request includes it, it must match the existing assignment. This keeps existing assignments valid even after their template becomes inactive.

Each template supplies both its homepage and article-page view. Public article pages must match their Superblog's selected template. The template registry also supplies the default theme and the default template used for newly created Superblogs. The catalog/default remains code-controlled; the database name is an admin label.

### Articles

Articles are the final content shown on a Superblog. They may be RSS-derived or manual. Article selection for the website and actual article publication status are separate values:

* `is_selected_for_publication` controls whether the article is included in the website content.
* `status` (`draft` or `published`) records whether that article has actually been published.

Saving selection on a live Superblog synchronizes the current page's selected articles with article publication. On an offline Superblog, saving changes selection only. Taking a whole Superblog offline changes the Superblog status only; it does not withdraw articles or alter their dates.

Public content includes only articles that are selected, published, and have a valid publication timestamp, and only when their Superblog is published. The public timeline is one combined chronological feed, newest first, for manual and RSS articles. The “Mest lästa artiklar just nu” sidebar is a chronological list of selected, published manual articles only. It is not a popularity ranking.

Manual articles have one optional image and are always published by an explicit editor action. RSS-derived articles may be transformed and receive a generated image. If Auto publish is disabled, a newly AI-processed article starts as an unchecked draft until an editor reviews it. Auto publish applies only to RSS-derived AI output, never to manual articles.

New manual articles and non-AI RSS drafts use the existing checked-by-default selection behavior. The AI pipeline is the exception when Auto publish is off: its drafts start unchecked.

The legacy `is_locked` article flag must not hide or truncate public content while reader authentication/paywalls are deferred. It may remain as a legacy field until a safe schema review, but the public page must not offer the admin/staff login as a reader login.

Article slugs identify individual articles. An editor may change a slug after publication; the old URL does not redirect and will return 404. This is an accepted SEO/link-stability trade-off.

### Article editing and presentation

Excerpts are removed for now. The public meta description is generated automatically from the first paragraph of the article body; it is not an independently edited excerpt or SEO-description field. RSS and manual article pages use the same article layout. The shared “Källor” box lists actual RSS sources and the brand website. The visual editor has one link insertion control: selected article text becomes the link text, and the editor chooses a normal link or a product/service backlink. Product/service links remain ArticleLink records and matching inline links render as sponsored; they do not appear in the Källor box. Do not add repeated generic link sentences to every new article.

The editor should feel like a document editor, not Markdown or a full-featured rich-text suite. Its essential controls are headings, bold, italic, links, text size and ordered/unordered lists. It does not need tables, embeds, color, or font controls. Article bodies use versioned JSON documents in the existing `articles.content` LONGTEXT column, identified by `content_format` (`legacy_html` or `blocks_v1`). Structured inline runs store plain text, approved formatting marks and safe links; browser-supplied HTML is not authoritative. Renderers escape text and emit only approved semantic HTML. Existing HTML remains readable as `legacy_html` until safely converted. The article title supplies the page's only H1; the body starts with exactly one introduction paragraph, and an H3 requires a preceding H2. Stable UUIDs identify blocks and list items for future targeted AI edits. Brand and product/service backlinks remain separate ArticleLink records; brand links render in Källor, while product/service links render inline in selected article text. Manual article images are handled separately from the text editor.

Only the current article version is stored. For RSS articles, the original first-seen RSS item remains available separately; there is no general article revision-history system in V1.

Status: the inline visual editor, structured `blocks_v1` creation/editing, separate backlink editing, safe legacy conversion/fallback, structured public rendering, and one-image article media workflow are implemented. New manual articles use `blocks_v1`. Existing RSS-created and legacy articles remain `legacy_html` until a supported edit converts them; unsupported legacy structure remains safely editable in legacy mode. No bulk conversion or AI/image-generation workflow is implemented.

### RSS and AI processing

Scheduled RSS fetching is configurable by an admin from the admin UI. The interval has safe presets and a custom value constrained to 15 minutes through 24 hours. The daily article cap is per RSS source and resets on the Europe/Stockholm calendar day. If a feed has more eligible new items than its cap, newest eligible items use the available slots; items beyond the cap are skipped rather than queued as a backlog.

RSS fetching and AI/image work use Laravel background jobs so external requests do not block admin requests and failures can be retried. The existing database queue is the simplest initial queue backend. A scheduled task dispatches feed work at the configured interval. The item cap is applied per source even when the scheduler runs multiple times in a day.

The Superblog-wide Auto publish setting controls only RSS-derived AI articles. Admins and assigned editors may change this setting. Other Superblog settings, appearance, and RSS source management remain unavailable to editors. With Auto publish off, AI output is an unchecked draft for human review. With it on, the article is published only after rewriting, image generation, and required validation all succeed. If processing or validation fails, the item remains a draft and creates a warning; one failed item does not publish partial output.

The daily cap must not silently turn into a backlog across repeated scheduled fetches. During implementation, persist enough identity/state to ensure over-cap items are not accidentally processed as though the cap had never been reached. The provider for text rewriting and image generation remains open; do not add real credentials to source control.

Status: automatic scheduling, quotas, AI/image jobs, and Auto publish are planned. Manual RSS fetching and the database queue infrastructure already exist.

### SEO and publication dates

SEO is a platform responsibility. Laravel must enforce required SEO/content validation before either manual or automatic publication; failed required checks block publication until corrected. Automatically generated metadata should be reused rather than duplicated in the article editor.

The platform should provide a canonical URL, one H1, a logical heading hierarchy, semantic HTML, appropriate image alt text, structured data, Open Graph metadata, robots directives, sitemap, and `robots.txt` as applicable. Article title is the page H1. A meta description is derived automatically from the first paragraph. Brand/product/service links are useful optional content, not universal publication requirements.

`published_at` means the first-publication date. Withdrawal and republishing do not clear or replace it. A separate reader-facing `content_modified_at` timestamp changes only when public-visible article content changes (title, body/formatting, or image). Publishing, republishing, selection changes, and taking a whole Superblog offline do not update it. Future changes to public-facing ArticleLink records must also update it. Do not use the generic technical `updated_at` as this value if selection or internal status changes can touch it. Expose the first publication date as `datePublished` and the content-modified date as `dateModified` in appropriate HTML/structured data.

Status: automatic SEO metadata and separate first-publication/content-modified date behavior are implemented in part. Required publication validation remains planned.

### Roles and permissions

There may be multiple admin accounts, and all admins have the same privileges. Other approved staff are editors. The application must allow an operator to explicitly provision additional admins; it must not enforce a one-admin limit. Google email matching and automatic account creation must never grant admin access.

An editor can only access explicitly assigned Superblogs. Ownership/creator `user_id` does not grant access. The legacy `has_all_superblogs_access` flag must not grant ordinary editors access and should be retired after existing data is safely reviewed.

Editors may:

* Create, edit, publish, and delete articles in assigned Superblogs.
* Remove a departing customer's backlinks.
* See warnings and inform an admin, who resolves them.
* Change the assigned Superblog's Auto publish setting (the explicit exception for this workflow).

Editors may not manage staff, templates, RSS sources, Superblog Grund settings, or appearance. The last permission follows the product decision to keep editor permissions limited to the documented list, with only the Auto publish exception above.

Admins manage the central application, staff approval/assignments, templates, RSS settings, site settings, and the global fetch interval. Approval applies to admins as well; pending and rejected accounts cannot use admin functionality. Admins share these privileges rather than forming a hierarchy among themselves.

New Google identities may create only ordinary pending staff accounts. Existing accounts are associated by an operator-controlled mapping using Google's stable subject identifier, never silently by email. Existing approval, flags, user IDs, and Superblog assignments are preserved when an identity is mapped.

### Customer backlink removal

When a customer leaves, the application searches stored article links and links in article body content by the exact URL host. `www` and subdomains are different hosts. There is no customer table in V1.

Remove matching customer/product/service links, not the article. When a product link is embedded in article text, remove the entire sentence containing that saved product-link anchor. Remove other matching link markup while retaining its surrounding sentence. After cleanup, check whether any saved customer/product anchor text remains in the article text. If none remains, leave the article's publication state and selection unchanged. If anchor text remains, cleanup failed: set the article to draft, clear its publication selection to prevent a later Innehåll save from republishing it, preserve its first `published_at` value, and create a warning visible to admins and editors. Do not unpublish solely because a customer or product is mentioned in generic text when no saved anchor text remains.

Status: planned; the exact-host rule and article behavior are confirmed.

### Warnings

Warnings are persistent application records, not just one-off flash messages. Admins and editors can see them; editors can inform an admin but cannot resolve them. Admins resolve the underlying problem. Examples include an RSS connection failure, an AI rewrite/image failure, or saved customer/product anchor text that remains after backlink cleanup. Avoid duplicate active warnings for the same underlying issue where practical.

Status: planned; roles and warning conditions are confirmed.

### Reader access and paywalls

Paywalls and reader authentication are deferred. Until a separate reader-authentication/subscription system exists, public articles remain fully readable. The admin/staff Google sign-in flow must never be presented as reader login or used to hide public article content.

Status: current locked-article rendering must be changed to match this target before the feature is considered complete.

## 5. Data model and relationships

### Users

The current identity/access model uses an ID, name, unique email, password hash retained for local/testing support, nullable unique Google subject, approval status, admin privilege flag, and timestamps. A separate generic role column is not required for the current admin/editor rules. Pending and rejected accounts remain blocked from admin features. Admin accounts are not limited to one.

### Superblogs

Target fields include ID, creator/owner reference, template key/reference, name, unique nullable current domain, description, settings, published/draft status, and timestamps. Remove the Superblog slug after host-based routes have been deployed and existing code no longer depends on it. A domain is required at publishing time, not at draft creation.

### Templates

Target fields: ID, immutable internal template key, unique editable name, active state, and timestamps. The key is used by code and seeders; it is never an admin input. The active state controls whether a template is offered for new Superblogs. Existing Superblogs retain inactive templates.

### RSS sources and items

An RSS source belongs directly to one Superblog. A source has a name, URL, active state, last-fetched timestamp, daily processing limit, and timestamps. The global fetch interval is application-level, not per source.

An RSS item belongs to one source and stores its original identifiers, title, URL, author, publication date, original content/summary, content hash, and timestamps. The first-seen snapshot is not overwritten by later fetches or AI processing.

### Articles

An article belongs to one Superblog and has at most one RSS item (nullable for manual articles). A unique database constraint on `rss_item_id` protects the one-article-per-RSS-item rule while allowing multiple nulls for manual articles. Article data includes title, per-Superblog unique slug, content and its `content_format` (`legacy_html` or `blocks_v1`), source type (`rss` or `manual`), status (`draft` or `published`), selection flag, first publication timestamp, reader-facing content-modified timestamp, and timestamps.

### Article links and media

Article links belong to one article and contain type, URL, and anchor text. Brand/product/service links are optional and can be removed without deleting an article.

Media belongs to an article and stores a path, filename, MIME type, alt text, and timestamps. An article may have one optional image, enforced by a unique index on `media.article_id`; an image block references its Media row, while its caption belongs to the structured document. Manual image uploads use the private fixed `article_media` disk, accept JPEG/PNG/WebP up to 5 MB, and require descriptive alt text unless marked decorative. Admin images are served only to users who can manage that Superblog; public images require the Superblog and article to be published, selected, and to have a publication date. The one-image index migration was tested with SQLite and applied to local DDEV MySQL 8.4. Article deletion may remove its media record and unreferenced physical file, but must not delete a file still referenced elsewhere.

### Relationship summary

```text
User
  hasMany Superblogs (creator/owner reference; does not grant access)
  belongsToMany Superblogs (explicit editor assignments)

Superblog
  belongsTo User (creator/owner reference)
  belongsToMany Users (assigned editors)
  belongsTo Template (immutable template key identifies implementation)
  hasMany RSS Sources
  hasMany Articles

RSS Source
  belongsTo Superblog
  hasMany RSS Items

RSS Item
  belongsTo RSS Source
  hasOne Article (at most one)

Article
  belongsTo Superblog
  belongsTo RSS Item (nullable)
  hasMany Article Links
  hasMany Media
```

For V1, RSS sources belong directly to a Superblog; do not add a many-to-many RSS relationship without a demonstrated requirement.

Deleting an article preserves its RSS item and source. Deleting an RSS item or its RSS source must preserve any linked article, including its content, selection, publication status, first-publication date, and position/order in the timeline. The nullable `articles.rss_item_id` may be set to null when its RSS item is deleted; the article remains an RSS-derived article, but its source record is no longer available. Deleting a Superblog deletes its dependent articles, sources, items, links, and media records, while leaving shared physical media files intact. User ownership/assignments must not make deleting a user cascade-delete Superblogs accidentally; ownership must be handled explicitly.

## 6. Core content flows

### Automated RSS content

```text
Scheduled fetch at configured interval
    ↓
Fetch source (respect per-source daily cap; newest eligible items first)
    ↓
Deduplicate and preserve immutable RSS Item snapshot
    ↓
Queue AI rewrite and image generation
    ↓
Validate source grounding, content, SEO, and image metadata
    ├── failure → draft + persistent warning
    └── success →
          ├── Auto publish on → selected and published
          └── Auto publish off → unchecked draft for editor review
```

AI cannot invent unrelated articles, source facts, or citations. Preserve original feed content even when a rewrite or image is generated. Manual publication remains available to authorized editors after review.

### Manual content

```text
Assigned editor creates article
    ↓
Basic visual editor (headings, bold/italic, links, text size, lists)
    ↓
Optional article image with alt text
    ↓
Required SEO/content validation
    ├── failure → remain draft with clear errors
    └── success → explicit publish action
```

### Publishing

Selection controls inclusion; status controls publication. A live Superblog exposes only selected, published articles with a valid first-publication date. Publishing a Superblog publishes eligible selected articles and sets the Superblog live. Unpublishing the Superblog takes the whole site offline without changing article statuses or publication dates. Withdrawing and republishing an article preserve its first-publication date and do not by themselves update `content_modified_at`.

## 7. Public rendering and URLs

The public request host resolves the Superblog by its exact current configured domain. The homepage is at `/`; an article is at `/articles/{article-slug}`. The public controller verifies that the Superblog is published and scopes article lookup to it. An article from another Superblog, an unselected article, a draft, or an article without a first-publication date returns 404.

Admin preview uses the same template views/content rules, but may render selected drafts for an offline Superblog. Preview routes stay authenticated and authorized and are never exposed as public routes. The homepage shows the combined chronological timeline, fills template positions first, and continues with all remaining eligible articles. The manual-only “Mest lästa artiklar just nu” sidebar remains separate from that timeline.

## 8. Duplicate protection and source audit

RSS ingestion deduplicates within a source using stable feed identifiers such as GUID, external ID, source URL, and content hash. The database unique key on `articles.rss_item_id` is the final guard against duplicate Article creation. Repeated fetches must not overwrite the RSS source snapshot, AI content, or article publication selection.

Different sources may describe the same underlying story. Cross-source similarity detection belongs in content processing and should not complicate the initial database model without a demonstrated need.

## 9. Migration and data-safety rules

* Prefer additive migrations and backfills that preserve existing records.
* Do not infer template identity from editable names or slugs. Map legacy template rows explicitly by ID or another verified stable reference before dropping old identity fields.
* Move template view/theme/layout behavior into the stable-key code registry before removing template slug/layout/description/settings columns.
* Remove the Superblog slug only after host-based routes are active and existing records/domains are reviewed. Draft Superblogs may still have no domain.
* Retire `has_all_superblogs_access` only after checking existing values; first ensure it cannot grant an editor unassigned access.
* Preserve first-publication dates during withdrawal and migration. Backfill the reader-facing modified date without inventing historical precision.
* Back up production before destructive schema/data changes. Never run `migrate:fresh`, database reset, or equivalent destructive setup against real/shared data.
* Verify MySQL-specific constraints and migrations on MySQL 8.4; SQLite CI is useful but is not MySQL runtime verification.

## 10. Testing and verification approach

The project uses PHPUnit feature tests with SQLite in memory and mocks Google OAuth. Tests should cover authorization, public host/domain resolution, article selection/status/date filters, migration/backfill retries, publication transactions, the distinct deletion rules for articles, RSS data, and Superblogs, RSS duplicate handling, and job idempotency.

When a change relies on MySQL behavior (foreign keys, unique nullable indexes, locking, or DDL), run a separate disposable MySQL 8.4 verification. Do not run seeders or migrations against a developer's existing database unless explicitly requested.

Use the existing Laravel test suite, Pint, `git diff --check`, and Vite build as applicable. Do not claim behavior was verified on MySQL, in a browser, or in production unless that environment was actually exercised.

Do not add or run unnecessary tests. Use focused existing tests and lightweight checks appropriate to the change; add tests when explicitly requested or needed to verify meaningful risk.

## 11. Implementation status snapshot

This snapshot is based on source inspection and verification on 19 November 2026. It records implementation gaps relative to the agreed target; it does not invalidate intentionally planned or open blueprint sections.

* Current public Superblog routes use `/superblogs/{slug}`; host/domain dispatch and central-host-only admin routing are not implemented.
* Current Superblog schema still has a slug and nullable domain.
* Template behavior now uses immutable `seed_key` values, not editable names or slugs. The forward data migration maps the approved local rows (ID 3 to `template-modern`, ID 4 to `template-classic`) and fails on conflicting or other unkeyed records; the seeder matches by key, preserves existing edits, and refuses to guess at unkeyed rows. `Superblog.template_id` relationships are unchanged. Template management now permits only renaming and active/inactive changes; create and delete routes are absent, and extra request fields are ignored. A unique-name index migration refuses to proceed if duplicate names exist. Both migrations were tested on SQLite but not run against DDEV, staging, or production; verify each environment's rows before rollout. Legacy slug/description/layout/settings columns remain in the database for compatibility but are no longer exposed or accepted through template management. Public article views are selected through the key registry, but both current templates still use the shared Modern article view.
* Superblog template selection is fixed at creation. The Grund tab displays the assigned template name without an editor; update requests may omit `template_id` and are rejected if they try to change it. Existing inactive assignments remain valid.
* Approved editors now require an explicit Superblog assignment; the legacy `has_all_superblogs_access` flag no longer grants access. Editors can manage articles and use the assigned blog's preview. Their sidebar contains only Översikt, where all assigned Superblogs are listed regardless of publication status; editor builder steps are numbered 1–3, while the admin sidebar and six-step numbering remain unchanged. Grund, appearance, RSS source changes, and whole-Superblog publish/unpublish are admin-only. The legacy flag remains in the schema until its existing values have been reviewed; saving explicit assignments clears it for that account.
* Multiple-admin provisioning is implemented through the interactive create command and explicit promotion command. Promotion locks only the target user row in a transaction; creating an account relies on the unique email constraint, so there is no global one-admin lock. Google identity mapping uses `--admin-account` to confirm an already-approved admin and never grants admin status.
* Staff Management now lets approved admins promote approved editors and demote other admins. Role changes preserve the user row, Google identity, approval status, and Superblog assignments; self-demotion and removing the final admin are blocked. The existing staff-update endpoint rechecks access and target state transactionally and locks admin rows in stable order for role and approval actions. Feature tests use SQLite, with focused role tests also run against disposable MySQL 8.4; simultaneous multi-process contention was not directly exercised.
* The inline editor stores validated versioned `blocks_v1` documents with stable block/list-item UUIDs, and public article pages render semantic HTML from them. Supported legacy HTML is converted safely when saved through the editor; unsupported legacy content stays in sanitized legacy mode rather than being silently discarded. RSS item snapshots remain separate and unchanged. One private article image can be uploaded, replaced, removed, captioned, and given descriptive or decorative alt text. Image references are checked against the article, public delivery rechecks publication visibility, and the database enforces at most one Media record per article. The editor uses the existing Blade/vanilla-JavaScript stack; no third-party editor engine was added. Bulk legacy conversion and AI processing remain unimplemented.
* Excerpt has been removed. Public meta description is derived from the first paragraph.
* `published_at` is preserved as the first-publication date across withdrawal and republication. `content_modified_at` is set only when reader-visible title/body content on a published article is edited; publication, republication, selection-only changes, slug-only edits, and whole-Superblog unpublishing do not change it. Public article pages show “Skapad” from `created_at` and show “Senast ändrad” whenever `content_modified_at` is later than `created_at`, including when both dates fall on the same calendar day; structured data includes a known actual modification timestamp. Existing dates were not backfilled: existing `published_at` values remain as stored, and `content_modified_at` starts null for existing rows because historical modification times cannot be reliably inferred. Focused and full tests passed on SQLite; the additive migration was separately verified against an ephemeral MySQL 8.4 database.
* The forward migration `2026_11_11_000001_preserve_articles_when_rss_items_are_deleted` changes `articles.rss_item_id` to `nullOnDelete()`. Deleting an RSS item or source detaches the article without removing its content, selection, status, publication date, or timeline record. The original migrations remain unchanged. SQLite and a disposable MySQL 8.4 database were used to verify the migration and preservation behavior.
* RSS fetching is currently manual. Database queue infrastructure exists, but scheduled fetches, quotas, AI/image jobs, persistent warnings, and Auto publish do not.
* Paywall rendering currently exists, but reader authentication/subscriptions do not. The agreed public behavior is full article access until reader access is designed.

These notes should be refreshed when major workflow milestones ship. The target architecture and confirmed decisions remain authoritative until changed through review.

## 12. Open decisions and implementation details

* AI text/image provider selection remains open.
* Hosting provider and the actual central admin hostname remain open.
* Define how items skipped because a feed reached its daily cap are recorded, so repeated scheduled runs do not accidentally create a backlog.
* Confirm the default template choice when the template registry/default is next changed; keep the default code-controlled.

## 13. Architectural boundaries

* Laravel owns authorization, validation, publication, and data-integrity rules.
* MySQL owns persistent application state.
* RSS is the automated source of content.
* AI transforms source material and does not invent independent news.
* Original RSS source records remain separate from transformed article content.
* Templates own public presentation and default SEO structure.
* Articles own final publishable content.
* One central admin application manages all Superblogs.
* Public sites use server-rendered Blade HTML.
* Preserve existing authentication and staff approval; do not use staff sign-in as reader authentication.

## 14. V1 scope discipline

Do not introduce the following without a concrete requirement:

* React/Next.js
* Microservices
* Multiple databases
* Kubernetes
* Complex event-driven infrastructure
* Many-to-many RSS sources
* A large permission framework
* A separate SEO metadata database without a demonstrated need
* A full rich-text suite when the basic editor requirements are sufficient

Prefer Laravel's built-in models, policies, Form Requests, database queue, scheduler, and storage abstraction before adding packages or infrastructure.

## 15. Decision and change workflow

Architecture is decided before implementation. Planned features and questions in this document are intentional; do not treat them as defects simply because code does not implement them yet.

AI coding agents may implement bounded tasks but must not silently change confirmed decisions. If a requirement conflicts with this document or if implementation needs a schema/architecture change, explain the decision, alternatives, and trade-offs and wait for approval.

After the application skeleton, work may be split into focused, low-overlap workstreams so the user and Oliver can work in parallel with minimal merge conflicts.

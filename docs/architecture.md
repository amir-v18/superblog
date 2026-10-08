# Superblog Architecture Blueprint

## 1. Purpose

Superblog is a single Laravel application that centrally manages multiple public content websites (Superblogs).

The public Superblogs are server-rendered HTML sites using Laravel Blade. All Superblogs are managed from one central admin application.

## 2. Technology Stack

* Laravel 13
* PHP 8.5+
* Blade
* MySQL
* Vite / Tailwind / Alpine as needed
* Git / GitHub

React and Next.js are not part of V1.

## 3. Core Architectural Principle

**RSS provides the information. AI transforms it. Laravel validates it. MySQL preserves the source and application state. Templates render it.**

AI is a processing mechanism, not an independent content source.

AI must not randomly invent articles or publish content without an approved underlying source.

For automatically generated content, the source chain must always be traceable:

`Article → RSS Item → RSS Source → Original URL`

Manual articles are allowed and use `source_type = manual` with no RSS item.

## 4. Main Domains

### Superblogs

A Superblog represents one public website.

A Superblog owns:

* Articles
* RSS sources
* Template selection/configuration
* Domain/site settings
* Publishing settings

### Templates

Templates define the public site's layout and SEO structure.

A template can provide:

* Page layout
* Header/navigation/footer
* Article structure
* Typography
* Colors
* Responsive structure
* SEO metadata structure
* JSON-LD structure
* Open Graph structure

A Superblog can customize allowed visual settings before its template/layout is locked.

### Articles

Articles are the canonical content entity used for both RSS-derived and manual content.

An article may have:

* RSS source provenance, or
* Manual provenance

AI is never an article source type.

### RSS

RSS is the automated content input mechanism.

The system first stores the original RSS item, then transforms it into an Article.

The original RSS data must not be overwritten by AI processing.

### AI Processing

AI may:

* Rewrite/restructure source content
* Summarize source content
* Expand based on source material
* Create headlines
* Create metadata suggestions
* Suggest image alt text
* Structure content
* Help produce required links

AI may not:

* Invent unrelated articles
* Select arbitrary topics without a source
* Make up facts
* Publish automated content without an underlying source

### SEO Engine

SEO is a platform responsibility rather than something administrators manually construct for every article.

The system should automatically handle, where applicable:

* Title
* Meta description
* Canonical URL
* Exactly one H1
* Logical heading hierarchy
* Semantic HTML
* Image alt text
* Internal links
* Required brand link
* Required product/service link
* JSON-LD
* Open Graph metadata
* Robots directives
* Sitemap
* robots.txt

The application validates important requirements rather than trusting AI instructions alone.

## 5. Database Model

### users

Stores administrators/users of the central application.

Key fields:

* id
* name
* email
* password
* timestamps

### superblogs

Represents a public Superblog.

Key fields:

* id
* user_id
* template_id
* name
* slug
* domain
* description
* settings
* status
* timestamps

### templates

Represents a predefined public-site template.

Key fields:

* id
* name
* slug
* description
* layout
* settings
* is_active
* timestamps

### rss_sources

Represents an RSS feed attached to a Superblog.

Key fields:

* id
* superblog_id
* name
* url
* is_active
* last_fetched_at
* timestamps

### rss_items

Stores the original information received from an RSS feed.

Key fields:

* id
* rss_source_id
* external_id
* guid
* title
* url
* author
* published_at
* content
* summary
* content_hash
* timestamps

RSS items are the source-of-record for automatically imported content.

### articles

Stores the final content shown on a Superblog.

Key fields:

* id
* superblog_id
* rss_item_id (nullable)
* title
* slug
* content
* excerpt
* source_type
* status
* is_locked
* published_at
* timestamps

`source_type` includes:

* rss
* manual

There is no `ai` source type.

### article_links

Stores structured links associated with an article.

Key fields:

* id
* article_id
* type
* url
* anchor_text
* timestamps

Expected types:

* brand
* product
* service
* internal
* external

The validator must be able to verify the required brand and product/service links.

### media

Stores article media information.

Key fields:

* id
* article_id
* path
* filename
* mime_type
* alt_text
* timestamps

## 6. Core Relationships

```text
User
  hasMany Superblogs

Superblog
  belongsTo User
  belongsTo Template
  hasMany RSS Sources
  hasMany Articles

RSS Source
  belongsTo Superblog
  hasMany RSS Items

RSS Item
  belongsTo RSS Source
  hasMany Articles

Article
  belongsTo Superblog
  belongsTo RSS Item (nullable)
  hasMany Article Links
  hasMany Media
```

For V1, RSS sources belong directly to a Superblog. A many-to-many RSS-source relationship is not introduced unless a real requirement appears.

## 7. Automated Content Flow

```text
RSS Source
    |
    v
Fetch RSS
    |
    v
RSS Item (preserve original data)
    |
    v
Duplicate detection
    |
    +---- duplicate ----> stop
    |
    v
AI transformation
    |
    v
Article draft
    |
    v
SEO/content validation
    |
    +---- fail ----> needs review / correction
    |
    v
Publish
```

## 8. Manual Content Flow

```text
Admin
  |
  v
Manual Article
  |
  v
SEO/content validation
  |
  +---- fail ----> needs review / correction
  |
  v
Publish
```

## 9. Duplicate Protection

RSS ingestion must avoid processing the same source item repeatedly.

Potential identifiers include:

* RSS GUID
* External ID
* Source URL
* Content hash

Different RSS feeds may still describe the same underlying story. Cross-source similarity detection belongs in the content-processing layer and should not unnecessarily complicate the initial database schema.

## 10. Source Preservation

Original RSS content must remain separate from the transformed Article.

```text
rss_items
    |
    +-- original title
    +-- original content
    +-- original URL
    +-- source identifiers

articles
    |
    +-- transformed title
    +-- transformed content
    +-- final publication data
```

This allows the system to trace and audit how published content relates to its source.

## 11. Public Rendering

```text
Browser / Google
       |
       v
Laravel route
       |
       v
Superblog
       |
       v
Article
       |
       v
Selected Template
       |
       v
Blade
       |
       v
Server-rendered HTML
```

Public pages should be crawlable without requiring a JavaScript application to render the primary content.

## 12. Architectural Boundaries

* Laravel owns application rules and validation.
* MySQL owns persistent application state.
* RSS is the automated content source.
* AI transforms source material; it is not an independent content source.
* Templates own presentation and default SEO structure.
* Articles own final publishable content.
* Original RSS data is preserved separately from transformed articles.
* The central admin manages all Superblogs.
* Public Superblogs are rendered with Blade.

## 13. V1 Scope Discipline

Do not introduce the following without a concrete requirement:

* React/Next.js
* Microservices
* Multiple databases
* Kubernetes
* Complex event-driven infrastructure
* Premature many-to-many relationships
* Large permission systems
* A separate SEO metadata database without a demonstrated need

Prefer the simplest architecture that satisfies the requirements.

## 14. Development Workflow

Architecture is decided before implementation.

AI coding agents may implement bounded tasks but must not silently change architectural decisions.

If implementation reveals a need for an architectural change, the change should be raised for review before proceeding.

After the initial skeleton, development will be split into two low-overlap workstreams so the user and Oliver can work in parallel with minimal merge conflicts.

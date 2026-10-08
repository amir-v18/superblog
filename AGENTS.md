<laravel-boost-guidelines>

# Laravel Application

This repository contains a Laravel application.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command.

## Agent Setup

Laravel Boost is already configured for this repository.

Do not reinstall or reinitialize Laravel Boost unless explicitly required.

</laravel-boost-guidelines>

# Superblog — Agent Instructions

## 1. Read the architecture first

Before making application changes, read:

```text
docs/architecture.md
```

Treat `docs/architecture.md` as the source of truth for the project's architecture and business rules.

Do not silently change architectural decisions defined there.

If an implementation requires an architectural change, stop and explain:

1. What needs to change
2. Why it is needed
3. What alternatives exist
4. The trade-offs
5. Your recommendation

Wait for approval before making the architectural change.

---

## 2. Project

Superblog is a centralized Laravel application that manages multiple public content websites.

Technology stack:

* Laravel 13
* PHP 8.5+
* Blade
* MySQL
* Vite / Tailwind / Alpine where appropriate
* Git / GitHub

React and Next.js are not part of V1.

---

## 3. Core content rule

RSS is the automated content source.

AI is a transformation/processing mechanism, not an independent content source.

AI must never randomly invent or publish articles without an underlying approved source.

For automatically generated content:

```text
RSS Source
    ↓
RSS Item
    ↓
AI transformation
    ↓
Article
```

Every RSS-derived Article must be traceable back to its original RSS Item.

Do not introduce an `ai` article source type.

Valid article source types are:

```text
rss
manual
```

---

## 4. Preserve source content

Never overwrite the original RSS content with AI-transformed content.

The original RSS item must remain preserved.

The relationship must remain traceable:

```text
Article
    ↓
RSS Item
    ↓
RSS Source
    ↓
Original URL
```

---

## 5. Laravel conventions

Prefer standard Laravel conventions and built-in framework functionality before introducing custom abstractions.

Use:

* Eloquent relationships
* Form Requests for validation where appropriate
* Policies/authorization where appropriate
* Jobs for asynchronous work
* Events/listeners only when they provide clear value
* Laravel's existing configuration and service patterns

Do not introduce a new package when Laravel already provides a suitable solution.

Do not introduce architectural complexity without a concrete requirement.

---

## 6. Database rules

The initial core entities are:

```text
users
superblogs
templates
rss_sources
rss_items
articles
article_links
media
```

Keep relationships simple unless the requirements require additional complexity.

For V1, an RSS source belongs directly to a Superblog.

Do not introduce a many-to-many RSS relationship unless there is a demonstrated requirement.

---

## 7. AI rules

AI may transform source material and assist with:

* Rewriting
* Summarization
* Article structure
* Headlines
* Metadata suggestions
* Image alt text
* Other transformations explicitly defined by the application

AI must not:

* Invent unrelated articles
* Generate arbitrary news
* Make up source information
* Publish content without an underlying source
* Bypass application validation

Laravel remains responsible for enforcing application rules.

---

## 8. SEO rules

SEO is a platform responsibility.

Do not rely exclusively on AI prompts to enforce SEO requirements.

The application should validate important requirements such as:

* Article title
* Meta description
* Canonical URL
* H1 structure
* Heading hierarchy
* Word count
* Required brand link
* Required product/service link
* Image alt text
* Slug validity
* Structured data

Do not use keyword stuffing or other manipulative SEO techniques.

---

## 9. Public rendering

Public Superblogs use server-rendered Blade HTML.

Do not introduce React, Next.js, or a client-side SPA for public article rendering unless the architecture is explicitly changed and approved.

Public content should be accessible to search engines without requiring JavaScript to render the primary article content.

---

## 10. Code changes

Keep changes:

* Small
* Focused
* Easy to review
* Consistent with the architecture
* Consistent with existing Laravel conventions

Do not modify unrelated files.

Do not refactor unrelated code while implementing a feature.

Do not create speculative abstractions for future requirements.

---

## 11. Dependencies

Do not add Composer, npm, or other dependencies without a clear reason.

Before adding a dependency:

1. Check whether Laravel already provides the required functionality.
2. Explain why the dependency is necessary.
3. Prefer well-maintained, established packages.

---

## 12. Testing and verification

Keep testing proportional to the project requirements.

Do not proactively create unit tests, feature tests, integration tests, or other automated test suites unless:

* The requirements explicitly require them.
* The feature has a clear reason that automated testing is necessary.
* The user explicitly asks for tests.
* A particular implementation has enough complexity/risk that testing should be discussed.

For normal development, perform appropriate lightweight verification after changes, such as:

* Checking Laravel commands succeed.
* Checking migrations run successfully.
* Checking routes/configuration where relevant.
* Manually verifying the affected feature where practical.

Do not add testing infrastructure or large test suites without a clear reason.

When tests already exist and are relevant to the changed code, do not intentionally break them. Report any failures.

---

## 13. Git ownership

**The user owns Git operations.**

The agent must NOT perform any of the following unless the user explicitly instructs it to do so:

* `git commit`
* `git push`
* `git merge`
* `git rebase`
* `git switch`
* `git checkout`
* Creating branches
* Deleting branches
* Resetting or rewriting Git history

The agent may safely use read-only Git commands to inspect the repository, such as:

```sh
git status
git diff
git log
git branch
```

The agent should show or explain the changes it made so the user can review them.

The user decides when changes are ready to commit.

When a logical checkpoint is reached, the agent should tell the user that a commit is recommended and suggest a commit message, but must not create the commit itself.

Example:

```text
Recommended checkpoint:

git add .
git commit -m "Add RSS source models"
```

The user will execute the Git commands.


---

## 14. Agent communication

Before implementing a significant task, briefly identify:

* What you are changing
* Which files you expect to change
* Any important assumptions

After implementation, report:

* What changed
* Why it was changed
* Tests run
* Important assumptions
* Any architectural concerns

If requirements are ambiguous and the ambiguity could affect architecture or data integrity, ask before implementing.

---

## 15. Architecture changes

The agent is an implementation worker, not the architectural decision-maker.

Do not silently:

* Change the database architecture
* Replace Laravel/Blade
* Introduce React/Next.js
* Change the article source model
* Change the RSS → AI → Article pipeline
* Add major infrastructure
* Add major dependencies

If such a change appears necessary, explain the problem and wait for approval.

---

## 16. Source of truth

Use the following hierarchy:

1. Explicit user requirements
2. `docs/architecture.md`
3. `AGENTS.md`
4. Existing Laravel/project conventions

If these appear to conflict, do not silently choose an architectural change. Explain the conflict and ask for clarification.


Add concise comments only where they improve understanding of non-obvious code, architectural decisions, business rules, or tricky Laravel behavior. Comments should explain the why / mental model, not restate what the code obviously does.

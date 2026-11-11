# Superblog agent guidance

## Source of truth

Read [`docs/architecture.md`](docs/architecture.md) before application work. It is the canonical source for product rules, architecture, decisions, and open questions. Do not duplicate or silently change those decisions. If the request, code, or architecture conflicts or leaves a consequential data/security choice unclear, explain the conflict and ask before choosing.

## Project conventions

Superblog is a Laravel 13, PHP 8.5+, Blade application using MySQL, Vite, Tailwind, and vanilla JavaScript/Alpine where appropriate. Prefer Laravel features and existing patterns; avoid unnecessary dependencies, abstractions, and unrelated changes. Preserve RSS source content and keep AI as a transformation of RSS material, as specified in the architecture.

Laravel Boost is already configured. Do not reinstall or reinitialize it.

## Testing and safety

Do not add or run unnecessary tests. Use focused existing tests and lightweight checks appropriate to the change; add tests when requested or needed to verify meaningful risk. Report checks accurately, distinguishing SQLite from MySQL and local/browser behavior from production.

Never run destructive database commands or alter existing data unless explicitly authorized. Do not run migrations or seeders against a real/shared database without explicit approval. Do not modify credentials or secrets unless requested.

## Git and communication

The user owns Git operations. Do not commit, push, merge, rebase, switch branches, or rewrite history. Preserve existing work. Before significant implementation, state the intended scope and files; afterward summarize changes, checks, assumptions, and remaining risks.

# AGENTS

Guidance for AI coding agents working in `filagora-package-skeleton`. `CLAUDE.md` imports this file; edit here.

## What this is

The template for new Filagora packages (Composer name `filagora/filagora-ai-forums`, namespace `Filagora\AiForums\`). It is not installed by anyone; it is copied to start a package. Part of Filagora, a family of MIT-licensed Laravel 13 / Filament 5 packages (forums, CMS, entitlements, logs, compliance, messages, search, push, memberships). Composer vendor `filagora`, namespace `Filagora\<Name>\`, default table prefix `filagora_`, commands `filagora:*`, env `FILAGORA_*`. Every package is pre-1.0 (`0.x`): the API may change between minor versions.

## Working here

- Keep it a *working* package: its own tests, lint and analysis must pass, so the template never rots.
- Changes here should be things every new package wants (a CI tweak, a convention, a better `ArchTest`). Package-specific ideas belong in that package.
- Placeholders to be renamed by users: the `Filagora\AiForums` namespace, `AiForumsServiceProvider`, `FilagoraAiForumsPlugin`, `config/filagora-ai-forums.php`, plugin id, `FILAGORA_AI_FORUMS_` env prefix, the Composer name and the `.ddev/config.yaml` name. Keep them consistent so a search-and-replace works.
- The README's "Creating a package from this template" steps (copy via `git ls-files -z | xargs -0 cp --parents`, never `git archive` or a directory copy) are the contract; update them when the template's file set changes.
- When a convention changes in the template, the existing packages usually need the same change; note that in the PR description rather than assuming it propagates.

## Commands

Run everything through DDEV from this directory (`ddev start` once; the `testing` database is created on the first `ddev restart`).

```bash
ddev composer install
ddev composer test      # Pest, on a real MariaDB
ddev composer lint      # Pint
ddev composer analyse   # Larastan (level 6)
```

Tests always run against MariaDB (database `testing`, forced in `phpunit.xml.dist`), never SQLite, so FULLTEXT, `ONLY_FULL_GROUP_BY` and collation behave like production. Do not weaken that to make a test pass. If a port is taken, `ddev poweroff` before starting another project's DDEV.

## Conventions

- PHP 8.4, `declare(strict_types=1)` in every source file (`tests/ArchTest.php` enforces it, plus no debug calls and no `env()` outside `config/`).
- Table names always go through core's `Tables::name('x')` (prefix-aware); user foreign keys through `UserKey`/`UserModel`. Never hard-code `filagora_` or `users`.
- Config holds no closures and is merged with the host's copy, so list only what differs. Env vars are `FILAGORA_`-prefixed.
- Authorization is one question, "may this user do this?", answered by core's `AccessChecker` from the package's `gates` config. Do not call a roles or permissions package directly.
- Extend through documented contracts, events and registries. Anything undocumented is `@internal`; public API follows SemVer.
- Migrations are mirrored by a snapshot in `database/schema.php` (`filagora:schema:dump`). A table change means updating the migration, the snapshot and the adoption check together, because `filagora:adopt` compares live tables against the snapshot.

## Definition of done

- Every behaviour change ships with tests; every bug fix with a regression test that fails before the fix.
- README and `CHANGELOG.md` (`[Unreleased]`) are updated in the same change.
- `composer test`, `composer lint` and `composer analyse` pass.
- Work on a branch and open a pull request (`gh pr merge --squash --delete-branch`). `master` is protected and squash-only.
- Confirm with the user before tagging a release, creating or deleting repositories or branches, or anything else outward-facing. Tags create the GitHub release from the matching `CHANGELOG.md` section; the user submits new packages to Packagist.

## Public repository: keep private details out

This repository is public and CI runs a leak scan (private patterns, any IPv4 literal, any email address not on a reserved test domain, gitleaks). Never write real hostnames, IP addresses, personal emails, the name of the site the packages were extracted from, or plan/infrastructure details into code, docs, fixtures, commit messages or PRs. Use `example.test`-style addresses.

## CI

`.github/workflows/ci.yml` calls the reusable workflows in `filagora/filagora-shared` at `@master` (tests, lint, leak scan, release). Fix CI problems there, not by copying logic into this repo.

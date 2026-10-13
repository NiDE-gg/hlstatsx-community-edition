# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

HLstatsX Community Edition (NiDE-gg fork of NomisCZ/hlstatsx-community-edition): real-time stats and rankings for Source/GoldSrc game servers. Requirements: PHP 8.3+, MySQL/MariaDB 5.7+ (8.0 recommended). Licensed GPLv2.

There is no build system, test suite or package manager (no composer/npm). Verify PHP changes with `php -l <file>` and Perl changes with `perl -c scripts/hlstats.pl` (run from `scripts/`, needs DBI/DBD::mysql installed).

CI (`.github/workflows/`) runs one workflow per tool, each triggered only by changes under its own paths: PHP syntax on PHP 8.3–8.5 (`php-lint.yml`), `perl -c` (`perl-lint.yml`), ShellCheck at error severity (`shellcheck.yml`), SourcePawn compile with SourceMod 1.12 (`sourcepawn.yml`, compiled `.smx` uploaded as an artifact), updater/`install.sql` consistency (`db-updaters.yml` → `.github/scripts/check-updaters.sh`), hadolint + image build on PRs (`dockerfile-lint.yml`) and actionlint (`actionlint.yml`).

## Contribution workflow

- **Never commit or push directly to `master`.** Always branch off the latest `master` (name it `fix/…`, `feature/…`, `chore/…`, `docs/…`, matching existing branch names) and open a PR; CI must be green before merging. This applies to Claude Code sessions too — including this file itself.
- **Bump the version on every functional change**, not only schema changes. `$VERSION` (e.g. `1.7.1`) and `$dbversion` (e.g. `82`) are rows in `hlstats_Options`, seeded by `SET @VERSION` / `SET @DBVERSION` in `sql/install.sql`, and only ever move forward through a `web/updater/<N>.php` script — there is no other place that bumps them. So any PR that changes behavior under `web/`, `scripts/`, `sourcemod/`, `amxmodx/` or `sql/` needs one:
  - **Schema change**: the new `web/updater/<N>.php` runs the `ALTER`/`INSERT` queries *and* sets `$version`/`$dbversion` (see `81.php`), per [Database schema changes](#database-schema-changes) below.
  - **No schema change** (bug fix, behavior change, dependency/requirement bump, etc.): add a version-only updater that just updates the `version`/`dbversion` rows in `hlstats_Options`, following `web/updater/82.php`. `dbversion` still increments by one — CI (`check-updaters.sh`) checks numbering/contiguity, not whether a schema actually changed.
  - Update `SET @VERSION` / `SET @DBVERSION` at the top of `sql/install.sql` to match the new updater (existing rule, repeated below).
  - Version numbers are `MAJOR.MINOR.PATCH`: bump `PATCH` for fixes/small changes, `MINOR` for new features, reserve `MAJOR` for breaking changes.
  - Skip the bump for changes that can't affect a running install: CI/workflow-only (`.github/`), documentation-only (`*.md`), or formatting-only changes.

## Components and data flow

```
game server --(UDP logs, via sourcemod/amxmodx plugins)--> scripts/hlstats.pl (Perl daemon, port 32578)
    --> MySQL (hlstats_* tables) <-- web/ (PHP frontend)
scripts/hlstats-awards.pl  -- daily cron: awards, ranks, cleanup
```

- **`scripts/`** — Perl daemon. `hlstats.pl` is the main loop (log parsing, skill calc, DB writes); it `require`s `HLstats_Server.pm`, `HLstats_Player.pm`, `HLstats_Game.pm`, RCON modules (`TRcon.pm`, `BASTARDrcon.pm`), and `do`s `HLstats_EventHandlers.plib` (per-event handlers). Config is `scripts/hlstats.conf` (DB credentials, bind IP/port); most runtime options are read from the `hlstats_Options` table. `run_hlstats` is the start/stop wrapper; `proxy-daemon.pl` forwards logs.
- **`web/`** — PHP frontend (see below).
- **`sourcemod/scripting/*.sp`**, **`amxmodx/scripting`** — game-server plugins (SourcePawn/Pawn) that emit extended log lines (`superlogs-*`) and handle in-game commands. Compiled `.smx`/`.amxx` are committed in `sourcemod/compiled` and `amxmodx/plugins`; recompile with the SourceMod/AMX Mod X compiler when editing sources.
- **`sql/`** — `install.sql` is the full schema + seed data for fresh installs. `Upgrade x - y/` folders are legacy manual upgrades (pre-1.6.2). `migrations/` holds standalone SQL fixes.
- **`heatmaps/`** — standalone PHP heatmap generator (`generate.php`, own `config.inc.php`).

## Web frontend architecture

- **Front controller**: `web/hlstats.php`. Defines `IN_HLSTATS`, loads `config.php`, legacy includes (`includes/class_db.php`, `class_table.php`, `functions.php`), then the DI container from `bootstrap.php`. Routes via `?mode=<name>` to `pages/<name>.php`; the mode **must be added to `$valid_modes`** in `hlstats.php` or it falls back to `contents`. Other entry points: `ingame.php` (pages in `pages/ingame/`), `status.php`, `sig.php`, `web/api/*.php` (JSON chart-data endpoints), `autocomplete.php`. Admin tasks live in `pages/admintasks/`.
- **Two DB layers coexist** — this is an in-progress refactor:
  - Legacy: global `$db` (`DB_mysql` in `includes/class_db.php`, mysqli, string-built queries; use `$db->escape()` / `valid_request()` on input).
  - New: PDO-based classes under `web/classes/` (PSR-4-ish autoload from `includes/autoload.php`, namespace = directory: `Repository\`, `Service\`, `Database\`, `Config\`, `Utils\`). `bootstrap.php` returns a tiny service container; get services with `$container->get(\Repository\PlayerRepository::class)`. New/refactored DB code should go into a Repository with prepared statements and be registered in `bootstrap.php` (recent commits migrate pages one by one this way).
- **Options**: `$g_options` (from `OptionService::getAllOptions()`, cached for the current request only) holds `hlstats_Options` rows, e.g. `scripturl`, `version`, `dbversion`.
- **External calls**: never call remote services (e.g. steamcommunity.com) inline while rendering a page. Steam avatar and online state go through `Service\SteamProfileService`, which caches them in `hlstats_SteamProfiles`, uses short curl timeouts and backs off after HTTP 429 (Steam rate-limits aggressively).
- **Config**: `web/config.php` defines constants (`DB_*`, `INCLUDE_PATH`, `PAGE_PATH`, `IMAGE_PATH`, `ROOT_PATH`, `CHAT_DELAY_*`). It is tracked and overwritten on every deploy, so it holds no secrets: DB credentials and API keys are read from an untracked `.env` at the repository root (one level above `web/`, outside the web root; see `.env.example`, same variable names as the Docker daemon). Never commit real credentials, and never put `.env` inside `web/` (it would be served as plain text). `web/hlstatsimg/progress/` must still be writable (forum signature image cache, `sig.php`).
- Note: `web/bootstrap.php‎` (with a trailing U+200E invisible char) is a stray duplicate of `web/bootstrap.php`; the real one is the plain name.

## Database schema changes

Schema changes must be applied in **both** places:
1. `sql/install.sql` (fresh installs), and
2. a new numbered updater script `web/updater/<N>.php` where `N` = current highest + 1. Each script checks `IN_UPDATER`, sets `$dbversion = N` / `$version`, updates `version` and `dbversion` in `hlstats_Options`, then runs its queries via `$db->query()`. `pages/updater.php` runs every script from `dbversion+1` upward. The `web/updater` folder is deployed with the site; `hlstats.php` blocks all pages with an update notice only while `updater/<dbversion+1>.php` exists (an update is pending), until someone runs `?mode=updater`.

Rules:
- **Never edit an existing updater script.** Databases already past that version will never run it again; always add a new `<N>.php`.
- Keep `SET @DBVERSION` at the top of `install.sql` equal to the highest updater number. A fresh install starts at that version and only runs newer updaters.
- `install.sql` creates MyISAM tables, while real installs are often InnoDB. Write plain `ALTER TABLE` statements in updaters (no `ALGORITHM=`/`LOCK=` clauses, which MyISAM rejects).
- Numbering history: in 2019, upstream bumped `install.sql` to 79/80 without adding updaters 79/80. The current `79.php`/`80.php` are unrelated later changes, so an old `dbversion` value does not reliably tell you which changes a database already has. Check the schema itself.

Game-specific data (weapons, awards, ribbons, actions) is plain rows keyed by `game` code; images live in `web/hlstatsimg/games/<game>/`.

## Query performance

The `hlstats_Events_*` tables hold millions of rows, and pages are crawled heavily by bots, so one slow page can saturate the server.
- Per-player/per-clan aggregates (`GROUP BY` over event tables) need a **covering index** that starts with the filter column (see updater `80.php`). Otherwise each query does thousands of random row lookups. When adding such a query, check it with `EXPLAIN` on a realistically sized database.
- When joining `hlstats_Actions` by `code`, also filter on `hlstats_Actions.game`: the same action code exists once per game.
- Qualify column names whenever `hlstats_Players` is joined. It shares `playerId` with most event tables, and an ambiguous column is a fatal error (HTTP 500).
- To re-read a legacy result set, use `$db->data_seek(0, $result)` and always pass `$result`: without it, `data_seek()` silently does nothing. Never re-run the query.

## Docker / deployment

`scripts/Dockerfile` builds the daemon image (context `./scripts`); CI (`.github/workflows/docker-build-push.yml`) pushes `ghcr.io/nide-gg/hlstatsx-deamon:latest` on every push to `master`. The entrypoints (`docker-hlxce-daemon-entrypoint`, `docker-hlxce-awards`) inject `DB_HOST`, `DB_NAME`, `DB_USERNAME`, `DB_PASSWORD` into `hlstats.conf` via sed. Kubernetes manifests are in `scripts/deploy/` (see `scripts/Readme.md`).

```bash
docker build -t hlstatsx-daemon ./scripts
```

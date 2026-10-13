## Summary

<!-- What does this change do, and why? -->

## Version bump

<!-- See "Contribution workflow" in CLAUDE.md. -->

- [ ] Not needed (CI/workflow-only, docs-only, or formatting-only change)
- [ ] Added `web/updater/<N>.php` and bumped `SET @VERSION` / `SET @DBVERSION` in `sql/install.sql` to match

## Checks

- [ ] Branched off the latest `master`; not pushed directly to `master`
- [ ] `php -l` / `perl -c scripts/hlstats.pl` run locally for changed files, as applicable
- [ ] CI is green

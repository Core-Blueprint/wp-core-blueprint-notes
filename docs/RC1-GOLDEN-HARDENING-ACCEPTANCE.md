# Notes RC1 Golden — integrated one-batch acceptance (Alpha24 candidate)

**2026-10-13 — IN PROGRESS. Not Golden, not RC1, not production ready.**

- Source: `christiaanbruinsma/wp-core-blueprint-notes`
- Working branch: `feature/notes-rc1-golden-hardening-v1`
- Derived from visually accepted Alpha23: `1d957031f90626614a68e1be3632903dc8410148`; **main is NOT yet merged with Alpha23**.
- Candidate package: `dist/core-blueprint-notes-0.1.0-alpha24.zip`
- Governance roadmap: `wp-core-blueprint-overseer/extensions/notes/rc1-golden-roadmap.md`
- Only operator approval permits a source merge; version/tag/distribution/deployment are separate decisions.

## One local source/build gate

Use PHP 8.4+, Node and `zip`/`unzip`. Work in the real local checkout, not in a different repository. Do not use real customer/private notes for acceptance.

```bash
bash -euo pipefail <<'SH'
cd ~/Downloads/wp-core-blueprint-notes
git status --short
git fetch origin --prune
git switch feature/notes-rc1-golden-hardening-v1 || git switch --track origin/feature/notes-rc1-golden-hardening-v1
git pull --ff-only
git rev-parse HEAD
php -v | head -n 2
node --version

php -l src/Sharing/Controller.php
php -l src/Organization/Catalog.php
node tests/rc1-source-contract.cjs
bash tools/check

bash tools/build-release
zip='dist/core-blueprint-notes-0.1.0-alpha24.zip'
unzip -t "$zip"
sha256sum "$zip" | tee /tmp/cb-notes-alpha24-build1.sha256
bash tools/build-release
sha256sum "$zip" | tee /tmp/cb-notes-alpha24-build2.sha256
diff -u /tmp/cb-notes-alpha24-build1.sha256 /tmp/cb-notes-alpha24-build2.sha256
git status --short
SH
```

Record actual local HEAD and SHA-256. `?? dist/` is expected untracked output, but any other source modifications must be examined. If any command fails, STOP: do not install the archive.

### Isolated Level 2 WordPress PHPUnit gate (9-test suite VERIFIED on WP 7.0 and 7.1.3)

Alpha24 includes a Notes-only integration provisioner and real WordPress/PHPUnit privacy and sharing tests. These are *test-infrastructure changes only*, not product feature changes. Operator evidence on 2026-10-14 confirms **PASS: 9 tests, 221 assertions** on **WordPress 7.0** and separately **WordPress 7.1.3**, from Notes HEAD `e194dffa36b607afc68b8d580d32edad42adc62f`. The earlier 7-test/142-assertion suite also passed on both versions. The new source contract runs under `bash tools/check`.

The runner `bash tools/check-integration` follows the established Work handbook pattern: it stages current Notes and Base source into a disposable directory, downloads an exact WordPress core and compatible wp-phpunit fixture, installs Base schema before booting Notes, and uses only the `core_blueprint_notes_test` database on the pre-existing `cb-base-test-db` MariaDB 10.11.19 container at `127.0.0.1:3307`. The runner resets **only this specifically named disposable database**. It does not touch the Base, Work, or operator WordPress databases. It refuses modified tracked source, a missing Base vendor PHPUnit installation and an unexpected DB image/port. No arbitrary DB name, host or temporary root may be supplied.

Run from the clean local Notes featurebranch:

```bash
cd ~/Downloads/wp-core-blueprint-notes
bash tools/check
bash tools/check-integration
CB_TEST_WP_VERSION=7.1.3 bash tools/check-integration
```

A single invocation covers only one WP version. The first (default) is WP 7.0 and the second is WP 7.1.3. Both must be executed and evidenced independently. Tests are configured with `tests/phpunit-notes.xml.dist` and `tests/phpunit-notes-bootstrap.php` and do not require `wp-config.php` in an interactive website directory.

**Initial PHPUnit coverage, not the complete R01–R13 matrix:**
- R02 partial: native publish remains private and Gutenberg blocks round-trip.
- R03 partial: two editors and anonymous exact REST read access, owner list scoping and no foreign body/title leaks.
- R09 partial: read-only Abilities disabled by default, enabled per-user owner boundaries, administrator's distinct AI scope and no content in list projections.

**Extended PHPUnit cases VERIFIED 2026-10-14:** `NotesRuntimeMutationSharingIntegrationTest` expands R02/R03/R07 with three real WordPress scenarios: owner REST edits and cross-owner edit/delete refusal; revision and autosave REST permission checks; and public-share confirmation, frozen content, cross-owner revocation refusal, and final revocation. Both WordPress 7.0 and 7.1.3 returned **OK (7 tests, 142 assertions)**. These are intentionally PHP/REST-dispatch-level tests. Do not mark R07/cache/revocation globally PASS without actual anonymous HTTP, cache/CDN and restore evidence.

**REST post-meta contract fix, PHPUnit runtime PASS on WP 7.0 and 7.1.3:** WordPress core excludes the `meta` field from a custom post type's REST schema unless the type supports `custom-fields`. Notes Alpha24 previously registered owner-scoped classification meta, but omitted that CPT support flag. The hardening branch now declares it explicitly and adds an RC1 source gate plus real Gutenberg-style REST mutation assertions. This is a runtime behavior fix, not a Notes editor layout change; confirm that the editor's native Custom Fields UI does not undermine the approved presentation or allow unvalidated classification edits. The expanded suite confirms real REST persistence of notebook, category and tag IDs plus owner-scoped catalog restoration on WP 7.0 and 7.1.3. Browser-level UI and accessibility acceptance remain open.

**R10 recovery runtime acceptance, 2026-10-14: PHPUnit PASS on WordPress 7.0 and 7.1.3, NOT full backup/restore acceptance.** The original run was blocked (WordPress 7.0). Operator first ran 9 tests / 182 assertions with 2 failures: (1) native REST returned HTTP 200 but the expected notebook postmeta was empty after a Gutenberg-style write; (2) a real WordPress Trash/Restore yielded `draft`, which is expected WordPress behavior, not a failed share revocation. The suite now asserts REST schema presence, per-key editing capabilities and response metadata to identify the first cause, and expects a non-public draft after Restore. Follow-up run: 9 tests / 197 assertions with one remaining failure, WordPress REST response `meta: []` despite the cached REST schema. Root cause identified in the actual `wp-phpunit` 7.0.0 `WP_UnitTestCase::tear_down()`: it calls `unregister_all_meta_keys()` after every test, while Notes registers these keys during site `init` only. The test fixture now re-registers Notes postmeta in `set_up()` and explicitly verifies all three keys. The fixture repair was verified by a full rerun: **OK (9 tests, 221 assertions)** on WordPress 7.0 and independently WordPress 7.1.3. WordPress `wp-phpunit` clears the meta registry between tests, so the fixture restores the native `init`-time registration in `set_up()`. The REST `custom-fields` CPT support fix remains required in production. These tests verify in-place WordPress catalog/references recovery and Trash/Restore share revocation, not external SQL restoration or cache behavior.

**R10 additional WordPress PHPUnit coverage VERIFIED on both WordPress versions:** `NotesRuntimeRecoveryIntegrationTest` adds a real per-user catalog/postmeta recovery dependency test and a real Trash/Restore stale-public-grant revocation test. These new tests ran successfully on WordPress 7.0 and 7.1.3, and **must not be called full-site backup/restore acceptance**. They exercise in-place WordPress meta recovery only; full SQL dump and separate-install restore with consistent user ID mapping remain blocking RC-03 work. **Still needed:** real authenticated HTTP/MCP adapter transport, native HTTP/cache/CDN, actual Secure Share provider, end-to-end backup/restore, locales, UI/a11y, performance/scale and installation compatibility. A passing PHPUnit smoke **must not** mark entire R02/R03/R09 or RC-02 as PASS.

### Stage S: source and contract evidence

- [ ] All legacy source, UI, privacy/contract, i18n and package checks pass on the **same** branch HEAD.
- [ ] Current plugin metadata requires WordPress 7.0+, PHP 8.4+, Core Blueprint Base; Core API minimum 1.2 enforced and optional Secure Share absent-safe.
- [ ] Audit every WordPress CPT, exact REST, revisions/autosaves, search/queries, private labels, AI/Abilities, all share modes and cleanup hooks. Alpha24 source hardening now checks multi-row label cleanup, public snapshot/key/mode persistence and secure grant rollback; PHP failure injection and live integration results still required. Test surfaces outside the Notes Library too.
- [ ] Fix PHP/JS gettext extraction and fail-safe locale formatting. **Initial audit found untranslated JS literals and raw Organize REST errors. Alpha24 also maps Organize REST error messages to static gettext keys. On the hardening branch they are now statically gettext-extractable and all five enqueues declare wp-i18n, with source contracts. Still **BLOCKED** until real gettext extraction, six locale outputs/fallback safety and interactive runtime behavior are validated.**
- [ ] Do not claim native WXR export preserves author-private catalog names and opaque relationships until experimentally verified; document full-site backup and export boundary.

### Stage R/I: disposable WordPress browser + API test matrix

Create two regular Notes editor accounts **A** and **B**, an actual site administrator and an anonymous browsing session; avoid using real content. Record the exact test environment, plugin versions, ZIP SHA, user role and sanitized screenshot/HTTP result.

| ID | Manual scenario | PASS expected | Status |
|---|---|---|---|
| R01 | WP 7.0/7.1, PHP 8.4+, Base API 1.2; Base missing/incompatible | Safe refusal, clear notice, no fatal fallback | NOT RUN |
| R02 | Gutenberg create/edit/save, autosave, revisions, restore, native Publish | Lossless blocks; private CPT cannot be publicly queried | NOT RUN |
| R03 | A/B/admin/anonymous, exact REST reads/edit/delete, revisions/autosaves/list | No unauthorized note, count, title, content, ID or meta disclosure | PARTIAL: 44 local HTTP origin checks PASS; full manual matrix NOT RUN |
| R04 | Notebook/category/tag create/rename/remove/filter, foreign catalog IDs | Independent personal catalogs; no cross-user suggestions or mutations | NOT RUN |
| R05 | List/Table, sort A–Z/Z–A + recent/oldest, search, paging, favorites/pins | Same owner-scoped results, no metadata leaks, saved view preference | NOT RUN |
| R06 | Bulk up to 20, invalid IDs/nonce, partial success, confirmed/cancelled Trash | No cross-owner writes; correct failures and share revocation | NOT RUN |
| R07 | Public frozen snapshot; revoked/trashed/restored note, private REST & CDN | Public gated, no resurrection, HTTP cache/robots policy honored | PARTIAL: frozen snapshot, headers & 404 revocation on local HTTP origin PASS; CDN/remaining manual scenarios NOT RUN |
| R08 | Secure Share absent/present, password, expiry, revocation and rollback | Soft absence; token isolated; no delivery after revocation | NOT RUN |
| R09 | MCP/Abilities disabled and explicitly read-enabled, A/B and admin | No unauthorized exposure, no write abilities, bounded queries | NOT RUN |
| R10 | Alpha migrations, label deletion, deactivation, backup and restore | No data loss; explicit validated catalog portability/uninstall decision | PARTIAL: full SQL source-to-target restore PASS (76 checks), other lifecycle cases NOT RUN |
| R11 | Light/Dark, mobile/table scroll, 200% zoom, keyboard/focus, screen reader | Accessible, responsive and intact in both Base themes | NOT RUN |
| R12 | 20/page lists, 250+ Note/label boundary, bulk + share audit log | Predictable limits; no unbounded writes, no leaked sensitive log values | NOT RUN |
| R13 | Two clean identical ZIP builds, checksum, root allowlist, dependency rules | Deterministic, no test/secret/hidden files in release package | PARTIAL: deterministic source build & ZIP integrity operator-reported PASS; final RC1 artifact NOT RUN |

**Security STOP:** any leak, unexpected anonymous/remote access, revocation failure, raw snapshot/body in logs, data loss, or broken activation. Never mark a NOT RUN cell PASS because a similar source smoke test passed.

### Full-site SQL restore gate (PASS on disposable WordPress 7.0, 2026-10-14)

**2026-10-14 real disposable runner evidence:** operator confirmed Alpha24 ZIP `22665ea1ca9445f1cd4092f0738e1965fab720b8a6e406c582c6b3f8d09c6dfc`, source checks and phpunit PASS. First source-site seed attempt failed because `wp eval-file` does not allow a top-level `declare(strict_types=1)` in evaluated PHP; fixed in test fixture. Second attempt passed that stage and genuine REST catalog mutations, but failed at `Create real native WordPress revision` because the fixture demanded a newly returned integer from `wp_save_post_revision()`. WordPress may return void when that exact content is already revisioned. The fixture now makes a real Gutenberg content edit and verifies actual stored revision IDs and content fingerprints across source and restored target SQL (not just a return value); the restore runner additionally owns database cleanup **only after both disposable databases are successfully created**. **Historical failure only:** superseded by the final successful rerun below. Missing `/usr/sbin/sendmail` warnings are nonfatal in this synthetic CLI-only fixture.

**2026-10-14 third restore attempt:** source fixture **PASS (39 checks)**; a complete SQL dump/import was initiated and completed without reported SQL errors. Target verification then stopped before its privacy assertions with PHP fatal `wp_get_post_revision(): Argument #1 ($post) could not be passed by reference`. Root cause: the test passed an inline `(int)` cast instead of a variable to WordPress's by-reference API. The test fixture and its regression gate now use a local integer variable for each revision ID. **Historical failure only:** fixed and superseded by final source-and-target PASS evidence below.

**2026-10-14 FINAL RESTORE PASS (operator evidence):** Notes HEAD `467d52246f2189fe2dda12e6dca1ef0050132f81`, branch `feature/notes-rc1-golden-hardening-v1`; `bash tools/check-restore` completed with `Notes restore source fixture: PASS (39 checks)`, a full SQL dump/import, `Notes full SQL target verification: PASS (37 checks)` and `Notes full SQL backup/restore: PASS (disposable source -> target)`. SQL dump SHA-256 `20cb18c97b324fec94f5013b69cac01057471c9a7dc5891a1e40d83ae785dcce`; packaged Alpha24 ZIP SHA-256 `22665ea1ca9445f1cd4092f0738e1965fab720b8a6e406c582c6b3f8d09c6dfc`. Synthetic ownership, per-user catalog references, exact revisions, Gutenberg content, private REST denial and Base source-approval rejection on target were verified. Runner printed disposable database cleanup. This closes **only the specific isolated SQL recovery test**, not all R10 manual lifecycle/migration cases, not an active-share historical-backup rollback, and not public HTTP/CDN/Secure Share validation.

### Full-site SQL restore procedure and evidence

An isolated, fail-closed restore rehearsal has been specified in [RC1-DISPOSABLE-RESTORE-ACCEPTANCE.md](RC1-DISPOSABLE-RESTORE-ACCEPTANCE.md). It requires a complete synthetic source WordPress site/database backup, a separately provisioned target database/site, user/author/catalog identity checks, Base trust-domain review, and privacy/sharing revocation checks. **PASS:** the documented SQL source-to-target restore completed on WordPress 7.0. **OPEN:** external HTTP/cache, historical active-share backup rollback and real provider token-revocation proof. `tools/check-integration` is not the backup/restore runner because it resets its sole PHPUnit database on every run.

**2026-10-14 operator confirmation:** the final Alpha24 source check, two identical releasebuilds, ZIP integrity and hash comparison were all reported PASS. The exact Alpha24 artifact SHA-256 is now pinned above; changes to docs in later commits will change newly built ZIP checksums. Restore and local HTTP tests intentionally used the previously verified artifact. These results do not close CDN, Secure Share, remaining browser/manual cases or the final RC1 release build.

### Real localhost HTTP origin security: PASS (44 checks), external cache/CDN and provider open

**2026-10-14 REAL LOCAL HTTP PASS (operator evidence):** On branch HEAD `bbb4d21dbdb7efed2bc69876cf4a91e04c9d0297`, `bash tools/check-http` completed with synthetic seed/edit/revocation PASS and **`Notes real loopback HTTP origin: PASS (44 checks, WP 7.0)`** against pinned Alpha24 ZIP SHA-256 `22665ea1ca9445f1cd4092f0738e1965fab720b8a6e406c582c6b3f8d09c6dfc`. The test verified A/B/anonymous REST privacy including B's explicit private list/count, a frozen public snapshot, origin cache/noindex security headers, and repeat 404 after revocation. [Detailed acceptance evidence](RC1-HTTP-SHARING-ACCEPTANCE.md). **This closes only local HTTP-origin checks**, not the full R03/R07 manual matrix, CDN/LiteSpeed/QUIC.cloud, real Secure Share delivery or external HTTPS. A full SQL restore plus local HTTP PASS also does **not** protect against restoring an **older backup containing a once-active share key**. The older-backup risk remains a distinct release security decision.

## Release-scope decisions requiring operator signoff

1. **Portability:** WordPress native CPT content and revisions remain canonical. Notebook/category/tag definitions use owner-private `user_meta`; normal post-only WXR does not automatically guarantee a valid round trip. Decide whether RC1 promises a tested full-site backup/restore route only, or needs a minimal scoped owner export. No new import UI without separate reviewed scope.
2. **Uninstall:** default preserve content, no silent removal of personal catalog data. Confirm what explicit user/operator cleanup and notices RC1 documents.
3. **Localization:** EN source; future NL/DE/FR/ES/IT/PT. Translated catalog completeness may be deferred under Suite RC policy, but source extraction, placeholder correctness, encoding and fallback safety cannot.
4. **Optional integrations:** the basic Notes workspace may not require Secure Share, MCP, Bricks, CRM, Work or Google/Microsoft services.

## Engineering closure & GO/NO-GO

- [ ] RC-01 Alpha23 UI accepted, full integrated source build and P2.7 branch difference reconciled.
- [ ] RC-02 real-world multi-account/security/caching/integrations evidence.
- [ ] RC-03 retention/upgrade/export/uninstall decision and round-trip evidence.
- [ ] RC-04 compatibility/i18n/a11y/performance evidence.
- [ ] RC-05 exact reproducible release artifact, package metadata, docs/README, version, SHA, final evidence.
- [ ] Every open P0 security or data defect closed; any accepted deferred feature transparently documented.
- [ ] Written operator approval of proposed `v1.0.0-rc1` promotion and **separate** merge/release/deployment GO.

The final report must state both positives and `NOT RUN`/blocking outcomes. Until then this remains a development alpha, not a certified Golden RC1.

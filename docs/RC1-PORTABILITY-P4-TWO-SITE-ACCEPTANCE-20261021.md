# Notes RC1 — P4 independent Site A/B portability acceptance

**Date:** 21 October 2026  
**Branch:** `feature/notes-rc1-portability-roundtrip-batch-20261019`  
**Candidate source:** runner/tests implemented on the feature branch; runtime evidence is **NOT RUN**.  
**Release:** P4 HOLD; RC1 Golden NO-GO. No Base, main, CI or production change.

## First installed-site execution (source SHA 4f61d84, 21 Oct 2026)

- WordPress 7.0 and 7.1.3: read-only P4 preflight PASS.
- WordPress 7.0: freshly installed Site A and Site B with staged Base and Notes both reached the post-activation checkpoint.
- First `wp eval-file` step failed before real JSON export/import with `strict_types declaration must be the very first statement`. This is a **P4 fixture execution incompatibility**, not proof of a Notes import defect.
- Runner printed `cleanup completed with status 1`. This reflects the failing test's overall exit code; the output did **not** report a particular `DROP DATABASE` failure, but schema absence is not independently verified.
- Runtime PASS **not awarded** for P4. WordPress 7.1.3 execute was correctly not started.
- Test-only corrective work: removed `strict_types` from the WP-CLI eval-file fixture, installed a test-site-only `pre_wp_mail` interceptor before site installation, and added a read-only preflight inventory that refuses **any existing P4-namespaced schema** instead of adopting/dropping it.
- The amended P4 source and real preflight must be rerun on the final exact SHA before any new executable batch. A new batch must receive its own operator approval for two fresh schemas per version. Protected historical schemas are never eligible for cleanup.

## Second installed-site execution (source SHA 588e4b, 21 October 2026)

- Both P4 read-only preflights PASS (WordPress 7.0 and 7.1.3).
- WordPress 7.0 installed two independent fresh sites and their separate Notes-only schemas; Base + Notes activation PASS on both.
- First `wp eval-file` source phase failed with `Notes P4 requires a named, runner-owned phase`. Export/import did **not** execute, and no product-code defect was demonstrated. WP 7.1.3 did not execute.
- Root cause confirmed against the official WP-CLI `eval-file` command contract: positional parameters are provided as **local** `$args`; the fixture's `global $args;` statement shadowed them.
- Test-only fix removes the global declaration and adds an independent read-only `wp eval-file ... p4-argv-probe --skip-wordpress` check. This test must PASS in both P4 preflights **before** provisioning SQL schemas; Level 1 also checks the probe is wired and cannot be bypassed.
- The runner logged `cleanup attempted; overall exitcode=1` with no explicit DROP failure. Pre-existing schema inventory was PASS before this run; subsequent fresh preflight is required to independently confirm absence of stranded P4 databases. Do not assume the failing process status alone proves cleanup.
- Source/test gate on the exact new SHA and real WP-CLI argument probe remain **NOT RUN** by the operator. No P4 runtime PASS awarded. A fresh SQL-execute batch requires a new explicit operator GO.

## Ownership and architecture

P4 extends the existing Notes-owned `tools/check-integration` canonical Level 2 entrypoint. It does **not** create a second canonical runner or a second MariaDB service.

The modes are:
- `--p4-preflight`: read-only infrastructure, toolchain, source and branch inspection. No new database, installed site, workspace or artifact is created.
- `--p4-execute`: requires **separate** `CB_NOTES_P4_DB_MUTATION_APPROVED=notes-p4-two-new-schemas-v1`. Creates precisely two fresh, non-colliding random schemas of the form `core_blueprint_notes_p4_<12 hex>_a_test` and `..._b_test`.
- Existing `--preflight` and `--execute` retain their previous PHPUnit behavior. A historical Level 2 PASS **does not imply P4 PASS**.

Both sites are independent installed WordPress runtimes with separate `wp-config.php`, Base/Notes plugin staging, site URLs, owners and SQL schemas. Their WordPress roots are beneath an owner-specific temporary directory under `$HOME/Downloads/.core-blueprint-tests/core-blueprint-notes`, configurable by `CB_TEST_ROOT`; this path is **not** a Git project. Both sites use the existing inspected loopback-only `cb-base-test-db` service at `127.0.0.1:3307`, per the Engineering Handbook. They do not share WordPress tables or users.

The execution creates no test schema until the read-only helper has validated the existing MariaDB version, port binding, volume and protected historical-schema inventory. Schema creation uses new-only `CREATE DATABASE`, never `IF NOT EXISTS`. Cleanup names are fixed at generation, validated against a strict product-specific regex, and made eligible for `DROP` only after successful creation by **this exact process**. A failure or interrupted process must never infer that another run's schema belongs to the current run. Runner staging and archives are private and ephemeral; in the event of power loss or force kill, manually inspect the newly named resources rather than adopting/reusing them.

## Test protocol

All source data is **synthetic**. No real Notes, personal data or confidential exports enter either database.

1. **Site A:** Create independent editor and foreign editor; store two owned Notes including Gutenberg paragraph/heading content, draft/private status, notebook, category, tag, favorite/pinned flags, distinct creation and modification UTC values. Also create a foreign Note. Export through Base's actual JSON engine and Notes' Markdown ZIP; ensure foreign material and sharing secrets are excluded; inspect ZIP Gutenberg sidecar fidelity.
2. **Site B:** Install WordPress/Base/Notes from the same exact source, with its own config and schema. Create an owner and a foreign editor with a private Note, enforcing actor isolation.
3. **Negative archive validation:** Inject an orphaned label and a forged sharing authorization field. Whole-document preflight must reject both **before any target writes**. Base `Engine::preview_json()` must continue refusing the unadvertised import capability.
4. **Owner-bound import:** Invoke the real Notes `ImportSession` → `NotesEntity::plan_import` → `NotesEntity::apply_import` path, with create-only per-record Base-compatible plans. Persist catalog and two Notes to Site B's WordPress database.
5. **Independent process replay:** Start a **new** WP-CLI process and re-import the same JSON. Journal receipts and target plans must recognize both notes as `skip`; no duplicates or new catalog entries.
6. **Site B export:** Use the real Base JSON export again; compare source/destination semantically, resolving local IDs through label names. Require exact Gutenberg source, title, normalized statuses, notebook/category/tag names, favorite/pinned flags, **both original UTC timestamps**, and absence of foreign/sharing data. Require different catalog opaque IDs and site-local post references.

### Non-goals / remaining Golden gates

- **This P4 test does not make the provider public.** `Provider` still omits `SUPPORT_IMPORT`; there is no public apply UI. The tested import is an internal authorization-bound Notes adapter, and the public Base `Engine::apply_json` orchestration/fingerprint gate remains **unproven** until a separately authorized import activation candidate.
- P4 does not claim all concurrency, worker crash, restored key-rotation, SQL-power-loss, network-interruption and recovery-operator cases. Existing fail-closed journal tests are complementary.
- UI/browser, downloadable headers across CDN, export size/performance, six-locale editorial acceptance, production-like package smoke and Level 3/4 remain open.
- If original `modified_gmt` does not survive the import/reexport, **fail RED**; do not weaken or normalize away source temporal fidelity. Investigate/fix the importer before Golden.
- The existing WP 7.0 and 7.1.3 PHPUnit PASS reported on `3597182f173ec17ead023fd8c45fe50cbce92a8f` is historical evidence for that exact SHA; it is not P4 evidence at the new SHA.

## Execution sequence

**Source preparation only; requires no Docker mutation:**

```bash
cd ~/Downloads/wp-core-blueprint-notes
git fetch origin --prune
git switch feature/notes-rc1-portability-roundtrip-batch-20261019
git pull --ff-only
git rev-parse HEAD
php -l tests/portability/p4-site.php
bash -n tools/check-portability-p4
node tests/portability-p4-runner-contract.cjs
bash tools/check
git status --short
```

**Read-only P4 preflight (after source PASS):**

```bash
bash tools/check-integration --p4-preflight
```

**Do not run the following until separately approved for the two newly created, disposable P4 schemas:**

```bash
CB_TEST_WP_VERSION=7.0 CB_NOTES_P4_DB_MUTATION_APPROVED=notes-p4-two-new-schemas-v1 bash tools/check-integration --p4-execute
CB_TEST_WP_VERSION=7.1.3 CB_NOTES_P4_DB_MUTATION_APPROVED=notes-p4-two-new-schemas-v1 bash tools/check-integration --p4-execute
```

If WP 7.0 fails, **stop** and investigate before WP 7.1.3. Record exit codes, exact Notes and Base SHAs, WP version, acceptance details and cleanup status. A successful source-only contract is not a WordPress P4 PASS.

## Handbook and governance

Follows the approved [First-Party Local Integration Runbook](https://github.com/christiaanbruinsma/wp-core-blueprint-engineering-handbook/blob/main/docs/release-qc/first-party-local-integration-runbook.md), [Local Build & Docker Safety Runbook](https://github.com/christiaanbruinsma/wp-core-blueprint-engineering-handbook/blob/main/docs/release-qc/local-build-docker-safety-runbook.md), and First-Party Validation Standard v1.

No `main` merge, Base patch, CI/Actions, release/tag, customer build, staging or production operation is authorized by P4 test preparation.

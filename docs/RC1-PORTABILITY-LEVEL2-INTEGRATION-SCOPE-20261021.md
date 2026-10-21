# Notes RC1 — product-isolated portability Level 2

**Date:** 21 October 2026  
**Branch:** `feature/notes-rc1-portability-roundtrip-batch-20261019`  
**Latest inspected source before this document:** `af3f96607d3e586f61abb4e402c8b93bdc709461`  
**Evidence:** Implemented source/contract, **not executed in actual WordPress** at this SHA. No Golden acceptance or import activation.

## Handbook resolution

The approved [First-Party Local Integration Runbook](https://github.com/christiaanbruinsma/wp-core-blueprint-engineering-handbook/blob/main/docs/release-qc/first-party-local-integration-runbook.md) deliberately uses one inspected, **shared MariaDB service** `cb-base-test-db` with **product-isolated schemas**, WordPress roots, fixtures and test cases. The container name does not mean Notes may use Base's historical `wordpress_test` database or Base integration runner.

The existing Notes `tools/check-integration` owns its Level 2 execution, requires read-only `tools/check-test-infrastructure`, a running verified loopback-only MariaDB 10.11.19 helper, exact Base source and a clean Notes checkout, then requires separate `CB_NOTES_DB_MUTATION_APPROVED=notes-alpha26-new-only-v1` for **new-only, randomly named** `core_blueprint_notes_int_<12 hex>_test` schemas. It stages Notes and Base into the Notes-only WordPress/PHPUnit runtime. It must never clear the protected historical `core_blueprint_notes_test`, Base schema, other products, Docker volume or live/staging database. A stopped existing helper remains a **BLOCKER** until explicit operator permission to start it; no new container is created for Notes.

## New required suite and scope

- `tests/integration/NotesRuntimePortabilityImportIntegrationTest.php` extends `WP_UnitTestCase`. Its seven execution-oriented test methods are discovered by `tests/phpunit-notes.xml.dist` (all `integration/*Test.php`) and invoked through canonical `tools/check-integration`.
- `tests/portability-level2-integration-contract.cjs` is a **source-only** guard, called by `tools/check`. It verifies disposable schema naming, mandatory read-only preflight and execute opt-in, PHPUnit discovery/failure flags, and that public import remains OFF. Its PASS is **never** a WordPress runtime PASS.

### Genuine WordPress test intentions

| Scope | Runtime assertion |
|---|---|
| Owner A → Owner B within the *same disposable site* | Base JSON export, authenticated whole-archive session, private catalog ID remapping, real post/meta writes, tags/markers and re-export semantic comparison |
| Replay and identity | Confirm journal receipt, destination note count remains one, catalog and note plans change to `skip`, actor switch invalidates session |
| Graph failures and collisions | Orphan label/foreign tag rejected before callbacks or mutations; duplicate notebook name refused without merging |
| Persistent intent and interruption | `add_option` creates a pending journal receipt; repeat import stops with review state and no unintended insert |
| Base public API safety | `Engine::preview_json/apply_json` refuse an unadvertised import provider, including inside a private planning session |
| Insert failure | Real `wp_insert_post_empty_content` filter injects a create error *after* journal reservation; retries remain blocked |
| Catalog CAS failure | Real WordPress `add_user_metadata` filter prevents destination catalog write; no post or journal may be created |
| Receipt postmeta omission | Real WordPress `add_post_metadata` filter omits receipt after post creation; second import may not duplicate the resulting ambiguous note |
| Temporal fidelity | Source `created_gmt` and `modified_gmt` must survive re-export unchanged; any mismatch is a legitimate RED requiring implementation repair, not an acceptance relaxation |

**Scope limitation:** Two isolated owners in one WP PHPUnit installation is **not** independent source Site A → target Site B migration. That remains an additional required P4 installed-WordPress profile, with separate owned schemas, no shared data tables and normalized re-export comparison. Likewise, a single simulated failure is **not** complete concurrency/power-loss proof, and no one should claim the import is recoverable in all conditions yet.

## Next source validation (no Docker mutation)

Read-only/local-only developer checks once the operator fetches the exact branch:

```bash
cd ~/Downloads/wp-core-blueprint-notes
git pull --ff-only
git rev-parse HEAD
php -l tests/integration/NotesRuntimePortabilityImportIntegrationTest.php
node tests/portability-level2-integration-contract.cjs
bash tools/check
git status --short
```

Run neither `--execute` nor `docker start` until the operator separately approves the existing helper start and the runner-owned **new disposable schema** creation. Do not use old Alpha26 master batch `tools/check-rc1-alpha26-batch` to work around its pinned Secure Share checkout/branch restrictions.

When approved, the canonical runner must execute the WP 7.0 and WP 7.1.3 matrix in sequence, collecting test methods, unexpected database errors, exact Notes/Base SHA and cleanup outcomes. If a test fails, classify actual WordPress persistence mismatch and patch the Notes import source or test fixture with evidence. Do not enable provider `SUPPORT_IMPORT`, public apply UI, CI or customer packaging as a side effect.

## Remaining gates

**P2-B/P3 source:** runtime tests written; not yet executed.  
**P4:** real WordPress 7.0/7.1.3 integration, independent Site A/B, fault/concurrency and browser/security acceptance OPEN.  
**P5:** Level 2/3 Golden release, exact-head build, ZIP digest, i18n editorial, UI a11y/light/dark/responsive OPEN.  
**Governance:** no Base patch, no product main merge, no CI/Actions, no tags/distribution or staging/production deployment without separate operator GO.

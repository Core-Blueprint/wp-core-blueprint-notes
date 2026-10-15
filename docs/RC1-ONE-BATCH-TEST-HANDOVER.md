# Notes Alpha26 | One-batch RC1 test handover

**State: original Alpha26 source and fixture build passed on 2026-10-15; WordPress integration was blocked when MariaDB was stopped. This Handbook-alignment patch requires new exact-HEAD execution. NOT RC1, NOT Golden.**

Notes repository: christiaanbruinsma/wp-core-blueprint-notes
Branch: feature/notes-rc1-golden-hardening-v1
Secure Share worktree: ~/Downloads/wp-core-blueprint-secure-share-r08
Secure Share pinned HEAD: 88c15c45a4898dd1c43c1dfad1d7265f5b693350

## What was developed together

- Stop sharing now rejects failed private-mode writes instead of incorrectly claiming revocation.
- Secure Share revocation is queued as a persistent non-autoload WordPress option, keyed by a hash of the grant UUID, with only UUID, expected revision and timestamp as values. It excludes invitation URLs, bearers, passwords and note contents.
- Provider failures, false returns and missing optional Secure Share retain the unresolved obligation. Only an actual true return clears it. A limited administrator-only nonce-validated manual retry and anonymous-free Settings Hub count are provided.
- Synthetic smoke checks cover persistence, exception, unavailable provider and recovery, plus the failure-to-stop scenario.
- R08 real-provider runner adds deactivation/reactivation with note retention and no resurrected grant; actual execution still pending.
- Alpha26 is separate from validated Alpha25. A double build records the exact Git HEAD and exact new ZIP SHA-256. The SQL, HTTP, rollback and real-provider runners require that local attestation before operating.

## Tomorrow: single automated local acceptance

Requires PHP 8.4+, Node, WP-CLI, Git, Base, the pinned clean Secure Share worktree, and existing MariaDB 10.11.19 test container at 127.0.0.1:3307. All runner sites are synthetic disposable installations. They must refuse already-existing test databases. Do not use real notes or credentials.

    cd ~/Downloads/wp-core-blueprint-notes
    git fetch origin
    git switch feature/notes-rc1-golden-hardening-v1
    git pull --ff-only origin feature/notes-rc1-golden-hardening-v1
    git rev-parse HEAD
    git status --short
    bash -n tools/check-rc1-alpha26-batch
    bash tools/check-rc1-alpha26-batch --preflight
    bash tools/check-rc1-alpha26-batch --execute

This should run:
1. Full PHP/JS/source contract and revoke-recovery smoke checks.
2. Two identical release ZIP builds on one HEAD, exact Alpha26 checksum and artifact verification.
3. WordPress 7.0 and 7.1.3 PHPUnit integration.
4. A 76-check full SQL restore from isolated source to isolated target.
5. A 44-check actual localhost A/B/anonymous HTTP origin suite.
6. The old active-share SQL rollback test using different E1/E2 host keys, old HTTP 404/new HTTP 200.
7. Real Secure Share grant issuance, password, frozen content, revocation plus deactivation/reactivation lifecycle.
8. Static R08.3 first-party HTTPS/security source checks, which explicitly do not prove browser/TLS acceptance.

**Any failure is STOP.** The full runner prints PASS only at the end. Do not treat intermediate static checks as passing runtime tests. Untracked dist/ is expected. Record the real HEAD, artifact hash, complete output, database cleanup result, and any failures.

## Manual R01–R13 still required for actual RC1

Test in a separate disposable WordPress site with editor A, editor B, a site administrator and a logged-out browser. Record PASS / FAIL / NOT RUN and sanitized evidence per case. Deployments and changes to live DNS/CDN are outside this batch without separate GO.

| ID | Remaining evidence | State |
|---|---|---|
| R01 | WP 7.0/7.1.3, PHP 8.4+, Base missing/incompatible activation warning | PARTIAL |
| R02 | Gutenberg editor, save/autosave/revision/restore/private Publish in real browser | PARTIAL |
| R03 | A/B/admin/anonymous REST + Library ownership, counts, search, exact meta and revisions | PARTIAL |
| R04 | Personal notebooks/categories/tags CRUD, ownership, foreign catalog IDs | PARTIAL |
| R05 | List/Table sorts, search, paging, favorites, pinned, view preference | UI previously accepted; integrated manual OPEN |
| R06 | Bulk up to 20, invalid IDs, partial failures, confirm/cancel Trash | PARTIAL |
| R07 | Public frozen snapshot; revoke/trash/restore and actual LiteSpeed/QUIC CDN bypass and purge | localhost PASS historically; CDN OPEN |
| R08 | Provider absent/present, HTTPS fragment/cookie/password/expiry and active-session revocation, provider false and full-host backup rollback | CLI PASS on Alpha25; browser and Alpha26 OPEN |
| R09 | Read-only AI Abilities disabled/enabled; owner/admin; MCP/external access explicit opt-in | PARTIAL |
| R10 | Alpha migration, full backup, lifecycle, user-meta catalog, separate uninstall/retention and WXR scope decisions | SQL PASS historically; manual OPEN |
| R11 | Light/Dark, mobile, 200% zoom, keyboard/screen reader, gettext and EN/NL/DE/FR/ES/IT/PT fallback | OPEN |
| R12 | At least 250 synthetic owned notes, 250-cap cleanup boundary, performance and secret-free audit | OPEN |
| R13 | Final exact HEAD, repeated build, ZIP file allowlist, correct metadata and separate RC1 promotion approval | Alpha26 OPEN |

## Security and release decision

No one should claim provider-level revocation when Secure Share returns false or throws. Old provider backups, entire-host rollback including old PHP or host keys, or CDN copies are not solved by the Public HMAC epoch or the queued provider cleanup alone. They require traffic quarantine, host-secret rotation, cache purge and an independently accepted provider recovery policy.

The supported current data-portability proof is **full-site SQL** with owner user-meta and author relationships. Standalone Notes WXR portability has not been proven; do not promise it. No destructive uninstall routine is included. Owner signoff is needed for retention, data-portability, and locale fallback release policy.

**Current NO-GO:** passing the local batch will close local engineering gates only. R01–R13 browser/manual security, locale/accessibility/load, origin/CDN and host recovery still need proof. Promotion to v1.0.0-rc1, merge, Marketplace/Repository distribution, or deployment requires separate explicit GO.

## Alpha26 Handbook-aligned local validation (October 2026)

The single supported operator flow is:

```bash
bash tools/check-rc1-alpha26-batch --preflight
bash tools/check-rc1-alpha26-batch --execute
unzip -t dist/core-blueprint-notes-0.1.0-alpha26.zip
sha256sum dist/core-blueprint-notes-0.1.0-alpha26.zip
```

`--preflight` inspects the existing MariaDB helper, loopback-only port bindings, protected volume consumers, fixed Notes schema collisions and pinned dependencies without starting containers or mutating schemas. `--execute` authorizes **new-only** fixed Notes test schemas and cleanup only for schemas created by the runner. A pre-existing schema blocks the batch.

The Alpha26 ZIP is a **non-customer test fixture** produced by `tools/build-alpha26-fixture` after WordPress Level 2. The canonical `tools/build-release` refuses customer packaging until the separate localization, runtime, field and RC1 approval requirements have been met.

**2026-10-15 integration database recovery:** The first Handbook-aligned batch correctly stopped because a historical `core_blueprint_notes_test` schema already exists. That schema is protected and must not be reset or deleted. The WordPress PHPUnit runner now generates a fresh, validated, single-use `core_blueprint_notes_int_<12 lowercase hex>_test` schema on each authorized execution, owns only its successful CREATE and drops only that newly created schema on cleanup. The read-only shared preflight reports unrelated historical Notes schemas instead of treating them as an attempted target. Other fixed-name runtime runners retain their own new-only collision refusal. Previously built Alpha26 ZIP and its SHA-256 are evidence for the *previous* HEAD only; rebuild and reattest after the updated Level 2 passes.

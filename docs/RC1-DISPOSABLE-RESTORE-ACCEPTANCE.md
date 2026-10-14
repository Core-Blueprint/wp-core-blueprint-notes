# Notes RC1 | Disposable full-site backup and restore acceptance

**Status: PASS for the isolated WordPress 7.0 full-site SQL roundtrip tested 2026-10-14.** The operator executed `bash tools/check-restore` against disposable WordPress 7.0 source/target installations; 39 source fixture checks and 37 target restore checks passed. This is not a claim for every host, WordPress version or migration scenario.

## Purpose and release boundary

Prove that a complete WordPress site/database restore preserves Notes private content, users, revisions, per-user notebook/category/tag catalogs, opaque assignments and authorization. A note-only WXR export is NOT an adequate substitute. This is an RC1 recovery acceptance plan, not a standalone Notes export/import product feature.

**Safety:** Never use staging, production, the Base/Work databases, or `core_blueprint_notes_test` (the PHPUnit runner drops it). Never run database DROP, import or restore against an operator-supplied database name. A later automated runner must hardcode two dedicated disposable databases and fail closed if its source/destination differ:

- `core_blueprint_notes_restore_source`
- `core_blueprint_notes_restore_target`

The guarded `tools/check-restore` runner now exists and has run successfully. It refuses either pre-existing database and only claims automatic cleanup ownership after both dedicated databases are successfully created; no other database is a valid target. Do not pass customer/private notes, real tokens, real emails or API secrets through test fixtures, logs or Git. If a recovery archive contains database secrets or note content, store it with restrictive permissions, outside Git and the public WordPress webroot, and delete it on completion.

## Preparation gates

1. Use a fixed disposable WordPress runtime with PHP 8.4+, matching Base and Notes build, and a pinned WordPress core version. Record WordPress, PHP, Base HEAD, Notes HEAD, SQL server/image, and exact Alpha24 ZIP SHA-256.
2. Provision a real **source** WordPress site with an isolated origin and disposable users. Seed two editor users A/B and a Base-approved admin in the source domain, using only synthetic content.
3. Create for A and B independent personal notebook, category and tag labels (including same-name labels). Assign distinct opaque IDs to private notes with Gutenberg blocks, revision history, favorite/pinned marker, list/table preference and a trashed note.
4. Create an explicitly confirmed public frozen test snapshot and then revoke it **before** taking the restorable baseline; confirm no active key remains. A separate negative test must check that restoring an older backup cannot silently reactivate a previously revoked link. This is a separate security policy decision, not a presumption.
5. Verify source private/owner REST reads and edits, anonymous denials and owner-scoped catalog resolution. Capture redacted evidence of note/user IDs, object counts, catalog ID relationships and authorization outcomes.

## Backup and recovery rehearsal

1. Back up the **entire disposable site database**, not just `wp_posts`. Include `wp_posts`, `wp_postmeta`, `wp_users`, `wp_usermeta`, `wp_options` and Base schema tables. Retain stable user IDs and author mapping. Capture only synthetic-content SQL.
2. Independently provision a **target** WordPress runtime and install the **same** Base and Notes plugin versions (plus required dependencies). Do not reuse the source site's active cookies or provider tokens.
3. Import into the hardcoded **target** database only. Validate that table prefixes, the salts/trust domain, site URLs and relevant Base Privileged Access Guard approvals are handled by an explicit, security-reviewed migration policy. In particular, imported administrator approvals must not be assumed valid on a different trust domain.
4. Verify post type and private status, exact Gutenberg block content, revisions, note ownership, notebook/category/tag names, opaque IDs and postmeta references. Verify B cannot read A content or metadata and anonymous REST reads remain denied.
5. Verify non-author and unapproved-admin permission gates, editor mutations, personal Library filters, preferences, and correct deny behavior for nonexistent/foreign catalog IDs. Check that Trash/Restore does not resurrect sharing grants.
6. Confirm that the target contains no unintended active public/secure sharing grants, sensitive audit data or externally usable tokens. Provider-backed Secure Share still requires a separate real-provider integration acceptance.
7. Compare redacted source and target invariants rather than raw database dumps. Write down the exact source/target environment, commands, assertion counts and evidence. Destroy test artifacts securely.

## STOP conditions

- Any private body/title/label appears for the wrong user or anonymous caller.
- Any missing/renamed catalog, orphaned opaque reference, wrong author mapping, lost revision or inconsistent note status.
- Any restored or exposed bearer/grant that is not explicitly authorized.
- A restore process proposes changing or clearing a non-disposable database or touching staging/production.
- An unapproved Base administrator gets privileged Notes access after migration.

## Outcome and documentation

- **PASS** requires an actual source backup, separate target restore, validated content and authorization round trip, checksummed archive, and redacted evidence.
- **FAIL** blocks RC1 and requires remediation.
- **PASS (2026-10-14)** applies to the exact completed WordPress 7.0 disposable SQL source-to-target test below. All other restore paths remain untested without their own evidence.

Product decision still open: full-site backup/restore may satisfy the **technical RC1 disaster-recovery minimum**; a portable owner-controlled Notes export/import is a separate data-autonomy capability and should not be represented as shipped. No destructive uninstall behavior is part of this acceptance.

## Recorded run: 2026-10-14, complete PASS

Operator-supplied terminal evidence on Notes branch HEAD `467d52246f2189fe2dda12e6dca1ef0050132f81`:

| Check | Recorded result |
|---|---|
| Static restore-runner contract, PHP syntax and full Notes source checks | PASS |
| WordPress source/target | Two isolated WordPress **7.0** installations |
| Database host | Disposable MariaDB **10.11.19** test container |
| Synthetic source fixture | **PASS (39 checks)** |
| Entire SQL dump/import to independent target database | Completed, followed by complete target verification |
| Target restoration and privacy validation | **PASS (37 checks)** |
| Whole test run | `Notes full SQL backup/restore: PASS (disposable source -> target)` |
| Database dump SHA-256 | `20cb18c97b324fec94f5013b69cac01057471c9a7dc5891a1e40d83ae785dcce` |
| Alpha24 ZIP SHA-256 | `22665ea1ca9445f1cd4092f0738e1965fab720b8a6e406c582c6b3f8d09c6dfc` |
| Teardown | Runner printed cleanup of its two dedicated databases and temporary files; independent post-cleanup inspection was not reported |

The fixture checks the complete synthetic backup and restore data graph: private note owners and Gutenberg content hash, exact preserved revision IDs/counts/content hashes, per-user catalog names and opaque IDs, favorite/pin flags, view preference, trashed-note state, owner and non-owner WordPress REST access, anonymous denial, prior public share revocation, and rejection of the source-signed privileged administrator approval on the target trust domain.

**Boundaries:** The source fixture revokes its test public share **before** the backup. This verifies that a revoked key stays revoked through the restore. It does **not** prove that restoring a *historical backup containing an active share* cannot re-expose that key. That risk needs its own explicit policy and negative test; delayed CDN/HTTP caches and a real Secure Share provider are also outside this SQL test. WordPress 7.1.3 was verified with nine PHPUnit tests, not this standalone SQL restore runner. Normal WXR export, cross-user-account portability, unknown hosting environments and multi-site restores are not certified.

No source merge, RC1 promotion or deployment is authorized by this PASS alone.

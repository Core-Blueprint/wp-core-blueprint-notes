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

### Stage S: source and contract evidence

- [ ] All legacy source, UI, privacy/contract, i18n and package checks pass on the **same** branch HEAD.
- [ ] Current plugin metadata requires WordPress 7.0+, PHP 8.4+, Core Blueprint Base; Core API minimum 1.2 enforced and optional Secure Share absent-safe.
- [ ] Audit every WordPress CPT, exact REST, revisions/autosaves, search/queries, private labels, AI/Abilities, all share modes and cleanup hooks. Test surfaces outside the Notes Library too.
- [ ] Fix PHP/JS gettext extraction and fail-safe locale formatting. **Initial audit found untranslated JS literals. On the hardening branch they are now statically gettext-extractable and all five enqueues declare wp-i18n, with source contracts. Still **BLOCKED** until real gettext extraction, six locale outputs/fallback safety and interactive runtime behavior are validated.**
- [ ] Do not claim native WXR export preserves author-private catalog names and opaque relationships until experimentally verified; document full-site backup and export boundary.

### Stage R/I: disposable WordPress browser + API test matrix

Create two regular Notes editor accounts **A** and **B**, an actual site administrator and an anonymous browsing session; avoid using real content. Record the exact test environment, plugin versions, ZIP SHA, user role and sanitized screenshot/HTTP result.

| ID | Manual scenario | PASS expected | Status |
|---|---|---|---|
| R01 | WP 7.0/7.1, PHP 8.4+, Base API 1.2; Base missing/incompatible | Safe refusal, clear notice, no fatal fallback | NOT RUN |
| R02 | Gutenberg create/edit/save, autosave, revisions, restore, native Publish | Lossless blocks; private CPT cannot be publicly queried | NOT RUN |
| R03 | A/B/admin/anonymous, exact REST reads/edit/delete, revisions/autosaves/list | No unauthorized note, count, title, content, ID or meta disclosure | NOT RUN |
| R04 | Notebook/category/tag create/rename/remove/filter, foreign catalog IDs | Independent personal catalogs; no cross-user suggestions or mutations | NOT RUN |
| R05 | List/Table, sort A–Z/Z–A + recent/oldest, search, paging, favorites/pins | Same owner-scoped results, no metadata leaks, saved view preference | NOT RUN |
| R06 | Bulk up to 20, invalid IDs/nonce, partial success, confirmed/cancelled Trash | No cross-owner writes; correct failures and share revocation | NOT RUN |
| R07 | Public frozen snapshot; revoked/trashed/restored note, private REST & CDN | Public gated, no resurrection, HTTP cache/robots policy honored | NOT RUN |
| R08 | Secure Share absent/present, password, expiry, revocation and rollback | Soft absence; token isolated; no delivery after revocation | NOT RUN |
| R09 | MCP/Abilities disabled and explicitly read-enabled, A/B and admin | No unauthorized exposure, no write abilities, bounded queries | NOT RUN |
| R10 | Alpha migrations, label deletion, deactivation, backup and restore | No data loss; explicit validated catalog portability/uninstall decision | NOT RUN |
| R11 | Light/Dark, mobile/table scroll, 200% zoom, keyboard/focus, screen reader | Accessible, responsive and intact in both Base themes | NOT RUN |
| R12 | 20/page lists, 250+ Note/label boundary, bulk + share audit log | Predictable limits; no unbounded writes, no leaked sensitive log values | NOT RUN |
| R13 | Two clean identical ZIP builds, checksum, root allowlist, dependency rules | Deterministic, no test/secret/hidden files in release package | NOT RUN |

**Security STOP:** any leak, unexpected anonymous/remote access, revocation failure, raw snapshot/body in logs, data loss, or broken activation. Never mark a NOT RUN cell PASS because a similar source smoke test passed.

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

# Notes RC1 — handover na Git-owned i18n foundation (2026-10-15)

## Directe taak voor opvolgende chat

Hervat de Notes RC1 Golden-acceptatie. **Geen merge/tag/distributie/deploy/CI zonder expliciete GO.**
Maak uitsluitend wijzigingen op de featurebranch en herstel source/runtime blockers waar nodig.

## Repositories en exact bewijs

- Repo: `christiaanbruinsma/wp-core-blueprint-notes`
- Actieve branch: `feature/notes-rc1-i18n-foundation-v1`
- Golden/security Alpha26-baseline: `feature/notes-rc1-golden-hardening-v1`, SHA `f880d7a7e2135c19b07c9f887a43928319da6ce1` (niet wijzigen).
- Base pin: `554963a3b27f5bad59452d03175b8f43896f10e8`
- First-party Secure Share pin: `88c15c45a4898dd1c43c1dfad1d7265f5b693350`

## Afgeronde technische aanpassingen

1. All 7 files live **directly in Git**, with an exact Git blob SHA contract:
   `languages/core-blueprint-notes.pot` plus six `core-blueprint-notes-<locale>.po`.
2. The catalog set currently has 305 source keys per locale, for
   `nl_NL`, `de_DE`, `fr_FR`, `es_ES`, `it_IT`, `pt_PT`.
   Three source plural messages per locale; local Babel parser/format validation
   reported zero errors before the Git commit. Each GitHub blob SHA equals the
   exact local audited source. **These are technical candidate translations, not
   independently approved editorial translations.**
3. The dynamic composite messages in `assets/organization-admin.js` were
   replaced with full sentence variants, with source interaction/regression tests.
4. `tools/i18n/check-reference` pins unchanged canonical first-party tooling
   v1.1.0. `tools/i18n/check` read-only validates extracted WordPress POT,
   six PO catalogs, headers, fuzzy/missing translations and gettext placeholders.
5. `tools/build-localized-candidate --execute` runs local Alpha26 validation,
   gettext, WordPress JSON, deterministic double ZIP, checksum/readback;
   it is **only an installable localized test artifact**.
6. Removed `tools/i18n/import-candidate` and
   `tools/i18n/import-candidate.py`. **No external ZIP download, manual file copy
   or archive import is needed.** The normal workflow is Git pull.
7. `tools/build-release` remains intentionally fail-closed (not customer ready).
8. `docs/RC1-I18N-EDITORIAL-REVIEW.md` details previous proposal QA/history
   and why the current PO files are still editorial CONTENT HOLD.

## Local terminal commands

```bash
cd ~/Downloads/wp-core-blueprint-notes
git fetch origin --prune
git switch feature/notes-rc1-i18n-foundation-v1
git pull --ff-only
git status --short
bash tools/check
bash tools/i18n/check
bash tools/build-localized-candidate --execute 2>&1 | tee /tmp/cb-notes-i18n-git-validation.log
unzip -tqq dist/core-blueprint-notes-0.1.0-alpha26-i18n-candidate.zip
(cd dist && sha256sum -c core-blueprint-notes-0.1.0-alpha26-i18n-candidate.zip.sha256)
```

**No post-commit local execution has been observed for the Git-owned catalog
candidate.** The previous user-validated i18n foundation source gate passed at
`61cd28f6bf8f0fdba5cff5d12e94f750c33fb1d1`, and the original Alpha26
local automatic batch passed at `f880d7a7e2135c19b07c9f887a43928319da6ce1`.
Do not report the new SHA as tested until terminal results are supplied.

## Likely next validation blockers

- Confirm canonical POT/source matching under actual WP CLI and gettext.
- Confirm WP-CLI `make-json` creates 5 JS file catalogs × 6 locales. The
  generated Jed JSON top-level domain should be `messages` (not the WordPress
  plugin slug); the filename retains `core-blueprint-notes`.
- Re-run and evaluate all real integration, restore, HTTP, rollback and provider
  tests. The source checks alone report their runtime gates as NOT RUN.
- Execute R01–R13 browser UX/accessibility/i18n, actual TLS/Secure Share
  cookies/passwords/expiry/fragment, real CDN/cache rollback and editorial
  six-language acceptance. These are not validated by local fixture ZIP.
- If a patch is needed, do it on the i18n featurebranch and give a new commit
  SHA plus exact fetch/switch/pull commands and full release-test evidence.
- Reconcile with Golden hardening only after explicitly authorized merge GO.

## Senior constraints

Privacy, integrity, no silent approval of AI-assisted translation proposals,
no remote/staging/production mutations, and no merges or GitHub Actions without
explicit user permission. Updates should be compact with concrete commands,
real PASS vs NOT RUN distinctions and the next acceptance decision.

## Follow-up: localized JS catalog source aliases (2026-10-15)

Local validation of source HEAD `e516a57597dc5f038d6e4135bb7e11a95f41f985`
passed `tools/check`, six-locale `tools/i18n/check`, real WP 7.0/7.1.3
integration (9 tests, 221 assertions each), Alpha26 deterministic fixture,
SQL restore, loopback HTTP, rollback and Secure Share CLI. The localized
candidate failed **after** these PASS results: WP-CLI produced 30 Jed JSON
files, but named two script sources `assets/organization-a.js` and
`assets/library-a.js`, not the registered `*-admin.js` source paths.
The missing candidate ZIP/checksum was a consequential failure, not a
checksum defect.

Fix scoped to the i18n build pipeline only:
- Use the documented WP-CLI `make-json --use-map` with
  `tools/i18n/js-source-map.json` (five canonical paths plus two observed
  source aliases); preserve all seven Git-pinned source catalogs unchanged.
- Check all 30 canonical JSON artifacts before the heavyweight Alpha26 batch
  to fail fast on packaging mistakes.
- Extend the existing localized package contract to protect this mapping.

**Pending acceptance:** the source patch is not locally validated merely by
committing. Pull its resulting feature HEAD; run `bash tools/check`,
`bash tools/i18n/check`, `bash tools/build-localized-candidate --execute`,
`unzip -tqq dist/core-blueprint-notes-0.1.0-alpha26-i18n-candidate.zip`,
and `(cd dist && sha256sum -c core-blueprint-notes-0.1.0-alpha26-i18n-candidate.zip.sha256)`.
Editorial language review, browser R01-R13, real TLS/CDN and rollback remain
open. Customer-release builder remains fail-closed. No merge or deployment GO.

## Corrective follow-up: WP-CLI min.js suffix handling (2026-10-15)

User executed commit `f151fe6ed2cba1e08cefdf81d502ed7f8ae8bef8`:
source contracts and six-locale check PASS, but `wp i18n make-json` generated
30 files and the preflight still failed on `core-blueprint-notes-nl_NL-55b0979ed721c28ac95ce829460011b9.json`.
The first `--use-map` was an identity map for the two `*-admin.js` paths;
it could not prevent the installed WP-CLI source-path suffix normalization.
The output `organization-a.js` and `library-a.js` is consistent with a
legacy overly-broad `min.js` normalizer matching the trailing `dmin.js`.

Scoped follow-up modifies only the i18n source map, source contract and docs:
map the original `*-admin.js` PO references to synthetic `*-admin.min.js`
paths. Both strict `.min.js` normalizers and the legacy behavior normalize
these to the canonical `*-admin.js` names required by the runtime. No actual
minified assets are created. Regression test checks both normalization variants.
This is a candidate workaround based on the observed runtime output, and needs
actual local WP-CLI confirmation. Preserve Git-tracked PO/POT, Base, and
Alpha26 runtime sources. No customer release, merge or CI GO.

## Notes parent menu / Library canonical URL (2026-10-15)

Observed on the isolated WP sandbox: the Notes parent menu loads
`admin.php?page=cb-notes-library` while the Library submenu loads
`edit.php?post_type=cb_note&page=cb-notes-library`. Same registered
`Page::render` callback but inconsistent WordPress active submenu highlight.
Root cause verified against WP core `wp-admin/menu-header.php`: the first
plugin submenu is linked through `admin.php?page` at the parent level,
while its submenu URL is scoped to `edit.php?post_type`.

Scoped feature-branch fix: register a `load-<Library hook>` handler that
temporarily redirects only the `admin.php` alias to the existing canonical
Library URL, reusing `Query::state` and `Page::url` for safe user-owned
search/filter state. No CPT replacement, no unrelated redirect and no changes
to Base, Secure Share, content, or the seven Git-owned translation sources.
The menu-order smoke now covers hook registration, owner/capability guard,
state preservation, unrecognized query keys and redirect-loop avoidance.
Local tests and actual WordPress browser acceptance still required.

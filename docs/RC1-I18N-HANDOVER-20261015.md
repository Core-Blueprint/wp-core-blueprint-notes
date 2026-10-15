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

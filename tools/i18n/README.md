# Core Blueprint Notes localization — current PI-1C candidate

**Source:** Git-tracked `languages/core-blueprint-notes.pot` and six PO catalogs (`nl_NL`, `de_DE`, `fr_FR`, `es_ES`, `it_IT`, `pt_PT`). This branch tracks **329 current source messages per catalog** after the PI-1C reconciliation (twelve added, two obsolete preview-only keys retired). No external ZIP or translation service is required.

The PO strings are **editorial review candidates**. Having a nonempty `msgstr` and no `fuzzy` flag is required for technical smoke tests but does **not** constitute independent human editorial approval, especially for import warnings, partial writes, sharing and recovery. See `docs/RC1-I18N-EDITORIAL-REVIEW.md` (historical proposals) and `docs/RC1-PI1C-I18N-SOURCE-CLEANUP-20261021.md` (current status).

## Operator source validation (no new databases)

```bash
cd ~/Downloads/wp-core-blueprint-notes
git fetch origin --prune
git switch feature/notes-rc1-portability-roundtrip-batch-20261019
git pull --ff-only origin feature/notes-rc1-portability-roundtrip-batch-20261019
git status --short
bash tools/check
bash tools/i18n/check
for locale in nl_NL de_DE fr_FR es_ES it_IT pt_PT; do
    msgfmt --check-header --check-format -o /dev/null "languages/core-blueprint-notes-$locale.po" || break
done
```

The canonical read-only tooling is `tools/i18n/check-reference` followed by `tools/i18n/check`. `tools/i18n/update` **mutates** catalog sources and must be used only after reviewed content changes; it must not be invoked during packaging. A patch assembled through GitHub alone is not claimed to have passed the local WordPress `make-pot` extraction until the operator actually runs the check.

## Internal localized package gate — STILL HOLD

`tools/build-localized-candidate --execute` stages six MO files and thirty JS translation catalogs and enforces a complete disposable Alpha26 runtime validation before creating a deterministic ZIP. Its delegated historical Alpha26 batch and verifier currently accept only specific older branches, so this is **not** an approved build path on the PI-1C branch. Do not bypass its checks or transplant an external ZIP. A separate reviewed runner/package follow-up is needed if localized field acceptance must use this branch.

The unlocalized `tools/build-alpha26-fixture` is only an internal test artifact; it deliberately omits `languages/`. `tools/build-release` continues to refuse customer release. All six human editorial reviews, WordPress locale UI checks and Golden P5 gates remain open.

## Script filename mapping

WP-CLI script translations use `tools/i18n/js-source-map.json` for all five registered JS entrypoints. Two mappings intentionally normalize synthetic `*-admin.min.js` references to actual `*-admin.js` script paths to preserve WordPress script translation identity. Those synthetic paths are not packaged. The build gate must verify all thirty actual JSON file names and source paths.

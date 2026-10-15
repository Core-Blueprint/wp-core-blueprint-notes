# Core Blueprint Notes localization (RC1 engineering candidate)

**Source delivery: Git only.** No download or ZIP import is needed.

The branch contains `languages/core-blueprint-notes.pot` and six PO files
(`nl_NL`, `de_DE`, `fr_FR`, `es_ES`, `it_IT`, `pt_PT`), each with 305
current gettext source messages. This POT is a review artifact from the full-sentence
Alpha26 source revision; `tools/i18n/check` independently validates the message
inventory against a freshly generated WordPress-aware POT.

**Important editorial boundary:** the six Git-tracked POs are translated
**runtime test candidates**. Their `fuzzy` flags were removed for functional
WordPress gettext/JS testing; this is NOT independent human translation approval.
See `docs/RC1-I18N-EDITORIAL-REVIEW.md`. No RC1 customer release GO.

## Operator workflow

```bash
git fetch origin --prune
git switch feature/notes-rc1-i18n-foundation-v1
git pull --ff-only
bash tools/check
bash tools/i18n/check
bash tools/build-localized-candidate --execute
(cd dist && sha256sum -c core-blueprint-notes-0.1.0-alpha26-i18n-candidate.zip.sha256)
```

The localized candidate builder generates MO and WordPress JavaScript JSON from
the Git-tracked PO files in temporary staging, validates all 30 Jed catalogs,
then runs the complete Alpha26 local batch on its pinned disposable test
environment before producing two deterministic builds with ZIP integrity and
checksum verification. No tracked PO/POT is rewritten.

WP-CLI `make-json` currently reports `assets/organization-a.js` and
`assets/library-a.js` for two source references in the six locale PO files.
`tools/i18n/js-source-map.json` provides explicit WordPress-supported
`--use-map` aliases back to the canonical registered
`assets/organization-admin.js` and `assets/library-admin.js` paths. The
map also explicitly includes the canonical five JS files, because `--use-map`
filters out all unmapped references. The JSON file SHA names and `source`
fields must match the five canonical paths before runtime tests start.

Canonical tooling:
- `tools/i18n/check-reference`: read-only pinned first-party engine integrity.
- `tools/i18n/check`: read-only source/POT/PO completeness and format checks.
- `tools/i18n/update`: explicit source-mutating synchronization ONLY for
  deliberately reviewed catalog updates; never during packaging.
- `tools/i18n/prepare-review`: optional source POT extraction to
  `dist/i18n-review/` for editorial comparison.
- `tools/i18n/prepare-drafts`: optional unreviewed PO generation under `dist/`.

The six-language browser/locale, real TLS, R01–R13 accessibility and external
environment gates remain open. `tools/build-release` still refuses a customer
release until a separate explicit GO and the required acceptance evidence.

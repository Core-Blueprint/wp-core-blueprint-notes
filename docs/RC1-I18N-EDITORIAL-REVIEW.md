# Core Blueprint Notes RC1: six-locale proposal audit

**Status:** CONTENT HOLD / proposals not approved / customer release BLOCKED
**Source:** independently extracted WordPress POT, `0.1.0-alpha26`
**Source POT SHA-256:** `dd3327e2713d0b6d813668a95c796b857cebf7d1ca9197fac84fd351844e43d2`
**Review artifact SHA-256:** `12f120c3463f64957d7b84fc4e04780e32ea8d94935d6d23ea2090bbc8fa383e` (archive: `notes-i18n-six-locale-audited-review.zip`, retained outside Git)

## Scope and evidence

- WordPress-aware extraction: 298 messages, PHP and JavaScript, 2026-10-15.
- Six review catalogs: `nl_NL`, `de_DE`, `fr_FR`, `es_ES`, `it_IT`, `pt_PT`.
- Exact POT message-key matching: **PASS** in each locale (298/298).
- Three two-form gettext plural messages per locale: **PASS**.
- Babel PO parser and format/placeholder validator: **PASS**, zero reported errors in all six locales.
- Proposal-only MO compile/readback (explicitly includes fuzzy messages): **PASS** for all six. This is **not** installed WordPress translation evidence.
- Every translated message deliberately retains `fuzzy` (298 per locale). **No** catalog is editorially accepted.
- Corrected three proposal strings: two NL UI grammar/clarity suggestions and one ES share-revocation warning clarification. Full before/after recorded in the review artifact's `technical-audit.json`.

The review artifact is not committed as a release source, and must not be moved into `languages/` or stripped of `fuzzy` automatically. The product `tools/i18n/check` must continue to reject incomplete/unreviewed catalogs.

## Source-side localization observations

1. `%1$s %2$s successfully.` composes a success sentence from a separately localized action and taxonomy label. This can produce awkward agreement and word order. Treat as source UX/i18n follow-up before final catalog approval.
2. `No %1$s yet. Use Add new to create your first %2$s.` depends on dynamic nouns; generic translations remain less natural. Prefer a complete translatable sentence per taxonomy when the source is next changed.
3. Critical Public/Secure Share copy was inspected for preservation of access restrictions and irrevocability of downloaded copies. No reversal of its safety meaning was found in the inspected proposals. This is **not** a substitute for six-language editorial acceptance.

## Acceptance still required

1. Final semantic and editorial approval for all six locale POs, especially security/privacy/revocation/operator strings; remove `fuzzy` only for accepted entries.
2. Re-extract current-source POT and reconcile any source or version drift with the canonical update process.
3. Run canonical `tools/i18n/check`, gettext `msgfmt --check-format --check-header`, and compile runtime MO and WordPress JS JSON from reviewed POs.
4. Validate installed WordPress PHP and JavaScript localization, including plurals, dynamic placeholders and locale switching.
5. Perform remaining browser/HTTPS/CDN/a11y and field tests; only then use the independent Handbook-compliant customer release gate.

**Do not merge, tag, publish or distribute solely on this audit.** The previous green Alpha26 test fixture remains separate and is not a customer release.

## 2026-10-15: 305-message full-sentence UI revision and six-language test candidates

The original 298-message POT and fuzzy proposal package are historical source evidence.
The current i18n branch removes five compositional msgids and introduces twelve
full-sentence variants. The new SOURCE target is **305** messages per locale.

Pinned audited candidate archive: `core-blueprint-notes-rc1-i18n-candidate.zip`
SHA-256: `b87d3fb77e324a77af431c6d6f5be3a809b243b2cc25f01f6c8692aaf5c4b5bc`.

The archive contains both:
- `review-proposals/*.po`: 305 **fuzzy**, editorial review proposals per locale.
- `languages/*.po`: the exact same 305 translations, with fuzzy omitted **for local gettext/JS runtime acceptance only**.
- `languages/core-blueprint-notes.pot`: audit template for cross-checking against a freshly WP-CLI-extracted POT.

Candidate PO strings have been checked with Babel's catalog parser and format
validation (all six, 305 source keys, three plural msgids each, no format failures).
This does not constitute independent human editorial approval or installed WordPress
locale acceptance. The source-aligned authoritative POT is generated locally by
`tools/i18n/import-candidate` using WP-CLI before importing the six POs.
The canonical `tools/i18n/check` must pass. The full Alpha26 batch remains a
prerequisite for `tools/build-localized-candidate --execute`.

**IMPORTANT:** The branch retains the fail-closed `tools/build-release` customer
builder. A successful i18n candidate build is not RC1 or a customer-release GO.

### Operator sequence (one validation batch)

Download the exact pinned archive into `~/Downloads/`. Use branch
`feature/notes-rc1-i18n-foundation-v1` and verify its current HEAD before execution.

```bash
bash tools/check
bash tools/i18n/import-candidate ~/Downloads/core-blueprint-notes-rc1-i18n-candidate.zip
bash tools/build-localized-candidate --execute
unzip -tq dist/core-blueprint-notes-0.1.0-alpha26-i18n-candidate.zip
(cd dist && sha256sum -c core-blueprint-notes-0.1.0-alpha26-i18n-candidate.zip.sha256)
```

No previously existing `languages/` directory is overwritten. Import is pinned
by SHA, branch-scoped, and requires a clean Git source state. Generated catalog
content remains untracked until explicitly committed and reviewed. A successful
candidate still needs the installation/UI/locale/HTTPS/browser and editor approval
specified in RC1 acceptance documentation before release promotion.

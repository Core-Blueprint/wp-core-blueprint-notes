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

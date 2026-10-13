# Core Blueprint Notes — alpha4 implementation status

**Version:** 0.1.0-alpha4.
**Branch:** \`feature/notes-personal-organization-alpha4-v1\`.
**Status:** source test candidate; no Golden or runtime-security claim.

## Scope added in alpha4

1. Personal catalog with three classification kinds: one notebook, one category and multiple tags (up to 12). Catalog definitions are private, owner-scoped user meta. Notes store only opaque catalog IDs in authorized post meta.
2. Gutenberg document panels for all three kinds and fast personal-label creation; the Notebook/Name and Category/Type labels avoid redundant section headings.
3. Native private **Notes → Organize** page with create, rename and delete using the Base danger confirmation where needed.
4. Notes Admin list shows resolved labels and filters by the owner's private catalog; no global term REST routes or cross-owner autocomplete.
5. Alpha1/2 text Notebook labels migrate to stable catalog IDs in bounded author-only batches. Label removal cleans up references on at most 250 owned Notes per action, otherwise fails without an unbounded operation.
6. Alpha4 security contracts and PHP/Node smoke tests added to the standard \`tools/check\` gate. Existing alpha3 private/Public/Secure Share behavior remains.

## Organize UI Golden polish (staging re-acceptance required)

- Organize registers its dedicated admin hook through Base `AdminTheme::register_screen()` with semantic component requirements for Select Picker, buttons, cards, fields, form controls and panels.
- The existing Base Modal Foundation is retained with its WordPress-native presentation. No Base code or other extension is modified.
- Organize uses Base card and button class contracts, scoped layout CSS and Base tokens (`--cb-space-*`, `--cb-border`, `--cb-text-*`). The Create action is visually separated from Name by 16 px.
- The Manage control uses an isolated native `select` progressively enhanced by Base Select Picker, avoiding React ownership conflicts. Native fallback remains available when enhancement has not yet completed.
- Added UI-contract checks and a PHP registration smoke. The dedicated presentation assets have a distinct revision so staging does not reuse the previous alpha4 JavaScript.
- Runtime acceptance must include Dark and Light, opened dropdowns, mouse/keyboard selection, live theme changes, responsive widths, Create/Rename/Remove, Base danger confirmation and regression of Library/Sharing.

## Ownership boundaries

- Base (mandatory) continues to own Settings Hub, UI Modal Foundation, capabilities/governance infrastructure; this branch does not modify Base.
- Secure Share (optional) continues to own grant and secure transport; this branch does not modify Secure Share.
- Notes owns classification, author isolation, shared snapshot projection and private/public mode authority.
- Team notebooks, hierarchy and shared classification catalogs require separate design acceptance.

## Validation gates

The sourcegate and canonical ZIP must be verified on the exact branch HEAD, with the resulting SHA-256 recorded. Full WordPress/Gutenberg, cross-user REST, migration, library filter, cache, Public Share, Secure Share and revocation tests remain pending until demonstrated in staging.

[Unified alpha4 acceptance](ALPHA4-UNIFIED-ACCEPTANCE.md).

No merge, CI/CD, production installation or WordPress.org submission without explicit GO.

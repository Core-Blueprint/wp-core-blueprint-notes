# Notes Library Overview + P2 alignment — Alpha18 staging acceptance

**Branch:** `feature/notes-library-overview-golden-v1`  
**Based on:** accepted Alpha16 source `d87c6c4494a74f41ff35d8d926caa43231ce3291`  
**Candidate:** `0.1.0-alpha18`  
**Status:** local sourcegate and real WordPress visual/runtime testing required. No release/merge/Golden claim.

## Local validation

```bash
cd ~/Downloads/wp-core-blueprint-notes
git fetch origin --prune
git switch feature/notes-library-overview-golden-v1 || git switch --track origin/feature/notes-library-overview-golden-v1
git pull --ff-only
git rev-parse HEAD
php -l src/Library/Page.php
node tests/library-bulk-contract.cjs
node tests/library-overview-contract.cjs
node tests/library-ui-contract.cjs
node tests/library-bulk-interaction.cjs
bash tools/check
bash tools/build-release
unzip -t dist/core-blueprint-notes-0.1.0-alpha18.zip
sha256sum dist/core-blueprint-notes-0.1.0-alpha18.zip
git status --short
```

## Focused staging acceptance

1. Verify the **Your notes** title is vertically centered with the select-all checkbox in Light/Dark Mode, at 100% and 200% zoom. Quick access and Notebooks headings must keep their existing spacing.
2. Install on disposable WP Sandbox after sourcegate/build PASS. Verify Notes parent and Library navigation still work and that redundant native **All Notes** remains hidden. Retain direct `/wp-admin/edit.php?post_type=cb_note` access for authorized users.
2. Select two notes and compare **Action**, visible **Notebook/Category**, **Apply** and **Move to Trash**. They must occupy a single bottom-aligned rail, use the same visual height at 100%/200% zoom, and the independent Trash action must stay Base red (normal/hover/focus).
3. Switch Action between notebook and category. Only the chosen destination is visible. Apply remains disabled until a deliberate target is selected. At narrow width (including 680px and 1000px), check wrapping, no horizontal overflow, legible labels and separated Trash.
4. For metadata, create notes with: no notebook, assigned notebook, category, zero tags, exactly three tags, and four or more tags. Verify **Notebook: Unfiled** when applicable, proper notebook/category labels, at most three visible tag chips, and an accurate `+N` counter with escaped, accessible additional-tag tooltip.
5. Long note titles and long personal label names must wrap without colliding with the four compact row actions. Compare Light and Dark Mode, keyboard focus, reader labels and 200% zoom.
6. Confirm Favorite/Pin/Edit/individual Trash continue functioning with their established privacy and confirmation rules. Test bulk category/notebook mutations and cancel/confirm Base danger modal using disposable notes.
7. Perform author A/B privacy regression. Labels from other users must never appear, including hover title text; both notes and categories remain owner-scoped. Frozen Public/Secure Share snapshots continue to omit private organization labels.

**Boundaries:** UI/metadata presentation only. WordPress post storage, catalogs, query filters, bulk owner validation and Sharing were not altered. No additional list-view implementation in this candidate.

## Next after acceptance

**Library Table View**: optional presentation mode for professionals (Title, Notebook, Category, Tags, Modified, Actions), reusing the *same* private Library result/query, pagination, bulk/marker interactions and responsive fallback. Plan and implement separately to keep Alpha18 acceptance focused.

**Governance:** no CI/Actions, merge, live deployment, WordPress.org submission or publish without separate explicit GO.

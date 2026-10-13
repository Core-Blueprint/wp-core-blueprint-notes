# Core Blueprint Notes — Productivity P2 Bulk Actions acceptance

**Version:** 0.1.0-alpha16
**Branch:** feature/notes-productivity-p2-bulk-actions-v1
**Base:** Accepted alpha13, commit aa4a07e9f87db5bdfdbaf377ec9b30c4615654f5
**Status:** source candidate; no Golden, staging-security, deployment or merge claim.

## Local sourcegate and release package

    cd ~/Downloads/wp-core-blueprint-notes
    git fetch origin --prune
    git switch feature/notes-productivity-p2-bulk-actions-v1 || git switch --track origin/feature/notes-productivity-p2-bulk-actions-v1
    git pull --ff-only
    git rev-parse HEAD
    php -l src/Library/Bulk.php
    php tests/library-bulk-smoke.php
    node --check assets/library-bulk.js
    node tests/library-bulk-contract.cjs
    node tests/library-bulk-interaction.cjs
    bash tools/check
    bash tools/build-release
    unzip -t dist/core-blueprint-notes-0.1.0-alpha16.zip
    sha256sum dist/core-blueprint-notes-0.1.0-alpha16.zip
    git status --short

Do not upload an alpha16 ZIP until all sourcegate, build and ZIP integrity checks pass.

## Alpha16 Notes navigation acceptance

1. In WordPress Admin, the Notes menu shows **Library**, **Add Note**, **Organize**, and authorized **AI Access**, but no redundant **All Notes** submenu. Unrelated or third-party submenus remain intact.
2. Click the parent **Notes** menu: it should open Library, not the native WordPress notes table.
3. Manually visit `/wp-admin/edit.php?post_type=cb_note` using an appropriately authorized account. The native WP list screen must remain functional and preserve its permission and ownership protections, without reappearing in the menu.
4. Direct editor URLs, Gutenberg create/edit, revisions, private notes and existing bookmarks must still work. Check Administrator and Editor roles; no permission expansion.

## Focused WordPress staging acceptance

Use disposable Notes with at least two separately signed-in Editor accounts, multiple notebooks/categories and 21+ Notes to exercise pagination. Earlier alpha13 P1 UI does not require full retesting.

1. **Progressive selection:** with JavaScript active, every visible personal Note has a keyboard-accessible checkbox and the header has Select all visible notes. Without selection, toolbar hidden. After selection, count and toolbar appear. Partial selection sets the indeterminate header state. Clear selection resets all.
2. **Page-only scope:** select all on a page of 20. Navigate to another page, notebook or search: no selection carries over. Never submit more than 20 IDs.
3. **Notebook move:** select two Notes. Choose Action: Move to notebook. Only the Notebook destination becomes visible, Category stays hidden, and Apply remains disabled until a notebook (or explicit Unfiled) is chosen. Then click Apply. Confirm persistence after Refresh results; clear a notebook by explicitly choosing Unfiled. Original note content remains.
4. **Category:** choose Action: Set category. Only the Category destination becomes visible and Apply stays disabled until a category or explicit No category is chosen. Switching from notebook to category clears both previous destination choices. Then clear by explicitly selecting No category. Check persistence and combined Library filters. Tags unchanged.
5. **Bulk Trash:** select Notes and click the separate red Move to Trash button on the right, even when no Action/destination is chosen. Verify canonical Base red outline, hover/focus and keyboard focus in Light and Dark Mode. A Core Blueprint Base danger modal opens. Cancel preserves all Notes, sharing and selection. Confirm invokes WordPress Trash, removes Notes after manual refresh and permanently revokes Public/Secure Share links, including after restore.
6. **Cross-user authorization:** user B cannot change a Note owned by A. Mixed A/B batches update only authorized Notes and return exact successful/failed counts. No foreign titles or catalog labels disclosed.
7. **Destination validation:** foreign/malformed catalog ID, invalid nonce, duplicate/malformed IDs, more than 20 IDs or unknown operation fail before mutation. Network request submits only selected IDs.
8. **Partial feedback:** mixed authorized/denied batch shows exact success/failure counts, identifies failed visible Notes by label, preserves accessible status and offers Refresh results; no auto-reload hiding errors.
9. **Failure:** simulate network loss before submission: error alert without falsely claiming success. Missing Base modal prevents Trash entirely.
10. **Accessibility/responsive:** Light/Dark, narrow admin, 200% zoom, keyboard/focus order, select-all, named checkboxes, Action and conditional destination labels, Apply enablement, status feedback, distinct red Base danger button. The two form groups must no longer appear simultaneously.

## Design/security constraints

- Select only visible rows already rendered by the personal owner-bound Library query and Access gate.
- Authenticated admin AJAX cb_notes_bulk; nonce cb_notes_bulk_v1, per-item owner/object capabilities, maximum 20 unique positive decimal ID strings. No persistent cross-page selection.
- Notebook/category destinations validated against the requesting user's personal catalog; empty means Unfiled/No category. Shared metadata and Gutenberg content unchanged.
- Trash always uses wp_trash_post() and existing trashed_post lifecycle, revoking Public/Secure Share. Never permanently delete.
- Server reports actual updated/failed IDs; client never fabricates sorting/counts.
- UI uses Base classes/tokens and Base Modal Foundation, without new Base or Secure Share patches.

## Governance

Sourcegate/mock tests do not prove live WordPress runtime isolation. Require staging acceptance before Golden. No CI/Actions, merge, publishing or deployment without explicit separate GO.

# Core Blueprint Notes

**Version:** \`0.1.0-alpha4\` | **Status:** staging test candidate, not Golden or production-ready.

Core Blueprint Notes is a WordPress-native Gutenberg private knowledge workspace. Core Blueprint Base API 1.2+ is required. Secure Share is an optional integration, not a requirement.

## Current functions

- Native Gutenberg Notes with revisions, autosaves, strict owner authorization and optional administrator override.
- Personal **Notebooks** (one per Note), **Categories** (one per Note) and **Tags** (up to 12 per Note).
- Owner-private organization catalog stored in **user meta**, with opaque identifiers stored in authorized Note post meta. This is intentionally **not** a global WordPress taxonomy, so term labels, suggestions and filters are never shared by default.
- Gutenberg document panels for Notebook, Category and Tags. Users can choose existing labels or create a new one. Use **Notes → Organize** for a compact Base-styled organizer with direct Notebooks/Categories/Tags tabs, contextual Add new, inline rename and confirmed removal. Removal cleans up references on up to 250 owned Notes; larger operations are explicitly refused pending a reviewed bulk process. Old Notebook text labels migrate on the owner's admin visits in batches of 30.
- Notes list columns for Notebook, Category and Tags, with combined owner-scoped filters. Internal organization metadata is never added to Public or Secure Share snapshots.
- Private-by-default \`cb_note\` WordPress post type. WordPress's native Publish action does **not** make Notes externally public.
- **Public Share:** separate unlisted URL with frozen allowlisted static Gutenberg HTML, explicitly created only through a Base danger modal with typed \`PUBLIC\` and required acknowledgement. Noindex/no-store are not substitutes for authorization.
- **Secure Share:** optional standalone premium-capable provider adapter using Secure Share's public consumer API. If absent, no prompt, warning, upsell or nonfunctional control appears. Secure links support available provider-side protection, expiry and revocation; only frozen view snapshots are delivered.
- Shared links can be revoked. Trash/delete permanently disarms Notes-managed public and secure delivery. The original Note remains private.
- Optional read-only Abilities/MCP integration; AI abilities remain disabled by default and author-scoped.
- Base Settings Hub tile under **Business**; Overview and AI Access settings.

## Privacy boundaries

No global Notes taxonomies or term endpoints are registered. REST organization operations always use the authenticated user's own catalog. A site administrator with \`cb_notes_manage_all\` can manage other authors' Note content under the established policy, but their different personal labels cannot be silently assigned to another author's note.

Tag/media/attachment visibility in third-party blocks is **not** supported for shared snapshots. Dynamic blocks, embeds and media are rejected. Hierarchical notebooks, team notebooks, collaboration, independent export/import and private file attachments remain explicitly out of scope. Core Blueprint Notes never claims secrecy against a server administrator or compromised hosting account.

## Build and staging acceptance

\`\`\`bash
bash tools/check
bash tools/build-release
unzip -t dist/core-blueprint-notes-0.1.0-alpha4.zip
sha256sum dist/core-blueprint-notes-0.1.0-alpha4.zip
\`\`\`

Artifact root: \`core-blueprint-notes/\`. Both PHP and JavaScript smoke suites include the alpha3 Sharing gates plus alpha4 owner-private organization checks. This is **source-level validation**, not proof of real WordPress security or cross-user isolation.

Use the [single alpha4 staging acceptance batch](docs/ALPHA4-UNIFIED-ACCEPTANCE.md) to verify upgrade/migration, Gutenberg, organization, private library filters, Public/Secure Share, cache and the mandatory two-user privacy matrix.

**Governance:** no merge, CI/CD, WordPress.org publication or production deployment without separate explicit GO.

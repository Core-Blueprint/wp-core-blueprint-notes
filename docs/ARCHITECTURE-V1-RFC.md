# Core Blueprint Notes — WordPress Architecture RFC v1

**Status:** PROPOSED / design-only; not approved for runtime implementation, release or merge.
**Repository:** `christiaanbruinsma/wp-core-blueprint-notes`
**Product:** Core Blueprint Notes (`core-blueprint-notes`)
**Date:** 2026-10-13
**Decision requested:** approve the v1 boundary and private/team collaboration model before implementation.

## Executive decision

Build an independent, WordPress-native **private notes and notebooks** extension. WordPress Gutenberg remains the only canonical editor; the WordPress database remains the system of record. Public integration contracts allow authorized sibling products to *reference* notes, and WordPress Abilities API provides an optional, permissioned AI interface. An MCP server/transport is **not** embedded in Notes.

**The product is not the separate Core Blueprint webapp Notes repository.** No runtime or data-model dependency exists on `christiaanbruinsma/core-blueprint-extension-notes`.

### Why this is a separate extension

- **Notes:** private working knowledge, draft thoughts, meeting records, personal/team knowledge (subject to approved sharing model).
- **Docs:** public/controlled **published documentation**, taxonomy, documentation navigation and public search.
- **CRM:** customer identity, Contacts, Organizations and customer relationship truth.
- **Work:** Projects, Work Items, recurrence and time/operational truth.
- **Helpdesk:** tickets, ticket conversations and ticket-specific internal notes.
- A Note may link to any of these; a link does **not** make Notes their authoritative data owner.

Avoid copying a Docs article into a Note as a second source of truth. Refer to it, or intentionally create new working notes.

## Proposed v1 product scope

1. **Notes**: create, edit, autosave and review native Gutenberg content with title, author, revisions, trash and recovery.
2. **Notebooks**: organize Notes under an internal `cb_notebook` hierarchy and list/filter notes by notebook; allow unfiled notes.
3. **Library UI**: private WordPress admin list with owner-aware search, filters and keyboard-accessible navigation. Editing uses the **native block editor**, not a second custom editor.
4. **Privacy**: notes and notebooks have no public archive, permalink, sitemap, feed or public frontend API; signed-in authorization is applied to all reads, queries, revisions, REST, exports and media access.
5. **Governance**: use Base's canonical ExtensionRegistry, health reporting and Governance/Audit foundations for real note lifecycle actions. WordPress revisions are canonical content-history truth.
6. **Portability**: bounded export of notes to a documented portable format and a future import path; no opaque proprietary database required for core note content.
7. **Optional reference integrations**: stable read/link contracts that do not require CRM, Work, Helpdesk or Docs to be installed.
8. **AI preparation**: design and test the public/application read boundary for Abilities. MCP exposure is separately opt-in and not a prerequisite for basic Notes.

### Out of scope for the first usable release

- Public-facing Notes templates, frontend writing, Bricks elements and public search.
- Replacing Docs' published documentation / Helpdesk's internal ticket notes.
- A custom rich-text editor, live collaborative editing or Google Docs/Notion feature parity.
- AI provider SDKs, model API keys, automatic outbound AI requests, automatic summarization or embeddings/vector databases.
- Silent AI content mutation, mass ingestion of other extension private records or always-on MCP.
- Full notebook-level ACL inheritance, granular team roles and per-note sharing *until* the collaboration policy is explicitly approved.

## Proposed WordPress domain model

| Object | Native mechanism | Proposed ownership |
|---|---|---|
| Note | Non-public `cb_note` CPT | Notes only |
| Notebook | Hierarchical, non-public `cb_notebook` taxonomy | Notes only; classification, **not** an authorization grant |
| Tags | Optional `cb_note_tag` taxonomy | Notes only; candidate for later v1 polish |
| Revision / autosave | WordPress core post revisions/autosaves | WordPress canonical |
| Note reference | Notes-owned, provider-neutral relation (proposed) | Notes owns reference identity, sibling owns target truth |
| Attachment | Separate private-file policy, not ordinary public Media Library URL | Decision/security gate before shipping attachments |

CPT defaults under review: `public=false`, `publicly_queryable=false`, `exclude_from_search=true`, `show_ui=true`, `show_in_rest=true` (required for Gutenberg), `map_meta_cap=true`, `supports=[title,editor,author,revisions]`, no public rewrite/query surfaces. Do not assume `public=false` automatically secures the REST API, revision endpoints, Media Library, term lists or admin search.

**Visibility decision pending:** recommended first slice is **personal/private by default**. A shared/team Notebook requires a separately reviewed access model (who can read, edit, remove, export, relate and AI-access it). WordPress `private` post status and generic `read_private_posts` are **not** sufficient to represent per-user privacy. Note ownership and object-specific access must be explicit.

## Runtime and dependencies

- **Required:** WordPress-native plugin with hard Core Blueprint Base prerequisite. If Base is inactive/missing or Core API incompatible, refuse interactive activation and remain inert; provide an explicit required-plugin notice like other suite extensions. No standalone fallback.
- **Target baseline:** WordPress 7.0+, PHP 8.4+; confirm the exact required Base public Core API minor against the implementation baseline **before creating the bootstrap**. Docs currently declares Core API 1.1, Work 1.0: Notes must not hardcode a version chosen from memory alone.
- **UI:** Core Blueprint Base public Admin foundations and WordPress Admin remain canonical. Use standard Gutenberg without a Bricks dependency.
- **Optional:** CRM, Work, Helpdesk and Docs only through their documented public contracts; unavailable extension = fail soft, not plugin failure.
- **Optional:** WordPress Abilities API (present since WP 6.9); an MCP Adapter and its network authentication are optional external components. Never bundle a separate MCP transport into Notes.
- **No custom tables by default:** prefer CPT + taxonomies + owned post meta; justify any future relation table with observed performance/integrity needs.

## Data ownership and optional integration rules

Use the existing suite precedent, **not** a hard Docs dependency:

- Docs: public `CB\Docs\Frontend\Data\Document`, `Queries\Documents` and `Search`; CRM's `DocumentLinks` holds CRM↔Docs relations and rechecks Docs read authority. Notes should follow the *contract/authorization pattern*, not Docs' published-only exposure.
- CRM: optional reference to a Contact or Organization; CRM performs customer authorization. A Notes relation is never authority to read CRM data or the Note itself.
- Work: optional Project/Work Item references using provider/type/ID semantics. Do not reach into Work repositories or duplicate its item/task domain.
- Helpdesk: optional ticket reference, but ticket-internal support notes stay in Helpdesk. Users who can read a ticket do not automatically gain permission to read related Notes.
- Docs: a Note may link to a published Doc. Docs remains the publisher and owner of that content.

For every reference, both ends must pass their **own** read gate at display time. No cross-plugin SQL foreign keys, private repository imports, copied access grants, or duplicated source data.

See `INTEGRATION-CONTRACTS-V1-RFC.md` for proposed interfaces and Abilities.

## Privacy and security invariants

1. **Fail closed:** anonymous visitors have no Notes access. An unauthorized or missing Note yields indistinguishable not-found semantics; no title, excerpt, count, taxonomy name, attachment name or relation is leaked.
2. **Authorize twice where needed:** list/search is scope-filtered at the source; each projected Note is checked again before returning content. An ID-based read checks access even if caller is authenticated.
3. **WordPress editor REST:** only expose the minimum WP REST surface Gutenberg needs. Inspect post routes, autosaves, revisions, taxonomies, meta schemas, embeds and search. Non-public CPT metadata alone is not a security boundary.
4. **Block content:** persist native `post_content` in Gutenberg block serialization, preserve unsupported/custom blocks rather than silently flattening them. Sanitize any *separate* rendered projection.
5. **Attachments:** do not ship public URL-based attachments as 'private'. Defer until protected storage, downloads, capability checks and backup/export behavior are tested end-to-end.
6. **No external communication by default:** no AI SDK calls, search indexing at a third-party service, telemetry, or MCP server exposure without explicit operator configuration.
7. **Auditing:** record meaningful creation, update, trash, restore, export, permission/exposure-setting changes and AI write-approval actions without logging note body or secrets. Respect canonical Base retention controls.
8. **Deactivation/uninstall:** preserve content on deactivation; uninstall/data-erasure behavior is explicit, destructive only with operator confirmation.
9. **Resilience:** missing sibling plugin, disabled MCP Adapter or inactive Abilities expose zero fallback data and do not affect native editing.
10. **Backup/export:** document what must be backed up (posts/revisions/taxonomy/relations and protected media, if added); never promise privacy against a compromised site administrator or database host.

## Proposed delivery sequence

- **Gate 0 — design acceptance:** confirm ownership, private/team model, notebook structure, retention and import/export requirements; inspect exact Base API and existing plugin conventions.
- **Wave 1 — Native foundation:** plugin bootstrap, Base required-notice/activation gate, `cb_note` CPT, `cb_notebook`, independent read/write authorization service, Gutenberg UI, REST privacy regression tests.
- **Wave 2 — Private library:** owner-filtered searches, revisions/restore, export, governance and accessibility; validate with two independent users and admin.
- **Wave 3 — Contract-first relations:** read/list/search/public application contracts and provider-neutral links. Wire CRM/Work/Helpdesk/Docs only after stable sibling APIs are confirmed.
- **Wave 4 — Opt-in Abilities read-only:** `list/search/read` with identical native authorization, bounded payloads and server-side explicit opt-in for MCP exposure. No AI write access by default.
- **Future reviewed wave — proposed writes:** `create-draft` / `propose-update` + human approval, expected revision checks, immutable revision history and strong audit evidence.

### Release acceptance gates (not yet run)

- User A's private Note/titles/tags/revisions/attachment references are invisible to User B through UI, WP REST, Abilities, MCP, embeds, admin query and exports.
- Unauthenticated Notes surfaces all fail closed. Admins have an explicitly documented support/override authority, not an implicit role bypass.
- Gutenberg autosave, restoration and supported blocks round-trip without corruption.
- Base deactivated/incompatible => activation gate and readable notice, no functional fallback.
- Abilities disabled/MCP Adapter absent => zero MCP access, native Notes still works.
- MCP read-only opted in => only user-authorized results; write endpoint absent.
- CRM, Work, Helpdesk and Docs can each be disabled without Notes boot failures or orphaned authority.
- CI/lint/unit/integration tests, UI keyboard/screen-reader review, package audit, installation and real runtime smoke checks must run on implementation artifacts before a 'Golden' claim.

## Open decisions (explicit user acceptance required)

1. **Sharing:** personal-only v1 (recommended risk-minimal first slice) versus explicit team notebooks in v1.
2. **Note-to-notebook relationship:** one canonical notebook per note (recommended initially) versus multiple notebooks.
3. **Export:** WordPress WXR versus structured JSON/Markdown bundle and import semantics; portability must remain practical.
4. **Attachments:** postpone to a hardened release (recommended) or block initial launch pending a private-file design.
5. **AI proposal workflow:** read-only in first release (recommended), with draft/write proposal in a distinct review wave.
6. **Exact Base API & release tooling:** verify current canonical contracts before creating files or publishing commands.

## Verified source references (2026-10-13)

- [Base README](https://github.com/Core-Blueprint/wp-core-blueprint/blob/main/README.md)
- [Docs README](https://github.com/Core-Blueprint/wp-core-blueprint-docs/blob/main/README.md)
- [Docs Integration API](https://github.com/Core-Blueprint/wp-core-blueprint-docs/blob/main/docs/INTEGRATION-API.md)
- [CRM Docs relation query](https://github.com/Core-Blueprint/wp-core-blueprint-crm/blob/main/src/Frontend/Queries/DocumentLinks.php)
- [CRM integration API](https://github.com/Core-Blueprint/wp-core-blueprint-crm/blob/main/docs/INTEGRATION-API.md)
- [Helpdesk CustomerDocumentation](https://github.com/christiaanbruinsma/wp-core-blueprint-helpdesk/blob/main/src/Frontend/Queries/CustomerDocumentation.php)
- [Work Domain Boundaries](https://github.com/Core-Blueprint/wp-core-blueprint-work/blob/main/docs/DOMAIN-BOUNDARIES.md)
- [WordPress Abilities API](https://developer.wordpress.org/apis/abilities-api/)
- [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter)
- [MCP Adapter creating abilities / exposure](https://github.com/WordPress/mcp-adapter/blob/trunk/docs/guides/creating-abilities.md)

**No implementation, automated tests, build, release, publication or merge is authorized by this RFC.**

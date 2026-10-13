# Core Blueprint Notes — Integration & Abilities Contracts RFC v1

**Status:** PROPOSED; contracts are design sketches, **not** existing PHP classes or callable abilities.
**Depends on:** [Architecture RFC](ARCHITECTURE-V1-RFC.md)
**Date:** 2026-10-13

## Design rule

**One canonical Notes application boundary, multiple adapters.** WordPress Admin/Gutenberg, future first-party CRM/Work/Helpdesk/Docs integrations, WordPress Abilities API and optional MCP all call the same bounded, authorization-aware application services.

A relationship to a Note conveys **context, not a permission grant**. A valid note ID is never sufficient to read or update content.

## Proposed application/service responsibilities

`CB\Notes\Application\Notes` (placeholder) is the sole canonical gateway:

| Operation (proposed, not implemented) | Input | Expected result / policy |
|---|---|---|
| `list_visible` | paging, notebook filter, query | bounded projections; source-level authorization filter; no hidden counts |
| `search_visible` | text, notebook, paging | bounded, owner-aware results; no raw unpublished data |
| `get_visible` | note ID | note projection or indistinguishable not-found |
| `create` | canonical Gutenberg content, owner, notebook | permission-checked write, revision, lifecycle audit |
| `update` | ID, content, expected revision | object authorization + optimistic concurrency; conflicting changes rejected |
| `trash` / `restore` | ID, expected revision | authenticated, audited lifecycle mutation |
| `export_visible` | bounded selection | permission-checked portable export; export contents are sensitive |
| `resolve_links` | note ID + provider/type/ID | independent read of both source Note and target extension |

Implementation names and signatures are **not** frozen. Define `WP_Error` cases, typed projection schema, search caps, cache policy and versioned public interface after implementation plan approval.

### Minimal read projection (example contract, not a schema commitment)

- `id`, `title`, `notebook`, `updated_at`, `author` only for authorized readers;
- `content_blocks` (or raw `post_content`) **only** for explicitly authorized exact reads;
- no body in list/search responses by default;
- signed-in identity and visibility always resolved by server, not from caller-supplied `user_id`;
- no leak of note existence in error differences.

### Notes ↔ siblings: proposed reference shape

```json
{
  "provider": "core-blueprint-crm",
  "resource_type": "contact",
  "resource_id": "123",
  "relationship": "related"
}
```

No copied customer names, ticket text, Work descriptions or Docs bodies in the relation record. Accept bounded stable IDs only. Validation and display go through the target module's public projection/query boundary.

| Target | Owner of truth | First integration design |
|---|---|---|
| CRM Contact/Organization | CRM | Note shows a reference resolved via public CRM APIs; CRM-side panel only with both sides authorized |
| Work Project/Work Item | Work | generic relation/reference; never modify Work state from Notes |
| Helpdesk Ticket | Helpdesk | link to existing authorized ticket; no duplicate ticket-internal notes |
| Docs Document | Docs | link published/authorized document via Docs API; no duplicate published article |

**Implementation placement is a decision, not a fixed requirement:** if a consuming product already owns the authoritative relation (e.g. CRM↔Docs), adapt that product's public relation contract rather than introducing a duplicate Notes table. For Notes-owned links, begin with a bounded storage abstraction; persist in post meta or a dedicated table only after cardinality/permission tests.

Every adapter:
- feature-detects the sibling;
- checks Notes access and target access independently at request time;
- uses only stable public APIs and documented hooks;
- fails soft when unavailable;
- does not directly read sibling tables;
- distinguishes stale links from forbidden links without revealing inaccessible metadata.

## Optional WordPress Abilities API

The Abilities API is available in WordPress >=6.9 and registers namespaced, JSON-schema-described operations on `wp_abilities_api_init`. It is an **API registry**, not an MCP server or AI provider. The official MCP Adapter is a separate optional transport.

**Proposed namespace** `core-blueprint-notes`; ability names below are conceptual until validated against the exact WordPress schema conventions:

| Ability | Phase | Policy |
|---|---|---|
| `core-blueprint-notes/list-notes` | read-only wave | only accessible, paged projections |
| `core-blueprint-notes/search-notes` | read-only wave | bounded query, no hidden hit counts |
| `core-blueprint-notes/read-note` | read-only wave | exact authorized body; no excerpts for forbidden notes |
| `core-blueprint-notes/create-draft` | **future** | may create a draft only under explicit user authorization/approval policy |
| `core-blueprint-notes/propose-update` | **future** | creates a revision-bound proposal, not silent overwrite |
| `core-blueprint-notes/apply-proposal` | **future** | human reviewer action, valid current revision, audit and new revision |

### Exposure layers and settings

1. **Ability registration:** register schema and execute/permission callback via WordPress, with correct supported version checks. Internal registration does not authorize remote exposure.
2. **MCP availability:** official MCP Adapter, if installed, hosts external protocol and transport; Notes does not implement HTTP/STDIO MCP itself.
3. **Operator consent:** **default off** site-level `Enable external Notes abilities` switch, and separate settings for read vs write proposals. Do not expose `meta.mcp.public=true` unless explicitly enabled. Prefer MCP-specific exposure `meta.mcp.public`, not broad `meta.public`. Reconcile WordPress 7.0 vs 7.1 REST exposure behavior at implementation.
4. **User authorization:** require authenticated WP user / supported scoped client authorization, notes-specific ability permission, per-object read gate. A global enabled setting does not override note access. Access tokens/app passwords are operator-managed and must never be emitted in note content or logs.
5. **Write authority:** no generic direct write ability in the first release. Future AI-created draft/update proposals require an approval path that the human can inspect and reject before canonical mutation.
6. **Client-side approval is insufficient:** do not rely only on an AI client's confirmation dialog; enforce server-side capabilities and policy.

Reference: [Abilities API](https://developer.wordpress.org/apis/abilities-api/), [registration](https://developer.wordpress.org/reference/functions/wp_register_ability/), [MCP exposure](https://github.com/WordPress/mcp-adapter/blob/trunk/docs/guides/creating-abilities.md).

### Audit and performance

- Log authenticated user/client identity class, action, note ID, outcome and decision **without** note text, secrets or search query contents.
- Bound search, list, batch IDs, response body sizes and execution rate; test query amplification.
- Distinguish interactive user permissions from application credentials; never implicitly impersonate site administrators.
- All callbacks call canonical Notes services and return consistent WP_Error responses.
- A malformed input schema, disabled MCP setting, missing authentication, or missing Notes access must fail closed.
- External calls to AI models and embeddings never originate automatically in this plugin.

## Test matrix required before approval of a runtime implementation

| Scenario | Expected |
|---|---|
| Logged-out requester to REST, Abilities or MCP | No note data or discoverable metadata |
| Logged-in User B searches/reads User A's private note | No document, title, tag, relation, filename, count or revision leakage |
| User A retrieves own Note | Exact allowed fields only, bounded pagination |
| CRM link exists but Notes is private to someone else | CRM panel shows no Note; no access escalation |
| Note link exists but CRM/Work/Helpdesk record inaccessible | No target projection is returned |
| Sibling integration disabled | No fatal error; Notes continues working |
| MCP Adapter not installed or Notes external access disabled | No remotely usable Notes ability |
| Read-only MCP opted in | Authorized read/list/search; zero write abilities |
| AI-suggested update conflicts with newer Gutenberg revision | Proposal rejected/rebased by human, never overwrite silently |
| Gutenberg custom block / revisions | Lossless native edit/save/autosave/restore |
| Public WP REST `cb_note`, taxonomy, search, embeds, revisions and media | All enforce policy, regardless of `public=false` |

## Implementation review checklist

Before writing runtime code, inspect and pin:

- Base ExtensionRegistry + required-plugin activation notice, health and governance APIs;
- Docs Gutenberg CPT and REST implementation as a WordPress-native reference, without inheriting its public visibility policy;
- CRM Docs link query and admin adapter, Helpdesk CustomerDocumentation, Work domain boundaries;
- correct WP 7.0/7.1 Abilities and REST metadata behavior;
- current MCP Adapter release semantics and authentication;
- permissions to `read`, `edit`, `delete`, `export`, `use_mcp` per note and per site; named capabilities are **to be designed**, not yet shipped;
- actual project-local check/build scripts, directory naming, release packaging and acceptance commands.

**No code, schema migration, public API, published endpoint, CI execution or merge is introduced by this document.**

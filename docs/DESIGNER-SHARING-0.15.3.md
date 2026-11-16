# Designer and item sharing — 0.15.3

Designer is a content role with assigned venue, advertiser, or location scopes. Designers can create and edit authorized artwork, templates and placement drafts. They cannot manage devices, accounts, ownership, sharing, approvals or playlist publication. Existing placement Active, duration and schedules remain controlled by playlist managers.

## Sharing

The native template and placement editor has a Sharing metabox. The owning team's authorized administrators can grant access to an exact user (username/email), venue team, advertiser team or location team:

- **Use a copy**: view and create an independent draft under an owner the recipient is authorized to use.
- **Edit the draft + use a copy**: also edit the original item's content. This does not transfer ownership or confer sharing, approval or publication permissions.

Shared items appear under **Shared with me**, and shared templates are available in eligible playlist template pickers. Recipients without an assigned owner can edit shared drafts but need an owner assignment before creating a copy. Group grants follow current membership. Existing owner-team access remains in effect; sharing is additive.

Sharing changes are saved separately from content. Revocation applies immediately to the original; existing independent copies remain. Copies do not inherit sharing grants, approvals or source schedules. Content changes reset approvals and do not replace immutable published playback versions.

## Validation

- Disposable WordPress 6.8.12 / PHP 8.3 / SQLite integration: full existing suite passed, plus 37 content-access checks.
- Covers use vs edit, forbidden owner transfer/resharing, stale grants, revocation, group membership removal, location isolation, native save protections, playlist publication denial and separate nonce-protected Sharing form.
- PHP syntax: 31 plugin files; JavaScript syntax: 12 runtime files.
- Divi tests simulate theme presence; the commercial theme and production hosting were not available for a live integration test.

No production site or HWADS Box was updated. No player update is required for these changes.

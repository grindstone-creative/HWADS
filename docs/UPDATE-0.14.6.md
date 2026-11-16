# HWADS 0.14.6 — playlist limits and editable inactive artwork

Local plugin candidate only; not installed on the live site. No Pi update is needed.

## Per-playlist limits

Every playlist can contain at most **100 placements total**, including inactive, scheduled and expired slides. Templates in the library do not count until added as placements.

Playlist Settings now has **Content type** and **Maximum active placements**. Suggested starting limits are Ads 15, Menus 5, Community Events 5, and House Ads/Fallback 15. House Ads includes venue specials/promotions. The maximum is editable on each playlist (whole numbers 1–100); it is not a location-wide setting or an extra limit on a location's weighted mix. Existing Primary/House routing is preserved separately from the new content-type label. Existing Primary playlists default to Ads/15 and existing House playlists to House Ads/15 unless a saved per-playlist limit says otherwise.

“Active” means the placement's **Active switch is on**. Dates do not change this count: morning and evening ads, or expired ads whose switches remain on, each consume an active slot. Turn a slide off to free its slot. There is no schedule-overlap policy.

Turning on one too many placements shows a prompt and leaves that switch off. Both the playlist editor and individual placement editor enforce the saved playlist maximum. Counters show total placements and Active switches. Lowering the maximum below the current count prevents saving until some switches are turned off or the maximum is increased. Selecting a different content type supplies its suggested default before saving.

Adding/duplicating/uploading can still create inactive entries when active slots are full. Save the playlist to retain pending card changes. When the saved playlist is already at 100, save removals first before creating more entries. Removing an entry does not delete its independent placement record/history.

Server validation covers playlist saves, individual placement changes and publication. Related saves share a per-playlist lock so separate editors cannot race the count. A batch may swap active/inactive slides if its final state respects the cap. Invalid batch updates roll back rather than partially changing placements. Existing publications are not silently recalled or truncated: over-cap older drafts must be corrected before a new publication.

## Inactive editor appearance

The large selected artwork stays full color and fully editable. A thin red frame and **Inactive** tab communicate status; they do not block selection, dragging or overlay edits. Thumbnails retain their greyed-out diagonal slash and status label. Scheduled slides use an amber frame; expired slides use red. These marks belong to the editor only and are not added to published media or TV output.

Visuals in docs/screenshots/inactive-full-color-desktop.png and inactive-full-color-mobile.png use sample artwork to illustrate the editor treatment.

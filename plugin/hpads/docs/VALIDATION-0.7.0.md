# HWADS 0.7.0 verification

Validated in an isolated local WordPress installation, not on live staging.

- PHP source parsed on PHP 8.0 and PHP 8.3.
- 22 integration checks passed: fresh activation without migration, native post adoption/identity, template and placement media/content saves, playlist membership/order, draft versus publication, immutable published content, native edit URLs and authorization rejection.
- All three actual WordPress Edit screens and all three native Add New screens rendered with the correct native form and detail boxes. Templates/placements support native content; playlists deliberately do not have a web-content editor.
- Browser verification used the actual rendered WordPress editor and actual WordPress Media Library modal. Attachment-query responses and final POST navigation were supplied by the isolated fixture; server save hooks and existing attachment validation were exercised separately.
- Browser checks passed for content editing, media selection, submitted fields/nonces, right save sidebar, Slidestone playlist card serialization, placement preview above its details card and the web-content toggle. Screenshots were inspected.
- The stubbed POST navigation produced a Chrome cross-document ViewTransition abort message; the test excludes that specific browser/fixture warning. No other application JavaScript errors were accepted.
- ZIP integrity and required plugin entries checked at packaging.

Not validated: Divi installation/rendering, physical Pi sync or playback, live-host/MySQL compatibility. Tracking and QR positioning are deferred. Unfinished membership work is not included.

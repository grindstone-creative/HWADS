# Private landing preview route — 0.15.5

The reported screenshot uses wp-admin/admin-post.php?action=hpads_landing_preview&id=2119. That request sets WP_ADMIN and does not run the normal front-end query/wp action. Loading theme headers and manually initializing Divi modules from that request is unsafe: the theme's front-end dependencies may be absent.

Preview links now use the site home URL with hwads_landing_preview=ID. template_redirect dispatches the route before canonical redirects, after the front-end setup. Both new and legacy routes require a logged-in user with the existing per-ad read permission. Legacy admin-post links authorize, send private headers and redirect; they do not invoke the theme renderer. The destination rechecks authorization and uses the existing draft item renderer. Missing records and anonymous requests return 403. No drafts are made public; publications and saved content are unchanged.

Changed includes/core.php and includes/placements.php in updater and integrated. Packaged only the current updater candidate as 0.15.5, including existing shared-source changes. Added tests/landing-preview-browser.mjs.

Validation: all 32 updater PHP files parsed with PHP 8.3. Browser tests against disposable WordPress passed for template/placement legacy redirects, successful front-end responses, private/no-store/noindex headers, invalid record ID, and anonymous denial on both entry points. Existing standalone simulated Divi regression passed. The licensed Divi theme is not in the fixture. The exact production exception is unknown until its error email/log is supplied. This corrects a confirmed lifecycle defect but does not establish that every cause of the reported production crash is fixed. Not installed on the live site.

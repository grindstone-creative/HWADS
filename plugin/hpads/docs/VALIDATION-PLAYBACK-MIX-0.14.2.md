# Playback mix validation — HWADS 0.14.2 / Pi 0.5.2

November 15, 2026. Local disposable fixtures only. No live installation, GitHub push or real Pi command was performed.

Passed:

- PHP syntax: 27 plugin modules. JavaScript and Python compilation checks passed.
- Complete Python player suite: 74 tests passed. New mix cases cover 60/30/10 shares using highly uneven image/video durations, no immediate repeats, per-playlist bag fairness, observed time rather than declared duration, two-group renormalization, restart state, request idempotence, actual navigation history, expired/missing media, fallback, local placeholders, corrupt state, state-write failure and duplicate fallback identity. Sync tests cover original attribution, orientation filtering, atomic failure retention and mixed-media cleanup protection. Existing browser, analytics, IPC, sync and updater tests remain included.
- WordPress suites all passed in a disposable WordPress 6.8.12 / PHP 8.3 site: native saves/publication (44), embeds/dashboard/health/update policy, analytics (38), visuals (27), shared layout (9), landing options (11), player updates (16), approvals (45), scheduled updates (21), Playback mix (26), address/navigation (32), and the shared configuration-connection suite.
- Playback mix PHP checks trace native location/device saves, exact percentages, unique/same-location published choices, device inheritance/legacy override, original version/grant attribution, private-draft exclusion, API legacy compatibility, mixed-version heartbeats, orientation, disabling and advertiser write denial.
- Address/navigation checks trace multiline/plain-text storage, length limits, preservation on older forms, mix retention, nine role families, real sidebar registration, advertiser placement access and SuperAdmin View-as permissions.
- Actual WordPress/Chromium browser checks: 12 passes for native mix percentage and address save/reload, device source override/inheritance, actual advertiser/producer menus, direct forbidden section URLs (403), and JavaScript errors.
- Actual local-server/Chromium player: images and full-length video played in a mixed stream. Manual video skip recorded partial time, later video playback completed naturally, automatic continuation worked, consecutive repeats were avoided, and every play retained its original version/placement attribution. Existing seven playback-accounting scenarios and arrow-navigation checks passed as well.
- Package integrity and unmodified protocol-1 updater compatibility are checked separately when building the final archives. No runtime filename or third-party dependency has been added.

Limits:

These are software/fixture checks, not physical Pi certification. Confirm actual Raspberry Pi codecs, desktop/user-service startup, sustained offline playback and SD write behavior. InnoDB concurrency and proprietary Divi still need staging checks. Shares are soft targets over many plays; long videos, manual navigation, outages and repeat avoidance can constrain exact proportions. An interrupted current play is not reconstructed precisely after a power cut. Existing analytics retains its own checkpoint/queue behavior.

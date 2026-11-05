# Alter web branding and business simplification

## Objective and authorization
User requests removing Contenido and Reportes from the business menu; removing business settings sections for audio/notifications, brand and timezone; replacing Signage TV presentation with Alter and the supplied D:/Desktop/logo.jpg across the WEB system, including login/footer. Explicit exclusion: do not change the Android application yet.

Local implementation/tests/work-unit commits are authorized by ODD. No remote sessions, production access, environment edits, deployment, push, merge or PR creation. Supplied logo is a brand asset, not an instruction document; copy exact bytes, no redesign or image editing.

## Exploration and decisions
- Shared BusinessSidebar covers desktop and mobile menus. Remove those two entries only, not their routes/data/permissions.
- Removing fields alone breaks required timezone validation and resets omitted notification flags. Make profile saves preserve every hidden preference, timezone and business brand field, including null/missing metadata keys. Keep location timezone payload required by existing backend, although no timezone control is displayed there.
- Keep the global notification bell, admin timezone controls and businesses' stored logos/names untouched. User requested business settings sections, not deleting functionality/history or replacing tenant identity.
- Presentation branding must be independent of deployed legacy APP_NAME/VITE_APP_NAME: APP_NAME also determines cookies/cache/Redis prefixes. Do not change these technical identities, app bridges/events, hosts, credentials, Android identifiers or real .env values.
- Use a small shared Alter logo/name component and dedicated web branding configuration/props; server title/favicon and client titles must show Alter even with old technical app names. Include small-screen login. Retain incumbent layout/theme, no wholesale redesign.
- Change seeded network default and migrate only the exact legacy default Red Signage TV Colombia -> Red Alter Colombia; preserve custom network names. No production reseed/migration execution.

## Route, checks and delivery
- Delegated direct: mapping crossed4+files; bounded writer per multi-file work unit. Parent owns scope, task record and independent checks.
- Strict TDD on/off remains unspecified by repository/session. Regression-first for settings/data/branding behavior where feasible; record observed RED/GREEN, never invent configured mode. Existing PHP and frontend runners only, no new dependencies.
- PHP runner C:/xampp/php/php.exe vendor/bin/phpunit with explicit APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory:, DB_URL empty, array cache/mail/session, sync queue, null broadcast, verified-nonexistent APP_CONFIG_CACHE. No ambient DB/cache changes.
- Frontend npm run typecheck, npm run build, node --experimental-strip-types --test tests/Frontend/*.test.mjs; scoped Pint and git diff --check. Verify exact logo copy hashes and residual UI brand references. Visual checks only if a permitted environment is available; prior local-server launch was policy-blocked and must not be bypassed.
- RDD OFF globally (status verified); disabled/unmanaged, no native review.
- Git baseline is CLEAN at9b4377f, branch originally feat/business-unified-scheduling. Earlier task document claiming all changes uncommitted is historical, contradicted by current git; do not rewrite its history. New branch feat/alter-web-branding. No unrelated dirty baseline to include.
- Forecast approximately300–430 authored code/test/doc lines plus exact binary logo. Delivery ask-on-risk; user explicitly chose feature-branch-chain (both work units together on the feature branch), 2026-09-26. Cache this choice for this feature. Local work-unit commits authorized; no PR is authorized. Never code-golf to meet advisory400 lines.
- Android preservation baseline:95 app/src + domain/src + app/build.gradle.kts files aggregate SHA256 0203CB73E3C611DE19837CBD5D8180AFD1276B41538CB123EE5275E3E357E75D. No Android writes permitted.

## Tasks
- [x] T1 — Simplify business menu/settings safely. Delegated writer: BusinessSidebar/Settings form/controller/request + regressions. Remove requested UI, preserve hidden fields on partial contact save (including absent/null metadata), legacy supplied settings requests still behave, location forms unaffected. Observe focused RED/GREEN, typecheck and commit evidence. Rollback: only these menu/settings diffs and corresponding tests, not unified programming or streaming.
- [x] T2 — Apply Alter brand throughout web and verify integration. Delegated writer: shared branding/config/logo/layouts/login/title/footer/favicon/default network migration + tests/docs. Exact logo, accessible expanded/collapsed/mobile identity; stale APP_NAME cannot restore visible Signage TV or reset technical prefixes; conditional data migration preserves custom names. Full PHP/frontend/type/build/hash/residual checks and bounded visual evidence or explicit blocked limitation. Record work-unit commit and Android unchanged proof. Rollback: web branding code/assets/config and guarded data migration as coordinated unit, preserving business data and Android.

## Progress and memory
Exploration complete, no source writes at document creation. Engram discovery exposes no tools; project/session identity unavailable and not invented. Full mirror odd/alter-web-branding/tasks and proactive saves/session-summary remain pending. Local task record is recovery copy; read back updates. Commit ledger follows below; local document is authoritative while Engram is unavailable.


## T1 verification before commit
- Source freeze: requested business menu entries and settings controls removed; location timezone payload preserved. Partial update no longer resets hidden timezone/logo/metadata/preferences, including null/missing values; supplied legacy fields remain supported.
- Worker observed regression RED7tests/17assertions with6failures (missingtimezone and omittednotificationreset); focused GREEN21tests/92assertions. Parent independently reran same focused suite:21/92 PASS,5.408s; npm run typecheck exit0. Logs artifacts/alter-t1-parent.log and alter-t1-parent-typecheck.log. Scoped Pint3PHPfiles/diff check passed. Browser checks remain unavailable under prior server-launch restriction, not claimed.
- T1 implementation count203 authored lines including86-line regression. Settings/menu rollback boundary is the five changed implementation/test files; no schema/data migration for T1. About241 lines including initial task record; exact committed count follows git evidence.
- Chain strategy accepted by user: feature-branch-chain. First local work-unit commit is permitted; no remote PR/push/deployment. RDD disabled/unmanaged. Android untouched. Engram proactive bugfix/preference saves unavailable; this document records the root cause and result.

T1 commit observed: 127ae7bff5c94a735bdb4e59491dae0ace53599d (142 additions +99 deletions =241 authored lines). First feature-chain work-unit boundary:9b4377f..127ae7b. RDD disabled/unmanaged, no review receipt. T2 may now change BusinessSidebar branding; all requested menu removals remain. Running committed count241.
## T2 final verification before commit
- Final source freeze includes guarded network-default migration using transaction + row lock and PHP value comparison; only exact legacy default changes, custom names/JSON metadata preserved, down deliberately preserves data. No production execution. SQLite coverage only; MySQL runtime not available/tested.
- Parent final isolated full PHPUnit suite after final migration change:179 tests /1382 assertions PASS,27.116s; artifacts/alter-web-parent-final.log and .xml. Previous full run also179/1382. Worker focused branding GREEN5/41 after RED2brandingfailures+2missingmigrationerrors.
- Parent all frontend tests15/15 PASS, including5 real React static-render brand/menu tests; npm run typecheck exit0; npm run build exit0. Existing HLS592.56kB bundle causes Vite>500kB warning, not a build failure. Initial SSR harness CommonJS/ESM lucide import error was corrected; final tests pass.
- Exact supplied JPG and committed asset independently hash equal:6F416AF1BD86647446115C5A077300463F10EA7614FA87E3F0335BD013B12886. Android95-file aggregate independently matches baseline0203CB73E3C611DE19837CBD5D8180AFD1276B41538CB123EE5275E3E357E75D.
- No Signage TV/SignageTV/VITE_APP_NAME references remain in resources/js or resources/views. Technical identifiers/APP_NAME/session prefixes left unchanged. Scoped PHP Pint passes. Parent diff check identified only an extra taskdoc EOF blank line; corrected before commit.
- Runtime boundaries exercised: Laravel HTTP login/Inertia responses, disposable SQLite migrations/profile persistence, actual React static render and production compilation. Interactive desktop/mobile visual checks remain PENDING because prior server startup was blocked; no bypass or screenshot claim. No remote/production/Android operations.
- Second proposed feature-chain slice starts127ae7b and contains only Alter web branding, its tests/docs and tracking. Both work units remain together on feat/alter-web-branding; no PR/push/merge/deploy. RDD disabled/unmanaged. No dependency additions.
- User-facing rollout and preservation details in docs/ALTER-WEB.md. Later authorized deployment must include logo/build plus migrations and config cache; current Docker entrypoint does not run those operations. No APK required.
- Engram memory/save/session-summary tools remain unavailable; mirror odd/alter-web-branding/tasks pending, not claimed saved. This final task record preserves goal, instructions, discoveries, accomplishments, checks, files and next step.
## Closure / recovery
T2 commit observed: ed69a3dff28028547f0108fddc9cb540734d8308 (321 additions +23 deletions =344 authored lines, plus exact26660-byte binary logo). Second feature-chain boundary127ae7b..ed69a3d. Both coherent code/test/doc units individually below400; accumulated authored work-unit count585. No PR opened; these are future slice boundaries, not fabricated PRs or review receipts. RDD disabled/unmanaged.

Implementation and automated verification completed. Interactive visual acceptance remains pending, not counted as passed. Next step: user-authorized normal dashboard deployment and desktop/mobile smoke check; no Android update. See docs/ALTER-WEB.md for migration/config cache and preserved data details. MySQL runtime not tested. Vite large HLS chunk warning remains. Engram unavailable; mirror/session summary pending. Two generated PHPUnit XML reports remain untracked in artifacts, deliberately excluded from source commits; logs/build outputs retain existing ignore policy.

Relevant files: business menu/settings controller/request/form + tests/Feature/BusinessSettingsSimplificationTest.php (T1); config/branding.php, shared Inertia props and React BrandLogo/shell/login/footer/client title, Blade favicon/title, guarded network migration/seeder, exact public/brand/alter-logo.jpg and PHP/SSR regressions (T2). No environment/production/Android changes.

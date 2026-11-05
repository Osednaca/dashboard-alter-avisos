# Unified business programming

## Objective, authorization and decisions
The user requests one business menu and one form combining playlist content and scheduling: choose/upload media, order, durations, transitions, days/hours and location. On 2026-09-26 the user explicitly chose independent content per programming entry: changing one must not change another. Remove the separate business playlist navigation/form from the normal workflow; preserve legacy records and safe access/import through the unified surface, not a second playlist editor.

Local implementation, tests and scoped work-unit commits are authorized. No production access, credential/session discovery, deployment, push, PR or merge. Existing streaming fixes and user documentation edits are unrelated and must remain unstaged/unmodified.

## Evidence and design
Exploration mapped ScheduleController/ScheduleRequest, playlist models/controllers, scheduling resolver, React schedule/picker/row/upload components, navigation and tests. Keep existing schedule -> playlist -> items and Android manifest contracts. Save independently owned content atomically. Legacy shared content is copied on first unified edit; no mutation/deletion of another schedule's playlist or fallback. Explicit ownership metadata excludes new scheduled-only content from out-of-hours fallback. Refresh affected business devices after committed changes, including prior locations. Uploads retain existing tenant/auth/type/size validation and remain in the library if composing is cancelled; scheduling happens only on Save. Pending/failed uploads are clearly shown and cannot silently publish unready media.

Operate-mode UI preserves existing typography/components/theme. One Programación entry, one composer with content, timing and one primary save action. Inline multi-upload and library selection preserve unsaved fields. Accessible reordering, mobile layouts, local validation and processing/error states are required. Existing legacy bookmarks must lead to this same surface rather than a second editor.

## Route, test configuration and delivery
- Delegated direct. Mapping trigger: 4+ files. Bounded writers: backend and frontend each touch multiple non-trivial files. Parent owns product choices, task record and independent verification.
- TDD configuration remains unspecified: no authoritative on/off setting found in repository/session (same source as prior streaming recovery record). Do not invent a configured mode. Use regression-first checks for new backend behavior with observed RED/GREEN, plus ordinary functional/type/build/browser checks.
- PHP runner: C:/xampp/php/php.exe vendor/bin/phpunit. Always explicit APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory:, DB_URL empty, array cache/session/mail, sync queue, null broadcast, and verified nonexistent APP_CONFIG_CACHE. Never use ambient production DB or clear real caches.
- Frontend: npm run typecheck; npm run build. Formatter: C:/xampp/php/php.exe vendor/bin/pint on scoped changed PHP paths. Browser checks local-only, isolated test data, mobile and desktop in one bounded pass plus one correction confirmation.
- RDD: OFF, decided by global (status observed). Disabled/unmanaged; no review ceremony.
- Branch: feat/business-unified-scheduling from 436a11d; prior dirty streaming files preserved. Work-unit staging must use exact paths/hunks, never git add .
- Delivery strategy: ask-on-risk. Forecast 850-1200 authored additions+deletions, excluding generated assets. Chain choice pending user question; no commit until resolved. No PR creation authorized. Planned slices below are provisional, never code-golf to reach 400 lines.

## Tasks and acceptance
- [ ] T1 — Independent unified persistence. Delegated backend writer (requests/controllers/models/resolver + regression tests). Validate ownership/type/readiness/permissions and whole payload before transaction; persist media ordering/transitions/durations and schedule together; edit hydration is complete; legacy shared lists and fallback stay independent; new content stops outside schedule; old/new locations receive refresh. Record focused RED/GREEN and commit evidence.
- [ ] T2 — Single programming composer with direct multi-upload. Delegated frontend writer (form/uploader/types/navigation + applicable tests). One menu/form, no separate playlist flow; selecting/uploading media, reorder, duration/transition editing, timings, create/edit all work. Preserve state during upload/processing/error; no duplicate publishing/reload loss. Legacy entry points route into unified surface. Typecheck/build and local desktop/mobile checks required; record commit evidence.
- [ ] T3 — Integration acceptance and delivery notes. Delegated bounded runtime/test worker where useful; parent independent checks. Full PHP suite, typecheck/build, scoped formatting/diff checks, local create/edit/upload/order/isolation/withdrawal/navigation evidence and responsive screenshots. Document deploy/migration requirements, failures/skips and rollback boundaries; record commit evidence. No physical-TV/production claims without test evidence.

## Progress and recovery
- Read-only exploration complete. User confirmed independent content and exactly one menu/form. No new source writes at task creation.
- Engram discovery returned no tools. Project identity/session identity cannot be resolved through unavailable tools; none is invented. Full mirror under odd/business-unified-scheduling/tasks and mem_save/session-summary are pending. This file is the recovery copy; read back after each update.
- Existing dirty baseline: BuildDeviceManifest.php, LiveSourcePayload.php, device ManifestController.php/SyncController.php, DeviceSettingsTest.php, LiveStreamTest.php, docs/LIVE-STREAMING.md; untracked artifacts and docs/LIVE-EMBED-ORIGIN-OPERATIONS.md. Keep unrelated work outside this feature commits.

## Verification and commit ledger
No new feature checks or commits yet. Running committed authored count: 0. Next step: bounded T1 implementation and agreement on T2 payload; chain decision before first work-unit commit.

### Implementation progress / scope clarification
- T1 backend writer observed 6/6 new regressions RED before implementing; candidate functional checks in progress. T2 frontend composer and T3 isolated local fixture preparation are delegated concurrently with disjoint file ownership.
- Agreed contract: hydrated schedule items/priority, ready owned availableMedia, transitions, legacy content import within same form, bounded owned-media status endpoint. Independent managed playlists retain history on deletion, never enter fallback/import lists, and legacy shared data is copied rather than altered. Existing permissions remain required; upload permission is separate.
- Pre-existing Android limitation discovered read-only: ManifestParser consumes business_playlist and schedule windows, but does not consume scheduled_playlists content or its playlist IDs. This feature preserves that contract and does not claim offline switching/withdrawal at every schedule boundary. T1 outside-hours acceptance is the server resolver plus online refreshed manifest; physical/offline Android acceptance remains out of scope. No Android source changed for this feature.
- Delivery choice question (feature-branch-chain recommended versus stacked-to-main) sent; response pending. Source work may proceed locally, commits wait for the choice. Engram still unavailable; this recovery record is the only persisted feature memory.

### Verified task outcomes (formal commits pending)
- T1 backend source is frozen. Observed initial RED6/6, search RED1/1 and media pagination RED405; final focused GREEN32 tests/175 assertions. Parent independently ran entire PHPUnit suite with explicit isolated :memory: settings: GREEN167 tests/1289 assertions,42.129s, exit0 (artifacts/unified-scheduling/full-phpunit.log/.xml). Scoped Pint12files and diff checks passed. T1 authored change estimate489 including242 test lines and22 migration lines; kept one coherent safety unit rather than cosmetic splitting. No commit because chain choice is unanswered.
- Added paginated tenant-scoped ready-media picker endpoint24/page so libraries over100 files remain selectable; regression includes101 own ready assets, oldest-file page and filtered search, foreign/pending exclusions. Search results now link schedules directly into the unified form and omit standalone lists/managed orphan rows.
- Bounded fallback-assignment check found no user-exposed assignment path requiring another guard: business screens only show current playlist; API settings reject current_playlist_id; BuildDeviceManifest owns the pointer. No unrelated device source changed.
- T2 initial single composer/menu source is implemented; frontend helper regression/typecheck/build verification remains underway. T3 actual socket-free Laravel HTTP-kernel acceptance passed1 test/60 assertions against a fresh artifact-only SQLite/storage fixture: two PNG uploads with real processing/checksums, rejected invalid file, no publication on upload, order/transitions/durations, copied legacy content independence, null all-day hydration and tenant isolation. Authentication/CSRF middleware stayed enabled.
- Local browser server launch was rejected by execution policy before execution. No alternate launch/bypass was attempted; there is no server process to stop. Interactive/responsive browser acceptance remains pending; socket-free tests are not screenshots/UI verification. Runtime fixtures are excluded from Git and contain only localdummy data, never production sessions.
- New docs/BUSINESS-PROGRAMMING.md explains unified flow, migration rollout, non-destructive legacy behavior, compatible rollback limits and actual verification scope. All eight pre-existing streaming/user files checked by parent retain their initial SHA256 hashes. Engram mirror/session-summary still unavailable.

## Final local implementation / session summary — 2026-09-26

### Goal
Unify business playlist composition and scheduling into exactly one Programación menu/form, with independent content and direct multi-upload.

### Instructions
User explicitly confirmed each programming entry must affect only itself and there must not be two menus/forms. Preserve shared legacy data, existing streaming/user edits, tenant boundaries, file storage and all production state. No remote credentials, deployment, pushes or PRs were used.

### Accomplished
- T1 functional outcome verified: independent transactional ownership, strict ready/type/tenant/permission validation, complete edit hydration, paginated media selection/status, safe legacy copying/redirects, no standalone list search group, managed content excluded from server fallback, whole-business refresh on changes.
- T2 functional source/checks complete: one inline ScheduleComposer, one Programación navigation entry, uploaded media append without Inertia reload, retry/processing/unavailable states, accessible ordering and settings, library search/pagination, legacy imports in same form. Existing separate playlist React pages remain unreachable via redirected routes, not a second visible editor. Shared uploader retains default reload behavior elsewhere.
- T3 local automation complete; visual acceptance remains pending. Parent independently reran7/7 actual directly-used form-state tests with node --experimental-strip-types --test tests/Frontend/schedule-state.test.mjs, npm run typecheck exit0 and npm run build exit0 in23.11s on the frozen tree (3137modules). Parent logs: artifacts/unified-scheduling/parent-ui-{state-tests,typecheck,build}.log. Prior full PHP167/1289 and isolated real-route upload1/60 evidence remain valid; no backend changes since suite. Final diff whitespace check passed (line-ending normalization warnings only). Existing HLS592.56kB chunk warning is not a new failure.
- Parent independently read key controller/request/form/helper changes, actual HTTP-kernel log and isolated fixture report. All eight prior streaming/user files retain original hashes. New migration was applied only to disposable isolated test databases, not ambient or production data.

### Delivery ledger and boundaries
- New source/test authored lines observed: T1backend489; T2frontend900 (502 tracked additions+deletions plus398 newfile lines, including blanks); combined1389 excluding generated assets, docs and this record. Increase over original forecast includes replacement/removal of the old form and directly-used state regressions after browser verification was unavailable. No cosmetic shrinking or artificial test omission.
- Committed authored count remains0: question about feature-branch-chain versus stacked-to-main is unanswered. No work-unit commit/PR identity exists; task checkboxes deliberately remain formally open. Do not treat functional checks as commit/review receipts. RDD disabled/unmanaged.
- Prospective boundaries remain independent persistence/API+regressions; unified composer/upload+state checks; entry-point integration/docs/acceptance. Final slice allocation awaits the chain decision; no PR is created or implied. Keep tests/docs with the behavior and permit an honestly justified cohesive overage rather than code-golf.
- Rollback T1 needs coordinated schema/resolver handling: dropping is_schedule_managed or restoring old fallback logic over managed rows risks out-of-hours playback; preserve data and plan compatibility first. T2 rollback boundary is changed business React components/pages/types plus state helper/test; do not revert unrelated media/live components or backend streaming work. Documentation describes safe rollout/rollback, not a destructive automatic recipe.

### Pending / next steps
- Complete real interactive desktop/mobile checks when a permitted local server environment is available: one form/menu, file batch partial failure, upload-state preservation, reordering/edit/cancel and no horizontal overflow. Server launch was policy-blocked before execution; no alternate bypass and no process remains. No screenshots or browser acceptance are claimed.
- Obtain the already-asked delivery-chain decision before work-unit commits. No production deployment, physical-TV validation or offline boundary guarantee. Deployment of dashboard needs the new migration; no APK update for this form feature.
- Engram tools/session identity remain unavailable. Full mirror odd/business-unified-scheduling/tasks and memory/session-summary writes are pending; local task document and docs are recovery evidence, not claimed persistent-memory saves.

### Relevant files
- app/Http/Controllers/Business/ScheduleController.php and app/Http/Requests/Business/ScheduleRequest.php — unified contract/validation/save and library endpoints.
- app/Domain/Playlists/Models/Playlist.php; app/Domain/Scheduling/Services/ResolveActivePlaylist.php; database/migrations/2026_09_26_230000_add_schedule_management_to_playlists.php — ownership/fallback marker.
- app/Http/Controllers/Business/PlaylistController.php, PlaylistItemController.php, SearchController.php; app/Http/Presenters/EntityPresenter.php; routes/business.php — safe old-entry compatibility/hydration/navigation.
- resources/js/Components/app/ScheduleComposer.tsx; resources/js/Pages/Business/Schedule/Index.tsx; resources/js/Utils/schedule-state.ts — one composer and tested draft state.
- resources/js/Components/app/UploadDropzone.tsx, MediaPicker.tsx, ScheduleCard.tsx, BusinessSidebar.tsx, BusinessTopNavigation.tsx; resources/js/Layouts/BusinessLayout.tsx; resources/js/Pages/Business/Home.tsx, Content/Index.tsx; resources/js/Types/index.ts — inline media workflow and single-menu entry points.
- tests/Feature/UnifiedBusinessScheduleTest.php, BusinessDashboardTest.php; tests/Frontend/schedule-state.test.mjs — regressions/compatibility.
- docs/BUSINESS-PROGRAMMING.md — user and rollout guidance. artifacts/unified-scheduling/ — excluded local-only acceptance evidence/fixtures, never publish raw DB/sessions.

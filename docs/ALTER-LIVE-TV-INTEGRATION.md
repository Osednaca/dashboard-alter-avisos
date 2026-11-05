# Verify Alter TV playback locally

Alter 0.1.20 reports the visible screen about every three seconds. The dashboard displays the reported layout, media identity and playback position while the report is fresh. A browser live stream has its own buffer; this is a playback-state preview, not a pixel capture of the TV.

## Deployment order

1. Deploy the dashboard code with EasyPanel and run `php artisan migrate --force`, including the latest-state and global-PIN migrations. The user owns this deployment.
2. Configure the new six-digit global PIN in `/admin/devices/global-pin` if the previous web feature has not been deployed yet. Legacy screen PINs are invalidated; no menu unlock is permitted until the global PIN is configured.
3. Install the signed production APK on the TV. Existing APKs keep the explicitly approximate preview until upgraded.
4. For a TV registered before the portrait-default correction, select rotation 90 and top/bottom once in its settings. New registrations get these defaults automatically. Existing explicit orientation choices remain authoritative. Hold OK for about two seconds to open the PIN/menu on remotes without a Menu button.
5. Confirm the dashboard shows a fresh report, the expected orientation and zone order. Stopping the app must remove visible media from the preview after 15 seconds.

## Isolated localhost fixture

The local harness lives in ignored `artifacts/alter-live-tv-{bootstrap,fixtures,router}.php`. It creates only `storage/app/alter-live-tv-browser.sqlite`, refuses to reset an existing database, pins Laravel to testing/SQLite, and supplies synthetic users and local public brand images. Its local MP4 is copied from the existing Android development test asset into ignored `public/alter-live-tv-fixture/combo.mp4`. It uses the real activation, manifest acknowledgement, quick-play and playback-state handlers. There are no fixture routes in deployed application code and no production requests.

From the dashboard repository:

```powershell
# Seed only when the named fixture database does not exist.
& C:/xampp/php/php.exe artifacts/alter-live-tv-fixtures.php seed
& C:/xampp/php/php.exe -S 127.0.0.1:8127 -t public artifacts/alter-live-tv-router.php
```

Use a fresh development emulator and a developmentDebug APK built with `-Pdevelopment.apiBaseUrl=http://10.0.2.2:8127`. Choose **Usar servidor real** instead of demo activation. Once the app requests an activation code, in a second terminal run:

```powershell
& C:/xampp/php/php.exe artifacts/alter-live-tv-fixtures.php assign
& C:/xampp/php/php.exe artifacts/alter-live-tv-fixtures.php status
```

The app claims that assigned activation, downloads the local assets, acknowledges its generated manifest and publishes its actual state. The status command prints only sanitized device/report/source data, never the bearer token or its hash. The fixture's global menu PIN is 123456.

The initial business cycle contains two eight-second images followed by a six-second MP4. For a longer measurement window, generate the local 60-second variant once with an installed FFmpeg, then update the isolated fixture metadata:

```powershell
& 'C:/Users/Oscar Navas/AppData/Local/Programs/Python/Python312/Lib/site-packages/imageio_ffmpeg/binaries/ffmpeg-win-x86_64-v7.1.exe' -hide_banner -stream_loop 9 -i public/alter-live-tv-fixture/combo.mp4 -t 60 -c copy -movflags +faststart -n public/alter-live-tv-fixture/combo-long60.mp4
& C:/xampp/php/php.exe artifacts/alter-live-tv-fixtures.php long-video
```

The original file stays intact. The new URL, checksum, size and duration are delivered by a real rebuilt manifest; wait for its real ACK. During the video, compare its reported `media_asset_id`/`position_ms` with the web video's source and `currentTime`, allowing sample age and time elapsed since the browser received the report. The web refresh interval is three seconds. Compare the same item only; a transition can occur between samples. Opening PIN/settings must hide the visible playback preview. There is no remote pause command in the existing application, so menu masking does not by itself prove a reported `paused` video state.

The fixture router serves MP4s with Symfony `BinaryFileResponse` and byte ranges. PHP's ordinary built-in static server returned the complete file for Range requests, leaving Chrome's seekable range at 0–0 even though all 60 seconds were buffered. Reload the preview after this harness correction before measuring cursor alignment. Production media delivery still uses the existing storage/CDN setup.

Open `http://127.0.0.1:8127/login`. Synthetic administrator login: `admin@alter-fixture.invalid`; business login: `business@alter-fixture.invalid`; password for both: `AlterFixture26!`. The business preview is at `/business/preview`, dashboard at `/business/dashboard`; administrator dashboard is `/admin/dashboard`.

To send a legitimate local fullscreen takeover:

```powershell
& C:/xampp/php/php.exe artifacts/alter-live-tv-fixtures.php quick
```

This sends a 30-second local image to the paired fixture TV. Compare the fullscreen report and source IDs, verify the command remains `sent` during `playing`, then verify it completes and normal zones return. Open the menu/PIN/settings screens to verify scene masking. Keep this development server running until those checks finish; stop its own process afterward.

## Contract and limits

The token-authenticated `POST /api/v1/device/playback-state` stores one latest row per device. Schema 1 includes session UUID, increasing sequence, sample age, scene, actual logical canvas/layout, and only visible business/advertising/fullscreen zones. Client URLs and unknown keys are rejected. Media URLs are resolved from the TV's authorized delivered sources, and private assets are checked against its current business on both receipt and preview reads.

Normal zones can refer to their previously activated manifest while a newer manifest is installed; each zone declares its own version and item. Quick play must identify its active delivery/command. Live fallback identifies both the original live creative and the actual fallback media. Empty surfaces contain only source/state. Optional positions are absent for provider embeds.

Freshness uses server receipt time plus sample age, with a 15-second ceiling. Duplicate/older sequences do not renew receipt time or last_seen. Stale, offline, unresolved or administrative scenes expose no visible media. GET preview reads never rebuild a manifest or write playback state. Videos seek only to the reported position with bounded extrapolation while playing; they never infer the next playlist item.

## Observed verification

The isolated fixture seeded successfully; `/login` and its public PNG returned HTTP 200. The local MP4 returned HTTP 200, `video/mp4`, 16315 bytes, SHA256 `290ced2a655fcc748e81638a68fccb572ddc20da900728b2be6e30116554851f`, matching the Android source. PHP syntax checks passed for all three local harness scripts.

The real development APK paired device 1, uploaded actual reports into one latest-state row and acknowledged manifest 20261003201757 at `2026-10-03T20:19:43Z`. The backend then reported that manifest as current with no pending version. Fresh reports for both PIN and settings scenes had empty visible zones, rotation 90, `top_bottom` split and logical canvas 1080×1920. After returning to playback, sequence 56 reported business item `business-2`/media 2 at 3543 ms and advertising `creative-1`/media 3 at 610 ms, both playing with 8000 ms durations; the resolver returned those exact authorized local image URLs with age 861 ms. The corrected server-shaped envelope parsed successfully using the existing compiled Android `ManifestParser`: three business items, one advertising item, four assets. The latest heartbeat subsequently reported `ready` with no sync error.

An initial harness campaign used weekdays 0–6, which Android correctly rejected before downloading. Its isolated database values were repaired to ISO weekdays 1–7; a real manifest rebuild/download/ACK completed afterward. An attempted CLI rebuild overlapped a SQLite write and returned `database is locked`; the local harness now uses WAL and a 10-second busy timeout. No report or ACK was manufactured, and no product source changed to hide these fixture issues.

The 60-second H264 fixture (640×360, 24 fps, stream copy without a download or re-encode) returned HTTP 200/video/mp4, 155482 bytes, SHA256 `34062174218a90db17c6ea4a0609a3a621ced06714676668bbf31b3a64a8f7b9`. The TV naturally activated manifest 20261003202653 at `20:27:05Z`, then actual sequence 175 reported business video media 4 playing at 6927 ms/60000 ms with a fresh server-resolved URL for `combo-long60.mp4`.

The real fullscreen quick-play takeover was observed with sequence 221 at approximately 23.9 seconds and only the fullscreen zone visible. The administrator preview showed the fullscreen source, then normal split zones returned when both delivery and command completed. PIN/settings and background scenes hid media in the browser. Force-stopping the development app left one unchanged latest-state row, which expired; the browser displayed a stale report without media. Restarting created a new session and restored fresh playback.

Fixture HTTP range checks passed: full GET 200/155482 bytes, `bytes=2000-2999` returns 206/1000 bytes with `Content-Range: bytes 2000-2999/155482`, HEAD 200 with no body, and an out-of-bounds range returns 416 with `Content-Range: bytes */155482` and a zero-length body. `Accept-Ranges: bytes` is present. This changes only the ignored local router, without changing the web renderer or fabricating a playback cursor.

After reloading the browser, the same video URL had readyState 4, was playing, and exposed seekable range 0–60 seconds. Its cursor advanced from 32.963 to 44.828 seconds around the actual TV sequence 14 report of 38.579 seconds, received at 20:33:13Z with age 842 ms between those samples. This is consistent with elapsed time and polling. The earlier cursor near zero was caused by the fixture's HTTP200 range responses and seekable range 0–0 despite fully buffered video; no product renderer change was needed.

Local screenshots and sanitized status samples are preserved in dashboard `storage/app/alter-live-tv-*` and Android `artifacts/alter-live-tv/`. Unit/security/layout checks and commit boundaries are recorded in `odd/tasks/alter-live-tv.md`. Physical-TV installation, API34 runtime and public live providers remain unverified. The manual provider probe tests remain ignored; no production pairing or deployment was performed.
The final role switch from administrator to business landed on `/business/dashboard`, then `/business/preview`. Both displayed the same actual fresh vertical/top-bottom state and authorized two-zone sources. The business browser video was playing at 20.824/60 seconds with readyState 4 and seekable range 0-60. The unedited full-page proof is `storage/app/alter-live-tv-web-business-final.jpg`.

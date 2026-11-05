# Screen preview status

Administrator and business previews use the latest actual playback report from the selected TV when available. The reader resolves media URLs from authorized device sources, never client-supplied URLs. Business controllers restrict selectable devices to the signed-in business; source validation and the legacy snapshot guard prevent former-business media and playlist names from surviving device reassignment. GET never builds a manifest or writes playback state.

Reported previews use the TV's actual logical canvas dimensions, rotation, split, percentages and zone order. Only reported visible zones appear, and a fullscreen takeover replaces split zones. Settings, PIN, background and activation scenes hide media. Images and normal videos use the TV's center crop; native HLS uses cover while embedded providers control their own fit.

Reports remain fresh for at most 15 seconds, including the sample age. Expired, offline or unverifiable reports hide source URLs and media instead of falling back to an inferred item. Video position uses the reported cursor, advances only while the TV reports playing, and stops at the reported duration; it never assumes which item plays next. Browser live buffers are independent, so the preview does not promise the same live frame as the TV.

Administrator selection is deterministic and has a screen selector. Pages refresh scoped preview props every three seconds through Inertia; administrator analytics are not recomputed by a preview-only poll. Refresh follows the Inertia poll lifecycle, and the renderer also expires reports locally between responses.

## Older APKs

Without a current-business playback report, the preview remains explicitly approximate. It reads the acknowledged device snapshot, chooses the first eligible playlist item and advertising using its schedule windows, and preserves explicit rotation and split settings. It never substitutes a pending manifest or unrelated upload. A missing valid snapshot shows unconfirmed content. Updating to Alter 0.1.20 enables actual playback reports through `/api/v1/device/playback-state`; the matching backend must also be deployed.

## Verification

`DevicePreviewTest` covers confirmed-versus-pending snapshots, actual reports and expiry, source ownership/reassignment, no GET mutation, schedules, stable selection and business isolation. `tests/Frontend/screen-preview.test.mjs` checks canvas/split/order, visible zones, fullscreen, scene masking, freshness, bounded positions and honest legacy/live labels. SSR assertions do not prove video seeking in a browser or remote provider availability.

The current isolated Android-to-browser fixture and observed runtime evidence are recorded in [ALTER-LIVE-TV-INTEGRATION.md](ALTER-LIVE-TV-INTEGRATION.md). No production pairing or remote providers are used; production deployment and physical-TV verification remain separate.

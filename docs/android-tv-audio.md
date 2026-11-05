# Android TV advertising audio default

New TV portrait layouts and newly seeded defaults use `audio_mode: advertising`.
Manifests also supply advertising when a saved layout omitted this setting.
Explicit `none`, `business`, and `advertising` settings remain authoritative.
Existing shared layouts are not rewritten; TV settings continue to create private layouts.
The Android player must use the matching advertising fallback for cached older manifests.

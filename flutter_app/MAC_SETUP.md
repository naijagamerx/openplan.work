# OpenPlan Work — Flutter app: Mac setup & build guide

Self-contained handoff to run/build the app on a Mac. The `lib/` Dart code is
already written; this guide generates the native shells, applies the **required
biometric config**, and runs the app.

> The app uses biometric unlock on launch (Face ID / fingerprint). The native
> tweaks in **Step 3 are mandatory** — skip them and the app crashes when it asks
> for biometrics.

---

## 0. Prerequisites (one-time)

```bash
# Flutter SDK (includes Dart)
#   https://docs.flutter.dev/get-started/install/macos
flutter --version          # confirm it runs

# Xcode (for iOS) from the App Store, then:
sudo xcodebuild -license accept
sudo xcode-select --switch /Applications/Xcode.app/Contents/Developer
xcodebuild -runFirstLaunch

# CocoaPods (iOS deps)
sudo gem install cocoapods

# Android Studio (for Android SDK + emulator), then accept licenses:
flutter doctor --android-licenses

# Sanity check — fix anything it flags
flutter doctor
```

---

## 1. Native shells — ALREADY GENERATED ✅

`android/` + `ios/` shells are already generated (package
`work.openplan.openplan_app`) and the biometric native config (Step 3) is already
applied in this repo. `flutter analyze` is clean and unit tests pass. **On the Mac,
skip straight to Step 2.**

Only if the shells are ever missing, regenerate (won't touch `lib/`) and then
re-apply Step 3:

```bash
cd flutter_app
flutter create . --org work.openplan --project-name openplan_app --platforms=android,ios
```

---

## 2. Packages + static analysis

```bash
flutter pub get
flutter analyze        # should be clean (fix any reported issues before running)
```

---

## 3. Biometric native config — ALREADY APPLIED ✅ (reference only)

All four edits below are already committed in this repo. Listed so you know what's
configured (and to re-apply if you ever regenerate the shells in Step 1).

- **Android `MainActivity.kt`** (`android/app/src/main/kotlin/work/openplan/openplan_app/MainActivity.kt`)
  extends `FlutterFragmentActivity` (local_auth's BiometricPrompt needs a
  FragmentActivity host).
- **Android manifest** has `<uses-permission android:name="android.permission.USE_BIOMETRIC"/>`.
- **Android `build.gradle.kts`** sets `minSdk = 26` (secure storage + biometric need ≥ 23).
- **iOS `Info.plist`** has `NSFaceIDUsageDescription` ("Unlock OpenPlan with Face ID").

---

## 4. Point the app at the backend

The PHP backend runs on the **Windows/MAMP machine**, not the Mac. Pick one:

### Option A — production (simplest, HTTPS) ✅ recommended
```bash
flutter run --dart-define=API_BASE=https://openplan.work
```
✅ Phase-0 device-token auth is **deployed to live (2026-06-26)** —
`device_login`, `devices.php`, updated `Auth.php`, and `DeviceTokens.php` are all
live, so this works now. No cleartext/firewall fuss (HTTPS). Best path for the Mac.

### Option B — Mac → Windows MAMP over the LAN (both on same Wi-Fi)
1. On Windows, find the LAN IP: `ipconfig` → IPv4 (e.g. `192.168.1.20`).
2. Allow inbound port 80 in Windows Firewall (and make sure MAMP Apache listens on
   the LAN, not just localhost).
3. Run with that IP:
```bash
flutter run --dart-define=API_BASE=http://192.168.1.20/taskmanager
```
This is plain HTTP → also do **Step 5 (Android cleartext)** for Android.

> The default base URL with no `--dart-define` is `http://10.0.2.2/taskmanager`,
> which only works for an Android emulator running **on the same machine as the
> backend** — not the Mac case. Always pass `--dart-define=API_BASE=...` here.

---

## 5. Android cleartext HTTP (dev only, if using plain http://)

Add to the `<application>` tag in `android/app/src/main/AndroidManifest.xml`:

```xml
<application
    android:usesCleartextTraffic="true"
    ... >
```
Remove for production (production is HTTPS).

---

## 6. Run

```bash
# list devices/emulators
flutter devices

# Android emulator (start one from Android Studio first), then:
flutter run --dart-define=API_BASE=http://192.168.1.20/taskmanager

# iOS simulator:
open -a Simulator
flutter run --dart-define=API_BASE=https://openplan.work

# physical device: plug in / pair, trust the computer, then `flutter run`
```

### Login to test
- Email: `lazymantools@gmail.com`
- Password: your account password
- Master password: `<your-master-password>`

Expected: login → (biometric prompt on next cold start) → dashboard shows project
/ task counts pulled over the device token (master password never sent per request).

---

## 7. Build release artifacts

```bash
# Android APK / App Bundle
flutter build apk    --dart-define=API_BASE=https://openplan.work --release
flutter build appbundle --dart-define=API_BASE=https://openplan.work --release

# iOS (needs an Apple Developer account + signing in Xcode)
flutter build ipa    --dart-define=API_BASE=https://openplan.work --release
open ios/Runner.xcworkspace    # set Team / signing, then Archive
```

---

## 8. App identity / Play Store

Current: `applicationId = "work.openplan.openplan_app"` (in
`android/app/build.gradle.kts`) — a **new** app, separate from the legacy WebView
(`com.taskmanager.app`). They can coexist on one device.

To instead **replace** the existing WebView app on the same Play listing, set
`applicationId = "com.taskmanager.app"` in `android/app/build.gradle.kts` (and match
the signing keystore).

---

## 9. Troubleshooting

| Symptom | Fix |
|---|---|
| Crash on launch asking biometrics | Step 3a not done (MainActivity must be FlutterFragmentActivity) |
| iOS build: missing Face ID string | Step 3b (NSFaceIDUsageDescription) |
| Login "Unknown action" | Phase-0 not deployed to that backend — use LAN/MAMP or deploy first |
| Android can't reach server | cleartext (Step 5) + Windows firewall + correct LAN IP + same Wi-Fi |
| `flutter doctor` warnings | resolve before running (licenses, CocoaPods, Xcode CLT) |
| Pods error on iOS | `cd ios && pod install && cd ..` then re-run |

---

## Quick copy-paste (after prerequisites)

Shells + biometric config already in the repo, so on the Mac it's just:

```bash
cd flutter_app
flutter pub get
flutter analyze
flutter run --dart-define=API_BASE=https://openplan.work
```

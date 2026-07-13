# OpenPlan Work — Flutter app

Native cross-platform client for OpenPlan Work. Talks to the existing PHP REST API
using the Phase-0 **device-token** auth (no master password per request).

> This is a **separate app** from the legacy WebView wrapper at repo-root
> `/android/` (package `com.taskmanager.app`). They do not conflict.

> **Building on a Mac?** Use [`MAC_SETUP.md`](./MAC_SETUP.md) — a self-contained
> step-by-step (shells, required biometric native config, backend URL, run/build).

## Status

Built with Flutter 3.44 / Dart 3.12. `android/` + `ios/` shells generated,
biometric native config applied, `flutter analyze` clean, unit tests pass
(`flutter test`). Package `work.openplan.openplan_app`. On a Mac: `flutter pub get`
→ run (see [`MAC_SETUP.md`](./MAC_SETUP.md)).

## First-time setup (on a machine with Flutter installed)

```bash
# 1. Install Flutter: https://docs.flutter.dev/get-started/install
flutter --version

# 2. Generate the native platform shells in-place (won't overwrite lib/):
cd flutter_app
flutter create . --org work.openplan --project-name openplan_app --platforms=android,ios

# 3. Fetch packages
flutter pub get

# 4. Static analysis (should be clean)
flutter analyze

# 5. Run against your local MAMP backend
#    Android emulator (10.0.2.2 = the emulator's route to your host machine):
flutter run --dart-define=API_BASE=http://10.0.2.2/taskmanager
#    iOS simulator:
flutter run --dart-define=API_BASE=http://localhost/taskmanager
#    Production:
flutter run --dart-define=API_BASE=https://openplan.work
```

## Dev gotcha — Android cleartext HTTP

Hitting `http://10.0.2.2` (plain HTTP) requires cleartext to be allowed. After
`flutter create`, add to `android/app/src/main/AndroidManifest.xml` on the
`<application>` tag **for debug only**:

```xml
android:usesCleartextTraffic="true"
```

Production is HTTPS, so this is a local-dev concern only.

## App identity / Play Store

Default org here is `work.openplan` → `applicationId work.openplan.app`. To instead
**replace** the existing WebView app on the same Play listing, set
`applicationId = com.taskmanager.app` in `android/app/build.gradle` after step 2.

## Architecture (lib/)

| Path | Role |
|------|------|
| `config/app_config.dart` | base URL + timeouts (env-overridable) |
| `api/api_client.dart` | Dio + Bearer/`X-Device-Token` + `{success,data}` unwrap + 401 |
| `auth/token_store.dart` | device token in Keychain/Keystore + biometric unlock |
| `auth/auth_repository.dart` | `device_login` call, persists token |
| `auth/auth_controller.dart` | Riverpod auth state — **biometric unlock on launch enabled** (needs native config, see MAC_SETUP.md) |
| `repositories/tasks_repository.dart` | `GET /api/tasks.php` → `Project[]` |
| `models/` | `Project`, `Task`, envelope, exception |
| `screens/` | login, dashboard, tasks |
| `router.dart` | go_router + auth redirect |

See `../docs/mobile/TRD-mobile.md` for the full contract and `../docs/mobile/FLOW-1to1.md`
for the desktop↔mobile screen map.

## Test login (local dev)

`<your-email>` / master `<your-master-password>` (your existing local account).

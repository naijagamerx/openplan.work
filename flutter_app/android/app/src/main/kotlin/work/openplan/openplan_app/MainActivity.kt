package work.openplan.openplan_app

import io.flutter.embedding.android.FlutterFragmentActivity

// FlutterFragmentActivity (not FlutterActivity) is required by local_auth for
// biometric prompts (BiometricPrompt needs a FragmentActivity host).
class MainActivity : FlutterFragmentActivity()

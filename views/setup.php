<?php
// First-time setup page
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $masterPassword = $_POST['master_password'] ?? '';
    $installMode = $_POST['mode'] ?? 'multi_user'; // Changed from install_mode to mode to match sample
    $csrfToken = $_POST['csrf_token'] ?? '';

    // Validation
    if (!Auth::validateCsrf($csrfToken)) {
        $error = 'Invalid request token. Please refresh and try again.';
    } elseif (empty($name) || empty($email) || empty($password) || empty($masterPassword)) {
        $error = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (!in_array($installMode, ['single_user', 'multi_user'], true)) {
        $error = 'Invalid install mode selected';
    } else {
        // Store master password in session for encryption
        $_SESSION[SESSION_MASTER_KEY] = $masterPassword;

        // Create database with master password
        $db = new Database($masterPassword);
        $auth = new Auth($db);

        // Register user
        $result = $auth->register($email, $password, $name, Auth::ROLE_ADMIN);

        if ($result['success']) {
            $newUser = $result['user'];
            savePublicConfig(['installMode' => $installMode]);
            $userDb = new Database($masterPassword, $newUser['id']);
            // Create initial config
            $userDb->save('config', [
                'siteName' => '',
                'businessName' => '',
                'businessEmail' => $email,
                'currency' => 'USD',
                'taxRate' => 0,
                'groqApiKey' => '',
                'openrouterApiKey' => '',
                'setupComplete' => true,
                'createdAt' => date('c')
            ]);

            $mailSent = false;
            if (isEmailVerificationEnabled()) {
                $tokenResult = $auth->issueEmailVerification($newUser['id'], true);
                if (!empty($tokenResult['success']) && !empty($tokenResult['updated']) && !empty($tokenResult['users'])) {
                    $auth->persistUsers($tokenResult['users']);
                    $mailer = new Mailer($userDb->load('config', false));
                    $mailSent = $mailer->sendVerificationEmail($newUser, $tokenResult['token']);
                }
            }

            setAuthFlash([
                'mode' => isEmailVerificationEnabled() ? 'verify_email' : 'open_source',
                'title' => isEmailVerificationEnabled() ? 'Welcome to the club.' : 'Account created.',
                'headline_emphasis' => 'club',
                'email' => $email,
                'mail_sent' => $mailSent,
                'cta_href' => '?page=login',
                'cta_label' => isEmailVerificationEnabled() ? 'Continue to Sign In' : 'Sign In',
                'status_label' => isEmailVerificationEnabled() ? 'Security Status: Verification Pending' : 'Security Status: Ready to Sign In',
                'message' => isEmailVerificationEnabled()
                    ? 'Your account is ready. Verify your email to unlock the full dashboard.'
                    : 'Your account is ready. You can sign in and continue immediately.',
                'detail' => isEmailVerificationEnabled()
                    ? 'We will send a verification link to your registered email address. If mail delivery fails, you can sign in and resend it from the verification screen.'
                    : 'This installation is running in plug-and-play mode with no email verification required.'
            ]);

            header('Location: ' . APP_URL . '?page=thank-you');
            exit;
        } else {
            $error = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title><?php echo e(getSiteName()); ?> | Setup</title>
<meta name="description" content="Initial installation and configuration screen for <?php echo e(getSiteName()); ?>."/>
<meta name="robots" content="noindex, nofollow, noarchive, nosnippet"/>
<link rel="canonical" href="<?php echo e(APP_URL); ?>/?page=setup"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800;900&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#000000",
                        "primary-dark": "#ffffff",
                        "background-light": "#ffffff",
                        "background-dark": "#000000",
                    },
                    fontFamily: {
                        "display": ["Public Sans", "sans-serif"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                },
            },
        }
    </script>
<style>
        body {
            font-family: 'Public Sans', sans-serif;
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 min-h-screen">
<div class="flex flex-col lg:flex-row min-h-screen w-full">
<!-- Left Side: Dark Branding Section -->
<div class="lg:w-5/12 bg-black flex flex-col justify-between p-12 lg:p-24 text-white">
<div class="flex items-center gap-4">
<div class="size-10 flex items-center justify-center text-white">
<svg fill="none" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" class="w-full h-full">
    <path clip-rule="evenodd" d="M24 4H6V17.3333V30.6667H24V44H42V30.6667V17.3333H24V4Z" fill="currentColor" fill-rule="evenodd"></path>
</svg>
</div>
<h1 class="text-3xl font-black tracking-tighter uppercase"><?php echo e(getSiteName()); ?></h1>
</div>
<div class="space-y-6">
<h2 class="text-5xl lg:text-7xl font-black leading-none tracking-tight">YOUR<br/>ENCRYPTED<br/>WORKSPACE.</h2>
<div class="h-1 w-24 bg-white"></div>
<p class="text-slate-400 text-lg max-w-md font-light leading-relaxed">
                    The open-source operating system for modern work. Secure, scalable, and built for privacy.
                </p>
</div>
<div class="flex items-center gap-2 text-slate-500 text-sm font-medium uppercase tracking-widest">
<span>© <?php echo date('Y'); ?> <?php echo e(getSiteName()); ?></span>
</div>
</div>
<!-- Right Side: Setup Form Section -->
<div class="lg:w-7/12 bg-white dark:bg-zinc-950 p-8 lg:p-24 flex items-center justify-center">
<div class="w-full max-w-xl">
<div class="mb-12">
<h3 class="text-4xl font-black text-slate-900 dark:text-white tracking-tight uppercase">Setup Workspace</h3>
<p class="text-slate-500 dark:text-slate-400 mt-2">Configure your environment to start collaborating.</p>
</div>

<?php if ($error): ?>
    <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700">
        <p class="font-bold">Error</p>
        <p><?php echo e($error); ?></p>
    </div>
<?php endif; ?>

<form class="space-y-8" method="POST">
<input type="hidden" name="csrf_token" value="<?php echo e(Auth::csrfToken()); ?>">

<!-- User Info Group -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
<div class="flex flex-col gap-2">
<label class="text-xs font-bold uppercase tracking-widest text-slate-900 dark:text-slate-300">Full Name</label>
<input name="name" value="<?php echo e($_POST['name'] ?? ''); ?>" required class="w-full border-2 border-slate-200 dark:border-slate-800 bg-transparent rounded-none p-4 focus:border-black dark:focus:border-white focus:ring-0 transition-colors placeholder:text-slate-300" placeholder="e.g. Jean Nouvel" type="text"/>
</div>
<div class="flex flex-col gap-2">
<label class="text-xs font-bold uppercase tracking-widest text-slate-900 dark:text-slate-300">Email Address</label>
<input name="email" value="<?php echo e($_POST['email'] ?? ''); ?>" required class="w-full border-2 border-slate-200 dark:border-slate-800 bg-transparent rounded-none p-4 focus:border-black dark:focus:border-white focus:ring-0 transition-colors placeholder:text-slate-300" placeholder="name@firm.com" type="email"/>
</div>
</div>
<!-- Installation Mode Toggles -->
<div class="space-y-4">
<label class="text-xs font-bold uppercase tracking-widest text-slate-900 dark:text-slate-300">Installation Mode</label>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<label class="relative flex cursor-pointer group">
<input class="peer sr-only" name="mode" value="single_user" type="radio" <?php echo (($_POST['mode'] ?? '') === 'single_user') ? 'checked' : ''; ?> />
<div class="w-full p-6 border-2 border-slate-100 dark:border-slate-800 peer-checked:border-black dark:peer-checked:border-white peer-checked:bg-slate-50 dark:peer-checked:bg-slate-900 transition-all">
<span class="material-symbols-outlined mb-2 text-slate-400 peer-checked:text-black dark:peer-checked:text-white">person</span>
<p class="font-bold text-slate-900 dark:text-white uppercase tracking-tight">Single User</p>
<p class="text-xs text-slate-500 mt-1">Standalone local workstation setup.</p>
</div>
</label>
<label class="relative flex cursor-pointer group">
<input class="peer sr-only" name="mode" value="multi_user" type="radio" <?php echo (($_POST['mode'] ?? 'multi_user') === 'multi_user') ? 'checked' : ''; ?> />
<div class="w-full p-6 border-2 border-slate-100 dark:border-slate-800 peer-checked:border-black dark:peer-checked:border-white peer-checked:bg-slate-50 dark:peer-checked:bg-slate-900 transition-all">
<span class="material-symbols-outlined mb-2 text-slate-400 peer-checked:text-black dark:peer-checked:text-white">groups</span>
<p class="font-bold text-slate-900 dark:text-white uppercase tracking-tight">Multi User</p>
<p class="text-xs text-slate-500 mt-1">Networked BIM server environment.</p>
</div>
</label>
</div>
</div>
<!-- Security Section -->
<div class="pt-6 border-t border-slate-100 dark:border-slate-800 space-y-6">
<div class="bg-slate-900 dark:bg-black p-8 text-white relative overflow-hidden">
<div class="relative z-10">
<div class="flex items-center gap-2 mb-4">
<span class="material-symbols-outlined text-white">encrypted</span>
<h4 class="text-xs font-bold uppercase tracking-widest">Encryption: Master Password</h4>
</div>
<p class="text-xs text-slate-400 mb-6 leading-relaxed max-w-sm">
                                    This key encrypts your entire project database. It cannot be recovered if lost. Store it in a physical vault.
                                </p>
<input name="master_password" required class="w-full bg-slate-800 border-none rounded-none p-4 text-white focus:ring-2 focus:ring-white placeholder:text-slate-600" placeholder="Enter secure master key" type="password"/>
</div>
<!-- Background Decor -->
<div class="absolute -right-4 -bottom-4 opacity-10">
<span class="material-symbols-outlined text-9xl">lock</span>
</div>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
<div class="flex flex-col gap-2">
<label class="text-xs font-bold uppercase tracking-widest text-slate-900 dark:text-slate-300">Account Password</label>
<input name="password" required class="w-full border-2 border-slate-200 dark:border-slate-800 bg-transparent rounded-none p-4 focus:border-black dark:focus:border-white focus:ring-0 transition-colors" placeholder="••••••••" type="password"/>
</div>
<div class="flex flex-col gap-2">
<label class="text-xs font-bold uppercase tracking-widest text-slate-900 dark:text-slate-300">Confirm Password</label>
<input name="confirm_password" required class="w-full border-2 border-slate-200 dark:border-slate-800 bg-transparent rounded-none p-4 focus:border-black dark:focus:border-white focus:ring-0 transition-colors" placeholder="••••••••" type="password"/>
</div>
</div>
</div>
<!-- CTA -->
<div class="pt-6">
<button class="w-full bg-slate-950 dark:bg-white text-white dark:text-black font-black py-6 uppercase tracking-[0.2em] text-lg hover:bg-black dark:hover:bg-slate-200 transition-all flex items-center justify-center gap-4 group" type="submit">
                            Complete Setup
                            <span class="material-symbols-outlined group-hover:translate-x-2 transition-transform">arrow_forward</span>
</button>
</div>
</form>
<div class="mt-8 flex justify-between items-center text-[10px] font-bold uppercase tracking-widest text-slate-400">
<a class="hover:text-black dark:hover:text-white transition-colors" href="?page=privacy">Privacy Protocol</a>
<a class="hover:text-black dark:hover:text-white transition-colors" href="?page=terms">Terms of Service</a>
</div>
</div>
</div>
</div>
</body></html>

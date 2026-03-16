<?php
// Security headers for HTML pages
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

$splitAuthPages = ['login', 'register', 'thank-you', 'forgot-password', 'reset-password', 'verify-email', 'verification-required', 'privacy', 'terms'];
$usesSplitAuthLayout = in_array($page, $splitAuthPages, true);
$publicAppName = getPublicAppName();
$publicTagline = getPublicAppTagline();

// SEO Configuration based on current page
$seoConfig = [
    'login' => [
        'title' => 'Sign In | ' . $publicAppName,
        'description' => 'Sign in to your ' . $publicAppName . ' workspace. Access your encrypted tasks, projects, notes, and more.',
        'keywords' => 'login, sign in, workspace login, secure login, task manager login',
        'og_image' => 'assets/images/chrome_B3N3g51Yeo.png'
    ],
    'register' => [
        'title' => 'Create Account | ' . $publicAppName,
        'description' => 'Create your free ' . $publicAppName . ' account. Start managing your tasks and projects with encrypted storage.',
        'keywords' => 'register, sign up, create account, free task manager, encrypted workspace',
        'og_image' => 'assets/images/chrome_B3N3g51Yeo.png'
    ],
    'forgot-password' => [
        'title' => 'Forgot Password | ' . $publicAppName,
        'description' => 'Reset your ' . $publicAppName . ' password. Secure password recovery for your encrypted workspace.',
        'keywords' => 'forgot password, password reset, account recovery, reset password',
        'og_image' => 'assets/images/chrome_B3N3g51Yeo.png'
    ],
    'reset-password' => [
        'title' => 'Reset Password | ' . $publicAppName,
        'description' => 'Reset your ' . $publicAppName . ' password. Create a new secure password for your workspace.',
        'keywords' => 'reset password, new password, password change, secure password',
        'og_image' => 'assets/images/chrome_B3N3g51Yeo.png'
    ],
    'verify-email' => [
        'title' => 'Verify Email | ' . $publicAppName,
        'description' => 'Verify your email address for ' . $publicAppName . '. Complete your account setup.',
        'keywords' => 'email verification, verify email, account verification, confirm email',
        'og_image' => 'assets/images/chrome_B3N3g51Yeo.png'
    ],
    'verification-required' => [
        'title' => 'Verification Required | ' . $publicAppName,
        'description' => 'Email verification required for ' . $publicAppName . '. Check your inbox to verify your account.',
        'keywords' => 'email verification, verify account, security verification',
        'og_image' => 'assets/images/chrome_B3N3g51Yeo.png'
    ],
    'thank-you' => [
        'title' => 'Welcome | ' . $publicAppName,
        'description' => 'Welcome to ' . $publicAppName . '. Your account has been created successfully.',
        'keywords' => 'welcome, account created, registration complete, thank you',
        'og_image' => 'assets/images/chrome_B3N3g51Yeo.png'
    ],
    'privacy' => [
        'title' => 'Privacy Policy | ' . $publicAppName,
        'description' => $publicAppName . ' privacy policy and data handling practices for the encrypted PHP workspace.',
        'keywords' => 'privacy policy, data privacy, encrypted storage privacy, GDPR',
        'og_image' => 'assets/images/chrome_B3N3g51Yeo.png'
    ],
    'terms' => [
        'title' => 'Terms of Service | ' . $publicAppName,
        'description' => $publicAppName . ' terms of service and usage policies for the encrypted PHP workspace.',
        'keywords' => 'terms of service, terms and conditions, usage policy, legal',
        'og_image' => 'assets/images/chrome_B3N3g51Yeo.png'
    ]
];

// Get SEO config for current page or use defaults
$currentSeo = $seoConfig[$page] ?? [
    'title' => $pageTitle ?? $publicAppName,
    'description' => 'The encrypted PHP workspace for teams and solo builders.',
    'keywords' => 'self-hosted task manager, encrypted project management, PHP workspace',
    'og_image' => 'assets/images/chrome_B3N3g51Yeo.png'
];

$seoTitle = $currentSeo['title'];
$seoDescription = $currentSeo['description'];
$seoKeywords = $currentSeo['keywords'];
$seoOgImage = $currentSeo['og_image'];
$canonicalUrl = APP_URL . '/?page=' . $page;
$ogImageUrl = APP_URL . '/' . $seoOgImage;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Primary Meta Tags -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($seoTitle); ?></title>
    <meta name="description" content="<?php echo e($seoDescription); ?>">
    <meta name="keywords" content="<?php echo e($seoKeywords); ?>">
    <meta name="author" content="<?php echo e($publicAppName); ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?php echo e($canonicalUrl); ?>">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo e($canonicalUrl); ?>">
    <meta property="og:title" content="<?php echo e($seoTitle); ?>">
    <meta property="og:description" content="<?php echo e($seoDescription); ?>">
    <meta property="og:image" content="<?php echo e($ogImageUrl); ?>">
    <meta property="og:image:alt" content="<?php echo e($seoTitle); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:site_name" content="<?php echo e($publicAppName); ?>">
    <meta property="og:locale" content="en_US">

    <!-- Schema.org Structured Data -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebPage",
      "name": "<?php echo e($seoTitle); ?>",
      "description": "<?php echo e($seoDescription); ?>",
      "url": "<?php echo e($canonicalUrl); ?>",
      "isPartOf": {
        "@type": "WebSite",
        "name": "<?php echo e($publicAppName); ?>",
        "url": "<?php echo e(APP_URL); ?>"
      },
      "breadcrumb": {
        "@type": "BreadcrumbList",
        "itemListElement": [
          {
            "@type": "ListItem",
            "position": 1,
            "name": "Home",
            "item": "<?php echo e(APP_URL); ?>/"
          },
          {
            "@type": "ListItem",
            "position": 2,
            "name": "<?php echo e(ucfirst(str_replace('-', ' ', $page))); ?>"
          }
        ]
      }
    }
    </script>

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?php echo e($canonicalUrl); ?>">
    <meta name="twitter:title" content="<?php echo e($seoTitle); ?>">
    <meta name="twitter:description" content="<?php echo e($seoDescription); ?>">
    <meta name="twitter:image" content="<?php echo e($ogImageUrl); ?>">
    <meta name="twitter:image:alt" content="<?php echo e($seoTitle); ?>">

    <!-- Note: Security headers (X-Frame-Options, CSP, etc.) are sent via HTTP in PHP, not meta tags -->

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Favicon - PNG first for better compatibility, SVG as alternative -->
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/favicons/favicon-16x16.png">
    <link rel="icon" type="image/svg+xml" href="assets/favicons/favicon.svg">
    <link rel="shortcut icon" href="assets/favicons/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/favicons/apple-touch-icon.png">

    <!-- Custom Tailwind Config -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                }
            }
        }
    </script>

    <!-- Custom Styles -->
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .animate-fade-in { animation: fadeIn 0.3s ease-out; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        /* Grid pattern for left panel */
        .grid-pattern {
            background-image: radial-gradient(#ffffff 1px, transparent 1px);
            background-size: 40px 40px;
        }
        .auth-field {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .auth-label {
            color: #9ca3af;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.2em;
            text-transform: uppercase;
        }
        .auth-input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }
        .auth-input {
            width: 100%;
            background: transparent;
            border: none;
            border-bottom: 1px solid #e5e7eb;
            color: #111827;
            font-size: 1rem;
            line-height: 1.5rem;
            padding: 0.75rem 0;
            transition: border-color 0.2s ease, color 0.2s ease;
        }
        .auth-input::placeholder {
            color: #d1d5db;
        }
        .auth-input:focus {
            outline: none;
            border-bottom-color: #000000;
        }
        .auth-input.has-trailing-icon {
            padding-right: 2.75rem;
        }
        .auth-visibility-toggle {
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            color: #9ca3af;
            transition: color 0.2s ease;
        }
        .auth-visibility-toggle:hover {
            color: #111827;
        }
        .auth-visibility-toggle .icon-hide {
            display: none;
        }
        .auth-visibility-toggle[data-visible="true"] .icon-show {
            display: none;
        }
        .auth-visibility-toggle[data-visible="true"] .icon-hide {
            display: block;
        }
        .auth-helper {
            color: #9ca3af;
            font-size: 10px;
            margin-top: 0.25rem;
        }
    </style>
</head>
    <?php if ($usesSplitAuthLayout): ?>
<!-- SPLIT-SCREEN LAYOUT FOR DESKTOP AUTH PAGES -->
<body class="min-h-screen bg-white font-sans">
    <div class="flex min-h-screen w-full flex-col lg:flex-row">
        <!-- Left Panel: Black Sidebar -->
        <div class="hidden lg:flex lg:w-1/2 bg-black items-center justify-center relative overflow-hidden">
            <!-- Background Grid Pattern -->
            <div class="absolute inset-0 opacity-10 grid-pattern"></div>
            <div class="relative z-10 flex flex-col items-center gap-6">
                <div class="w-24 h-24 text-white flex items-center justify-center">
                    <?php echo getSidebarLogoHtml(96); ?>
                </div>
                <h1 class="text-white text-5xl font-black tracking-tighter uppercase text-center leading-none">
                    <?php echo e($publicAppName); ?>
                </h1>
                <p class="text-white/40 text-sm tracking-[0.15em] uppercase font-light text-center max-w-sm"><?php echo e($publicTagline); ?></p>
            </div>
            <!-- Bottom Left Decorative Text -->
            <div class="absolute bottom-10 left-10 text-white/20 text-xs tracking-widest uppercase">
                Est. <?php echo date('Y'); ?> &copy; <?php echo e($publicAppName); ?>
            </div>
        </div>

        <!-- Right Panel: Login Form -->
        <div class="flex-1 flex flex-col bg-white">
            <!-- Top Nav -->
            <header class="flex items-center justify-between px-8 py-6 lg:px-20">
                <!-- Mobile Logo -->
                <div class="lg:hidden flex items-center gap-2">
                    <div class="w-6 h-6 text-black flex items-center justify-center">
                        <?php echo getSidebarLogoHtml(24); ?>
                    </div>
                    <span class="font-bold text-lg text-black"><?php echo e($publicAppName); ?></span>
                </div>
                <div class="hidden lg:block"></div>
                <div class="flex items-center gap-6">
                    <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-gray-400"><?php echo e($publicAppName); ?></span>
                    <a class="text-[10px] font-bold uppercase tracking-[0.2em] text-gray-400 hover:text-black transition-colors" href="?page=homepage">Back to Home</a>
                </div>
            </header>

            <!-- Form Container -->
            <main class="flex-1 flex flex-col justify-center px-8 lg:px-20 xl:px-24 py-12">
                <div class="max-w-xl w-full mx-auto lg:mx-0 animate-fade-in">
                    <?php include $viewFile; ?>
                </div>
            </main>

            <!-- Bottom Footer -->
            <footer class="px-8 lg:px-20 py-8 flex justify-between items-center text-[10px] text-gray-300 tracking-widest uppercase">
                <span>V<?php echo e(APP_VERSION); ?> - Secure Task Workspace</span>
                <div class="flex gap-4">
                    <a class="hover:text-black transition-colors" href="?page=privacy">Privacy</a>
                    <a class="hover:text-black transition-colors" href="?page=terms">Terms</a>
                </div>
            </footer>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

    <script>
        function toggleAuthVisibility(inputId, button) {
            const input = document.getElementById(inputId);
            if (!input || !button) {
                return;
            }

            const makeVisible = input.type === 'password';
            input.type = makeVisible ? 'text' : 'password';
            button.setAttribute('data-visible', makeVisible ? 'true' : 'false');
            button.setAttribute('aria-label', makeVisible ? 'Hide value' : 'Show value');
        }

        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = 'bg-black text-white px-4 py-3 border border-black shadow-lg animate-fade-in text-sm font-medium tracking-wide';
            toast.textContent = message;
            container.appendChild(toast);

            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
    </script>
</body>

<?php else: ?>
<!-- CENTERED CARD LAYOUT FOR SETUP & OTHER AUTH PAGES -->
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <div class="w-full max-w-md animate-fade-in">
        <?php include $viewFile; ?>
    </div>

    <!-- Toast Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

    <script>
        function toggleAuthVisibility(inputId, button) {
            const input = document.getElementById(inputId);
            if (!input || !button) {
                return;
            }

            const makeVisible = input.type === 'password';
            input.type = makeVisible ? 'text' : 'password';
            button.setAttribute('data-visible', makeVisible ? 'true' : 'false');
            button.setAttribute('aria-label', makeVisible ? 'Hide value' : 'Show value');
        }

        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = 'bg-black text-white px-4 py-3 border border-black shadow-lg animate-fade-in text-sm font-medium tracking-wide';
            toast.textContent = message;
            container.appendChild(toast);

            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
    </script>
</body>
<?php endif; ?>
</html>

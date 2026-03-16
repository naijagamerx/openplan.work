<?php
/**
 * SEO Helper Class
 * Provides centralized SEO functionality for OpenPlan Work
 *
 * Usage:
 *   require_once __DIR__ . '/SEOHelper.php';
 *   SEOHelper::renderMetaTags('homepage');
 */

class SEOHelper
{
    /**
     * Page metadata configuration
     */
    private static array $pageMeta = [
        'homepage' => [
            'title' => 'OpenPlan Work - The Encrypted PHP Workspace',
            'description' => 'Self-hosted PHP productivity suite for tasks, projects, notes, habits, invoices, and secure team workflows.',
            'keywords' => 'self-hosted task manager, encrypted project management, PHP workspace, productivity app, open source task manager',
            'og_image' => 'assets/images/chrome_B3N3g51Yeo.png', // Use existing screenshot
            'schema_types' => ['Organization', 'SoftwareApplication']
        ],
        'docs' => [
            'title' => 'Documentation | OpenPlan Work',
            'description' => 'Complete documentation for OpenPlan Work - setup guides, features, and API reference for the encrypted PHP workspace.',
            'keywords' => 'documentation, setup guide, PHP app documentation, self-hosted documentation',
            'og_image' => 'assets/images/chrome_B3N3g51Yeo.png', // Use existing screenshot
            'schema_types' => ['WebPage']
        ],
        'login' => [
            'title' => 'Sign In | OpenPlan Work',
            'description' => 'Sign in to your OpenPlan Work workspace. Access your encrypted tasks, projects, notes, and more.',
            'keywords' => 'login, sign in, workspace login, secure login',
            'og_image' => 'assets/images/chrome_B3N3g51Yeo.png', // Use existing screenshot
            'schema_types' => ['WebPage']
        ],
        'register' => [
            'title' => 'Create Account | OpenPlan Work',
            'description' => 'Create your free OpenPlan Work account. Start managing your tasks and projects with encrypted storage.',
            'keywords' => 'register, sign up, create account, free task manager',
            'og_image' => 'assets/images/chrome_B3N3g51Yeo.png', // Use existing screenshot
            'schema_types' => ['WebPage']
        ],
        'privacy' => [
            'title' => 'Privacy Policy | OpenPlan Work',
            'description' => 'OpenPlan Work privacy policy and data handling practices for the encrypted PHP workspace.',
            'keywords' => 'privacy policy, data privacy, encrypted storage privacy',
            'og_image' => 'assets/images/chrome_B3N3g51Yeo.png', // Use existing screenshot
            'schema_types' => ['WebPage']
        ],
        'terms' => [
            'title' => 'Terms of Service | OpenPlan Work',
            'description' => 'OpenPlan Work terms of service and usage policies for the encrypted PHP workspace.',
            'keywords' => 'terms of service, terms and conditions, usage policy',
            'og_image' => 'assets/images/chrome_B3N3g51Yeo.png', // Use existing screenshot
            'schema_types' => ['WebPage']
        ],
        'forgot-password' => [
            'title' => 'Forgot Password | OpenPlan Work',
            'description' => 'Reset your OpenPlan Work password. Secure password recovery for your encrypted workspace.',
            'keywords' => 'forgot password, password reset, account recovery',
            'og_image' => 'assets/images/chrome_B3N3g51Yeo.png',
            'schema_types' => ['WebPage']
        ],
        'reset-password' => [
            'title' => 'Reset Password | OpenPlan Work',
            'description' => 'Reset your OpenPlan Work password. Create a new secure password for your workspace.',
            'keywords' => 'reset password, new password, password change',
            'og_image' => 'assets/images/chrome_B3N3g51Yeo.png',
            'schema_types' => ['WebPage']
        ],
        'verify-email' => [
            'title' => 'Verify Email | OpenPlan Work',
            'description' => 'Verify your email address for OpenPlan Work. Complete your account setup.',
            'keywords' => 'email verification, verify email, account verification',
            'og_image' => 'assets/images/chrome_B3N3g51Yeo.png',
            'schema_types' => ['WebPage']
        ],
        'thank-you' => [
            'title' => 'Welcome | OpenPlan Work',
            'description' => 'Welcome to OpenPlan Work. Your account has been created successfully.',
            'keywords' => 'welcome, account created, registration complete',
            'og_image' => 'assets/images/chrome_B3N3g51Yeo.png',
            'schema_types' => ['WebPage']
        ]
    ];

    /**
     * Get meta title for a page
     */
    public static function title(string $page): string
    {
        return self::$pageMeta[$page]['title'] ?? 'OpenPlan Work';
    }

    /**
     * Get meta description for a page
     */
    public static function description(string $page): string
    {
        return self::$pageMeta[$page]['description'] ??
               'The encrypted PHP workspace for teams and solo builders who want ownership of their data.';
    }

    /**
     * Get meta keywords for a page
     */
    public static function keywords(string $page): string
    {
        return self::$pageMeta[$page]['keywords'] ??
               'self-hosted task manager, encrypted project management, PHP workspace';
    }

    /**
     * Generate canonical URL for a page
     */
    public static function canonical(string $page): string
    {
        $baseUrl = rtrim(self::getAppUrl(), '/');
        return $baseUrl . ($page === 'homepage' ? '/' : '/?page=' . $page);
    }

    /**
     * Get Open Graph image URL for a page
     */
    public static function ogImage(string $page): string
    {
        $image = self::$pageMeta[$page]['og_image'] ?? 'assets/images/chrome_B3N3g51Yeo.png';
        return self::getAppUrl() . '/' . $image;
    }

    /**
     * Get application base URL
     */
    private static function getAppUrl(): string
    {
        // Try to get from config or environment
        if (defined('APP_URL')) {
            return APP_URL;
        }

        // Fallback: construct from server variables
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }

    /**
     * Get site name
     */
    public static function siteName(): string
    {
        if (function_exists('getPublicAppName')) {
            return getPublicAppName();
        }
        if (function_exists('getSiteName')) {
            return getSiteName();
        }
        return 'OpenPlan Work';
    }

    /**
     * Generate Schema.org JSON-LD
     */
    public static function schemaJson(string $page): string
    {
        $schemas = [];
        $types = self::$pageMeta[$page]['schema_types'] ?? ['WebPage'];

        foreach ($types as $type) {
            $schemas[] = self::generateSchema($type, $page);
        }

        return json_encode($schemas, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Generate individual schema
     */
    private static function generateSchema(string $type, string $page): array
    {
        $base = [
            '@context' => 'https://schema.org',
            '@type' => $type
        ];

        switch ($type) {
            case 'Organization':
                return array_merge($base, [
                    'name' => 'OpenPlan Work',
                    'alternateName' => 'Lazy Man Tools',
                    'url' => self::getAppUrl(),
                    'logo' => [
                        '@type' => 'ImageObject',
                        'url' => self::getAppUrl() . '/assets/favicons/apple-touch-icon.png',
                        'width' => 180,
                        'height' => 180
                    ],
                    'description' => self::description($page),
                    'sameAs' => [
                        'https://github.com/naijagamerx/openplan.work'
                    ],
                    'contactPoint' => [
                        '@type' => 'ContactPoint',
                        'contactType' => 'Support',
                        'availableLanguage' => 'English'
                    ]
                ]);

            case 'SoftwareApplication':
                return array_merge($base, [
                    'name' => 'OpenPlan Work',
                    'applicationCategory' => 'BusinessApplication',
                    'operatingSystem' => 'Any',
                    'offers' => [
                        '@type' => 'Offer',
                        'price' => '0',
                        'priceCurrency' => 'USD',
                        'availability' => 'https://schema.org/InStock'
                    ],
                    'featureList' => [
                        'Task Management',
                        'Project Tracking',
                        'Notes & Knowledge Base',
                        'Habit Tracking',
                        'Invoicing & Quotes',
                        'Inventory Management',
                        'AI Assistance',
                        'Encrypted Data Storage',
                        'Pomodoro Timer',
                        'Water Tracker'
                    ],
                    'softwareRequirements' => 'PHP 8.0+, json, mbstring, openssl extensions',
                    'description' => self::description($page)
                ]);

            case 'WebPage':
                return array_merge($base, [
                    'name' => self::title($page),
                    'description' => self::description($page),
                    'url' => self::canonical($page),
                    'isPartOf' => [
                        '@type' => 'WebSite',
                        'name' => 'OpenPlan Work',
                        'url' => self::getAppUrl()
                    ],
                    'breadcrumb' => [
                        '@type' => 'BreadcrumbList',
                        'itemListElement' => [
                            [
                                '@type' => 'ListItem',
                                'position' => 1,
                                'name' => 'Home',
                                'item' => self::getAppUrl() . '/'
                            ],
                            [
                                '@type' => 'ListItem',
                                'position' => 2,
                                'name' => ucfirst(str_replace('-', ' ', $page))
                            ]
                        ]
                    ]
                ]);
        }

        return $base;
    }

    /**
     * Output all meta tags for a page
     */
    public static function renderMetaTags(string $page): void
    {
        $title = self::title($page);
        $description = self::description($page);
        $keywords = self::keywords($page);
        $canonical = self::canonical($page);
        $ogImage = self::ogImage($page);
        $siteName = self::siteName();

        // Meta charset and viewport (should be first)
        echo '<meta charset="utf-8">' . "\n";
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";

        // Primary Meta Tags
        echo '<!-- Primary Meta Tags -->' . "\n";
        echo '<title>' . self::e($title) . '</title>' . "\n";
        echo '<meta name="description" content="' . self::e($description) . '">' . "\n";
        echo '<meta name="keywords" content="' . self::e($keywords) . '">' . "\n";
        echo '<meta name="author" content="OpenPlan Work">' . "\n";
        echo '<meta name="robots" content="index, follow">' . "\n";
        echo '<link rel="canonical" href="' . self::e($canonical) . '">' . "\n";

        // Open Graph / Facebook
        echo "\n" . '<!-- Open Graph / Facebook -->' . "\n";
        echo '<meta property="og:type" content="website">' . "\n";
        echo '<meta property="og:url" content="' . self::e($canonical) . '">' . "\n";
        echo '<meta property="og:title" content="' . self::e($title) . '">' . "\n";
        echo '<meta property="og:description" content="' . self::e($description) . '">' . "\n";
        echo '<meta property="og:image" content="' . self::e($ogImage) . '">' . "\n";
        echo '<meta property="og:image:alt" content="' . self::e($title) . '">' . "\n";
        echo '<meta property="og:image:width" content="1200">' . "\n";
        echo '<meta property="og:image:height" content="630">' . "\n";
        echo '<meta property="og:site_name" content="' . self::e($siteName) . '">' . "\n";
        echo '<meta property="og:locale" content="en_US">' . "\n";

        // Twitter
        echo "\n" . '<!-- Twitter -->' . "\n";
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '<meta name="twitter:url" content="' . self::e($canonical) . '">' . "\n";
        echo '<meta name="twitter:title" content="' . self::e($title) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . self::e($description) . '">' . "\n";
        echo '<meta name="twitter:image" content="' . self::e($ogImage) . '">' . "\n";
        echo '<meta name="twitter:image:alt" content="' . self::e($title) . '">' . "\n";

        // Schema markup
        $schemaJson = self::schemaJson($page);
        echo "\n" . '<!-- Schema.org Structured Data -->' . "\n";
        echo '<script type="application/ld+json">' . "\n";
        echo $schemaJson;
        echo "\n" . '</script>' . "\n";
    }

    /**
     * Output basic meta tags (for layout files)
     */
    public static function renderBasicMetaTags(string $pageTitle, string $pageDescription = ''): void
    {
        $description = $pageDescription ?: 'The encrypted PHP workspace for teams and solo builders.';

        echo '<meta charset="utf-8">' . "\n";
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
        echo '<title>' . self::e($pageTitle) . '</title>' . "\n";
        echo '<meta name="description" content="' . self::e($description) . '">' . "\n";
    }

    /**
     * Escape HTML entities
     */
    private static function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Get all available pages
     */
    public static function getAvailablePages(): array
    {
        return array_keys(self::$pageMeta);
    }

    /**
     * Check if page exists in config
     */
    public static function hasPage(string $page): bool
    {
        return isset(self::$pageMeta[$page]);
    }

    /**
     * Add or update page configuration
     */
    public static function setPageConfig(string $page, array $config): void
    {
        self::$pageMeta[$page] = array_merge(
            self::$pageMeta[$page] ?? [],
            $config
        );
    }
}

<?php
/**
 * File Manifest Verification Script
 * Checks if all critical files exist and reports any missing
 * Run: php verify_manifest.php
 */

$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$blue = "\033[34m";
$reset = "\033[0m";

$baseDir = __DIR__;

// Expected file structure based on FILE_MANIFEST.md
$expectedFiles = [
    // Root
    'config.php',
    'index.php',
    'manifest.php',
    'migrate.php',
    'mobile-logout.php',
    'setup_models.php',
    'export.php',

    // API
    'api/advanced-invoices.php',
    'api/ai-agent.php',
    'api/ai-generate.php',
    'api/ai.php',
    'api/attachments.php',
    'api/audit.php',
    'api/auth.php',
    'api/backup.php',
    'api/backup_settings.php',
    'api/clients.php',
    'api/cron.php',
    'api/data-recovery.php',
    'api/deep-migration.php',
    'api/export.php',
    'api/favicon.php',
    'api/finance.php',
    'api/habits.php',
    'api/health.php',
    'api/index.php',
    'api/inventory.php',
    'api/invoices.php',
    'api/knowledge-base.php',
    'api/models.php',
    'api/notes.php',
    'api/pomodoro.php',
    'api/projects.php',
    'api/scheduler_bootstrap.php',
    'api/scheduler_config.php',
    'api/scheduler_status.php',
    'api/settings.php',
    'api/task-inventory-link.php',
    'api/tasks.php',
    'api/todos.php',
    'api/users.php',
    'api/water.php',
    'api/wipe-data.php',

    // Includes
    'includes/AIAgent.php',
    'includes/AIFunctions.php',
    'includes/AIHelper.php',
    'includes/AIVerifier.php',
    'includes/Attachment.php',
    'includes/Audit.php',
    'includes/Auth.php',
    'includes/Backup.php',
    'includes/BaseAPI.php',
    'includes/ConversationMemory.php',
    'includes/Database.php',
    'includes/DeepMigration.php',
    'includes/DeviceDetector.php',
    'includes/Encryption.php',
    'includes/Exceptions.php',
    'includes/FunctionExecutor.php',
    'includes/GroqAPI.php',
    'includes/Helpers.php',
    'includes/Mailer.php',
    'includes/NotesAPI.php',
    'includes/OpenRouterAPI.php',
    'includes/ProjectsAPI.php',
    'includes/RateLimiter.php',
    'includes/SEOHelper.php',
    'includes/TasksAPI.php',
    'includes/TodosAPI.php',
    'includes/Validator.php',

    // Views
    'views/404.php',
    'views/advanced-invoice-form.php',
    'views/advanced-invoice-view-modern.php',
    'views/advanced-invoice-view.php',
    'views/advanced-invoices.php',
    'views/ai-assistant.php',
    'views/audit-logs.php',
    'views/calendar.php',
    'views/client-form.php',
    'views/clients.php',
    'views/custom-instruction.php',
    'views/dashboard.php',
    'views/data-recovery.php',
    'views/docs.php',
    'views/finance.php',
    'views/forgot-password.php',
    'views/habit-form.php',
    'views/habit-history.php',
    'views/habits-all.php',
    'views/habits.php',
    'views/homepage.php',
    'views/import-data.php',
    'views/inventory-history.php',
    'views/inventory.php',
    'views/invoice-form.php',
    'views/invoice-view.php',
    'views/invoices.php',
    'views/kanban-board.php',
    'views/knowledge-base.php',
    'views/login.php',
    'views/model-settings.php',
    'views/note-form.php',
    'views/notes-list.php',
    'views/notes.php',
    'views/pomodoro.php',
    'views/privacy.php',
    'views/product-form.php',
    'views/project-form.php',
    'views/projects.php',
    'views/register.php',
    'views/release-export.php',
    'views/scheduler-status.php',
    'views/settings.php',
    'views/setup.php',
    'views/shared-music.php',
    'views/task-form.php',
    'views/tasks.php',
    'views/terms.php',
    'views/thank-you.php',
    'views/transaction-form.php',
    'users.php',
    'views/verification-required.php',
    'views/verify-email.php',
    'views/view-habit.php',
    'views/view-notes.php',
    'views/view-project.php',
    'views/view-task.php',
    'views/water-plan-details.php',
    'views/water-plan-history.php',
    'views/water-plan.php',
    'views/water-tracker.php',
    'views/layouts/auth.php',
    'views/layouts/main.php',
    'views/layouts/three-pane.php',
    'views/partials/header.php',
    'views/partials/sidebar.php',
];

echo "{$blue}═══════════════════════════════════════════════════════════════{$reset}\n";
echo "{$blue}  OpenPlan Work - File Manifest Verification{$reset}\n";
echo "{$blue}═══════════════════════════════════════════════════════════════{$reset}\n\n";

$missing = [];
$existing = 0;
$total = count($expectedFiles);

foreach ($expectedFiles as $file) {
    $path = $baseDir . '/' . $file;
    if (file_exists($path)) {
        $existing++;
        echo "{$green}✓{$reset} {$file}\n";
    } else {
        $missing[] = $file;
        echo "{$red}✗{$reset} {$file}\n";
    }
}

echo "\n{$blue}═══════════════════════════════════════════════════════════════{$reset}\n";
echo "{$blue}  Summary{$reset}\n";
echo "{$blue}═══════════════════════════════════════════════════════════════{$reset}\n";
echo "Total expected:  {$total}\n";
echo "Files present:   {$existing}\n";
echo "Files missing:   " . count($missing) . "\n";

if (count($missing) > 0) {
    echo "\n{$red}MISSING FILES (need restoration):{$reset}\n";
    foreach ($missing as $file) {
        echo "  - {$file}\n";
    }
    echo "\n{$yellow}To restore missing files, run: php restore_from_backup.php{$reset}\n";
    exit(1);
} else {
    echo "\n{$green}✓ All critical files are present!{$reset}\n";
    exit(0);
}

<?php
/**
 * Job Definitions for the Scheduler
 * Defines all available scheduled jobs with their labels, descriptions, and default schedules.
 */

return [
    'overdue_invoices' => [
        'label' => 'Overdue Invoices',
        'description' => 'Mark invoices as overdue when past due date',
        'enabled' => true,
        'schedule' => [
            'frequency' => 'daily',
            'hour' => 0,
            'day' => 0
        ]
    ],
    'rate_limit_cleanup' => [
        'label' => 'Rate Limit Cleanup',
        'description' => 'Clean up expired rate limit entries',
        'enabled' => true,
        'schedule' => [
            'frequency' => 'daily',
            'hour' => 1,
            'day' => 0
        ]
    ],
    'audit_cleanup' => [
        'label' => 'Audit Log Cleanup',
        'description' => 'Remove audit logs older than retention period',
        'enabled' => true,
        'schedule' => [
            'frequency' => 'daily',
            'hour' => 2,
            'day' => 0
        ]
    ],
    'inventory_alerts' => [
        'label' => 'Inventory Alerts',
        'description' => 'Check for low stock inventory items',
        'enabled' => true,
        'schedule' => [
            'frequency' => 'daily',
            'hour' => 8,
            'day' => 0
        ]
    ],
    'invoice_reminders' => [
        'label' => 'Invoice Reminders',
        'description' => 'Send reminders for upcoming invoice due dates',
        'enabled' => true,
        'schedule' => [
            'frequency' => 'daily',
            'hour' => 9,
            'day' => 0
        ]
    ],
    'task_reminders' => [
        'label' => 'Task Reminders',
        'description' => 'Send reminders for upcoming task due dates',
        'enabled' => true,
        'schedule' => [
            'frequency' => 'daily',
            'hour' => 8,
            'day' => 0
        ]
    ]
];

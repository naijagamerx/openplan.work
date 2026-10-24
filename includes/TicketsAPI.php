<?php
/**
 * TicketsAPI — user ↔ admin threaded support tickets.
 *
 * Storage: global `tickets` collection (Database(APP_NAME)).
 * Status lifecycle:
 *   open → awaiting_admin → awaiting_user → closed
 *
 * Each ticket carries a `messages[]` thread with {authorId, authorRole, body, createdAt}.
 */

require_once __DIR__ . '/Database.php';

class TicketsAPI {
    private Database $db;

    public function __construct() {
        $this->db = new Database(APP_NAME);
    }

    public function create(string $userId, string $subject, string $body, string $priority = 'normal'): array {
        $subject = trim($subject);
        $body = trim($body);
        if ($subject === '' || $body === '') {
            throw new Exception('Subject and message are required');
        }
        $id = $this->db->generateId();
        $now = date('c');
        $ticket = [
            'id' => $id,
            'userId' => $userId,
            'subject' => $subject,
            'status' => 'awaiting_admin',
            'priority' => in_array($priority, ['low', 'normal', 'high'], true) ? $priority : 'normal',
            'messages' => [[
                'id' => $this->db->generateId(),
                'authorId' => $userId,
                'authorRole' => 'user',
                'body' => $body,
                'createdAt' => $now
            ]],
            'createdAt' => $now,
            'updatedAt' => $now
        ];
        $tickets = $this->db->load('tickets', true);
        $tickets[] = $ticket;
        $this->db->save('tickets', $tickets);
        if (class_exists('Audit')) {
            try {
                Audit::logData('create', 'ticket', $id, ['subject' => $subject]);
            } catch (Throwable $e) {
                error_log('ticket audit failed: ' . $e->getMessage());
            }
        }
        return $ticket;
    }

    public function addReply(string $ticketId, string $authorId, string $authorRole, string $body): ?array {
        $body = trim($body);
        if ($body === '') {
            throw new Exception('Reply body is required');
        }
        $tickets = $this->db->load('tickets', true);
        foreach ($tickets as $i => $t) {
            if (($t['id'] ?? '') !== $ticketId) {
                continue;
            }
            $messages = $tickets[$i]['messages'] ?? [];
            $messages[] = [
                'id' => $this->db->generateId(),
                'authorId' => $authorId,
                'authorRole' => $authorRole === 'admin' ? 'admin' : 'user',
                'body' => $body,
                'createdAt' => date('c')
            ];
            $tickets[$i]['messages'] = $messages;
            $tickets[$i]['status'] = ($authorRole === 'admin') ? 'awaiting_user' : 'awaiting_admin';
            $tickets[$i]['updatedAt'] = date('c');
            $this->db->save('tickets', $tickets);
            if (class_exists('Audit')) {
                try {
                    Audit::logData('update', 'ticket', $ticketId, ['reply_by' => $authorRole]);
                } catch (Throwable $e) {}
            }
            // Notify the ticket owner when an admin replies.
            if ($authorRole === 'admin' && class_exists('Mailer')) {
                $this->emailReplyToUser($tickets[$i], $body);
            }
            return $tickets[$i];
        }
        return null;
    }

    private function emailReplyToUser(array $ticket, string $replyBody): void {
        try {
            $users = $this->db->load('users', true);
            $recipient = null;
            foreach ($users as $u) {
                if (($u['id'] ?? '') === ($ticket['userId'] ?? '')) {
                    $recipient = $u;
                    break;
                }
            }
            if (!$recipient || empty($recipient['email'])) {
                return;
            }
            $masterPassword = getMasterPassword();
            $senderId = class_exists('Auth') ? Auth::userId() : null;
            if (!$senderId || $masterPassword === '') {
                return;
            }
            $senderDb = new Database($masterPassword, $senderId);
            $config = $senderDb->load('config', false);
            $mailer = new Mailer(is_array($config) ? $config : []);
            if (method_exists($mailer, 'sendTicketReplyEmail')) {
                $mailer->sendTicketReplyEmail($recipient, $ticket, $replyBody);
            }
        } catch (Throwable $e) {
            error_log('ticket reply email failed: ' . $e->getMessage());
        }
    }

    public function setStatus(string $ticketId, string $status): ?array {
        if (!in_array($status, ['open', 'awaiting_admin', 'awaiting_user', 'closed'], true)) {
            throw new Exception('Invalid status');
        }
        $tickets = $this->db->load('tickets', true);
        foreach ($tickets as $i => $t) {
            if (($t['id'] ?? '') !== $ticketId) {
                continue;
            }
            $tickets[$i]['status'] = $status;
            $tickets[$i]['updatedAt'] = date('c');
            $this->db->save('tickets', $tickets);
            return $tickets[$i];
        }
        return null;
    }

    public function find(string $id): ?array {
        $tickets = $this->db->load('tickets', true);
        foreach ($tickets as $t) {
            if (($t['id'] ?? '') === $id) {
                return $t;
            }
        }
        return null;
    }

    public function getByUser(string $userId): array {
        $tickets = $this->db->load('tickets', true);
        $rows = array_values(array_filter($tickets, fn($t) => ($t['userId'] ?? '') === $userId));
        usort($rows, fn($a, $b) => strcmp((string)($b['updatedAt'] ?? ''), (string)($a['updatedAt'] ?? '')));
        return $rows;
    }

    public function getAll(): array {
        $tickets = $this->db->load('tickets', true);
        usort($tickets, function ($a, $b) {
            // Awaiting admin first, then updated desc.
            $aWait = (($a['status'] ?? '') === 'awaiting_admin') ? 0 : 1;
            $bWait = (($b['status'] ?? '') === 'awaiting_admin') ? 0 : 1;
            if ($aWait !== $bWait) {
                return $aWait - $bWait;
            }
            return strcmp((string)($b['updatedAt'] ?? ''), (string)($a['updatedAt'] ?? ''));
        });
        return $tickets;
    }
}

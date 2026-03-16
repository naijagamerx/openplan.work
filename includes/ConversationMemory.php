<?php
/**
 * Conversation Memory
 *
 * Manages encrypted storage and retrieval of AI agent conversations.
 * Provides rolling window context management for multi-turn dialogues.
 */

class ConversationMemory {
    private Database $db;
    private ?string $conversationId;
    private int $maxMessages;
    private string $userId;

    /**
     * Constructor
     *
     * @param Database $db Database instance for encrypted storage
     * @param string|null $conversationId Optional conversation ID to load
     * @param int $maxMessages Maximum messages to keep in rolling window
     */
    public function __construct(Database $db, ?string $conversationId = null, int $maxMessages = 50) {
        $this->db = $db;
        $this->conversationId = $conversationId;
        $this->maxMessages = $maxMessages;
        $this->userId = Auth::userId() ?? '';

        // Initialize conversations collection if it doesn't exist
        $this->initializeCollection();
    }

    /**
     * Initialize the conversations collection
     */
    private function initializeCollection(): void {
        $conversations = $this->db->load('conversations');
        if ($conversations === null) {
            $this->db->save('conversations', []);
        }
    }

    /**
     * Create a new conversation
     *
     * @param string $firstMessage First user message to use as title
     * @return string The new conversation ID
     */
    public function createConversation(string $firstMessage): string {
        $conversations = $this->db->load('conversations') ?? [];
        $id = $this->db->generateId();

        // Generate title from first message (max 60 chars)
        $title = $this->generateTitle($firstMessage);

        $conversation = [
            'id' => $id,
            'userId' => $this->userId,
            'title' => $title,
            'messages' => [],
            'isCancelled' => false,
            'createdAt' => date('c'),
            'updatedAt' => date('c')
        ];

        $conversations[] = $conversation;
        $this->db->save('conversations', $conversations);

        $this->conversationId = $id;
        return $id;
    }

    /**
     * Save a message to the conversation
     *
     * @param string $role Message role ('user' or 'assistant')
     * @param string $content Message content
     * @param array $functionCalls Optional function calls made by AI
     * @param array $toolResults Optional tool execution results
     * @return void
     */
    public function saveMessage(string $role, string $content, array $functionCalls = [], array $toolResults = []): void {
        if (empty($this->conversationId)) {
            throw new ValidationException('No active conversation');
        }

        $conversations = $this->db->load('conversations') ?? [];

        foreach ($conversations as &$conv) {
            if ($conv['id'] === $this->conversationId && $conv['userId'] === $this->userId) {
                $message = [
                    'role' => $role,
                    'content' => $content,
                    'functionCalls' => $functionCalls,
                    'toolResults' => $toolResults,
                    'timestamp' => date('c')
                ];

                $conv['messages'][] = $message;

                // Apply rolling window if too many messages
                if (count($conv['messages']) > $this->maxMessages) {
                    $conv['messages'] = array_slice($conv['messages'], -$this->maxMessages);
                }

                $conv['updatedAt'] = date('c');
                break;
            }
        }

        $this->db->save('conversations', $conversations);
    }

    /**
     * Get recent messages for context
     *
     * @param int $limit Number of recent messages to retrieve
     * @return array Array of message arrays with 'role' and 'content'
     */
    public function getRecentMessages(int $limit = 20): array {
        $conversation = $this->loadConversation($this->conversationId);
        if (!$conversation) {
            return [];
        }

        $messages = array_slice($conversation['messages'], -$limit);

        // Convert to OpenAI format
        return array_map(function($m) {
            $msg = [
                'role' => $m['role'],
                'content' => $m['content']
            ];

            // Include function call results if present
            if (!empty($m['functionCalls'])) {
                // For now, we'll keep function calls separate
                // The AI agent will handle them
            }

            return $msg;
        }, $messages);
    }

    /**
     * Get full conversation details including metadata
     *
     * @param string $conversationId
     * @return array|null Conversation array or null if not found
     */
    public function loadConversation(?string $conversationId): ?array {
        if (empty($conversationId)) {
            return null;
        }

        $conversations = $this->db->load('conversations') ?? [];

        foreach ($conversations as $conv) {
            if ($conv['id'] === $conversationId && $conv['userId'] === $this->userId) {
                return $conv;
            }
        }

        return null;
    }

    /**
     * List all conversations for the current user
     *
     * @param int $limit Maximum number to return
     * @return array Array of conversation summaries
     */
    public function listConversations(int $limit = 50): array {
        $conversations = $this->db->load('conversations') ?? [];

        // Filter to user's conversations
        $userConversations = array_filter($conversations, function($conv) {
            return $conv['userId'] === $this->userId;
        });

        // Sort by updated date descending
        usort($userConversations, function($a, $b) {
            return strtotime($b['updatedAt']) - strtotime($a['updatedAt']);
        });

        // Return summaries
        return array_map(function($conv) {
            return [
                'id' => $conv['id'],
                'title' => $conv['title'],
                'messageCount' => count($conv['messages']),
                'createdAt' => $conv['createdAt'],
                'updatedAt' => $conv['updatedAt']
            ];
        }, array_slice($userConversations, 0, $limit));
    }

    /**
     * Delete a conversation
     *
     * @param string $conversationId
     * @return bool True if deleted, false if not found
     */
    public function deleteConversation(string $conversationId): bool {
        $conversations = $this->db->load('conversations') ?? [];

        $found = false;
        $filtered = array_filter($conversations, function($conv) use ($conversationId, &$found) {
            if ($conv['id'] === $conversationId && $conv['userId'] === $this->userId) {
                $found = true;
                return false;
            }
            return true;
        });

        if ($found) {
            $this->db->save('conversations', array_values($filtered));
            return true;
        }

        return false;
    }

    /**
     * Update conversation title
     *
     * @param string $conversationId
     * @param string $title
     * @return bool True if updated, false if not found
     */
    public function updateTitle(string $conversationId, string $title): bool {
        $conversations = $this->db->load('conversations') ?? [];

        foreach ($conversations as &$conv) {
            if ($conv['id'] === $conversationId && $conv['userId'] === $this->userId) {
                $conv['title'] = $title;
                $conv['updatedAt'] = date('c');
                $this->db->save('conversations', $conversations);
                return true;
            }
        }

        return false;
    }

    /**
     * Get current conversation ID
     *
     * @return string|null
     */
    public function getConversationId(): ?string {
        return $this->conversationId;
    }

    /**
     * Set the active conversation ID
     *
     * @param string $conversationId
     * @return void
     */
    public function setConversationId(string $conversationId): void {
        $this->conversationId = $conversationId;
    }

    /**
     * Clear all messages from current conversation (keeps conversation record)
     *
     * @return void
     */
    public function clearMessages(): void {
        if (empty($this->conversationId)) {
            return;
        }

        $conversations = $this->db->load('conversations') ?? [];

        foreach ($conversations as &$conv) {
            if ($conv['id'] === $this->conversationId && $conv['userId'] === $this->userId) {
                $conv['messages'] = [];
                $conv['updatedAt'] = date('c');
                break;
            }
        }

        $this->db->save('conversations', $conversations);
    }

    /**
     * Mark a conversation run as cancelled (or clear cancel state).
     */
    public function setCancelled(string $conversationId, bool $cancelled = true): bool {
        $conversations = $this->db->load('conversations') ?? [];

        foreach ($conversations as &$conv) {
            if ($conv['id'] === $conversationId && $conv['userId'] === $this->userId) {
                $conv['isCancelled'] = $cancelled;
                $conv['updatedAt'] = date('c');
                $this->db->save('conversations', $conversations);
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether a conversation was cancelled by user.
     */
    public function isCancelled(string $conversationId): bool {
        $conversation = $this->loadConversation($conversationId);
        if (!$conversation) {
            return false;
        }

        return (bool)($conversation['isCancelled'] ?? false);
    }

    /**
     * Clear cancel flag for a conversation.
     */
    public function clearCancelFlag(string $conversationId): bool {
        return $this->setCancelled($conversationId, false);
    }

    /**
     * Get statistics about current conversation
     *
     * @return array|null Statistics array or null if no active conversation
     */
    public function getStats(): ?array {
        $conversation = $this->loadConversation($this->conversationId);
        if (!$conversation) {
            return null;
        }

        $messageCount = count($conversation['messages']);
        $userMessages = count(array_filter($conversation['messages'], fn($m) => $m['role'] === 'user'));
        $assistantMessages = count(array_filter($conversation['messages'], fn($m) => $m['role'] === 'assistant'));
        $functionCallCount = 0;

        foreach ($conversation['messages'] as $msg) {
            if (!empty($msg['functionCalls'])) {
                $functionCallCount += count($msg['functionCalls']);
            }
        }

        return [
            'messageCount' => $messageCount,
            'userMessages' => $userMessages,
            'assistantMessages' => $assistantMessages,
            'functionCallCount' => $functionCallCount,
            'createdAt' => $conversation['createdAt'],
            'updatedAt' => $conversation['updatedAt']
        ];
    }

    /**
     * Generate a title from the first message
     *
     * @param string $message
     * @return string
     */
    private function generateTitle(string $message): string {
        // Remove newlines and extra whitespace
        $clean = preg_replace('/\s+/', ' ', trim($message));

        // Truncate to 60 characters
        if (strlen($clean) > 60) {
            $clean = substr($clean, 0, 57) . '...';
        }

        return $clean ?: 'New Conversation';
    }

    /**
     * Export conversation as markdown text
     *
     * @param string $conversationId
     * @return string Markdown formatted conversation
     */
    public function exportAsMarkdown(string $conversationId): string {
        $conversation = $this->loadConversation($conversationId);
        if (!$conversation) {
            return '';
        }

        $lines = [];
        $lines[] = "# {$conversation['title']}\n";
        $lines[] = "*Created: " . date('Y-m-d H:i:s', strtotime($conversation['createdAt'])) . "*\n";
        $lines[] = "*Updated: " . date('Y-m-d H:i:s', strtotime($conversation['updatedAt'])) . "*\n";
        $lines[] = "---\n\n";

        foreach ($conversation['messages'] as $msg) {
            $role = ucfirst($msg['role']);
            $timestamp = date('H:i:s', strtotime($msg['timestamp']));
            $lines[] = "### {$role} ({$timestamp})\n";
            $lines[] = $msg['content'] . "\n";

            // Show function calls
            if (!empty($msg['functionCalls'])) {
                $lines[] = "\n**Actions:**\n";
                foreach ($msg['functionCalls'] as $call) {
                    $lines[] = "- `{$call['name']}`\n";
                }
            }

            // Show tool results
            if (!empty($msg['toolResults'])) {
                $lines[] = "\n**Results:**\n";
                foreach ($msg['toolResults'] as $result) {
                    $summary = $result['summary'] ?? ($result['error'] ?? 'Done');
                    $lines[] = "- {$summary}\n";
                }
            }

            $lines[] = "\n---\n\n";
        }

        return implode('', $lines);
    }

    /**
     * Get a suggested summary of the conversation
     *
     * @param string $conversationId
     * @return string
     */
    public function getSummary(string $conversationId): string {
        $conversation = $this->loadConversation($conversationId);
        if (!$conversation) {
            return '';
        }

        $actions = [];
        foreach ($conversation['messages'] as $msg) {
            if (!empty($msg['functionCalls'])) {
                foreach ($msg['functionCalls'] as $call) {
                    $actions[] = $call['name'];
                }
            }
        }

        if (empty($actions)) {
            $count = count($conversation['messages']);
            return "{$count} messages";
        }

        $uniqueActions = array_unique($actions);
        return implode(', ', $uniqueActions);
    }
}

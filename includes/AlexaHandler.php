<?php
/**
 * AlexaHandler — maps Alexa intents to data reads/writes against the user's
 * encrypted database.
 *
 * TWO MODES:
 *   (1) Single-user owner mode: ALEXA_OWNER_USER_ID + LAZYMAN_MASTER_PASSWORD
 *       env vars define which OpenPlan account this skill speaks for. Used when
 *       no account-linking access token is present in the Alexa request.
 *   (2) Multi-user mode: $accessToken passed to constructor. Resolved via
 *       AlexaOAuth::resolveAccessToken() to an OpenPlan user + wrapped data key.
 *       Used after account-linking flow completes.
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Encryption.php';

class AlexaUnauthorizedException extends Exception {}

class AlexaHandler {
    private Database $db;
    private string $userId;
    private ?array $event = null;

    public function __construct(?string $accessToken = null) {
        if ($accessToken !== null && $accessToken !== '') {
            $this->bootstrapFromAccessToken($accessToken);
        } else {
            $this->bootstrapFromEnv();
        }
    }

    private function bootstrapFromEnv(): void {
        $this->userId = (string)(getenv('ALEXA_OWNER_USER_ID') ?: '');
        $masterPassword = (string)(getenv('LAZYMAN_MASTER_PASSWORD') ?: '');
        if ($this->userId === '' || $masterPassword === '') {
            throw new Exception('Alexa skill not configured. Set ALEXA_OWNER_USER_ID and LAZYMAN_MASTER_PASSWORD env vars, OR link your Amazon account.');
        }
        $this->db = new Database($masterPassword, $this->userId);
    }

    private function bootstrapFromAccessToken(string $accessToken): void {
        if (!file_exists(__DIR__ . '/AlexaOAuth.php')) {
            throw new AlexaUnauthorizedException('Account linking is not enabled on this server yet.');
        }
        require_once __DIR__ . '/AlexaOAuth.php';
        $link = AlexaOAuth::resolveAccessToken($accessToken);
        if (!$link) {
            throw new AlexaUnauthorizedException('Invalid or expired access token. Please re-link your Amazon account.');
        }
        $this->userId = (string)$link['openplanUserId'];
        $rawKey = AlexaOAuth::unwrapDataKey($link);
        $this->db = Database::fromRawKey($this->userId, $rawKey);
        AlexaOAuth::touchLastUsed($link['id'] ?? '');
    }

    public static function linkAccountCardResponse(string $speech = 'To use OpenPlan with Alexa, please link your account in the Alexa app.'): array {
        return [
            'version' => '1.0',
            'response' => [
                'outputSpeech' => ['type' => 'PlainText', 'text' => $speech],
                'card' => ['type' => 'LinkAccount'],
                'shouldEndSession' => true
            ]
        ];
    }

    public function handle(array $event): array {
        $this->event = $event;
        $type = $event['request']['type'] ?? '';
        switch ($type) {
            case 'LaunchRequest':
                return $this->respond("Welcome to OpenPlan. You can ask about tasks, todos, habits, water, finance. Or say things like: add task buy milk, create project Q3 plan, start a pomodoro, or add habit meditation. What would you like?", false);

            case 'SessionEndedRequest':
                return $this->respond('', true);

            case 'IntentRequest':
                return $this->dispatchIntent($event['request']['intent'] ?? []);

            default:
                return $this->respond("I'm not sure how to handle that.", true);
        }
    }

    private function dispatchIntent(array $intent): array {
        $name = $intent['name'] ?? '';
        $slots = $intent['slots'] ?? [];

        switch ($name) {
            // ── Read intents
            case 'GetTodayTasksIntent':       return $this->intentTodayTasks();
            case 'GetTodosIntent':            return $this->intentTodos();
            case 'GetHabitsTodayIntent':      return $this->intentHabitsToday();
            case 'GetWaterIntent':            return $this->intentWater();
            case 'GetFinanceSummaryIntent':   return $this->intentFinanceSummary();

            // ── Write intents (Phase 5)
            case 'AddTodoIntent':             return $this->intentAddTodo($slots);
            case 'MarkHabitDoneIntent':       return $this->intentMarkHabitDone($slots);
            case 'AddTaskIntent':             return $this->intentAddTask($slots);
            case 'AddProjectIntent':          return $this->intentAddProject($slots);
            case 'AddClientIntent':           return $this->intentAddClient($slots);
            case 'AddNoteIntent':             return $this->intentAddNote($slots);
            case 'AddMeetingIntent':          return $this->intentAddMeeting($slots);
            case 'StartPomodoroIntent':       return $this->intentStartPomodoro($slots);
            case 'AddHabitIntent':            return $this->intentAddHabit($slots);
            case 'AdjustInventoryIntent':     return $this->intentAdjustInventory($slots);

            // ── Built-ins
            case 'AMAZON.HelpIntent':
                return $this->respond(
                    "You can ask: what's on my list today, read my todos, did I do my habits, how much water did I drink. "
                    . "Or say: add task buy milk, create project Q3 plan, add client John Smith, take a note remember this, "
                    . "schedule meeting design review tomorrow at 3, start a pomodoro, add habit meditation, "
                    . "or remove 5 widgets from inventory. What would you like?",
                    false
                );

            case 'AMAZON.CancelIntent':
            case 'AMAZON.StopIntent':
                return $this->respond('Goodbye.', true);

            case 'AMAZON.FallbackIntent':
                return $this->respond("I did not catch that. Try saying, what are my todos, or, add task something.", false);

            default:
                return $this->respond("That command is not implemented yet.", true);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // READ INTENTS
    // ─────────────────────────────────────────────────────────────────────

    private function intentTodayTasks(): array {
        $projects = $this->db->load('projects', false);
        $today = date('Y-m-d');
        $dueToday = [];
        $overdue = [];
        foreach ($projects as $p) {
            foreach (($p['tasks'] ?? []) as $t) {
                $status = $this->normalizeStatus($t['status'] ?? 'todo');
                if ($status === 'done') continue;
                $due = isset($t['dueDate']) ? substr((string)$t['dueDate'], 0, 10) : '';
                if ($due === '') continue;
                $title = $t['title'] ?? 'Untitled';
                if ($due === $today) {
                    $dueToday[] = $title;
                } elseif ($due < $today) {
                    $overdue[] = $title;
                }
            }
        }
        if (empty($dueToday) && empty($overdue)) {
            return $this->respond("You have no tasks due today and nothing overdue. Anything else?", false);
        }

        $parts = [];
        if (!empty($overdue)) {
            $topOverdue = array_slice($overdue, 0, 3);
            $extra = count($overdue) > 3 ? sprintf(" and %d more overdue", count($overdue) - 3) : '';
            $parts[] = count($overdue) . " overdue: " . $this->joinList($topOverdue) . $extra;
        }
        if (!empty($dueToday)) {
            $topToday = array_slice($dueToday, 0, 3);
            $extra = count($dueToday) > 3 ? sprintf(" and %d more due today", count($dueToday) - 3) : '';
            $parts[] = count($dueToday) . " due today: " . $this->joinList($topToday) . $extra;
        }
        return $this->respond("You have " . implode('. ', $parts) . ". Anything else?", false);
    }

    private function intentTodos(): array {
        $todos = $this->db->load('todos', false);
        $open = [];
        foreach ($todos as $t) {
            if (!empty($t['completed'])) continue;
            $open[] = $t['title'] ?? $t['text'] ?? 'Untitled';
        }
        if (empty($open)) {
            return $this->respond("Your todo list is empty. You can say, add todo, followed by what you need to do. Anything else?", false);
        }
        $top = array_slice($open, 0, 5);
        $extra = count($open) > 5 ? sprintf(" Plus %d more.", count($open) - 5) : '';
        return $this->respond("You have " . count($open) . " open todos. " . $this->joinList($top) . "." . $extra . " Anything else?", false);
    }

    private function intentHabitsToday(): array {
        $habits = $this->db->load('habits', false);
        $completions = $this->db->load('habit_completions', false);
        $today = date('Y-m-d');
        $doneIds = [];
        foreach ($completions as $c) {
            if (($c['date'] ?? '') === $today && ($c['status'] ?? '') === 'complete') {
                $doneIds[] = $c['habitId'] ?? '';
            }
        }
        $total = count($habits);
        if ($total === 0) {
            return $this->respond("You have not set up any habits yet. You can say, add habit meditation, to start. Anything else?", false);
        }
        $done = 0;
        $missing = [];
        foreach ($habits as $h) {
            if (in_array($h['id'] ?? '', $doneIds, true)) {
                $done++;
            } else {
                $missing[] = $h['name'] ?? 'a habit';
            }
        }
        if ($done === $total) {
            return $this->respond("Great job. You have completed all $total habits today. Anything else?", false);
        }
        $list = $this->joinList(array_slice($missing, 0, 3));
        return $this->respond("You have done $done out of $total habits today. Still pending: $list. Anything else?", false);
    }

    private function intentWater(): array {
        $entries = $this->db->load('water_tracker', false);
        if (!is_array($entries) || empty($entries)) {
            $entries = $this->db->load('water_log', false);
        }
        $config = $this->db->load('config', false);
        $goal = (int)($config['waterGoal'] ?? 8);
        $today = date('Y-m-d');
        $count = 0;
        foreach ((array)$entries as $e) {
            if (($e['date'] ?? '') === $today) {
                $count = max($count, (int)($e['glasses'] ?? $e['count'] ?? 0));
            }
        }
        if ($count === 0) {
            return $this->respond("You have not logged any water today. Your goal is $goal glasses. Anything else?", false);
        }
        if ($count >= $goal) {
            return $this->respond("Nice. You have hit your goal of $goal glasses today. Logged $count so far. Anything else?", false);
        }
        $remaining = $goal - $count;
        return $this->respond("You have had $count out of $goal glasses today. $remaining to go. Anything else?", false);
    }

    private function intentFinanceSummary(): array {
        $finance = $this->db->load('finance', false);
        $yearMonth = date('Y-m');
        $revenue = 0.0;
        $expense = 0.0;
        foreach ((array)$finance as $t) {
            $d = (string)($t['date'] ?? '');
            if (substr($d, 0, 7) !== $yearMonth) continue;
            $amount = (float)($t['amount'] ?? 0);
            $type = strtolower((string)($t['type'] ?? ''));
            if ($type === 'revenue' || $type === 'income') {
                $revenue += $amount;
            } elseif ($type === 'expense' || $type === 'cost') {
                $expense += $amount;
            }
        }
        $net = $revenue - $expense;
        return $this->respond(
            "This month, revenue is " . $this->moneyToSpeech($revenue) .
            ", expenses are " . $this->moneyToSpeech($expense) .
            ", net is " . $this->moneyToSpeech($net) . ". Anything else?",
            false
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // WRITE INTENTS
    // ─────────────────────────────────────────────────────────────────────

    private function intentAddTodo(array $slots): array {
        $text = $this->slotValue($slots, ['Item', 'item']);
        if ($text === '') return $this->respond("What is the todo?", false);

        $todos = $this->db->load('todos', true);
        $now = date('c');
        $todos[] = [
            'id' => $this->db->generateId(),
            'title' => $text,
            'completed' => false,
            'createdAt' => $now,
            'updatedAt' => $now,
            'source' => 'alexa'
        ];
        $this->db->save('todos', $todos);
        return $this->respond("Added todo: $text. Anything else?", false);
    }

    private function intentMarkHabitDone(array $slots): array {
        $name = $this->slotValue($slots, ['HabitName', 'habit']);
        if ($name === '') return $this->respond("Which habit should I mark as done?", false);

        $habits = $this->db->load('habits', false);
        $match = null;
        foreach ($habits as $h) {
            $hname = (string)($h['name'] ?? '');
            if (strcasecmp($hname, $name) === 0 || stripos($hname, $name) !== false) {
                $match = $h;
                break;
            }
        }
        if (!$match) {
            return $this->respond("I could not find a habit called $name. Anything else?", false);
        }
        $completions = $this->db->load('habit_completions', true);
        $completions[] = [
            'id' => $this->db->generateId(),
            'habitId' => $match['id'],
            'date' => date('Y-m-d'),
            'status' => 'complete',
            'completedAt' => date('c'),
            'source' => 'alexa'
        ];
        $this->db->save('habit_completions', $completions);
        return $this->respond("Marked " . ($match['name'] ?? 'habit') . " as done for today. Anything else?", false);
    }

    private function intentAddTask(array $slots): array {
        $title = $this->slotValue($slots, ['TaskTitle']);
        $projectName = $this->slotValue($slots, ['ProjectName']);
        if ($title === '') return $this->respond("What is the task?", false);

        $projects = $this->db->load('projects', true);
        $targetIdx = -1;

        if ($projectName !== '') {
            foreach ($projects as $i => $p) {
                if (stripos((string)($p['name'] ?? ''), $projectName) !== false) {
                    $targetIdx = $i;
                    break;
                }
            }
        }

        if ($targetIdx === -1) {
            foreach ($projects as $i => $p) {
                if (strcasecmp((string)($p['name'] ?? ''), 'Inbox') === 0) {
                    $targetIdx = $i;
                    break;
                }
            }
        }

        if ($targetIdx === -1) {
            $projects[] = [
                'id' => $this->db->generateId(),
                'name' => 'Inbox',
                'description' => 'Quick-capture tasks',
                'status' => 'active',
                'color' => '#6b7280',
                'tasks' => [],
                'createdAt' => date('c'),
                'updatedAt' => date('c')
            ];
            $targetIdx = count($projects) - 1;
        }

        if (!isset($projects[$targetIdx]['tasks']) || !is_array($projects[$targetIdx]['tasks'])) {
            $projects[$targetIdx]['tasks'] = [];
        }
        $projects[$targetIdx]['tasks'][] = [
            'id' => $this->db->generateId(),
            'title' => $title,
            'description' => '',
            'status' => 'todo',
            'priority' => 'medium',
            'createdAt' => date('c'),
            'updatedAt' => date('c'),
            'source' => 'alexa'
        ];
        $projects[$targetIdx]['updatedAt'] = date('c');
        $this->db->save('projects', $projects);

        $where = $projectName !== '' ? "to {$projects[$targetIdx]['name']}" : "to your Inbox";
        return $this->respond("Added task: $title, $where. Anything else?", false);
    }

    private function intentAddProject(array $slots): array {
        $name = $this->slotValue($slots, ['ProjectName']);
        if ($name === '') return $this->respond("What should I name the project?", false);

        $projects = $this->db->load('projects', true);
        foreach ($projects as $p) {
            if (strcasecmp((string)($p['name'] ?? ''), $name) === 0) {
                return $this->respond("You already have a project called $name. Anything else?", false);
            }
        }
        $projects[] = [
            'id' => $this->db->generateId(),
            'name' => $name,
            'description' => '',
            'status' => 'active',
            'color' => '#3b82f6',
            'tasks' => [],
            'createdAt' => date('c'),
            'updatedAt' => date('c'),
            'source' => 'alexa'
        ];
        $this->db->save('projects', $projects);
        return $this->respond("Created project: $name. Anything else?", false);
    }

    private function intentAddClient(array $slots): array {
        $name = $this->slotValue($slots, ['ClientName']);
        if ($name === '') return $this->respond("Who is the client?", false);

        $clients = $this->db->load('clients', true);
        foreach ($clients as $c) {
            if (strcasecmp((string)($c['name'] ?? ''), $name) === 0) {
                return $this->respond("You already have a client named $name. Anything else?", false);
            }
        }
        $clients[] = [
            'id' => $this->db->generateId(),
            'name' => $name,
            'email' => '',
            'phone' => '',
            'company' => '',
            'address' => ['street' => '', 'city' => '', 'state' => '', 'zip' => '', 'country' => ''],
            'notes' => 'Added via Alexa',
            'createdAt' => date('c'),
            'updatedAt' => date('c'),
            'source' => 'alexa'
        ];
        $this->db->save('clients', $clients);
        return $this->respond("Added client: $name. You can fill in contact info from the web. Anything else?", false);
    }

    private function intentAddNote(array $slots): array {
        $content = $this->slotValue($slots, ['NoteContent']);
        if ($content === '') return $this->respond("What is the note?", false);

        $notes = $this->db->load('notes', true);
        $firstLine = strlen($content) > 50 ? substr($content, 0, 47) . '...' : $content;
        $notes[] = [
            'id' => $this->db->generateId(),
            'title' => $firstLine,
            'content' => $content,
            'tags' => ['alexa'],
            'color' => '#fef3c7',
            'isPinned' => false,
            'isFavorite' => false,
            'createdAt' => date('c'),
            'updatedAt' => date('c'),
            'source' => 'alexa'
        ];
        $this->db->save('notes', $notes);
        return $this->respond("Note saved. Anything else?", false);
    }

    private function intentAddMeeting(array $slots): array {
        $title = $this->slotValue($slots, ['MeetingTitle']);
        $dateSlot = $this->slotValue($slots, ['MeetingDate']);
        $timeSlot = $this->slotValue($slots, ['MeetingTime']);
        if ($title === '') return $this->respond("What is the meeting about?", false);

        $date = $dateSlot !== '' ? $this->normalizeAlexaDate($dateSlot) : date('Y-m-d');
        $time = $timeSlot !== '' ? $this->normalizeAlexaTime($timeSlot) : '09:00';

        $meetings = $this->db->load('meetings', true);
        $meetings[] = [
            'id' => $this->db->generateId(),
            'title' => $title,
            'date' => $date,
            'startTime' => $time,
            'endTime' => '',
            'attendees' => [],
            'location' => '',
            'notes' => '',
            'createdAt' => date('c'),
            'updatedAt' => date('c'),
            'source' => 'alexa'
        ];
        $this->db->save('meetings', $meetings);
        $spokenDate = $date === date('Y-m-d') ? 'today' : ($date === date('Y-m-d', strtotime('+1 day')) ? 'tomorrow' : $date);
        return $this->respond("Meeting scheduled: $title on $spokenDate at $time. Anything else?", false);
    }

    private function intentStartPomodoro(array $slots): array {
        $durationSlot = $this->slotValue($slots, ['Duration']);
        $minutes = $this->parseAlexaDuration($durationSlot, 25);

        $sessions = $this->db->load('pomodoro_sessions', true);
        $sessions[] = [
            'id' => $this->db->generateId(),
            'startedAt' => date('c'),
            'plannedDurationMinutes' => $minutes,
            'status' => 'running',
            'source' => 'alexa'
        ];
        $this->db->save('pomodoro_sessions', $sessions);

        $timerResult = $this->createNativeAlexaTimer($minutes);
        if ($timerResult === 'success') {
            return $this->respond("Pomodoro started for $minutes minutes. I'll let you know when time's up. Focus!", true);
        }
        if ($timerResult === 'no_permission') {
            return $this->respond("Pomodoro logged for $minutes minutes. To get an audible alarm, grant timer permission to OpenPlan in the Alexa app under Skills. Anything else?", false);
        }
        return $this->respond("Pomodoro logged for $minutes minutes. Couldn't set an Alexa timer this time. Anything else?", false);
    }

    private function intentAddHabit(array $slots): array {
        $name = $this->slotValue($slots, ['HabitName']);
        if ($name === '') return $this->respond("What habit should I add?", false);

        $habits = $this->db->load('habits', true);
        foreach ($habits as $h) {
            if (strcasecmp((string)($h['name'] ?? ''), $name) === 0) {
                return $this->respond("You're already tracking a habit called $name. Anything else?", false);
            }
        }
        $habits[] = [
            'id' => $this->db->generateId(),
            'name' => $name,
            'frequency' => 'daily',
            'color' => '#10b981',
            'icon' => 'check',
            'description' => '',
            'createdAt' => date('c'),
            'updatedAt' => date('c'),
            'source' => 'alexa'
        ];
        $this->db->save('habits', $habits);
        return $this->respond("Added habit: $name. I'll track it daily by default. Anything else?", false);
    }

    private function intentAdjustInventory(array $slots): array {
        $phrase = $this->slotValue($slots, ['InventoryPhrase', 'Item']);
        if ($phrase === '') {
            return $this->respond("Tell me what to adjust. For example: add 5 widgets to inventory.", false);
        }

        // Parse phrase like "5 widgets", "ten boxes", "5 paper plates"
        // Also handle leading operation words if user said them inside the slot:
        //   "5 widgets", "subtract 3 markers", "I used 2 batteries"
        $parsed = $this->parseInventoryPhrase($phrase);
        $qty = $parsed['qty'];
        $itemName = $parsed['item'];
        $op = $parsed['op'];  // 'add' or 'remove'

        if ($itemName === '' || $qty <= 0) {
            return $this->respond("I heard '$phrase' but couldn't find a quantity and item. Try: add 5 widgets.", false);
        }

        $inventory = $this->db->load('inventory', true);
        $matchIdx = -1;
        foreach ($inventory as $i => $item) {
            if (stripos((string)($item['name'] ?? ''), $itemName) !== false) {
                $matchIdx = $i;
                break;
            }
        }

        if ($matchIdx === -1) {
            return $this->respond("I couldn't find $itemName in your inventory. Add it from the web first. Anything else?", false);
        }

        // Op comes from the utterance template prefix ("add ... to inventory" vs
        // "remove ... from inventory"). If the phrase started with subtract/remove/used,
        // parseInventoryPhrase() already detected that.
        $current = (int)($inventory[$matchIdx]['quantity'] ?? 0);
        $new = ($op === 'remove') ? max(0, $current - $qty) : $current + $qty;
        $inventory[$matchIdx]['quantity'] = $new;
        $inventory[$matchIdx]['updatedAt'] = date('c');
        $this->db->save('inventory', $inventory);

        $verb = ($op === 'remove') ? 'Removed' : 'Added';
        return $this->respond("$verb $qty {$inventory[$matchIdx]['name']}. Now $new in stock. Anything else?", false);
    }

    /**
     * Parses voice-captured inventory phrase into [op, qty, item].
     * Examples:
     *   "5 widgets"           → ['op' => 'add',    'qty' => 5,  'item' => 'widgets']
     *   "subtract 3 markers"  → ['op' => 'remove', 'qty' => 3,  'item' => 'markers']
     *   "used 2 batteries"    → ['op' => 'remove', 'qty' => 2,  'item' => 'batteries']
     *   "ten boxes"           → ['op' => 'add',    'qty' => 10, 'item' => 'boxes']
     */
    private function parseInventoryPhrase(string $phrase): array {
        $phrase = trim(strtolower($phrase));
        $op = 'add';

        // Detect operation prefix
        $removeWords = ['subtract', 'remove', 'take', 'used', 'use', 'consumed', 'minus'];
        foreach ($removeWords as $w) {
            if (strpos($phrase, $w . ' ') === 0) {
                $op = 'remove';
                $phrase = trim(substr($phrase, strlen($w) + 1));
                break;
            }
        }
        // Also detect "add" / "stock" prefix (no-op since add is default, but strip word)
        $addWords = ['add', 'stock', 'restock', 'put', 'received', 'increase'];
        foreach ($addWords as $w) {
            if (strpos($phrase, $w . ' ') === 0) {
                $phrase = trim(substr($phrase, strlen($w) + 1));
                break;
            }
        }

        // Extract leading number (digit or English word)
        $qty = 0;
        $item = $phrase;

        if (preg_match('/^(\d+)\s+(.+)$/', $phrase, $m)) {
            $qty = (int)$m[1];
            $item = trim($m[2]);
        } else {
            $wordMap = [
                'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5,
                'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10,
                'eleven' => 11, 'twelve' => 12, 'twenty' => 20, 'fifty' => 50, 'hundred' => 100,
                'a' => 1, 'an' => 1
            ];
            if (preg_match('/^([a-z]+)\s+(.+)$/', $phrase, $m) && isset($wordMap[$m[1]])) {
                $qty = $wordMap[$m[1]];
                $item = trim($m[2]);
            }
        }

        return ['op' => $op, 'qty' => $qty, 'item' => $item];
    }

    // ─────────────────────────────────────────────────────────────────────
    // NATIVE ALEXA TIMER (Phase 5.5)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Returns: 'success' | 'no_permission' | 'failed' | 'no_token'
     */
    private function createNativeAlexaTimer(int $minutes): string {
        $apiAccessToken = $this->event['context']['System']['apiAccessToken'] ?? '';
        $apiEndpoint = $this->event['context']['System']['apiEndpoint'] ?? 'https://api.amazonalexa.com';
        if ($apiAccessToken === '') return 'no_token';

        $body = [
            'duration' => 'PT' . $minutes . 'M',
            'timerLabel' => 'Pomodoro',
            'creationBehavior' => [
                'displayExperience' => ['visibility' => 'VISIBLE']
            ],
            'triggeringBehavior' => [
                'operation' => [
                    'type' => 'ANNOUNCE',
                    'textToAnnounce' => [
                        ['locale' => 'en-US', 'text' => 'Focus session complete. Time for a five minute break.']
                    ]
                ],
                'notificationConfig' => ['playAudible' => true]
            ]
        ];

        $ch = curl_init(rtrim($apiEndpoint, '/') . '/v1/alerts/timers');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiAccessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) return 'success';
        if ($httpCode === 401 || $httpCode === 403) {
            error_log("Alexa timer permission denied (HTTP $httpCode): $response");
            return 'no_permission';
        }
        error_log("Alexa timer create failed (HTTP $httpCode): $response");
        return 'failed';
    }

    // ─────────────────────────────────────────────────────────────────────
    // SLOT HELPERS
    // ─────────────────────────────────────────────────────────────────────

    private function slotValue(array $slots, array $names): string {
        foreach ($names as $n) {
            if (isset($slots[$n]['value']) && trim((string)$slots[$n]['value']) !== '') {
                return trim((string)$slots[$n]['value']);
            }
        }
        return '';
    }

    /**
     * Resolves custom slot types — prefers Amazon's resolved authority value
     * (handles synonyms like "I used X" mapping to operation=remove).
     */
    private function resolveSlotValue(array $slots, string $name): ?string {
        $slot = $slots[$name] ?? null;
        if (!is_array($slot)) return null;
        $resolutions = $slot['resolutions']['resolutionsPerAuthority'] ?? [];
        foreach ($resolutions as $r) {
            if (($r['status']['code'] ?? '') === 'ER_SUCCESS_MATCH') {
                return $r['values'][0]['value']['name'] ?? null;
            }
        }
        return $slot['value'] ?? null;
    }

    private function normalizeAlexaDate(string $alexaDate): string {
        if ($alexaDate === '' || $alexaDate === 'PRESENT_REF') return date('Y-m-d');
        if (preg_match('/^\d{4}-W\d{2}-\d$/', $alexaDate)) {
            try { return (new DateTime($alexaDate))->format('Y-m-d'); } catch (Throwable $e) {}
        }
        if (preg_match('/^(\d{4})-W(\d{2})$/', $alexaDate, $m)) {
            try { return (new DateTime())->setISODate((int)$m[1], (int)$m[2])->format('Y-m-d'); } catch (Throwable $e) {}
        }
        if (preg_match('/^\d{4}-\d{2}$/', $alexaDate)) {
            return $alexaDate . '-01';
        }
        if (preg_match('/^\d{4}$/', $alexaDate)) {
            return $alexaDate . '-01-01';
        }
        return $alexaDate;
    }

    private function normalizeAlexaTime(string $alexaTime): string {
        $map = ['MO' => '09:00', 'AF' => '14:00', 'EV' => '18:00', 'NI' => '21:00'];
        if (isset($map[$alexaTime])) return $map[$alexaTime];
        if (preg_match('/^\d{2}:\d{2}/', $alexaTime)) return substr($alexaTime, 0, 5);
        return '09:00';
    }

    private function parseAlexaDuration(string $iso8601, int $defaultMin): int {
        if ($iso8601 === '') return $defaultMin;
        if (preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $iso8601, $m)) {
            $h = (int)($m[1] ?? 0);
            $mn = (int)($m[2] ?? 0);
            $s = (int)($m[3] ?? 0);
            $total = $h * 60 + $mn + ($s > 0 ? 1 : 0);
            return $total > 0 ? $total : $defaultMin;
        }
        return $defaultMin;
    }

    // ─────────────────────────────────────────────────────────────────────
    // RESPONSE HELPERS
    // ─────────────────────────────────────────────────────────────────────

    private function normalizeStatus(string $s): string {
        $map = ['complete' => 'done', 'completed' => 'done', 'in-progress' => 'in_progress'];
        return $map[strtolower($s)] ?? strtolower($s);
    }

    private function joinList(array $items): string {
        $items = array_values(array_filter(array_map('trim', $items), fn($i) => $i !== ''));
        if (empty($items)) return '';
        if (count($items) === 1) return $items[0];
        $last = array_pop($items);
        return implode(', ', $items) . ' and ' . $last;
    }

    private function moneyToSpeech(float $amount): string {
        $abs = abs($amount);
        $whole = floor($abs);
        $sign = $amount < 0 ? 'negative ' : '';
        return $sign . number_format($whole, 0) . ' dollars';
    }

    private function respond(string $speech, bool $endSession): array {
        $clean = $this->stripSsmlControls($speech);
        $response = [
            'outputSpeech' => ['type' => 'PlainText', 'text' => $clean],
            'shouldEndSession' => $endSession
        ];
        if (!$endSession) {
            $response['reprompt'] = [
                'outputSpeech' => [
                    'type' => 'PlainText',
                    'text' => "You can ask for tasks, todos, habits, water. Or say add task, create project, add note, schedule meeting, start pomodoro, add habit, or adjust inventory. Or say stop."
                ]
            ];
        }
        return [
            'version' => '1.0',
            'response' => $response
        ];
    }

    private function stripSsmlControls(string $s): string {
        return preg_replace('/[<>&]/', '', $s);
    }
}

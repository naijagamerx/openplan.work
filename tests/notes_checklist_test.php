<?php
/**
 * Characterization tests for NotesAPI::sanitizeChecklist() — the structured
 * task-list normalization added for the Notes-AI / in-note checklist feature.
 *
 * sanitizeChecklist() is a pure function (never touches $this->db), so we
 * instantiate NotesAPI WITHOUT its constructor and invoke the private method
 * via reflection — no Database / session required.
 *
 * Run: php ~/.claude/skills/php-guardian/scripts/test_runner.php C:/MAMP/htdocs/taskmanager
 */

require_once __DIR__ . '/../includes/NotesAPI.php';

$__ref = new ReflectionClass('NotesAPI');
$__api = $__ref->newInstanceWithoutConstructor();
$__sanitize = new ReflectionMethod('NotesAPI', 'sanitizeChecklist');
$__sanitize->setAccessible(true);
$sc = function ($input) use ($__api, $__sanitize) {
    return $__sanitize->invoke($__api, $input);
};

test('keeps valid items and preserves id/text/done', function () use ($sc) {
    $out = $sc([
        ['id' => 'a1', 'text' => 'Buy milk',  'done' => true],
        ['id' => 'a2', 'text' => 'Call bank', 'done' => false],
    ]);
    assertSame(2, count($out));
    assertSame('a1', $out[0]['id']);
    assertSame('Buy milk', $out[0]['text']);
    assertSame(true, $out[0]['done']);
    assertSame('a2', $out[1]['id']);
    assertSame(false, $out[1]['done']);
});

test('drops empty-text items and non-array entries', function () use ($sc) {
    $out = $sc([
        ['text' => '   '],      // empty after trim -> dropped
        'not-an-array',         // dropped
        ['text' => 'Keep me'],  // kept; gets generated id + done=false
    ]);
    assertSame(1, count($out));
    assertSame('Keep me', $out[0]['text']);
    assertSame(false, $out[0]['done']);
    assertTrue(!empty($out[0]['id']), 'missing item should get a generated id');
});

test('returns [] for non-array input', function () use ($sc) {
    assertSame([], $sc('nope'));
    assertSame([], $sc(null));
    assertSame([], $sc([]));
});

test('trims and caps text at 500 chars', function () use ($sc) {
    $out = $sc([['text' => '  ' . str_repeat('x', 600) . '  ']]);
    assertSame(500, mb_strlen($out[0]['text']));
});

test('coerces done to a strict boolean', function () use ($sc) {
    $out = $sc([
        ['text' => 't', 'done' => 1],
        ['text' => 'u', 'done' => 0],
        ['text' => 'v'],               // missing done -> false
    ]);
    assertSame(true, $out[0]['done']);
    assertSame(false, $out[1]['done']);
    assertSame(false, $out[2]['done']);
});

test('reindexes to a clean 0-based array after drops', function () use ($sc) {
    $out = $sc([
        ['text' => ''],           // dropped (index 0)
        ['text' => 'first kept'],  // -> index 0
        ['text' => 'second kept'], // -> index 1
    ]);
    assertSame([0, 1], array_keys($out));
});

test('regenerates unsafe ids and keeps safe ones (XSS guard)', function () use ($sc) {
    $out = $sc([
        ['id' => 'safe_id-1.2',                 'text' => 'ok'],       // valid token -> kept
        ['id' => 'x" onmouseover="alert(1)',    'text' => 'evil'],     // has quotes/space -> regenerated
        ['id' => "'); alert(1)//",              'text' => 'evil2'],    // JS breakout -> regenerated
        ['id' => str_repeat('a', 200),          'text' => 'toolong'],  // >64 chars -> regenerated
        ['id' => 12345,                          'text' => 'nonstring'], // non-string -> regenerated
    ]);
    assertSame(5, count($out));
    assertSame('safe_id-1.2', $out[0]['id']); // valid id preserved
    foreach ($out as $item) {
        assertTrue(
            (bool) preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $item['id']),
            'every stored id must be a safe token, got: ' . $item['id']
        );
    }
});

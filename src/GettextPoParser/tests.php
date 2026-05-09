<?php

declare(strict_types=1);

require_once __DIR__ . '/GettextPoEntry.php';
require_once __DIR__ . '/GettextPoFile.php';

use Zolinga\Intl\GettextPoParser\GettextPoFile;

$pass = 0; $fail = 0;

function test(string $name, callable $fn): void {
    global $pass, $fail;
    try { $fn(); echo "  ✓ $name\n"; $pass++; }
    catch (\Throwable $e) { echo "  ✗ $name: " . $e->getMessage() . "\n"; $fail++; }
}
function assertEq($got, $expected, string $msg = ''): void {
    if ($got !== $expected) throw new \RuntimeException("$msg\n  expected: " . var_export($expected, true) . "\n  got:      " . var_export($got, true));
}
function assertTrue(bool $cond, string $msg = ''): void {
    if (!$cond) throw new \RuntimeException($msg);
}
function msgfmtCheck(string $poFile): void {
    $moFile = tempnam(sys_get_temp_dir(), 'mo');
    exec("msgfmt --strict -o " . escapeshellarg($moFile) . " " . escapeshellarg($poFile) . " 2>&1", $out, $rc);
    unlink($moFile);
    if ($rc !== 0) throw new \RuntimeException("msgfmt failed:\n" . implode("\n", $out));
}

echo "Test 1: Simple round-trip\n";
test('parse and re-serialize', function () {
    $input = "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\nmsgid \"Hello\"\nmsgstr \"Ahoj\"\n";
    $po = new GettextPoFile(); $po->parse($input);
    $po2 = new GettextPoFile(); $po2->parse($po->toString());
    assertEq(count($po2->entries), 1);
    assertEq($po2->entries[0]->msgid, 'Hello');
    assertEq($po2->entries[0]->msgstr[''], 'Ahoj');
    assertEq($po2->entries[0]->isTranslated, true);
    assertEq($po2->entries[0]->isPlural, false);
});

echo "\nTest 2: Multi-line strings\n";
test('multi-line msgid and msgstr', function () {
    $input = "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\nmsgid \"\"\n\"Line 1 \"\n\"Line 2\"\nmsgstr \"\"\n\"Translation \"\n\"line 2\"\n";
    $po = new GettextPoFile(); $po->parse($input);
    assertEq($po->entries[0]->msgid, 'Line 1 Line 2');
    assertEq($po->entries[0]->msgstr[''], 'Translation line 2');
});

echo "\nTest 3: Plural forms\n";
test('plural with nplurals', function () {
    $input = "msgid \"\"\nmsgstr \"\"\n\"Plural-Forms: nplurals=3; plural=(n==1) ? 0 : (n>=2 && n<=4) ? 1 : 2;\\n\"\n\nmsgid \"apple\"\nmsgid_plural \"apples\"\nmsgstr[0] \"jablko\"\nmsgstr[1] \"jablka\"\nmsgstr[2] \"jablek\"\n";
    $po = new GettextPoFile(); $po->parse($input);
    assertEq($po->nplurals, 3);
    assertEq($po->entries[0]->isPlural, true);
    assertEq($po->entries[0]->isTranslated, true);
    assertEq(count($po->getUntranslatedEntries()), 0);
});

test('untranslated plural detected', function () {
    $input = "msgid \"\"\nmsgstr \"\"\n\"Plural-Forms: nplurals=3; plural=(n==1) ? 0 : (n>=2 && n<=4) ? 1 : 2;\\n\"\n\nmsgid \"apple\"\nmsgid_plural \"apples\"\nmsgstr[0] \"jablko\"\nmsgstr[1] \"\"\nmsgstr[2] \"\"\n";
    $po = new GettextPoFile(); $po->parse($input);
    assertEq($po->entries[0]->isTranslated, false);
    assertEq(count($po->getUntranslatedEntries()), 1);
});

echo "\nTest 4: msgctxt\n";
test('msgctxt round-trip', function () {
    $input = "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\nmsgctxt \"menu\"\nmsgid \"Open\"\nmsgstr \"Otevřít\"\n";
    $po = new GettextPoFile(); $po->parse($input);
    assertEq($po->entries[0]->context, 'menu');
    assertEq($po->entries[0]->msgid, 'Open');
    $po2 = new GettextPoFile(); $po2->parse($po->toString());
    assertEq($po2->entries[0]->context, 'menu');
    assertEq($po2->entries[0]->msgid, 'Open');
    assertEq($po2->entries[0]->msgstr[''], 'Otevřít');
});

echo "\nTest 5: Comments and fuzzy\n";
test('comments preserved', function () {
    $input = "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\n#. TRANSLATORS: Note\n#: src/file.php:42\n#, php-format\nmsgid \"Hello %s\"\nmsgstr \"Ahoj %s\"\n";
    $po = new GettextPoFile(); $po->parse($input);
    assertEq(count($po->entries[0]->translatorComments), 1);
    assertEq(count($po->entries[0]->references), 1);
    assertEq(count($po->entries[0]->flags), 1);
    assertEq($po->entries[0]->fuzzy, false);
});

test('fuzzy flag detected', function () {
    $input = "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\n#, fuzzy\nmsgid \"Hello\"\nmsgstr \"Ahoj\"\n";
    $po = new GettextPoFile(); $po->parse($input);
    assertEq($po->entries[0]->fuzzy, true);
});

echo "\nTest 6: updateTranslations\n";
test('update singular', function () {
    $po = new GettextPoFile();
    $po->parse("msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\nmsgid \"Hello\"\nmsgstr \"\"\n");
    $po->translate(['Hello' => 'Ahoj']);
    assertEq($po->entries[0]->msgstr[''], 'Ahoj');
    assertEq($po->entries[0]->isTranslated, true);
});

test('update with context key', function () {
    $po = new GettextPoFile();
    $po->parse("msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\nmsgctxt \"menu\"\nmsgid \"Open\"\nmsgstr \"\"\n");
    $po->translate(["menu\x04Open" => 'Otevřít']);
    assertEq($po->entries[0]->msgstr[''], 'Otevřít');
});

test('update strips fuzzy', function () {
    $po = new GettextPoFile();
    $po->parse("msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\n#, fuzzy\nmsgid \"Hello\"\nmsgstr \"\"\n");
    $po->translate(['Hello' => 'Ahoj']);
    assertEq($po->entries[0]->fuzzy, false);
    assertEq(count($po->entries[0]->flags), 0);
});

test('update preserves other flags', function () {
    $po = new GettextPoFile();
    $po->parse("msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\n#, fuzzy, php-format\nmsgid \"Hello %s\"\nmsgstr \"\"\n");
    $po->translate(['Hello %s' => 'Ahoj %s']);
    assertEq($po->entries[0]->fuzzy, false);
    $flags = $po->entries[0]->flags;
    assertEq(count($flags), 1);
    assertTrue(str_contains($flags[0], 'php-format'));
    assertTrue(!str_contains($flags[0], 'fuzzy'));
});

echo "\nTest 7: Escape sequences\n";
test('newlines and tabs', function () {
    $po = new GettextPoFile();
    $po->parse("msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\nmsgid \"Line 1\\nLine 2\\tTabbed\"\nmsgstr \"Řádek 1\\nŘádek 2\\tTabulátor\"\n");
    assertEq($po->entries[0]->msgid, "Line 1\nLine 2\tTabbed");
    assertEq($po->entries[0]->msgstr[''], "Řádek 1\nŘádek 2\tTabulátor");
});

test('quotes in strings', function () {
    $po = new GettextPoFile();
    $po->parse("msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\nmsgid \"He said \\\"hello\\\"\"\nmsgstr \"Řekl \\\"ahoj\\\"\"\n");
    assertEq($po->entries[0]->msgid, 'He said "hello"');
    assertEq($po->entries[0]->msgstr[''], 'Řekl "ahoj"');
});

echo "\nTest 8: msgfmt compatibility\n";
test('msgfmt parses output', function () {
    $po = new GettextPoFile();
    $po->parse("msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\"Plural-Forms: nplurals=3; plural=(n==1) ? 0 : (n>=2 && n<=4) ? 1 : 2;\\n\"\n\n#. Note\n#: test.php:1\n#, php-format\nmsgid \"Hello %s\"\nmsgstr \"Ahoj %s\"\n\nmsgctxt \"menu\"\nmsgid \"Open\"\nmsgstr \"Otevřít\"\n\nmsgid \"apple\"\nmsgid_plural \"apples\"\nmsgstr[0] \"jablko\"\nmsgstr[1] \"jablka\"\nmsgstr[2] \"jablek\"\n");
    $tmpFile = '/var/www/v2.ipdefender.eu/tmp/ai-test-po-msgfmt.po';
    $po->save($tmpFile);
    msgfmtCheck($tmpFile);
});

echo "\nTest 9: Real file round-trip\n";
test('load real PO, save, re-parse, msgfmt', function () {
    $path = '/var/www/v2.ipdefender.eu/data/zolinga-intl/gettext-test/locale/cs_CZ.po';
    $po = GettextPoFile::load($path);
    assertTrue(count($po->entries) > 0, 'should have entries');
    assertEq($po->nplurals, 3, 'Czech has 3 plural forms');
    $found = false;
    foreach ($po->entries as $e) {
        if ($e->isPlural) { $found = true; assertEq($e->msgid, 'There is one apple'); assertEq($e->msgidPlural, 'There are %d apples'); assertEq(count($e->msgstr), 3); break; }
    }
    assertTrue($found, 'should find plural entry');
    $tmpFile = '/var/www/v2.ipdefender.eu/tmp/ai-test-po-roundtrip.po';
    $po->save($tmpFile);
    $po2 = GettextPoFile::load($tmpFile);
    assertEq(count($po2->entries), count($po->entries));
    assertEq($po2->nplurals, 3);
    msgfmtCheck($tmpFile);
});

// ===== Test 10: Overwrite existing translations =====
echo "\nTest 10: Overwrite existing translations\n";
test('overwrite singular translation', function () {
    $po = new GettextPoFile();
    $po->parse("msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\nmsgid \"Hello\"\nmsgstr \"Ahoj\"\n");
    // Overwrite with new translation
    $po->translate(['Hello' => 'Nazdar']);
    assertEq($po->entries[0]->msgstr[''], 'Nazdar');
    assertEq($po->entries[0]->isTranslated, true);
});

test('overwrite plural translation', function () {
    $po = new GettextPoFile();
    $po->parse("msgid \"\"\nmsgstr \"\"\n\"Plural-Forms: nplurals=3; plural=(n==1) ? 0 : (n>=2 && n<=4) ? 1 : 2;\\n\"\n\nmsgid \"apple\"\nmsgid_plural \"apples\"\nmsgstr[0] \"jablko\"\nmsgstr[1] \"jablka\"\nmsgstr[2] \"jablek\"\n");
    // Overwrite all plural forms
    $po->translate(['apple' => ['0' => 'jabko', '1' => 'jabka', '2' => 'jabek']]);
    assertEq($po->entries[0]->msgstr['0'], 'jabko');
    assertEq($po->entries[0]->msgstr['1'], 'jabka');
    assertEq($po->entries[0]->msgstr['2'], 'jabek');
    assertEq($po->entries[0]->isTranslated, true);
});

test('overwrite partial plural', function () {
    $po = new GettextPoFile();
    $po->parse("msgid \"\"\nmsgstr \"\"\n\"Plural-Forms: nplurals=3; plural=(n==1) ? 0 : (n>=2 && n<=4) ? 1 : 2;\\n\"\n\nmsgid \"apple\"\nmsgid_plural \"apples\"\nmsgstr[0] \"jablko\"\nmsgstr[1] \"jablka\"\nmsgstr[2] \"jablek\"\n");
    // Overwrite only form 0 — others become empty
    $po->translate(['apple' => ['0' => 'jabko']]);
    assertEq($po->entries[0]->msgstr['0'], 'jabko');
    assertEq($po->entries[0]->msgstr['1'] ?? '', '');
    assertEq($po->entries[0]->msgstr['2'] ?? '', '');
    assertEq($po->entries[0]->isTranslated, false);
});

test('overwrite with context', function () {
    $po = new GettextPoFile();
    $po->parse("msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\nmsgctxt \"menu\"\nmsgid \"Open\"\nmsgstr \"Otevřít\"\n");
    $po->translate(["menu\x04Open" => 'Rozbalit']);
    assertEq($po->entries[0]->msgstr[''], 'Rozbalit');
    assertEq($po->entries[0]->context, 'menu');
});

// ===== Test 11: Ugly UTF-8 and special characters =====
echo "\nTest 11: Ugly UTF-8 and special characters\n";
test('emoji and RTL text', function () {
    $po = new GettextPoFile();
    $po->parse("msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\nmsgid \"🎉 Party time! 🥳\"\nmsgstr \"🎊 Oslava! 🎆\"\n");
    assertEq($po->entries[0]->msgid, '🎉 Party time! 🥳');
    assertEq($po->entries[0]->msgstr[''], '🎊 Oslava! 🎆');
    assertEq($po->entries[0]->isTranslated, true);
});

test('CJK characters', function () {
    $po = new GettextPoFile();
    $po->parse("msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\nmsgid \"你好，世界\"\nmsgstr \"こんにちは世界\"\n");
    assertEq($po->entries[0]->msgid, '你好，世界');
    assertEq($po->entries[0]->msgstr[''], 'こんにちは世界');
});

test('angle brackets and HTML-like content', function () {
    $po = new GettextPoFile();
    $po->parse("msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\nmsgid \"Click <a href=\\\"#\\\">here</a> to continue\"\nmsgstr \"Klikněte <a href=\\\"#\\\">zde</a> pro pokračování\"\n");
    assertEq($po->entries[0]->msgid, 'Click <a href="#">here</a> to continue');
    assertEq($po->entries[0]->msgstr[''], 'Klikněte <a href="#">zde</a> pro pokračování');
});

test('single quotes and backticks', function () {
    $po = new GettextPoFile();
    // Single quotes are literal in PO — no escaping needed
    $po->parse("msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\nmsgid \"It's a \\\"test\\\" with `backticks`\"\nmsgstr \"Je to \\\"test\\\" s `zpětnými uvozovkami`\"\n");
    assertEq($po->entries[0]->msgid, "It's a \"test\" with `backticks`");
    assertEq($po->entries[0]->msgstr[''], "Je to \"test\" s `zpětnými uvozovkami`");
});

test('backslashes and escape hell', function () {
    $po = new GettextPoFile();
    $po->parse("msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\nmsgid \"Path: C:\\\\Users\\\\danny\\\\file.txt\\nNew line\\tTab\"\nmsgstr \"Cesta: /home/danny/soubor.txt\\nNový řádek\\tTabulátor\"\n");
    assertEq($po->entries[0]->msgid, "Path: C:\\Users\\danny\\file.txt\nNew line\tTab");
    assertEq($po->entries[0]->msgstr[''], "Cesta: /home/danny/soubor.txt\nNový řádek\tTabulátor");
});

test('percent signs and format strings', function () {
    $po = new GettextPoFile();
    $po->parse("msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\n#, php-format\nmsgid \"%d%% discount on %s — only $%01.2f!\"\nmsgstr \"%d%% sleva na %s — jen %01.2f Kč!\"\n");
    assertEq($po->entries[0]->msgid, '%d%% discount on %s — only $%01.2f!');
    assertEq($po->entries[0]->msgstr[''], '%d%% sleva na %s — jen %01.2f Kč!');
});

test('multiline with ugly chars round-trip', function () {
    // Use single-quoted string to avoid PHP interpolating $var
    $input = 'msgid ""' . "\n" . 'msgstr ""' . "\n" . '"Content-Type: text/plain; charset=UTF-8\\n"' . "\n" . "\n"
        . 'msgid ""' . "\n"
        . '"<div class=\"alert\">⚠️ "' . "\n"
        . '"Error: \'\\$var\' is not defined 🐛</div>"' . "\n"
        . 'msgstr ""' . "\n"
        . '"<div class=\"alert\">⚠️ "' . "\n"
        . '"Chyba: \'\\$var\' není definována 🐛</div>"' . "\n";
    $po = new GettextPoFile(); $po->parse($input);
    assertEq($po->entries[0]->msgid, "<div class=\"alert\">⚠️ Error: '\$var' is not defined 🐛</div>");
    assertEq($po->entries[0]->msgstr[''], "<div class=\"alert\">⚠️ Chyba: '\$var' není definována 🐛</div>");
    // Round-trip
    $po2 = new GettextPoFile(); $po2->parse($po->toString());
    assertEq($po2->entries[0]->msgid, $po->entries[0]->msgid);
    assertEq($po2->entries[0]->msgstr[''], $po->entries[0]->msgstr['']);
});

test('update with ugly chars and msgfmt', function () {
    $po = new GettextPoFile();
    $po->parse("msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\"Plural-Forms: nplurals=3; plural=(n==1) ? 0 : (n>=2 && n<=4) ? 1 : 2;\\n\"\n\nmsgid \"⚠️ <b>\\\"'one'\\\"</b> error\"\nmsgid_plural \"⚠️ <b>\\\"'%d'\\\"</b> errors\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"\nmsgstr[2] \"\"\n");
    $po->translate([
        "⚠️ <b>\"'one'\"</b> error" => [
            '0' => '⚠️ <b>"\'jedna\'"</b> chyba',
            '1' => '⚠️ <b>"\'%d\'"</b> chyby',
            '2' => '⚠️ <b>"\'%d\'"</b> chyb',
        ]
    ]);
    assertEq($po->entries[0]->isTranslated, true);
    $tmpFile = '/var/www/v2.ipdefender.eu/tmp/ai-test-po-ugly.po';
    $po->save($tmpFile);
    msgfmtCheck($tmpFile);
});

echo "\n========================================\n";
echo "Results: $pass passed, $fail failed\n";
if ($fail > 0) { echo "SOME TESTS FAILED!\n"; exit(1); }
echo "All tests passed!\n";

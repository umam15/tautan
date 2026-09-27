<?php
declare(strict_types=1);

/**
 * Minimal regression tests for authorization helpers.
 * Run: php tests/authorization.php
 */
define('TESTING', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

$_SESSION = [];

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    fwrite(STDOUT, "PASS: {$message}\n");
}

$user = ['id' => 10, 'username' => 'owner', 'role' => 'user'];
$_SESSION['user_id'] = 10;
assert_true(can_manage_link(['id' => 1, 'user_id' => 10]) === true, 'owner can manage own link');
assert_true(can_manage_link(['id' => 2, 'user_id' => 11]) === false, 'user cannot manage another user link');

$_SESSION['user_id'] = 11;
assert_true(can_manage_link(['id' => 1, 'user_id' => 10]) === false, 'different user remains denied');

$_SESSION['user_id'] = 1;
assert_true(can_manage_link(['id' => 1, 'user_id' => 10]) === true, 'admin can manage another user link');

fwrite(STDOUT, "Authorization regression tests passed.\n");

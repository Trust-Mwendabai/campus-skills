<?php
// Create an administrator from the command line (no default admin ships with the app).
//
//   php database/create-admin.php "Full Name" admin@campus.edu [super]
//
// The password is read from standard input so it never appears in shell history.

if (PHP_SAPI !== 'cli') {
    exit('Run this script from the command line.');
}
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';

[$script, $name, $email] = $argv + [null, null, null];
$role = in_array('super', $argv, true) ? 'super_admin' : 'admin';

if (!$name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php database/create-admin.php \"Full Name\" email@example.com [super]\n");
    exit(1);
}

fwrite(STDOUT, 'Password (min 8 chars, letters + numbers): ');
$password = trim((string) fgets(STDIN));
if ($err = password_error($password)) {
    fwrite(STDERR, $err . "\n");
    exit(1);
}

$email = mb_strtolower($email);
if (fetch_value('SELECT 1 FROM users WHERE email = ?', [$email])) {
    fwrite(STDERR, "An account with that email already exists.\n");
    exit(1);
}

q("INSERT INTO users (student_id, name, email, password_hash, role, status) VALUES (?,?,?,?,?, 'active')",
  ['ADM-' . strtoupper(bin2hex(random_bytes(3))), $name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);

fwrite(STDOUT, "Created $role account for $email\n");

<?php
require __DIR__ . '/includes/bootstrap.php';

// Logging out changes state, so it only happens on a POST with a valid CSRF token.
if (is_post()) {
    csrf_check();
    logout_user();
    session_start();
    flash('success', 'You have been logged out.');
}
redirect(url('login.php'));

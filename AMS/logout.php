<?php
require_once __DIR__ . '/includes/auth.php';

if (current_user() !== null) {
    logout_user();
    set_flash('success', 'You have been logged out.');
}

redirect('login.php');

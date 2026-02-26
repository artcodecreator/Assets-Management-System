<?php
require_once __DIR__ . '/includes/auth.php';

if (current_user() !== null) {
    redirect('dashboard.php');
}

redirect('login.php');

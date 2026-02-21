<?php
// Dashboard redirects to profile page — profile is the main view
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
boot_session();
require_login();
$me = current_user();
redirect(profile_url($me['username']));

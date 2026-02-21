<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
boot_session();
session_destroy();
redirect(url('auth/login.php'));

<?php
/** End the portal session. */

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';

logout_user();

session_start();
flash('You have been signed out.');

redirect('login.php');

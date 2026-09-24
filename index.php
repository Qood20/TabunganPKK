<?php
// Entry point aplikasi: arahkan pengguna ke dashboard atau halaman login.
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header("Location: dashboard.php");
} else {
    header("Location: login.php");
}
exit();

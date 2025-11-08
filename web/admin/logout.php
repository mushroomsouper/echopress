<?php
require_once __DIR__ . '/session_secure.php';
session_start();
session_destroy();
header('Location: login.php');
exit;

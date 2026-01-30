<?php
require_once __DIR__ . '/../includes/session.php';
session_unset();
session_destroy();
header('Location: index.php');
exit;

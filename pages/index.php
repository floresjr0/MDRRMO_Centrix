<?php
require_once __DIR__ . '/session.php';
require_login();

header('Location: navigation.php');
exit;
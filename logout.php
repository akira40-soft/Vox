<?php
require_once 'config/helpers.php';
session_destroy();
setcookie('remember_token', '', time() - 3600, '/');
header('Location: index.php');
exit;

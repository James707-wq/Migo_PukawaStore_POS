<?php
require_once 'config.php';
session_destroy();
header('Location: ' . BASE_URL . 'login.php');
exit;

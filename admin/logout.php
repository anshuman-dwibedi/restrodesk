<?php
require_once dirname(__DIR__) . '/core/bootstrap.php';
Auth::logout();
header('Location: login.php');
exit;

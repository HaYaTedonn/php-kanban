<?php
declare(strict_types=1);
error_reporting(E_ALL); ini_set('display_errors','0');
require __DIR__.'/db.php'; require __DIR__.'/functions.php';
$cfg = require __DIR__.'/config.php';
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off');
session_name($cfg['session_name'] ?? 'kanban_sess');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','httponly'=>true,'secure'=>$secure,'samesite'=>'Lax']);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require __DIR__.'/csrf.php'; require __DIR__.'/auth.php';

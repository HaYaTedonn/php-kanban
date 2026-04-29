<?php
declare(strict_types=1);
function e(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $p): never { header('Location: ' . $p); exit; }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }
function param(string $k, string $d = ''): string { $v = $_POST[$k] ?? $_GET[$k] ?? $d; return is_string($v) ? trim($v) : $d; }
function flash(?string $m = null, string $t = 'ok') {
    if ($m !== null) { $_SESSION['_flash'][] = ['t'=>$t,'m'=>$m]; return null; }
    $f = $_SESSION['_flash'] ?? []; unset($_SESSION['_flash']); return $f;
}
function json_out($data): never { header('Content-Type: application/json; charset=UTF-8'); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
const PRIORITIES = ['high'=>'高','mid'=>'中','low'=>'低'];
const LABEL_COLORS = ['#d8584e','#dd9a36','#3f9d5a','#5aa6c4','#8f6fd0','#c95f9b'];

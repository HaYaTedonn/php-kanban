<?php
declare(strict_types=1);
function csrf_token(): string { if (empty($_SESSION['_csrf'])) $_SESSION['_csrf']=bin2hex(random_bytes(32)); return $_SESSION['_csrf']; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="'.e(csrf_token()).'">'; }
function csrf_check(): void {
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $real = $_SESSION['_csrf'] ?? '';
    if ($real === '' || !is_string($sent) || !hash_equals($real, $sent)) {
        http_response_code(419); exit('不正なリクエストです（CSRFトークン不一致）。');
    }
}

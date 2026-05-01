<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
if (current_admin()) redirect('index.php');
$error = '';
if (is_post()) {
    csrf_check();
    if (attempt_login(param('email'), param('password'))) redirect('index.php');
    $error = 'メールアドレスまたはパスワードが正しくありません。';
}
?><!DOCTYPE html>
<html lang="ja"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ログイン｜FlowBoard</title><link rel="stylesheet" href="assets/style.css"></head>
<body>
<div class="login">
  <form class="box" method="post" action="login.php">
    <?= csrf_field() ?>
    <div class="logo">Flow<span>Board</span></div>
    <div class="sub">TASK &amp; PROJECT</div>
    <?php if ($error): ?><div class="flash error" style="margin:0 0 10px"><?= e($error) ?></div><?php endif; ?>
    <label>メールアドレス</label>
    <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" autofocus>
    <label>パスワード</label>
    <input type="password" name="password">
    <button class="btn" type="submit">ログイン</button>
    <div class="hint">デモ用ログイン<br>admin@example.com ／ kanban-admin-2026</div>
  </form>
</div>
</body></html>

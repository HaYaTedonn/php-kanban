<?php
declare(strict_types=1);
function attempt_login(string $email, string $password): bool {
    $u = DB::run('SELECT * FROM admin_users WHERE email = ? LIMIT 1',[$email])->fetch();
    if (!$u || !password_verify($password, $u['password_hash'])) return false;
    session_regenerate_id(true);
    $_SESSION['admin'] = ['id'=>(int)$u['id'],'name'=>$u['name'],'email'=>$u['email']];
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT))
        DB::run('UPDATE admin_users SET password_hash=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$u['id']]);
    return true;
}
function current_admin(): ?array { return $_SESSION['admin'] ?? null; }
function require_login(): void { if (!current_admin()) redirect('login.php'); }
function logout(): void {
    $_SESSION=[];
    if (ini_get('session.use_cookies')) { $p=session_get_cookie_params(); setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']); }
    session_destroy();
}

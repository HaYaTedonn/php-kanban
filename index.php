<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

// ボードの作成・削除
if (is_post()) {
    csrf_check();
    $action = param('action');
    if ($action === 'create') {
        $name = param('name');
        if ($name !== '' && mb_strlen($name) <= 120) {
            $pdo = DB::conn();
            $pdo->beginTransaction();
            try {
                DB::run('INSERT INTO boards (name) VALUES (?)', [$name]);
                $bid = (int) $pdo->lastInsertId();
                // 既定の列をつくる
                $pos = 0;
                foreach (['未着手', '進行中', '完了'] as $c) {
                    DB::run('INSERT INTO board_columns (board_id, name, position) VALUES (?,?,?)', [$bid, $c, $pos++]);
                }
                $pdo->commit();
                flash('ボードを作成しました。');
            } catch (Throwable $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); flash('作成に失敗しました。', 'error'); }
        } else {
            flash('ボード名を入力してください。', 'error');
        }
    } elseif ($action === 'delete_board') {
        DB::run('DELETE FROM boards WHERE id = ?', [(int) param('id')]); // 列・カードはCASCADE
        flash('ボードを削除しました。');
    }
    redirect('index.php');
}

$boards = DB::run('SELECT * FROM boards ORDER BY id')->fetchAll();
// 各ボードの列数・カード数・完了率
$stat = [];
foreach (DB::run(
    'SELECT b.id,
            (SELECT COUNT(*) FROM board_columns c WHERE c.board_id=b.id) cols,
            (SELECT COUNT(*) FROM cards k JOIN board_columns c ON c.id=k.column_id WHERE c.board_id=b.id) cards,
            (SELECT COUNT(*) FROM cards k JOIN board_columns c ON c.id=k.column_id WHERE c.board_id=b.id AND c.name=(SELECT name FROM board_columns WHERE board_id=b.id ORDER BY position DESC LIMIT 1)) done
       FROM boards b'
)->fetchAll() as $r) { $stat[$r['id']] = $r; }

$me = current_admin();
?><!DOCTYPE html>
<html lang="ja"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ボード一覧｜FlowBoard</title><link rel="stylesheet" href="assets/style.css"></head>
<body>
<div class="topbar">
  <div class="brand">Flow<span>Board</span></div>
  <div class="sp"></div>
  <span class="who"><?= e($me['name']) ?></span>
  <a href="logout.php">ログアウト</a>
</div>
<?php foreach ((flash() ?? []) as $f): ?><div class="flash <?= $f['t']==='error'?'error':'' ?>"><?= e($f['m']) ?></div><?php endforeach; ?>

<div class="wrap">
  <h1>ボード</h1>
  <p class="desc">プロジェクトやチームごとにボードを分けて管理できます。</p>

  <div class="boards">
    <?php foreach ($boards as $b): $s = $stat[$b['id']] ?? ['cols'=>0,'cards'=>0,'done'=>0];
        $pct = $s['cards'] ? round($s['done'] / $s['cards'] * 100) : 0; ?>
      <a class="board-card" href="board.php?id=<?= (int)$b['id'] ?>">
        <form class="del-inline" method="post" action="index.php" onsubmit="event.stopPropagation();return confirm('「<?= e($b['name']) ?>」を削除しますか？（カードも全て削除）')">
          <?= csrf_field() ?><input type="hidden" name="action" value="delete_board"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
          <button class="ic" style="border:0;background:none;color:#bbb6c0;cursor:pointer;font-size:14px" onclick="event.stopPropagation()" title="削除">×</button>
        </form>
        <div class="nm"><?= e($b['name']) ?></div>
        <div class="meta"><?= (int)$s['cols'] ?> 列 ・ <?= (int)$s['cards'] ?> 枚 ・ 完了 <?= $pct ?>%</div>
        <div class="bar"><i style="width:<?= $pct ?>%"></i></div>
      </a>
    <?php endforeach; ?>
  </div>

  <form class="newboard" method="post" action="index.php">
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <input type="text" name="name" placeholder="新しいボード名（例：マーケ施策）" maxlength="120">
    <button class="btn" type="submit">＋ ボードを作成</button>
  </form>
</div>
<p class="foot-note">提案用ポートフォリオ・デモ ／ データは架空のサンプルです ／ PHP 8 + MySQL ／ © 2026 鈴木颯</p>
</body></html>

<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();

$boardId = (int) param('id');
$board = DB::run('SELECT * FROM boards WHERE id = ?', [$boardId])->fetch();
if (!$board) { flash('ボードが見つかりませんでした。', 'error'); redirect('index.php'); }

/** 指定カラムがこのボードのものか検証 */
function column_in_board(int $colId, int $boardId): bool {
    return (bool) DB::run('SELECT 1 FROM board_columns WHERE id = ? AND board_id = ?', [$colId, $boardId])->fetch();
}
/** 指定カードがこのボードのものか */
function card_in_board(int $cardId, int $boardId): ?array {
    return DB::run(
        'SELECT k.* FROM cards k JOIN board_columns c ON c.id = k.column_id WHERE k.id = ? AND c.board_id = ?',
        [$cardId, $boardId]
    )->fetch() ?: null;
}
/** カラム内のカードの position を 0..n に振り直す */
function reindex(int $colId): void {
    $ids = DB::run('SELECT id FROM cards WHERE column_id = ? ORDER BY position, id', [$colId])->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $i => $cid) DB::run('UPDATE cards SET position = ? WHERE id = ?', [$i, $cid]);
}

/* ========================= POST ========================= */
if (is_post()) {
    csrf_check();
    $action = param('action');

    // --- ドラッグ移動（AJAX / JSON）---
    if ($action === 'move') {
        $cardId = (int) param('card_id');
        $toCol  = (int) param('to_column');
        $toIdx  = max(0, (int) param('to_index'));
        $card = card_in_board($cardId, $boardId);
        if (!$card || !column_in_board($toCol, $boardId)) json_out(['ok' => false, 'error' => 'invalid']);

        $pdo = DB::conn();
        $pdo->beginTransaction();
        try {
            $fromCol = (int) $card['column_id'];
            // 一旦末尾に大きなpositionで移して、対象カラムを取得→指定indexに差し込む
            DB::run('UPDATE cards SET column_id = ?, position = 9999 WHERE id = ?', [$toCol, $cardId]);
            // 対象カラムの並び（移動カード以外）を取得し、指定位置に挿入して振り直す
            $ids = DB::run('SELECT id FROM cards WHERE column_id = ? AND id <> ? ORDER BY position, id', [$toCol, $cardId])
                     ->fetchAll(PDO::FETCH_COLUMN);
            $toIdx = min($toIdx, count($ids));
            array_splice($ids, $toIdx, 0, [$cardId]);
            foreach ($ids as $i => $cid) DB::run('UPDATE cards SET position = ? WHERE id = ?', [$i, $cid]);
            if ($fromCol !== $toCol) reindex($fromCol);
            $pdo->commit();
            json_out(['ok' => true]);
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            json_out(['ok' => false, 'error' => 'db']);
        }
    }

    // --- カード 追加/編集/削除 ---
    if ($action === 'save_card') {
        $cardId = (int) param('card_id');
        $colId  = (int) param('column_id');
        $title  = param('title');
        $editing = $cardId > 0;

        if ($title === '' || mb_strlen($title) > 200) {
            flash('タイトルを入力してください。', 'error');
        } elseif ($editing ? !card_in_board($cardId, $boardId) : !column_in_board($colId, $boardId)) {
            flash('対象が見つかりません。', 'error');
        } else {
            $desc = mb_substr(param('description'), 0, 1000);
            $asg  = mb_substr(param('assignee'), 0, 60);
            $due  = param('due_date'); $due = preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) ? $due : null;
            $pri  = param('priority'); if (!isset(PRIORITIES[$pri])) $pri = 'mid';
            $lab  = param('label_color'); if (!in_array($lab, LABEL_COLORS, true)) $lab = '';
            if ($editing) {
                DB::run('UPDATE cards SET title=?, description=?, assignee=?, due_date=?, priority=?, label_color=? WHERE id=?',
                    [$title, $desc, $asg, $due, $pri, $lab, $cardId]);
            } else {
                $pos = (int) DB::run('SELECT COALESCE(MAX(position)+1,0) p FROM cards WHERE column_id=?', [$colId])->fetch()['p'];
                DB::run('INSERT INTO cards (column_id,title,description,assignee,due_date,priority,label_color,position) VALUES (?,?,?,?,?,?,?,?)',
                    [$colId, $title, $desc, $asg, $due, $pri, $lab, $pos]);
            }
            flash($editing ? 'カードを更新しました。' : 'カードを追加しました。');
        }
    } elseif ($action === 'delete_card') {
        $cardId = (int) param('card_id');
        if (card_in_board($cardId, $boardId)) { DB::run('DELETE FROM cards WHERE id=?', [$cardId]); flash('カードを削除しました。'); }
    } elseif ($action === 'add_column') {
        $name = param('name');
        if ($name !== '' && mb_strlen($name) <= 60) {
            $pos = (int) DB::run('SELECT COALESCE(MAX(position)+1,0) p FROM board_columns WHERE board_id=?', [$boardId])->fetch()['p'];
            DB::run('INSERT INTO board_columns (board_id,name,position) VALUES (?,?,?)', [$boardId, $name, $pos]);
            flash('列を追加しました。');
        }
    } elseif ($action === 'rename_column') {
        $colId = (int) param('column_id'); $name = param('name');
        if (column_in_board($colId, $boardId) && $name !== '' && mb_strlen($name) <= 60)
            DB::run('UPDATE board_columns SET name=? WHERE id=?', [$name, $colId]);
    } elseif ($action === 'delete_column') {
        $colId = (int) param('column_id');
        if (column_in_board($colId, $boardId)) { DB::run('DELETE FROM board_columns WHERE id=?', [$colId]); flash('列を削除しました。'); }
    }
    redirect('board.php?id=' . $boardId);
}

/* ========================= 表示 ========================= */
$columns = DB::run('SELECT * FROM board_columns WHERE board_id=? ORDER BY position, id', [$boardId])->fetchAll();
$cardsByCol = [];
$allCards = [];
foreach (DB::run(
    'SELECT k.* FROM cards k JOIN board_columns c ON c.id=k.column_id WHERE c.board_id=? ORDER BY k.position, k.id',
    [$boardId]
)->fetchAll() as $k) { $cardsByCol[$k['column_id']][] = $k; $allCards[$k['id']] = $k; }

$totalCards = count($allCards);
$lastColId = $columns ? (int) end($columns)['id'] : 0;
$doneCards = $lastColId ? count($cardsByCol[$lastColId] ?? []) : 0;
$pct = $totalCards ? round($doneCards / $totalCards * 100) : 0;

$me = current_admin();
$today = date('Y-m-d');
?><!DOCTYPE html>
<html lang="ja"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($board['name']) ?>｜FlowBoard</title><link rel="stylesheet" href="assets/style.css"></head>
<body>
<div class="topbar">
  <div class="brand">Flow<span>Board</span></div>
  <a href="index.php">← ボード一覧</a>
  <span class="bname"><?= e($board['name']) ?></span>
  <div class="sp"></div>
  <span class="who"><?= e($me['name']) ?></span>
  <a href="logout.php">ログアウト</a>
</div>
<?php foreach ((flash() ?? []) as $f): ?><div class="flash <?= $f['t']==='error'?'error':'' ?>"><?= e($f['m']) ?></div><?php endforeach; ?>

<div class="boardhead">
  <h1><?= e($board['name']) ?></h1>
  <div class="sp"></div>
  <div class="progress">進捗 <div class="pbar"><i style="width:<?= $pct ?>%"></i></div> <b><?= $pct ?>%</b>（<?= $doneCards ?>/<?= $totalCards ?>）</div>
</div>

<div class="board" id="board">
  <?php foreach ($columns as $col): $list = $cardsByCol[$col['id']] ?? []; ?>
    <div class="colm" data-col="<?= (int)$col['id'] ?>">
      <div class="colhead">
        <span class="ttl"><?= e($col['name']) ?></span>
        <span class="cnt"><?= count($list) ?></span>
        <span class="acts">
          <button class="ic" title="列名を変更" onclick="renameCol(<?= (int)$col['id'] ?>,'<?= e($col['name']) ?>')">名</button>
          <button class="ic" title="列を削除" onclick="delCol(<?= (int)$col['id'] ?>,'<?= e($col['name']) ?>',<?= count($list) ?>)">×</button>
        </span>
      </div>
      <div class="cards" data-col="<?= (int)$col['id'] ?>">
        <?php foreach ($list as $k):
            $over = $k['due_date'] && $k['due_date'] < $today; ?>
          <div class="kcard p-<?= e($k['priority']) ?>" draggable="true" data-id="<?= (int)$k['id'] ?>" onclick="openCard(<?= (int)$col['id'] ?>,<?= (int)$k['id'] ?>)">
            <?php if ($k['label_color'] !== ''): ?><div class="lab" style="background:<?= e($k['label_color']) ?>"></div><?php endif; ?>
            <div class="ti"><?= e($k['title']) ?></div>
            <div class="meta">
              <?php if ($k['due_date']): ?><span class="due <?= $over?'over':'' ?>"><?= (int)substr($k['due_date'],5,2) ?>/<?= (int)substr($k['due_date'],8,2) ?></span><?php endif; ?>
              <?php if ($k['assignee'] !== ''): ?><span class="asg"><span class="av"><?= e(mb_substr($k['assignee'],0,1)) ?></span><?= e($k['assignee']) ?></span><?php endif; ?>
              <span class="pri p-<?= e($k['priority']) ?>"><?= PRIORITIES[$k['priority']] ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <button class="addcard" onclick="openCard(<?= (int)$col['id'] ?>)">＋ カードを追加</button>
    </div>
  <?php endforeach; ?>

  <div class="addcol">
    <form method="post" action="board.php?id=<?= $boardId ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="add_column">
      <input type="text" name="name" placeholder="新しい列名" maxlength="60">
      <button class="btn sm" type="submit">＋ 列を追加</button>
    </form>
  </div>
</div>
<p class="foot-note">カードはドラッグ&ドロップで移動でき、並び順はサーバー(DB)に保存されます ／ © 2026 鈴木颯</p>

<!-- card modal -->
<div class="modal" id="modal">
  <form class="mbox" method="post" action="board.php?id=<?= $boardId ?>">
    <h3 id="mTitle">カードを追加</h3>
    <div class="mform">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_card">
      <input type="hidden" name="card_id" id="f_id" value="0">
      <input type="hidden" name="column_id" id="f_col" value="0">
      <label>タイトル *</label><input name="title" id="f_title" maxlength="200">
      <label>説明</label><textarea name="description" id="f_desc" maxlength="1000"></textarea>
      <div class="two">
        <div><label>担当者</label><input name="assignee" id="f_asg" maxlength="60"></div>
        <div><label>期限</label><input type="date" name="due_date" id="f_due"></div>
      </div>
      <div class="two">
        <div><label>優先度</label>
          <select name="priority" id="f_pri">
            <?php foreach (PRIORITIES as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
          </select>
        </div>
        <div><label>ラベル色</label>
          <div class="swatch" id="f_labels">
            <label><input type="radio" name="label_color" value="" checked><span class="sw none">無</span></label>
            <?php foreach (LABEL_COLORS as $c): ?>
              <label><input type="radio" name="label_color" value="<?= $c ?>"><span class="sw" style="background:<?= $c ?>"></span></label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
    <div class="mfoot">
      <button type="button" class="btn danger del" id="delBtn" style="display:none" onclick="deleteCard()">削除</button>
      <button type="button" class="btn ghost" onclick="closeModal()">キャンセル</button>
      <button type="submit" class="btn">保存</button>
    </div>
  </form>
</div>

<!-- hidden forms for delete card / column ops -->
<form id="opForm" method="post" action="board.php?id=<?= $boardId ?>" style="display:none">
  <?= csrf_field() ?>
  <input type="hidden" name="action" id="op_action">
  <input type="hidden" name="card_id" id="op_card">
  <input type="hidden" name="column_id" id="op_col">
  <input type="hidden" name="name" id="op_name">
</form>

<script>
const CSRF = "<?= e(csrf_token()) ?>";
const BOARD_ID = <?= $boardId ?>;
const CARDS = <?= json_encode($allCards, JSON_UNESCAPED_UNICODE) ?>;

/* ---- カードモーダル ---- */
function openCard(colId, cardId){
  const m = document.getElementById('modal');
  document.getElementById('f_col').value = colId;
  if (cardId && CARDS[cardId]) {
    const c = CARDS[cardId];
    document.getElementById('mTitle').textContent = 'カードを編集';
    document.getElementById('f_id').value = c.id;
    document.getElementById('f_title').value = c.title;
    document.getElementById('f_desc').value = c.description || '';
    document.getElementById('f_asg').value = c.assignee || '';
    document.getElementById('f_due').value = c.due_date || '';
    document.getElementById('f_pri').value = c.priority;
    setLabel(c.label_color || '');
    document.getElementById('delBtn').style.display = 'inline-block';
  } else {
    document.getElementById('mTitle').textContent = 'カードを追加';
    document.getElementById('f_id').value = 0;
    document.getElementById('f_title').value = '';
    document.getElementById('f_desc').value = '';
    document.getElementById('f_asg').value = '';
    document.getElementById('f_due').value = '';
    document.getElementById('f_pri').value = 'mid';
    setLabel('');
    document.getElementById('delBtn').style.display = 'none';
  }
  m.classList.add('show');
  setTimeout(()=>document.getElementById('f_title').focus(), 50);
}
function setLabel(v){ document.querySelectorAll('#f_labels input').forEach(i=>i.checked = (i.value===v)); }
function closeModal(){ document.getElementById('modal').classList.remove('show'); }
document.getElementById('modal').addEventListener('click', e=>{ if(e.target.id==='modal') closeModal(); });

function deleteCard(){
  if (!confirm('このカードを削除しますか？')) return;
  document.getElementById('op_action').value = 'delete_card';
  document.getElementById('op_card').value = document.getElementById('f_id').value;
  document.getElementById('opForm').submit();
}
function renameCol(id, cur){
  const name = prompt('列名を変更', cur); if (name===null) return;
  document.getElementById('op_action').value = 'rename_column';
  document.getElementById('op_col').value = id;
  document.getElementById('op_name').value = name;
  document.getElementById('opForm').submit();
}
function delCol(id, name, n){
  if (!confirm('「'+name+'」を削除しますか？'+(n>0?'（カード'+n+'枚も削除）':''))) return;
  document.getElementById('op_action').value = 'delete_column';
  document.getElementById('op_col').value = id;
  document.getElementById('opForm').submit();
}

/* ---- ドラッグ&ドロップ（移動はAJAXでDBに保存）---- */
let dragId = null;
document.querySelectorAll('.kcard').forEach(card=>{
  card.addEventListener('dragstart', e=>{ dragId = card.dataset.id; card.classList.add('dragging'); e.dataTransfer.effectAllowed='move'; });
  card.addEventListener('dragend', ()=> card.classList.remove('dragging'));
});
document.querySelectorAll('.cards').forEach(list=>{
  const colm = list.closest('.colm');
  list.addEventListener('dragover', e=>{
    e.preventDefault(); colm.classList.add('dragover');
    const after = getAfter(list, e.clientY);
    const dragging = document.querySelector('.kcard.dragging');
    if (!dragging) return;
    if (after == null) list.appendChild(dragging); else list.insertBefore(dragging, after);
  });
  list.addEventListener('dragleave', ()=> colm.classList.remove('dragover'));
  list.addEventListener('drop', e=>{
    e.preventDefault(); colm.classList.remove('dragover');
    const toCol = list.dataset.col;
    const ids = [...list.querySelectorAll('.kcard')].map(c=>c.dataset.id);
    const toIndex = ids.indexOf(dragId);
    persistMove(dragId, toCol, toIndex);
    refreshCounts();
  });
});
function getAfter(list, y){
  const els = [...list.querySelectorAll('.kcard:not(.dragging)')];
  return els.reduce((closest, child)=>{
    const box = child.getBoundingClientRect();
    const offset = y - box.top - box.height/2;
    if (offset < 0 && offset > closest.offset) return {offset, element: child};
    return closest;
  }, {offset: -Infinity}).element || null;
}
function persistMove(cardId, toCol, toIndex){
  const body = new URLSearchParams({action:'move', card_id:cardId, to_column:toCol, to_index:toIndex, _csrf:CSRF});
  fetch('board.php?id='+BOARD_ID, {method:'POST', headers:{'X-CSRF-TOKEN':CSRF}, body})
    .then(r=>r.json()).then(d=>{ if(!d.ok) location.reload(); })
    .catch(()=> location.reload());
}
function refreshCounts(){
  document.querySelectorAll('.colm').forEach(colm=>{
    const n = colm.querySelectorAll('.kcard').length;
    colm.querySelector('.colhead .cnt').textContent = n;
  });
}
</script>
</body></html>

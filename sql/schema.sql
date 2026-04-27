-- ============================================================
--  タスク/案件管理(カンバン)  スキーマ + 初期データ（MySQL 8.0）
--  使い方:  mysql -u <user> -p <db> < sql/schema.sql
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS cards;
DROP TABLE IF EXISTS board_columns;
DROP TABLE IF EXISTS boards;
DROP TABLE IF EXISTS admin_users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(100) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE boards (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE board_columns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  board_id INT NOT NULL,
  name VARCHAR(60) NOT NULL,
  position INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_col_board FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE,
  INDEX idx_board (board_id, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cards (
  id INT AUTO_INCREMENT PRIMARY KEY,
  column_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  description VARCHAR(1000) NOT NULL DEFAULT '',
  assignee VARCHAR(60) NOT NULL DEFAULT '',
  due_date DATE NULL,
  priority ENUM('high','mid','low') NOT NULL DEFAULT 'mid',
  label_color VARCHAR(7) NOT NULL DEFAULT '',
  position INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_card_col FOREIGN KEY (column_id) REFERENCES board_columns(id) ON DELETE CASCADE,
  INDEX idx_col (column_id, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  初期データ
-- ============================================================
-- ログイン: admin@example.com / kanban-admin-2026
INSERT INTO admin_users (email, password_hash, name) VALUES
('admin@example.com', '$2y$12$Qhr3T54EtN2pmoeXzDygfOHwsPLPwtFx3crOO59KEW075A/OSQXWG', '管理者');

INSERT INTO boards (id, name) VALUES (1, 'Webサイト制作プロジェクト'), (2, '社内タスク');

INSERT INTO board_columns (id, board_id, name, position) VALUES
(1,1,'未着手',0),(2,1,'進行中',1),(3,1,'レビュー',2),(4,1,'完了',3),
(5,2,'ToDo',0),(6,2,'対応中',1),(7,2,'完了',2);

INSERT INTO cards (column_id, title, description, assignee, due_date, priority, label_color, position) VALUES
(1,'トップページのデザイン案','3案つくって比較する','佐藤', DATE_ADD(CURDATE(), INTERVAL 3 DAY),'high','#d8584e',0),
(1,'問い合わせフォーム','バリデーションとメール送信','山田', DATE_ADD(CURDATE(), INTERVAL 6 DAY),'mid','#3f9d5a',1),
(1,'ロゴ素材の整理','','', NULL,'low','',2),
(2,'予約システムの実装','枠の重複判定まで','鈴木', DATE_ADD(CURDATE(), INTERVAL 2 DAY),'high','#dd9a36',0),
(2,'CSV出力機能','UTF-8 BOM対応','山田', DATE_ADD(CURDATE(), INTERVAL 1 DAY),'mid','#5aa6c4',1),
(3,'管理画面の権限分離','レビュー待ち','佐藤', DATE_SUB(CURDATE(), INTERVAL 1 DAY),'mid','#8f6fd0',0),
(4,'要件ヒアリング','議事録共有済み','鈴木', DATE_SUB(CURDATE(), INTERVAL 5 DAY),'low','#3f9d5a',0),
(4,'環境構築','','山田', DATE_SUB(CURDATE(), INTERVAL 7 DAY),'low','',1),
(5,'勤怠の集計','月末締め','', NULL,'mid','',0),
(6,'備品の発注','','佐藤', DATE_ADD(CURDATE(), INTERVAL 2 DAY),'low','#5aa6c4',0);

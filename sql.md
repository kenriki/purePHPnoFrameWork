```sql
CREATE TABLE IF NOT EXISTS `survey_responses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT '回答ID',
  `user_id` INT NOT NULL COMMENT 'ログインユーザーのID',
  `rating` INT NOT NULL COMMENT '5段階評価（1-5）',
  `comment` TEXT COMMENT '自由入力コメント',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '投稿日時'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT 'ユーザーID',
  `username` VARCHAR(50) NOT NULL UNIQUE COMMENT 'ログインID（ユーザー名）',
  `password` VARCHAR(255) NOT NULL COMMENT 'ハッシュ化されたパスワード',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'アカウント作成日時'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `users` (`id`, `username`, `password`) VALUES
(1, 'root', 'P@ss12345'),
(2, 'test_user', 'password123');

```

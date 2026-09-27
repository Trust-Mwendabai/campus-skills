-- Campus SkillX — Skill Exchange Platform
-- MySQL / MariaDB schema.  Import with:
--   mysql -u root < database/campus_skill_exchange.sql

CREATE DATABASE IF NOT EXISTS campus_skill_exchange
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE campus_skill_exchange;

-- ---------------------------------------------------------------- users
CREATE TABLE IF NOT EXISTS users (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id       VARCHAR(30)  NOT NULL UNIQUE,
  name             VARCHAR(100) NOT NULL,
  email            VARCHAR(150) NOT NULL UNIQUE,
  phone            VARCHAR(30)  NULL,
  programme        VARCHAR(120) NULL,
  faculty          VARCHAR(120) NULL,
  year_of_study    TINYINT UNSIGNED NULL,
  password_hash    VARCHAR(255) NOT NULL,
  bio              TEXT NULL,
  role             ENUM('student','admin','super_admin') NOT NULL DEFAULT 'student',
  status           ENUM('pending','active','suspended')  NOT NULL DEFAULT 'active',
  -- learning preferences
  learning_method  ENUM('any','online','physical','group','one_to_one') NOT NULL DEFAULT 'any',
  session_duration SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  -- privacy
  profile_visibility ENUM('everyone','matches') NOT NULL DEFAULT 'everyone',
  skill_visibility   ENUM('everyone','matches') NOT NULL DEFAULT 'everyone',
  message_permission ENUM('everyone','matches') NOT NULL DEFAULT 'matches',
  -- notification preferences
  notify_matches   TINYINT(1) NOT NULL DEFAULT 1,
  notify_requests  TINYINT(1) NOT NULL DEFAULT 1,
  notify_messages  TINYINT(1) NOT NULL DEFAULT 1,
  notify_sessions  TINYINT(1) NOT NULL DEFAULT 1,
  last_login       DATETIME NULL,
  session_epoch    INT UNSIGNED NOT NULL DEFAULT 0,   -- bumped to sign out every other session
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_status_role (status, role)
) ENGINE=InnoDB;

-- ----------------------------------------------------- categories, skills
CREATE TABLE IF NOT EXISTS categories (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name      VARCHAR(80) NOT NULL UNIQUE,
  slug      VARCHAR(60) NOT NULL UNIQUE,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS skills (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NOT NULL,
  name        VARCHAR(80) NOT NULL UNIQUE,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- A user either offers (teaches) or wants (learns) a skill.
CREATE TABLE IF NOT EXISTS user_skills (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  skill_id     INT UNSIGNED NOT NULL,
  type         ENUM('offer','want') NOT NULL,
  level        ENUM('beginner','intermediate','advanced') NOT NULL DEFAULT 'intermediate',
  description  VARCHAR(300) NULL,
  experience   VARCHAR(150) NULL,
  availability VARCHAR(150) NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_skill_type (user_id, skill_id, type),
  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
  FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Weekly availability (day_of_week: 1 = Monday ... 7 = Sunday)
CREATE TABLE IF NOT EXISTS availability (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  day_of_week TINYINT UNSIGNED NOT NULL,
  start_time  TIME NOT NULL,
  end_time    TIME NOT NULL,
  KEY idx_user_day (user_id, day_of_week),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------ requests and matches
CREATE TABLE IF NOT EXISTS requests (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sender_id      INT UNSIGNED NOT NULL,
  receiver_id    INT UNSIGNED NOT NULL,
  want_skill_id  INT UNSIGNED NOT NULL,          -- skill the sender wants to learn (receiver teaches)
  offer_skill_id INT UNSIGNED NULL,              -- skill the sender offers in return
  message        VARCHAR(600) NULL,
  method         ENUM('any','online','physical','group','one_to_one') NOT NULL DEFAULT 'any',
  preferred_date DATE NULL,
  preferred_time TIME NULL,
  status         ENUM('pending','accepted','rejected','cancelled','completed') NOT NULL DEFAULT 'pending',
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  responded_at   DATETIME NULL,
  KEY idx_receiver_status (receiver_id, status),
  KEY idx_sender_status (sender_id, status),
  FOREIGN KEY (sender_id)      REFERENCES users(id)  ON DELETE CASCADE,
  FOREIGN KEY (receiver_id)    REFERENCES users(id)  ON DELETE CASCADE,
  FOREIGN KEY (want_skill_id)  REFERENCES skills(id) ON DELETE CASCADE,
  FOREIGN KEY (offer_skill_id) REFERENCES skills(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Created when a request is accepted. user_a is the requester, user_b the receiver.
CREATE TABLE IF NOT EXISTS matches (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id INT UNSIGNED NOT NULL UNIQUE,
  user_a     INT UNSIGNED NOT NULL,
  user_b     INT UNSIGNED NOT NULL,
  skill_a_id INT UNSIGNED NOT NULL,              -- what user_a learns (user_b teaches)
  skill_b_id INT UNSIGNED NULL,                  -- what user_b learns (user_a teaches)
  score      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  status     ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_a (user_a, status),
  KEY idx_b (user_b, status),
  FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
  FOREIGN KEY (user_a)     REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (user_b)     REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (skill_a_id) REFERENCES skills(id)   ON DELETE CASCADE,
  FOREIGN KEY (skill_b_id) REFERENCES skills(id)   ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------- messaging
CREATE TABLE IF NOT EXISTS messages (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sender_id   INT UNSIGNED NOT NULL,
  receiver_id INT UNSIGNED NOT NULL,
  body        TEXT NOT NULL,
  is_read     TINYINT(1) NOT NULL DEFAULT 0,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_inbox (receiver_id, is_read),
  KEY idx_pair (sender_id, receiver_id),
  FOREIGN KEY (sender_id)   REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------- sessions and ratings
CREATE TABLE IF NOT EXISTS sessions (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  match_id     INT UNSIGNED NOT NULL,
  skill_id     INT UNSIGNED NULL,
  created_by   INT UNSIGNED NOT NULL,
  title        VARCHAR(150) NOT NULL,
  session_date DATE NOT NULL,
  start_time   TIME NOT NULL,
  end_time     TIME NOT NULL,
  location     VARCHAR(150) NULL,
  meeting_link VARCHAR(255) NULL,
  notes        VARCHAR(500) NULL,
  status       ENUM('scheduled','completed','cancelled','missed') NOT NULL DEFAULT 'scheduled',
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_match (match_id),
  KEY idx_date (session_date, status),
  FOREIGN KEY (match_id)   REFERENCES matches(id) ON DELETE CASCADE,
  FOREIGN KEY (skill_id)   REFERENCES skills(id)  ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ratings (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  match_id    INT UNSIGNED NOT NULL,
  reviewer_id INT UNSIGNED NOT NULL,
  reviewee_id INT UNSIGNED NOT NULL,
  score       TINYINT UNSIGNED NOT NULL,
  comment     VARCHAR(600) NULL,
  is_hidden   TINYINT(1) NOT NULL DEFAULT 0,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_one_review (match_id, reviewer_id),   -- one review per person per exchange
  KEY idx_reviewee (reviewee_id, is_hidden),
  CONSTRAINT chk_score CHECK (score BETWEEN 1 AND 5),
  FOREIGN KEY (match_id)    REFERENCES matches(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewer_id) REFERENCES users(id)   ON DELETE CASCADE,
  FOREIGN KEY (reviewee_id) REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------- notifications, reports, admin
CREATE TABLE IF NOT EXISTS notifications (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  type       ENUM('match','request','request_accepted','request_rejected','message','session','session_completed','review','announcement') NOT NULL,
  title      VARCHAR(150) NOT NULL,
  body       VARCHAR(300) NULL,
  link       VARCHAR(200) NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user_read (user_id, is_read, created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reports (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reporter_id      INT UNSIGNED NOT NULL,
  reported_user_id INT UNSIGNED NOT NULL,
  category         ENUM('fake_profile','spam','inappropriate_behaviour','harassment','misleading_information','inappropriate_message','other') NOT NULL,
  description      VARCHAR(1000) NOT NULL,
  status           ENUM('open','investigating','resolved','dismissed') NOT NULL DEFAULT 'open',
  admin_note       VARCHAR(500) NULL,
  handled_by       INT UNSIGNED NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_status (status),
  FOREIGN KEY (reporter_id)      REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (reported_user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (handled_by)       REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS announcements (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id   INT UNSIGNED NULL,
  title      VARCHAR(150) NOT NULL,
  body       TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_actions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id    INT UNSIGNED NULL,
  action      VARCHAR(80) NOT NULL,
  target_type VARCHAR(40) NULL,
  target_id   INT UNSIGNED NULL,
  details     VARCHAR(300) NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS settings (
  name  VARCHAR(60) PRIMARY KEY,
  value VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

-- Failed-login tracking used to throttle brute-force attempts.
CREATE TABLE IF NOT EXISTS login_attempts (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  identifier   VARCHAR(150) NOT NULL,
  ip           VARCHAR(45) NOT NULL,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ident_time (identifier, attempted_at),
  KEY idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------- seed data
INSERT IGNORE INTO settings (name, value) VALUES
  ('require_verification', '0'),      -- 1 = new accounts stay "pending" until an admin activates them
  ('allow_registration',   '1'),
  ('site_notice',          '');

INSERT IGNORE INTO categories (name, slug) VALUES
  ('Programming & Tech', 'programming'), ('Cybersecurity', 'cybersecurity'),
  ('Design & Media', 'design'),          ('Data & AI', 'data'),
  ('Languages', 'languages'),            ('Music & Arts', 'music'),
  ('Academics', 'academics'),            ('Business & Career', 'business'),
  ('Life Skills', 'lifestyle');

INSERT IGNORE INTO skills (category_id, name) VALUES
  ((SELECT id FROM categories WHERE slug='programming'),'Python'),
  ((SELECT id FROM categories WHERE slug='programming'),'Java'),
  ((SELECT id FROM categories WHERE slug='programming'),'JavaScript'),
  ((SELECT id FROM categories WHERE slug='programming'),'PHP & MySQL'),
  ((SELECT id FROM categories WHERE slug='programming'),'HTML & CSS'),
  ((SELECT id FROM categories WHERE slug='programming'),'Git & GitHub'),
  ((SELECT id FROM categories WHERE slug='programming'),'Networking'),
  ((SELECT id FROM categories WHERE slug='cybersecurity'),'Ethical hacking'),
  ((SELECT id FROM categories WHERE slug='cybersecurity'),'Digital forensics'),
  ((SELECT id FROM categories WHERE slug='cybersecurity'),'Cryptography'),
  ((SELECT id FROM categories WHERE slug='design'),'Graphic design'),
  ((SELECT id FROM categories WHERE slug='design'),'UI/UX design'),
  ((SELECT id FROM categories WHERE slug='design'),'Video editing'),
  ((SELECT id FROM categories WHERE slug='design'),'Photography'),
  ((SELECT id FROM categories WHERE slug='design'),'Adobe Photoshop'),
  ((SELECT id FROM categories WHERE slug='data'),'Data analysis'),
  ((SELECT id FROM categories WHERE slug='data'),'Data science'),
  ((SELECT id FROM categories WHERE slug='data'),'Machine learning'),
  ((SELECT id FROM categories WHERE slug='data'),'SQL'),
  ((SELECT id FROM categories WHERE slug='languages'),'Spanish'),
  ((SELECT id FROM categories WHERE slug='languages'),'French'),
  ((SELECT id FROM categories WHERE slug='languages'),'Mandarin'),
  ((SELECT id FROM categories WHERE slug='languages'),'Swahili'),
  ((SELECT id FROM categories WHERE slug='music'),'Guitar'),
  ((SELECT id FROM categories WHERE slug='music'),'Piano'),
  ((SELECT id FROM categories WHERE slug='music'),'Music theory'),
  ((SELECT id FROM categories WHERE slug='music'),'Drawing'),
  ((SELECT id FROM categories WHERE slug='academics'),'Calculus'),
  ((SELECT id FROM categories WHERE slug='academics'),'Statistics'),
  ((SELECT id FROM categories WHERE slug='academics'),'Essay writing'),
  ((SELECT id FROM categories WHERE slug='academics'),'Public speaking'),
  ((SELECT id FROM categories WHERE slug='business'),'Excel'),
  ((SELECT id FROM categories WHERE slug='business'),'CV writing'),
  ((SELECT id FROM categories WHERE slug='business'),'Pitching'),
  ((SELECT id FROM categories WHERE slug='business'),'Project management'),
  ((SELECT id FROM categories WHERE slug='lifestyle'),'Cooking'),
  ((SELECT id FROM categories WHERE slug='lifestyle'),'Fitness'),
  ((SELECT id FROM categories WHERE slug='lifestyle'),'Budgeting'),
  ((SELECT id FROM categories WHERE slug='lifestyle'),'Time management');

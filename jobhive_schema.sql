-- ============================================================
--  JobHive Database Schema
--  Run this entire file once on your MySQL / MariaDB server.
--  Example:  mysql -u root -p < jobhive_schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS jobhive
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE jobhive;

-- ────────────────────────────────────────────────────────────
-- 1. USERS  (job seekers who register/login)
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120)  NOT NULL,
  email         VARCHAR(191)  NOT NULL UNIQUE,
  password_hash VARCHAR(255)  NOT NULL,          -- bcrypt
  city          VARCHAR(100)  DEFAULT NULL,
  country       VARCHAR(100)  DEFAULT NULL,
  phone         VARCHAR(30)   DEFAULT NULL,
  avatar_url    VARCHAR(500)  DEFAULT NULL,
  role          ENUM('seeker','employer','admin') NOT NULL DEFAULT 'seeker',
  is_active     TINYINT(1)    NOT NULL DEFAULT 1,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_role  (role)
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- 2. USER EDUCATION  (multiple rows per user)
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS user_education (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  degree       VARCHAR(150) NOT NULL,            -- e.g. "B.Sc Computer Science"
  institution  VARCHAR(200) NOT NULL,
  field        VARCHAR(150) DEFAULT NULL,        -- e.g. "Software Engineering"
  start_year   YEAR         DEFAULT NULL,
  end_year     YEAR         DEFAULT NULL,
  is_current   TINYINT(1)   NOT NULL DEFAULT 0,
  grade        VARCHAR(50)  DEFAULT NULL,        -- e.g. "3.8 GPA", "First Class"
  description  TEXT         DEFAULT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id)
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- 3. USER EXPERIENCE  (multiple rows per user)
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS user_experience (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  job_title    VARCHAR(150) NOT NULL,
  company      VARCHAR(200) NOT NULL,
  location     VARCHAR(150) DEFAULT NULL,
  employment_type ENUM('full-time','part-time','freelance','internship','contract','other')
                NOT NULL DEFAULT 'full-time',
  start_date   DATE         DEFAULT NULL,
  end_date     DATE         DEFAULT NULL,
  is_current   TINYINT(1)   NOT NULL DEFAULT 0,
  description  TEXT         DEFAULT NULL,        -- responsibilities / achievements
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id)
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- 4. USER SKILLS  (many-to-many via simple join table)
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS skills (
  id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name  VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_skills (
  user_id   INT UNSIGNED NOT NULL,
  skill_id  INT UNSIGNED NOT NULL,
  level     ENUM('beginner','intermediate','advanced','expert') DEFAULT 'intermediate',
  PRIMARY KEY (user_id, skill_id),
  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
  FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- 5. JOBS  (posted by employers / seeded by admins)
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS jobs (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  posted_by       INT UNSIGNED DEFAULT NULL,     -- NULL = seeded/admin
  title           VARCHAR(200) NOT NULL,
  company         VARCHAR(200) NOT NULL,
  company_logo    VARCHAR(500) DEFAULT NULL,
  location        VARCHAR(150) DEFAULT NULL,
  is_remote       TINYINT(1)   NOT NULL DEFAULT 0,
  job_type        ENUM('full-time','part-time','freelance','internship','contract')
                  NOT NULL DEFAULT 'full-time',
  salary_min      DECIMAL(12,2) DEFAULT NULL,
  salary_max      DECIMAL(12,2) DEFAULT NULL,
  salary_currency VARCHAR(10)   DEFAULT 'USD',
  description     TEXT          DEFAULT NULL,
  requirements    TEXT          DEFAULT NULL,
  category        VARCHAR(100)  DEFAULT NULL,    -- e.g. "Development", "Design"
  is_active       TINYINT(1)    NOT NULL DEFAULT 1,
  is_featured     TINYINT(1)    NOT NULL DEFAULT 0,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_active   (is_active),
  INDEX idx_featured (is_featured),
  INDEX idx_type     (job_type),
  FULLTEXT idx_search (title, company, description, requirements)
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- 6. APPLICATIONS  (seeker applies for a job)
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS applications (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id          INT UNSIGNED NOT NULL,
  job_id           INT UNSIGNED DEFAULT NULL,   -- NULL = job title typed manually
  job_title_manual VARCHAR(200) DEFAULT NULL,   -- kept for legacy / freestyle apply
  degree           VARCHAR(150) DEFAULT NULL,
  experience_years TINYINT UNSIGNED DEFAULT NULL,
  age              TINYINT UNSIGNED DEFAULT NULL,
  gender           ENUM('male','female','prefer_not_to_say') DEFAULT NULL,
  city             VARCHAR(100) DEFAULT NULL,
  country          VARCHAR(100) DEFAULT NULL,
  cover_letter     TEXT         DEFAULT NULL,
  resume_url       VARCHAR(500) DEFAULT NULL,
  status           ENUM('pending','reviewed','shortlisted','rejected','hired')
                   NOT NULL DEFAULT 'pending',
  applied_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (job_id)  REFERENCES jobs(id)  ON DELETE SET NULL,
  UNIQUE KEY uq_user_job (user_id, job_id),   -- prevent duplicate applications
  INDEX idx_status (status),
  INDEX idx_user   (user_id)
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- 7. SAVED / BOOKMARKED JOBS
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS saved_jobs (
  user_id    INT UNSIGNED NOT NULL,
  job_id     INT UNSIGNED NOT NULL,
  saved_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, job_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (job_id)  REFERENCES jobs(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- 8. CONTACT MESSAGES
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS contact_messages (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED DEFAULT NULL,          -- NULL if not logged in
  name       VARCHAR(120) NOT NULL,
  email      VARCHAR(191) NOT NULL,
  subject    VARCHAR(250) DEFAULT NULL,
  message    TEXT         NOT NULL,
  is_read    TINYINT(1)   NOT NULL DEFAULT 0,
  sent_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- SEED: a handful of demo jobs (mirrors the HTML hardcodes)
-- ────────────────────────────────────────────────────────────
INSERT IGNORE INTO jobs
  (title, company, location, is_remote, job_type, salary_min, salary_max, category, is_featured)
VALUES
  ('Senior UI/UX Designer',      'PixelCraft Studio',   'Remote',       1, 'full-time',  80000, 110000, 'Design',      1),
  ('Full Stack Developer',        'NexaTech Solutions',  'Lahore, PK',   0, 'full-time',  90000, 130000, 'Development', 1),
  ('Digital Marketing Manager',   'BrandBoost Agency',   'Karachi, PK',  0, 'full-time',  60000,  85000, 'Marketing',   1),
  ('Data Scientist',              'InsightAI Labs',      'Remote',       1, 'full-time', 100000, 140000, 'Data',        1),
  ('Product Manager',             'ScaleUp Inc',         'Islamabad, PK',0, 'full-time',  85000, 115000, 'Management',  0),
  ('Mobile Developer (React Native)','AppForge',         'Remote',       1, 'full-time',  75000, 105000, 'Development', 0);

-- ────────────────────────────────────────────────────────────
-- ADMIN USER SETUP
-- Password: Admin@123 (bcrypt hash)
-- Login kr k phir password change kar lena
-- ────────────────────────────────────────────────────────────
INSERT IGNORE INTO users (name, email, password_hash, role)
VALUES (
  'Admin',
  'admin@jobhive.com',
  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  'admin'
);
-- Note: Hash above is for password 'password' - ZAROOR change karo!
-- Apna admin banane k liye yeh PHP code chalao:
-- php -r "echo password_hash('tumhara_password', PASSWORD_BCRYPT, ['cost'=>12]);"
-- Phir us hash ko upar replace karo.

-- admin_replies table (reply feature k liye)
CREATE TABLE IF NOT EXISTS admin_replies (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id INT UNSIGNED NOT NULL,
  message        TEXT NOT NULL,
  sent_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_app (application_id)
) ENGINE=InnoDB;

-- ────────────────────────────────────────────────────────────
-- 9. PENDING JOBS  (user-submitted jobs, awaiting admin approval)
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pending_jobs (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  title         VARCHAR(200) NOT NULL,
  company       VARCHAR(200) NOT NULL,
  location      VARCHAR(200) DEFAULT NULL,
  is_remote     TINYINT(1)   NOT NULL DEFAULT 0,
  job_type      ENUM('full-time','part-time','freelance','internship','contract') NOT NULL DEFAULT 'full-time',
  salary_min    INT UNSIGNED DEFAULT NULL,
  salary_max    INT UNSIGNED DEFAULT NULL,
  category      VARCHAR(100) DEFAULT NULL,
  description   TEXT         DEFAULT NULL,
  requirements  TEXT         DEFAULT NULL,
  status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  admin_note    VARCHAR(500) DEFAULT NULL,
  submitted_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at   DATETIME     DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_status  (status),
  INDEX idx_user    (user_id)
) ENGINE=InnoDB;

-- ── Seed Jobs (demo data for admin panel) ──────────────────────
INSERT IGNORE INTO jobs (id, title, company, location, is_remote, job_type, salary_min, salary_max, salary_currency, description, requirements, category, is_featured, is_active) VALUES
(1, 'Senior UI/UX Designer',       'PixelCraft Studio',   'Remote',          1, 'full-time',  80000,  110000, 'USD', 'Lead design for our SaaS product suite.',        'Figma, 4+ yrs experience, portfolio required.',   'Design',     1, 1),
(2, 'Full Stack Developer',         'NexaTech Solutions',  'Lahore, PK',      0, 'full-time',  90000,  130000, 'USD', 'Build and maintain scalable web applications.',   'React, Node.js, PostgreSQL, 3+ yrs.',             'Technology', 1, 1),
(3, 'Digital Marketing Manager',    'BrandBoost Agency',   'Karachi, PK',     0, 'full-time',  60000,   85000, 'USD', 'Own digital campaigns across all channels.',     'SEO, SEM, Google Ads, 3+ yrs.',                   'Marketing',  0, 1),
(4, 'Data Scientist',               'InsightAI Labs',      'Remote',          1, 'full-time', 100000,  140000, 'USD', 'Build ML models and analyse large datasets.',    'Python, TensorFlow, SQL, 4+ yrs.',                'Technology', 1, 1),
(5, 'Product Manager',              'BuildRight Corp',     'Islamabad, PK',   0, 'full-time',  85000,  120000, 'USD', 'Own product roadmap for our core platform.',     'Agile, stakeholder management, 5+ yrs.',          'Management', 0, 1),
(6, 'Cloud DevOps Engineer',        'SkyOps Infra',        'Remote',          1, 'contract',   95000,  135000, 'USD', 'Manage CI/CD pipelines and cloud infrastructure.','AWS, Terraform, Docker, Kubernetes, 4+ yrs.',     'Technology', 1, 1),
(7, 'React Native Developer',       'AppForge PK',         'Remote',          1, 'full-time',  70000,  100000, 'USD', 'Build cross-platform mobile apps.',              'React Native, TypeScript, REST APIs, 3+ yrs.',    'Technology', 0, 1),
(8, 'Financial Analyst',            'PakFinance Group',    'Karachi, PK',     0, 'full-time',  50000,   70000, 'USD', 'Analyse financial data and prepare reports.',    'Excel, accounting principles, CFA preferred.',    'Finance',    0, 1),
(9, 'Graphic Designer',             'CreativeHub',         'Remote',          1, 'freelance',  30000,   50000, 'USD', 'Create visual assets for digital campaigns.',    'Adobe Suite, branding, 2+ yrs.',                  'Design',     0, 1),
(10,'Backend Engineer (Python)',    'DataStream PK',       'Remote',          1, 'full-time',  75000,  110000, 'USD', 'Design and maintain RESTful APIs.',              'Python, Django/FastAPI, PostgreSQL, 3+ yrs.',     'Technology', 1, 1);

-- ════════════════════════════════════════════════════
--  SkillConnect – Complete Database Schema
--  Run this in phpMyAdmin or MySQL CLI
--  Database: skillconnect
-- ════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS skillconnect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE skillconnect;

-- ── USERS ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100)  NOT NULL,
    email           VARCHAR(150)  NOT NULL UNIQUE,
    password        VARCHAR(255)  NOT NULL,
    phone           VARCHAR(20)   DEFAULT NULL,
    role            ENUM('worker','client','admin') DEFAULT 'worker',
    location        VARCHAR(150)  DEFAULT NULL,
    bio             TEXT          DEFAULT NULL,
    skills          TEXT          DEFAULT NULL,
    education       TEXT          DEFAULT NULL,
    experience      TEXT          DEFAULT NULL,
    profile_pic     VARCHAR(255)  DEFAULT NULL,
    show_phone      TINYINT(1)    DEFAULT 0,
    show_email      TINYINT(1)    DEFAULT 0,
    show_location   TINYINT(1)    DEFAULT 1,
    profile_public  TINYINT(1)    DEFAULT 1,
    created_at      DATETIME      DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── JOBS ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS jobs (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    client_id        INT           NOT NULL,
    title            VARCHAR(200)  NOT NULL,
    description      TEXT          DEFAULT NULL,
    skills_required  TEXT          DEFAULT NULL,
    budget           DECIMAL(10,2) DEFAULT NULL,
    job_type         VARCHAR(50)   DEFAULT 'Full-time',  -- Full-time, Part-time, Freelance
    status           ENUM('open','closed','paused') DEFAULT 'open',
    created_at       DATETIME      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── APPLICATIONS ───────────────────────────────────
CREATE TABLE IF NOT EXISTS applications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    worker_id   INT  NOT NULL,
    job_id      INT  NOT NULL,
    status      ENUM('pending','shortlisted','accepted','rejected','interview scheduled')
                DEFAULT 'pending',
    cover_note  TEXT DEFAULT NULL,
    applied_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_application (worker_id, job_id),
    FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id)    REFERENCES jobs(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── SAVED JOBS ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS saved_jobs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    worker_id   INT  NOT NULL,
    job_id      INT  NOT NULL,
    saved_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_saved (worker_id, job_id),
    FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id)    REFERENCES jobs(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── CONNECTIONS (Worker ↔ Client Network) ──────────
CREATE TABLE IF NOT EXISTS connections (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    worker_id   INT  NOT NULL,
    client_id   INT  NOT NULL,
    status      ENUM('pending','accepted','rejected') DEFAULT 'pending',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_conn (worker_id, client_id),
    FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── POSTS (Portfolio) ──────────────────────────────
CREATE TABLE IF NOT EXISTS posts (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    worker_id   INT           NOT NULL,
    media_file  VARCHAR(255)  NOT NULL,
    caption     TEXT          DEFAULT NULL,
    post_type   ENUM('photo','video','reel') DEFAULT 'photo',
    is_video    TINYINT(1)    DEFAULT 0,
    likes       INT           DEFAULT 0,
    views       INT           DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── POST LIKES ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS post_likes (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    post_id  INT NOT NULL,
    user_id  INT NOT NULL,
    UNIQUE KEY unique_like (post_id, user_id),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── NOTIFICATIONS ──────────────────────────────────
CREATE TABLE IF NOT EXISTS notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT           NOT NULL,
    type        VARCHAR(50)   DEFAULT 'general',
    -- Types: job_alert, app_viewed, app_accepted, app_rejected,
    --        shortlisted, interview, message, connection, review
    message     TEXT          NOT NULL,
    is_read     TINYINT(1)    DEFAULT 0,
    link        VARCHAR(255)  DEFAULT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── MESSAGES (Chat) ────────────────────────────────
CREATE TABLE IF NOT EXISTS messages (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    sender_id    INT  NOT NULL,
    receiver_id  INT  NOT NULL,
    message      TEXT NOT NULL,
    is_read      TINYINT(1) DEFAULT 0,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id)   REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── RATINGS & REVIEWS ──────────────────────────────
CREATE TABLE IF NOT EXISTS ratings (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    worker_id   INT         NOT NULL,
    client_id   INT         NOT NULL,
    stars       TINYINT(1)  NOT NULL CHECK (stars BETWEEN 1 AND 5),
    review      TEXT        DEFAULT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_rating (worker_id, client_id),
    FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── WORKER SKILLS (Skill Progress) ─────────────────
CREATE TABLE IF NOT EXISTS worker_skills (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    worker_id    INT          NOT NULL,
    skill_name   VARCHAR(100) NOT NULL,
    progress     TINYINT      DEFAULT 50 CHECK (progress BETWEEN 0 AND 100),
    FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 
 


-- ── POST COMMENTS ──────────────────────────────────
CREATE TABLE IF NOT EXISTS post_comments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    post_id     INT  NOT NULL,
    user_id     INT  NOT NULL,
    comment     TEXT NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── POST VIEWS (track unique views) ────────────────
CREATE TABLE IF NOT EXISTS post_views (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    post_id  INT NOT NULL,
    user_id  INT NOT NULL,
    viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_view (post_id, user_id),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;


-- ── MIGRATION: Add new tables if upgrading from old version ──
-- Run these if you already have the database set up:
-- CREATE TABLE IF NOT EXISTS post_comments (id INT AUTO_INCREMENT PRIMARY KEY, post_id INT NOT NULL, user_id INT NOT NULL, comment TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB;
-- CREATE TABLE IF NOT EXISTS post_views (id INT AUTO_INCREMENT PRIMARY KEY, post_id INT NOT NULL, user_id INT NOT NULL, viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY unique_view (post_id, user_id), FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB;

ALTER TABLE jobs 
ADD COLUMN timing VARCHAR(20) NOT NULL DEFAULT 'Flexible' 
AFTER job_type;
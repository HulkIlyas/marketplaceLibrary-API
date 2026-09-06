-- ============================================
-- MARKETPLACE LIBRARY DATABASE
-- ============================================

CREATE DATABASE IF NOT EXISTS marketplace_library
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE marketplace_library;


-- ============================================
-- USERS
-- ============================================

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(100) NOT NULL,

    email VARCHAR(255) NOT NULL UNIQUE,

    -- This will contain password_hash(), NEVER plain text
    password VARCHAR(255) NOT NULL,

    -- Administrator can enable/disable the account
    is_active BOOLEAN NOT NULL DEFAULT TRUE,

    -- NULL = email not verified
    -- Date = email successfully verified
    email_verified_at DATETIME NULL,

    -- Email verification token
    email_verification_token VARCHAR(255) NULL,

    -- Verification token expiration
    email_verification_expires_at DATETIME NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
);


-- ============================================
-- BOOKS
-- ============================================

CREATE TABLE IF NOT EXISTS books (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    title VARCHAR(255) NOT NULL,

    author VARCHAR(150) NOT NULL,

    owner_id INT UNSIGNED NOT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_books_owner
        FOREIGN KEY (owner_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);


-- ============================================
-- INDEXES
-- ============================================

CREATE INDEX idx_users_email
    ON users(email);

CREATE INDEX idx_users_verification_token
    ON users(email_verification_token);

CREATE INDEX idx_books_owner
    ON books(owner_id);
-- database.sql (full, updated)
CREATE DATABASE IF NOT EXISTS telegram_bot_db;
USE telegram_bot_db;

-- Users table: Stores user info and balance
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    telegram_id BIGINT UNIQUE NOT NULL,
    username VARCHAR(255),
    first_name VARCHAR(255),
    referral_code VARCHAR(50) UNIQUE,
    referred_by BIGINT DEFAULT NULL,
    balance DECIMAL(10, 2) DEFAULT 0.00,
    ton_address VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Referrals table: Tracks referral links
CREATE TABLE IF NOT EXISTS referrals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    referrer_id BIGINT NOT NULL,
    referred_id BIGINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (referrer_id) REFERENCES users(telegram_id),
    FOREIGN KEY (referred_id) REFERENCES users(telegram_id)
);

-- Ad watches: Tracks ad viewing history
CREATE TABLE IF NOT EXISTS ad_watches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    watched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(telegram_id),
    INDEX idx_user_watched (user_id, watched_at) -- For faster cooldown checks
);

-- Daily rewards: Tracks daily claims
CREATE TABLE IF NOT EXISTS daily_rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    claimed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(telegram_id),
    UNIQUE KEY unique_claim_per_day (user_id, DATE(claimed_at))  -- One per day
);

-- Transactions table: Logs balance changes
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    type ENUM('referral', 'ad_watch', 'withdraw', 'welcome_bonus', 'daily_reward', 'referral_tier_bonus') NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(telegram_id)
);

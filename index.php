<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Bot configuration
$bot_token = getenv('BOT_TOKEN');
if (!$bot_token) {
    http_response_code(500);
    exit("❌ BOT_TOKEN not set");
}

define('BOT_TOKEN', $bot_token);
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
define('DB_FILE', '/var/www/html/bot.db');
define('ERROR_LOG', '/var/www/html/error.log');

// TON Rewards
define('AD_REWARD', 0.0001);
define('REF_REWARD', 0.0005);
define('MIN_WITHDRAW_REF', 5);
define('MIN_WITHDRAW_AMOUNT', 0.01);
define('AD_COOLDOWN', 10);
define('DAILY_AD_LIMIT', 100);

// Dosya oluşturma ve izin kontrolü
if (!file_exists(DB_FILE)) {
    touch(DB_FILE);
    chmod(DB_FILE, 0664); // rw-rw-r--
    if (function_exists('chown')) {
        chown(DB_FILE, 'www-data');
        chgrp(DB_FILE, 'www-data');
    }
}
if (!file_exists(ERROR_LOG)) {
    touch(ERROR_LOG);
    chmod(ERROR_LOG, 0664); // rw-rw-r--
    if (function_exists('chown')) {
        chown(ERROR_LOG, 'www-data');
        chgrp(ERROR_LOG, 'www-data');
    }
}
if (!is_writable(dirname(DB_FILE)) || !is_writable(dirname(ERROR_LOG))) {
    file_put_contents(ERROR_LOG, "Directory /var/www/html is not writable by PHP.\n", FILE_APPEND);
}

// Initialize error log
function logError($message) {
    file_put_contents(ERROR_LOG, date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND | LOCK_EX);
}

// Initialize SQLite database
function initDatabase() {
    try {
        $db = new PDO('sqlite:' . DB_FILE);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $db->exec("PRAGMA journal_mode=WAL;");
        $db->exec("PRAGMA synchronous=NORMAL;");
        $db->exec("PRAGMA cache_size=-10000;");
        $db->exec("PRAGMA auto_vacuum=1;");

        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                chat_id BIGINT PRIMARY KEY,
                balance DECIMAL(8,4) DEFAULT 0,
                referrals INTEGER DEFAULT 0,
                ref_code VARCHAR(10) UNIQUE,
                last_ad_watch INTEGER DEFAULT 0,
                ads_watched_today INTEGER DEFAULT 0,
                last_daily_reset VARCHAR(10),
                ton_address VARCHAR(255),
                total_earned DECIMAL(8,4) DEFAULT 0,
                created_at INTEGER,
                referred_by BIGINT,
                username VARCHAR(50),
                awaiting_ton_address BOOLEAN DEFAULT 0,
                ton_address_temp VARCHAR(255)
            )
        ");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_chat_id ON users(chat_id);");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_ref_code ON users(ref_code);");

        $db->exec("
            CREATE TABLE IF NOT EXISTS referral_list (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                referrer_id BIGINT,
                referred_id BIGINT,
                username VARCHAR(50),
                joined_at INTEGER,
                earned_from DECIMAL(8,4),
                FOREIGN KEY (referrer_id) REFERENCES users(chat_id),
                FOREIGN KEY (referred_id) REFERENCES users(chat_id)
            )
        ");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_referrer_id ON referral_list(referrer_id);");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_referred_id ON referral_list(referred_id);");

        return $db;
    } catch (Exception $e) {
        logError("Database init error: " . $e->getMessage());
        exit("Database error");
    }
}

function loadUser($db, $chat_id) {
    if (extension_loaded('apcu')) {
        $cache_key = "user_$chat_id";
        $user = apcu_fetch($cache_key);
        if ($user === false) {
            $stmt = $db->prepare("SELECT * FROM users WHERE chat_id = ?");
            $stmt->execute([$chat_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            apcu_store($cache_key, $user, 300); // 5 dakika önbellek
        }
        return $user;
    } else {
        $stmt = $db->prepare("SELECT * FROM users WHERE chat_id = ?");
        $stmt->execute([$chat_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

function saveUser($db, $chat_id, $data) {
    $stmt = $db->prepare("
        INSERT OR REPLACE INTO users (
            chat_id, balance, referrals, ref_code, last_ad_watch, ads_watched_today,
            last_daily_reset, ton_address, total_earned, created_at, referred_by, username,
            awaiting_ton_address, ton_address_temp
        ) VALUES (
            :chat_id, :balance, :referrals, :ref_code, :last_ad_watch, :ads_watched_today,
            :last_daily_reset, :ton_address, :total_earned, :created_at, :referred_by, :username,
            :awaiting_ton_address, :ton_address_temp
        )
    ");
    return $stmt->execute($data);
}

function resetDailyLimits($db) {
    $today = date('Y-m-d');
    $stmt = $db->query("SELECT chat_id, last_daily_reset FROM users WHERE last_daily_reset != '$today'");
    $users_to_reset = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $db->beginTransaction();
    try {
        foreach ($users_to_reset as $user) {
            $db->prepare("UPDATE users SET ads_watched_today = 0, last_daily_reset = ? WHERE chat_id = ?")
               ->execute([$today, $user['chat_id']]);
        }
        $db->commit();
        if (!empty($users_to_reset)) {
            logError("Daily limits reset for " . count($users_to_reset) . " users");
        }
    } catch (Exception $e) {
        $db->rollBack();
        logError("Daily limits reset error: " . $e->getMessage());
    }
}

function sendMessage($chat_id, $text, $keyboard = null) {
    $params = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($keyboard) $params['reply_markup'] = json_encode($keyboard);
    $url = API_URL . 'sendMessage?' . http_build_query($params);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    if ($result === false) {
        logError("Failed to send message to $chat_id: " . curl_error($ch));
    }
    curl_close($ch);
    return $result !== false;
}

function editMessageText($chat_id, $message_id, $text, $keyboard = null) {
    $params = ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($keyboard) $params['reply_markup'] = json_encode($keyboard);
    $url = API_URL . 'editMessageText?' . http_build_query($params);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    if ($result === false) {
        logError("Failed to edit message for $chat_id, message_id: $message_id: " . curl_error($ch));
    }
    curl_close($ch);
    return $result !== false;
}

function generateRefCode($chat_id) {
    return 'TAK' . substr(md5($chat_id), 0, 7); // Her kullanıcıya özel benzersiz kod
}

function getMainKeyboard() {
    return [
        'inline_keyboard' => [
            [['text' => '💰 Earn TON', 'callback_data' => 'earn'], ['text' => '💳 Balance', 'callback_data' => 'balance']],
            [['text' => '👥 Referrals', 'callback_data' => 'referrals'], ['text' => '🏧 Withdraw', 'callback_data' => 'withdraw']]
        ]
    ];
}

function getEarnKeyboard() {
    $webapp_url = "https://takoniads.onrender.com/webapp.html";
    return [
        'inline_keyboard' => [
            [['text' => '📱 Watch Ad (' . AD_REWARD . ' TON)', 'web_app' => ['url' => $webapp_url]]],
            [['text' => '🔄 Check Balance', 'callback_data' => 'balance']],
            [['text' => '⬅️ Back to Main', 'callback_data' => 'main_menu']]
        ]
    ];
}

function getBalanceKeyboard() {
    return [
        'inline_keyboard' => [
            [['text' => '📱 Watch Another Ad', 'callback_data' => 'earn']],
            [['text' => '🔄 Refresh Balance', 'callback_data' => 'balance']],
            [['text' => '⬅️ Back to Main', 'callback_data' => 'main_menu']]
        ]
    ];
}

function getReferralsKeyboard() {
    return [
        'inline_keyboard' => [
            [['text' => '📤 Share Referral', 'callback_data' => 'share_referral']],
            [['text' => '🔄 Refresh', 'callback_data' => 'referrals']],
            [['text' => '⬅️ Back to Main', 'callback_data' => 'main_menu']]
        ]
    ];
}

function getWithdrawKeyboard($has_address = false) {
    $keyboard = [
        'inline_keyboard' => [
            [['text' => $has_address ? '🚀 Submit Withdrawal' : '💳 Enter TON Address', 'callback_data' => $has_address ? 'submit_withdrawal' : 'enter_ton_address']],
            [['text' => '⬅️ Back to Main', 'callback_data' => 'main_menu']]
        ]
    ];
    if ($has_address) {
        $keyboard['inline_keyboard'][0][] = ['text' => '🔄 Change Address', 'callback_data' => 'enter_ton_address'];
    }
    return $keyboard;
}

function getSaveAddressKeyboard() {
    return [
        'inline_keyboard' => [
            [['text' => '✅ Confirm Address', 'callback_data' => 'save_ton_address'], ['text' => '❌ Cancel', 'callback_data' => 'main_menu']]
        ]
    ];
}

function processUpdate($update) {
    $db = initDatabase();
    resetDailyLimits($db);
    
    // Handle Web App data (ad completion)
    if (isset($update['message']['web_app_data'])) {
        $chat_id = $update['message']['chat']['id'];
        $web_app_data = json_decode($update['message']['web_app_data']['data'], true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            logError("Invalid web app data JSON from $chat_id: " . json_last_error_msg());
            sendMessage($chat_id, "❌ Error processing ad data. Please try again.", getMainKeyboard());
            return;
        }
        
        logError("Web app data from $chat_id: " . json_encode($web_app_data));
        
        if (isset($web_app_data['action']) && $web_app_data['action'] === 'ad_completed' && isset($web_app_data['chat_id'], $web_app_data['reward'])) {
            $user = loadUser($db, $chat_id);
            if ($user) {
                $current_time = time();
                $ads_today = $user['ads_watched_today'] ?? 0;
                $last_ad_watch = $user['last_ad_watch'] ?? 0;
                
                if ($ads_today >= DAILY_AD_LIMIT) {
                    sendMessage($chat_id, "❌ You've reached the daily ad limit (" . DAILY_AD_LIMIT . "). Try again tomorrow!", getMainKeyboard());
                    return;
                }
                
                if ($current_time - $last_ad_watch < AD_COOLDOWN) {
                    sendMessage($chat_id, "⏳ Please wait " . (AD_COOLDOWN - ($current_time - $last_ad_watch)) . " seconds before watching another ad.", getMainKeyboard());
                    return;
                }
                
                $db->beginTransaction();
                try {
                    $new_balance = $user['balance'] + AD_REWARD;
                    $new_total_earned = $user['total_earned'] + AD_REWARD;
                    $new_ads_watched = $ads_today + 1;
                    
                    $db->prepare("UPDATE users SET balance = ?, total_earned = ?, ads_watched_today = ?, last_ad_watch = ? WHERE chat_id = ?")
                       ->execute([$new_balance, $new_total_earned, $new_ads_watched, $current_time, $chat_id]);
                    
                    $db->commit();
                    sendMessage($chat_id, "✅ Ad watched! You earned " . AD_REWARD . " TON. New balance: " . number_format($new_balance, 6) . " TON", getEarnKeyboard());
                    logError("Ad reward credited to $chat_id: " . AD_REWARD . " TON");
                } catch (Exception $e) {
                    $db->rollBack();
                    logError("Ad reward error for $chat_id: " . $e->getMessage());
                    sendMessage($chat_id, "❌ Failed to credit ad reward. Please try again.", getMainKeyboard());
                }
            }
        }
        return;
    }
    
    // Handle text messages
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $username = $message['chat']['username'] ?? 'Unknown';
        
        logError("Message from $chat_id: $text");
        
        $user = loadUser($db, $chat_id);
        if (!$user) {
            $ref_code = generateRefCode($chat_id);
            $user = [
                'chat_id' => $chat_id,
                'balance' => 0,
                'referrals' => 0,
                'ref_code' => $ref_code,
                'last_ad_watch' => 0,
                'ads_watched_today' => 0,
                'last_daily_reset' => date('Y-m-d'),
                'ton_address' => '',
                'total_earned' => 0,
                'created_at' => time(),
                'referred_by' => null,
                'username' => $username,
                'awaiting_ton_address' => 0,
                'ton_address_temp' => null
            ];
            saveUser($db, $chat_id, $user);
            logError("New user created: $chat_id with ref_code: $ref_code");
        }
        
        switch (true) {
            case strpos($text, '/start') === 0:
                $parts = explode(' ', $text);
                $ref_code_param = $parts[1] ?? null;
                $welcome = "🚀 <b>Welcome to TAKONI ADS!</b>\n\n";
                if ($ref_code_param && $ref_code_param !== $user['ref_code'] && !$user['referred_by']) {
                    $stmt = $db->prepare("SELECT chat_id, username, referrals, balance, total_earned FROM users WHERE ref_code = ? AND chat_id != ?");
                    $stmt->execute([$ref_code_param, $chat_id]);
                    $referrer = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($referrer) {
                        $referrer_id = $referrer['chat_id'];
                        $db->beginTransaction();
                        try {
                            $db->prepare("UPDATE users SET referred_by = ? WHERE chat_id = ?")
                               ->execute([$referrer_id, $chat_id]);
                            $new_referrals = $referrer['referrals'] + 1;
                            $new_balance = $referrer['balance'] + REF_REWARD;
                            $new_total_earned = $referrer['total_earned'] + REF_REWARD;
                            $db->prepare("UPDATE users SET referrals = ?, balance = ?, total_earned = ? WHERE chat_id = ?")
                               ->execute([$new_referrals, $new_balance, $new_total_earned, $referrer_id]);
                            $db->prepare("INSERT INTO referral_list (referrer_id, referred_id, username, joined_at, earned_from) VALUES (?, ?, ?, ?, ?)")
                               ->execute([$referrer_id, $chat_id, $username, time(), REF_REWARD]);
                            $db->commit();
                            $ref_message = "🎉 <b>New Referral!</b>\n👤 New user @$username joined!\n💰 Earned: " . REF_REWARD . " TON\n👥 Total: $new_referrals\n💳 Balance: " . number_format($new_balance, 6) . " TON";
                            sendMessage($referrer_id, $ref_message, getReferralsKeyboard());
                            $welcome = "🎉 <b>Welcome via Referral!</b>\nYou joined using @" . ($referrer['username'] ?? 'User') . "'s link!\n\n";
                        } catch (Exception $e) {
                            $db->rollBack();
                            logError("Referral error: " . $e->getMessage());
                        }
                    } else {
                        $welcome .= "Invalid referral code.\n\n";
                    }
                }
                $ref_link = "https://t.me/takoniAdsBot?start=" . $user['ref_code'];
                $welcome .= "💰 Earn TON by watching ads\n👥 Invite friends for bonus TON\n🏧 Withdraw to TON wallet\n\n🔗 Your referral link:\n<code>$ref_link</code>\n\n📊 Rewards:\n• Ad: " . AD_REWARD . " TON\n• Referral: " . REF_REWARD . " TON\n\n⚠️ Daily Limit: " . DAILY_AD_LIMIT . " ads\n⚠️ Withdrawal: " . MIN_WITHDRAW_REF . " referrals";
                sendMessage($chat_id, $welcome, getMainKeyboard());
                break;
            case $text === '/balance':
                $balance = $user['balance'] ?? 0;
                $response = "💳 <b>Your Balance:</b> " . number_format($balance, 6) . " TON";
                sendMessage($chat_id, $response, getBalanceKeyboard());
                break;
            case $text === '/withdraw':
                $balance = $user['balance'] ?? 0;
                $response = "🏧 <b>Withdraw:</b> " . number_format($balance, 6) . " TON";
                sendMessage($chat_id, $response, getWithdrawKeyboard());
                break;
            case $text === '/referral':
                $referrals = $user['referrals'] ?? 0;
                $response = "👥 <b>Your Referrals:</b> " . $referrals;
                sendMessage($chat_id, $response, getReferralsKeyboard());
                break;
            default:
                sendMessage($chat_id, "Unknown command. Use /start, /balance, /withdraw, or /referral.", getMainKeyboard());
                break;
        }
    } elseif (isset($update['callback_query'])) {
        $callback = $update['callback_query'];
        $chat_id = $callback['message']['chat']['id'];
        $message_id = $callback['message']['message_id'];
        $data = $callback['data'];
        
        logError("Callback from $chat_id: $data");
        $user = loadUser($db, $chat_id);
        
        if (!$user) {
            $ref_code = generateRefCode($chat_id);
            $user = [
                'chat_id' => $chat_id,
                'balance' => 0,
                'referrals' => 0,
                'ref_code' => $ref_code,
                'last_ad_watch' => 0,
                'ads_watched_today' => 0,
                'last_daily_reset' => date('Y-m-d'),
                'ton_address' => '',
                'total_earned' => 0,
                'created_at' => time(),
                'referred_by' => null,
                'username' => $callback['from']['username'] ?? 'Unknown',
                'awaiting_ton_address' => 0,
                'ton_address_temp' => null
            ];
            saveUser($db, $chat_id, $user);
        }
        
        switch ($data) {
            case 'earn':
                $ads_today = $user['ads_watched_today'] ?? 0;
                $ads_remaining = DAILY_AD_LIMIT - $ads_today;
                $response = "💰 <b>Earn TON</b>\n\n📱 Watch Ads & Earn " . AD_REWARD . " TON\n🎬 How to earn:\n1. Click 'Watch Ad'\n2. Watch fully\n3. Get " . AD_REWARD . " TON\n\n⏰ Cooldown: " . AD_COOLDOWN . "s\n📊 Progress: $ads_today/$DAILY_AD_LIMIT ads\n👥 Bonus: " . REF_REWARD . " TON/referral";
                editMessageText($chat_id, $message_id, $response, getEarnKeyboard());
                break;
            case 'balance':
                $balance = $user['balance'] ?? 0;
                $referrals = $user['referrals'] ?? 0;
                $total_earned = $user['total_earned'] ?? 0;
                $ads_today = $user['ads_watched_today'] ?? 0;
                $ref_needed = max(0, MIN_WITHDRAW_REF - $referrals);
                $response = "💳 <b>Your TON Balance</b>\n💰 Balance: " . number_format($balance, 6) . " TON\n👥 Referrals: $referrals/$MIN_WITHDRAW_REF\n📊 Ads Today: $ads_today/$DAILY_AD_LIMIT\n🏆 Total Earned: " . number_format($total_earned, 6) . " TON\n";
                $response .= $referrals < MIN_WITHDRAW_REF ? "❌ Need $ref_needed more referrals to withdraw\n" : "✅ Ready to withdraw!\n";
                $response .= "🔗 TON Address: " . ($user['ton_address'] ?: 'Not set');
                editMessageText($chat_id, $message_id, $response, getBalanceKeyboard());
                break;
            case 'referrals':
                $ref_code = $user['ref_code'];
                $referrals = $user['referrals'] ?? 0;
                $ref_earnings = $referrals * REF_REWARD;
                $ref_needed = max(0, MIN_WITHDRAW_REF - $referrals);
                $ref_link = "https://t.me/takoniAdsBot?start=" . $ref_code;
                $response = "👥 <b>Referral System</b>\n🔗 Link: <code>$ref_link</code>\n📊 Stats:\n• Referrals: $referrals/$MIN_WITHDRAW_REF\n• Earnings: " . number_format($ref_earnings, 6) . " TON\n• Need: $ref_needed more\n💰 Earn " . REF_REWARD . " TON per referral";
                editMessageText($chat_id, $message_id, $response, getReferralsKeyboard());
                break;
            case 'share_referral':
                $ref_code = $user['ref_code'];
                $ref_link = "https://t.me/takoniAdsBot?start=" . $ref_code;
                $share_text = "🎉 Join TAKONI ADS!\n💰 Earn " . AD_REWARD . " TON per ad\n👥 Use my link: $ref_link\n🚀 Start now!";
                $keyboard = [
                    'inline_keyboard' => [
                        [['text' => '📤 Share', 'url' => "https://t.me/share/url?url=" . urlencode($ref_link) . "&text=" . urlencode($share_text)]],
                        [['text' => '⬅️ Back', 'callback_data' => 'referrals']]
                    ]
                ];
                editMessageText($chat_id, $message_id, "📤 Share your referral link:", $keyboard);
                break;
            case 'withdraw':
                $balance = $user['balance'] ?? 0;
                $ton_address = $user['ton_address'] ?? '';
                $referrals = $user['referrals'] ?? 0;
                $response = "🏧 <b>Withdraw TON</b>\n💰 Balance: " . number_format($balance, 6) . " TON\n👥 Referrals: $referrals/$MIN_WITHDRAW_REF\n🔗 Address: " . ($ton_address ? "<code>$ton_address</code>" : "Not set") . "\n";
                if ($balance < MIN_WITHDRAW_AMOUNT || $referrals < MIN_WITHDRAW_REF || !$ton_address) {
                    $errors = [];
                    if ($balance < MIN_WITHDRAW_AMOUNT) $errors[] = "❌ Min: " . MIN_WITHDRAW_AMOUNT . " TON";
                    if ($referrals < MIN_WITHDRAW_REF) $errors[] = "❌ Need " . (MIN_WITHDRAW_REF - $referrals) . " referrals";
                    if (!$ton_address) $errors[] = "❌ Set TON address";
                    $response .= implode("\n", $errors);
                } else {
                    $response .= "✅ Ready to withdraw!";
                }
                editMessageText($chat_id, $message_id, $response, getWithdrawKeyboard($ton_address !== ''));
                break;
            case 'enter_ton_address':
                $db->prepare("UPDATE users SET awaiting_ton_address = 1 WHERE chat_id = ?")
                   ->execute([$chat_id]);
                sendMessage($chat_id, "💳 Enter your TON address:", getMainKeyboard());
                break;
            case 'save_ton_address':
                $stmt = $db->prepare("SELECT ton_address_temp FROM users WHERE chat_id = ?");
                $stmt->execute([$chat_id]);
                $temp_address = $stmt->fetchColumn();
                if ($temp_address) {
                    $db->prepare("UPDATE users SET ton_address = ?, ton_address_temp = NULL WHERE chat_id = ?")
                       ->execute([$temp_address, $chat_id]);
                    sendMessage($chat_id, "✅ TON address saved!", getMainKeyboard());
                } else {
                    sendMessage($chat_id, "❌ No address provided.", getMainKeyboard());
                }
                break;
            case 'submit_withdrawal':
                $balance = $user['balance'] ?? 0;
                $referrals = $user['referrals'] ?? 0;
                $ton_address = $user['ton_address'] ?? '';
                if ($balance >= MIN_WITHDRAW_AMOUNT && $referrals >= MIN_WITHDRAW_REF && $ton_address) {
                    $db->beginTransaction();
                    try {
                        $db->prepare("UPDATE users SET balance = 0 WHERE chat_id = ?")
                           ->execute([$chat_id]);
                        $db->commit();
                        $response = "🏧 Withdrawal requested!\nAmount: " . number_format($balance, 6) . " TON\nTo: <code>$ton_address</code>";
                        sendMessage($chat_id, $response, getMainKeyboard());
                        logError("Withdrawal by $chat_id: $balance TON to $ton_address");
                    } catch (Exception $e) {
                        $db->rollBack();
                        logError("Withdrawal error: " . $e->getMessage());
                        sendMessage($chat_id, "❌ Withdrawal failed.", getMainKeyboard());
                    }
                } else {
                    $response = "❌ Cannot withdraw:\n";
                    if ($balance < MIN_WITHDRAW_AMOUNT) $response .= "• Min: " . MIN_WITHDRAW_AMOUNT . " TON\n";
                    if ($referrals < MIN_WITHDRAW_REF) $response .= "• Need " . (MIN_WITHDRAW_REF - $referrals) . " referrals\n";
                    if (!$ton_address) $response .= "• No address\n";
                    sendMessage($chat_id, $response, getWithdrawKeyboard($ton_address !== ''));
                }
                break;
            case 'main_menu':
                $response = "🚀 <b>TAKONI ADS</b>\n💰 Earn TON\n👥 Invite friends\n🏧 Withdraw\nSelect an option:";
                editMessageText($chat_id, $message_id, $response, getMainKeyboard());
                break;
        }
    }
}

// Handle incoming webhook
$input = file_get_contents('php://input');
$update = json_decode($input, true);
if ($update === null && $input !== '') {
    logError("Invalid JSON input: $input, Error: " . json_last_error_msg());
    http_response_code(400);
    exit("Invalid request");
}

if ($update) {
    processUpdate($update);
} else {
    http_response_code(200);
    echo "OK";
}

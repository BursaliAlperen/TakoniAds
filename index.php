<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$bot_token = getenv('BOT_TOKEN');
if (!$bot_token) die("❌ BOT_TOKEN not set");

define('BOT_TOKEN', $bot_token);
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
define('DB_FILE', 'takoni_ads.db');
define('ERROR_LOG', 'error.log');
define('AD_REWARD', 0.0005); // Güncellendi: 0.0005 TON per ad
define('REF_REWARD', 0.02); // Güncellendi: 0.02 TON per referral
define('MIN_WITHDRAW_REF', 5);
define('MIN_WITHDRAW_AMOUNT', 1); // Güncellendi: 1 TON minimum
define('AD_COOLDOWN', 10);
define('DAILY_AD_LIMIT', 100);
define('CHANNEL_USERNAME', '@TakoniFinance');
define('CHANNEL_ID', '-1002855918077');
define('CHANNEL_URL', 'https://t.me/TakoniFinance');
define('BOT_USERNAME', 'takoniAdsBot');

// Database Class
class Database {
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO("sqlite:" . DB_FILE);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->initTables();
        } catch (PDOException $e) {
            $this->logError("DB Error: " . $e->getMessage());
        }
    }
    
    private function logError($message) {
        @file_put_contents(ERROR_LOG, date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
    }
    
    private function initTables() {
        $tables = [
            "CREATE TABLE IF NOT EXISTS users (
                chat_id INTEGER PRIMARY KEY, username TEXT, balance REAL DEFAULT 0,
                total_referrals INTEGER DEFAULT 0, ref_code TEXT UNIQUE, last_ad_watch INTEGER DEFAULT 0,
                ads_watched_today INTEGER DEFAULT 0, last_daily_reset TEXT, ton_address TEXT DEFAULT '',
                total_earned REAL DEFAULT 0, created_at INTEGER, referred_by TEXT,
                max_balance REAL DEFAULT 0, channel_joined BOOLEAN DEFAULT 0,
                updated_at INTEGER, awaiting_ton_address BOOLEAN DEFAULT 0
            )",
            "CREATE TABLE IF NOT EXISTS referrals (
                id INTEGER PRIMARY KEY AUTOINCREMENT, referrer_chat_id INTEGER,
                referred_chat_id INTEGER, referred_username TEXT, level INTEGER DEFAULT 1,
                earned_amount REAL DEFAULT 0, created_at INTEGER,
                FOREIGN KEY (referrer_chat_id) REFERENCES users (chat_id),
                FOREIGN KEY (referred_chat_id) REFERENCES users (chat_id)
            )",
            "CREATE TABLE IF NOT EXISTS transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT, chat_id INTEGER, type TEXT,
                amount REAL, description TEXT, created_at INTEGER,
                FOREIGN KEY (chat_id) REFERENCES users (chat_id)
            )",
            "CREATE TABLE IF NOT EXISTS withdrawals (
                id INTEGER PRIMARY KEY AUTOINCREMENT, chat_id INTEGER, amount REAL,
                ton_address TEXT, status TEXT DEFAULT 'pending', created_at INTEGER,
                processed_at INTEGER, FOREIGN KEY (chat_id) REFERENCES users (chat_id)
            )",
            "CREATE TABLE IF NOT EXISTS ad_watches (
                id INTEGER PRIMARY KEY AUTOINCREMENT, chat_id INTEGER, reward_amount REAL,
                watch_time INTEGER, created_at INTEGER, FOREIGN KEY (chat_id) REFERENCES users (chat_id)
            )"
        ];
        
        foreach ($tables as $table) {
            $this->pdo->exec($table);
        }
    }
    
    public function getUser($chat_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE chat_id = ?");
        $stmt->execute([$chat_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function createUser($user_data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO users (chat_id, username, balance, total_referrals, ref_code, 
            last_ad_watch, ads_watched_today, last_daily_reset, ton_address, 
            total_earned, created_at, referred_by, max_balance, channel_joined, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $user_data['chat_id'], $user_data['username'] ?? 'Unknown',
            $user_data['balance'] ?? 0, $user_data['total_referrals'] ?? 0,
            $user_data['ref_code'], $user_data['last_ad_watch'] ?? 0,
            $user_data['ads_watched_today'] ?? 0, $user_data['last_daily_reset'] ?? date('Y-m-d'),
            $user_data['ton_address'] ?? '', $user_data['total_earned'] ?? 0,
            $user_data['created_at'] ?? time(), $user_data['referred_by'] ?? null,
            $user_data['max_balance'] ?? 0, $user_data['channel_joined'] ?? 0, time()
        ]);
    }
    
    public function updateUser($chat_id, $update_data) {
        $update_data['updated_at'] = time();
        $set_clause = implode(', ', array_map(function($key) {
            return "$key = ?";
        }, array_keys($update_data)));
        
        $values = array_values($update_data);
        $values[] = $chat_id;
        
        $stmt = $this->pdo->prepare("UPDATE users SET $set_clause WHERE chat_id = ?");
        return $stmt->execute($values);
    }
    
    public function addTransaction($chat_id, $type, $amount, $description = '') {
        $stmt = $this->pdo->prepare("
            INSERT INTO transactions (chat_id, type, amount, description, created_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$chat_id, $type, $amount, $description, time()]);
    }
    
    public function addAdWatch($chat_id, $reward_amount) {
        $stmt = $this->pdo->prepare("
            INSERT INTO ad_watches (chat_id, reward_amount, watch_time, created_at)
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([$chat_id, $reward_amount, time(), time()]);
    }
    
    public function addReferral($referrer_chat_id, $referred_chat_id, $referred_username, $level = 1) {
        $stmt = $this->pdo->prepare("
            INSERT INTO referrals (referrer_chat_id, referred_chat_id, referred_username, level, created_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$referrer_chat_id, $referred_chat_id, $referred_username, $level, time()]);
    }
    
    public function getUserByRefCode($ref_code) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE ref_code = ?");
        $stmt->execute([$ref_code]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getReferralCount($chat_id) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM referrals WHERE referrer_chat_id = ?");
        $stmt->execute([$chat_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }
    
    public function getReferralHistory($chat_id, $limit = 50) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM referrals 
            WHERE referrer_chat_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$chat_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getTotalReferralStats($chat_id) {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_referrals,
                COUNT(CASE WHEN created_at >= ? THEN 1 END) as today_referrals,
                COUNT(CASE WHEN created_at >= ? THEN 1 END) as week_referrals
            FROM referrals 
            WHERE referrer_chat_id = ?
        ");
        
        $today = strtotime('today');
        $week_ago = strtotime('-7 days');
        
        $stmt->execute([$today, $week_ago, $chat_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getTodayAdWatches($chat_id) {
        $today = date('Y-m-d');
        $start_time = strtotime($today);
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count FROM ad_watches 
            WHERE chat_id = ? AND created_at >= ?
        ");
        $stmt->execute([$chat_id, $start_time]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }
    
    public function resetDailyLimits() {
        $today = date('Y-m-d');
        $stmt = $this->pdo->prepare("
            UPDATE users SET ads_watched_today = 0, last_daily_reset = ? 
            WHERE last_daily_reset != ? OR last_daily_reset IS NULL
        ");
        return $stmt->execute([$today, $today]);
    }
    
    public function getTopUsers($limit = 10) {
        $stmt = $this->pdo->prepare("
            SELECT username, balance, total_referrals, total_earned 
            FROM users ORDER BY balance DESC LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getTotalStats() {
        $stats = [];
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM users");
        $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM ad_watches");
        $stats['total_ads'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM withdrawals WHERE status = 'completed'");
        $stats['total_withdrawals'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        return $stats;
    }
}

// Helper Functions
function logError($message) {
    @file_put_contents(ERROR_LOG, date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

function sendMessage($chat_id, $text, $keyboard = null) {
    $params = array('chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML');
    if ($keyboard) $params['reply_markup'] = json_encode($keyboard);
    $url = API_URL . 'sendMessage?' . http_build_query($params);
    return @file_get_contents($url) !== false;
}

function editMessageText($chat_id, $message_id, $text, $keyboard = null) {
    $params = array('chat_id' => $chat_id, 'message_id' => $message_id, 'text' => $text, 'parse_mode' => 'HTML');
    if ($keyboard) $params['reply_markup'] = json_encode($keyboard);
    $url = API_URL . 'editMessageText?' . http_build_query($params);
    return @file_get_contents($url) !== false;
}

function generateRefCode($chat_id) {
    return 'TAK' . substr(md5($chat_id . time()), 0, 7);
}

function isUserInChannel($chat_id) {
    $method = 'getChatMember';
    $params = array('chat_id' => CHANNEL_ID, 'user_id' => $chat_id);
    $url = API_URL . $method . '?' . http_build_query($params);
    $response = @file_get_contents($url);
    
    if ($response === false) return false;
    
    $data = json_decode($response, true);
    if (isset($data['ok']) && $data['ok'] === true) {
        $status = $data['result']['status'];
        return in_array($status, ['member', 'administrator', 'creator', 'restricted']);
    }
    return false;
}

function isValidTONAddress($address) {
    $address = trim($address);
    $patterns = [
        '/^EQ[0-9a-zA-Z_-]{48}$/', '/^UQ[0-9a-zA-Z_-]{48}$/',
        '/^Ef[0-9a-zA-Z_-]{48}$/', '/^Uf[0-9a-zA-Z_-]{48}$/',
        '/^0:[0-9a-fA-F]{64}$/', '/^[0-9a-zA-Z_-]{48}$/',
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $address)) return true;
    }
    
    if (preg_match('/^EQ[a-zA-Z0-9_-]{44,50}$/', $address)) return true;
    if (preg_match('/^UQ[a-zA-Z0-9_-]{44,50}$/', $address)) return true;
    
    return false;
}

// Keyboards
function getMainKeyboard() {
    return array('inline_keyboard' => array(
        array(
            array('text' => '💰 Earn TON', 'callback_data' => 'earn'),
            array('text' => '💳 Balance', 'callback_data' => 'balance')
        ),
        array(
            array('text' => '👥 Referrals', 'callback_data' => 'referrals'),
            array('text' => '🏧 Withdraw', 'callback_data' => 'withdraw')
        ),
        array(
            array('text' => '📊 Statistics', 'callback_data' => 'statistics')
        )
    ));
}

function getEarnKeyboard() {
    $webapp_url = "https://takoniads.onrender.com/webapp.html";
    return array('inline_keyboard' => array(
        array(array('text' => '📱 Watch Ad (' . AD_REWARD . ' TON)', 'web_app' => array('url' => $webapp_url))),
        array(array('text' => '🔄 Check Balance', 'callback_data' => 'balance')),
        array(array('text' => '⬅️ Back to Main', 'callback_data' => 'main_menu'))
    ));
}

function getBalanceKeyboard() {
    return array('inline_keyboard' => array(
        array(array('text' => '📱 Watch Another Ad', 'callback_data' => 'earn')),
        array(array('text' => '🔄 Refresh Balance', 'callback_data' => 'balance')),
        array(array('text' => '⬅️ Back to Main', 'callback_data' => 'main_menu'))
    ));
}

function getReferralsKeyboard() {
    return array('inline_keyboard' => array(
        array(
            array('text' => '📤 Share Referral', 'callback_data' => 'share_referral'),
            array('text' => '📊 Full History', 'callback_data' => 'referral_history')
        ),
        array(
            array('text' => '🔄 Refresh', 'callback_data' => 'referrals'),
            array('text' => '⬅️ Back to Main', 'callback_data' => 'main_menu')
        )
    ));
}

function getWithdrawKeyboard($has_address = false) {
    if ($has_address) {
        return array('inline_keyboard' => array(
            array(array('text' => '🚀 Submit Withdrawal', 'callback_data' => 'submit_withdrawal')),
            array(array('text' => '✏️ Change Address', 'callback_data' => 'enter_ton_address')),
            array(array('text' => '⬅️ Back to Main', 'callback_data' => 'main_menu'))
        ));
    } else {
        return array('inline_keyboard' => array(
            array(array('text' => '💳 Enter TON Address', 'callback_data' => 'enter_ton_address')),
            array(array('text' => '⬅️ Back to Main', 'callback_data' => 'main_menu'))
        ));
    }
}

function getChannelJoinKeyboard() {
    return array('inline_keyboard' => array(
        array(array('text' => '📢 Join Channel', 'url' => CHANNEL_URL)),
        array(array('text' => '✅ I Joined', 'callback_data' => 'check_join'))
    ));
}

// GELİŞMİŞ REFERANS SİSTEMİ
function processReferralSystem($referrer_chat_id, $referred_chat_id, $referred_username) {
    global $db;
    
    // Referans sayısını al
    $current_ref_count = $db->getReferralCount($referrer_chat_id);
    $new_ref_count = $current_ref_count + 1;
    
    // 1. Seviye referansı kaydet
    $db->addReferral($referrer_chat_id, $referred_chat_id, $referred_username, 1);
    
    // Referans sayısını güncelle
    $db->updateUser($referrer_chat_id, ['total_referrals' => $new_ref_count]);
    
    // Ödül ver
    $referrer = $db->getUser($referrer_chat_id);
    $new_balance = $referrer['balance'] + REF_REWARD;
    $new_max_balance = max($referrer['max_balance'], $new_balance);
    
    $db->updateUser($referrer_chat_id, [
        'balance' => $new_balance,
        'max_balance' => $new_max_balance,
        'total_earned' => $referrer['total_earned'] + REF_REWARD
    ]);
    
    // Transaction kaydı
    $db->addTransaction($referrer_chat_id, 'referral', REF_REWARD, "Referral #" . $new_ref_count . ": @" . $referred_username);
    
    // DETAYLI REFERANS MESAJI
    $ref_message = "🎉 <b>NEW REFERRAL #" . $new_ref_count . "!</b>\n\n";
    $ref_message .= "👤 <b>New User:</b> @" . ($referred_username ?: 'Unknown') . "\n";
    $ref_message .= "💰 <b>Reward Received:</b> " . REF_REWARD . " TON\n";
    $ref_message .= "📊 <b>Total Referrals:</b> <b>" . $new_ref_count . "</b>\n";
    $ref_message .= "💳 <b>New Balance:</b> " . number_format($new_balance, 6) . " TON\n\n";
    
    // Özel mesajlar belirli sayılarda
    if ($new_ref_count == 1) {
        $ref_message .= "🎊 <b>First referral! Welcome to the team!</b>\n";
    } elseif ($new_ref_count == 5) {
        $ref_message .= "🔥 <b>5 referrals! You're on fire!</b>\n";
    } elseif ($new_ref_count == 10) {
        $ref_message .= "🚀 <b>10 referrals! Amazing growth!</b>\n";
    } elseif ($new_ref_count == 25) {
        $ref_message .= "🏆 <b>25 referrals! You're a superstar!</b>\n";
    } elseif ($new_ref_count == 50) {
        $ref_message .= "👑 <b>50 referrals! Legend status achieved!</b>\n";
    } elseif ($new_ref_count % 10 == 0) {
        $ref_message .= "⭐ <b>Milestone reached! Keep going!</b>\n";
    }
    
    $ref_message .= "\nKeep inviting to build your empire! 💪";
    
    sendMessage($referrer_chat_id, $ref_message);
    
    // Yeni kullanıcıya hoş geldin mesajı
    $welcome_message = "👋 <b>Welcome to Takoni Ads!</b>\n\n";
    $welcome_message .= "You were referred by @" . ($referrer['username'] ?: 'a friend') . "\n";
    $welcome_message .= "Start earning " . AD_REWARD . " TON per ad!\n\n";
    $welcome_message .= "Invite friends to earn " . REF_REWARD . " TON each!";
    
    sendMessage($referred_chat_id, $welcome_message, getMainKeyboard());
}

// Main Bot Functions
$db = new Database();

function showMainMenu($chat_id, $user) {
    $response = "🤖 <b>Welcome to Takoni Ads Bot!</b>\n\n";
    $response .= "💰 <b>Your Balance:</b> " . number_format($user['balance'], 6) . " TON\n";
    $response .= "👥 <b>Referrals:</b> " . $user['total_referrals'] . "\n\n";
    $response .= "🎯 <b>Available Actions:</b>\n";
    $response .= "• <b>Earn TON:</b> Watch ads and earn " . AD_REWARD . " TON each\n";
    $response .= "• <b>Referrals:</b> Earn " . REF_REWARD . " TON per referral\n";
    $response .= "• <b>Withdraw:</b> Minimum " . MIN_WITHDRAW_AMOUNT . " TON (" . MIN_WITHDRAW_REF . " refs required)\n\n";
    $response .= "🚀 <b>Start earning now!</b>";
    
    sendMessage($chat_id, $response, getMainKeyboard());
}

function processEnhancedStartCommand($chat_id, $text, $username) {
    global $db;
    
    $user = $db->getUser($chat_id);
    $ref_code = generateRefCode($chat_id);
    
    if (!$user) {
        $user_data = array(
            'chat_id' => $chat_id, 'username' => $username, 'balance' => 0,
            'total_referrals' => 0, 'ref_code' => $ref_code, 'last_ad_watch' => 0,
            'ads_watched_today' => 0, 'last_daily_reset' => date('Y-m-d'),
            'ton_address' => '', 'total_earned' => 0, 'created_at' => time(),
            'referred_by' => null, 'max_balance' => 0, 'channel_joined' => true
        );
        
        // Referral kontrolü
        $parts = explode(' ', $text);
        if (count($parts) > 1) {
            $ref_code = $parts[1];
            $referrer = $db->getUserByRefCode($ref_code);
            if ($referrer) {
                $user_data['referred_by'] = $ref_code;
                
                // Referans sistemini tetikle
                processReferralSystem($referrer['chat_id'], $chat_id, $username);
            }
        }
        
        $db->createUser($user_data);
        $user = $user_data;
    }
    
    showMainMenu($chat_id, $user);
}

function showEarnMenu($chat_id, $message_id, $user) {
    global $db;
    $ads_today = $db->getTodayAdWatches($chat_id);
    $ads_remaining = DAILY_AD_LIMIT - $ads_today;
    
    $response = "💰 <b>Earn TON</b>\n\n";
    $response .= "📱 <b>Watch Ads & Earn " . AD_REWARD . " TON Each</b>\n\n";
    $response .= "🎬 How to earn:\n";
    $response .= "1. Click 'Watch Ad Now' button\n";
    $response .= "2. Watch the advertisement completely\n";
    $response .= "3. Get " . AD_REWARD . " TON automatically!\n\n";
    $response .= "⏰ Cooldown: " . AD_COOLDOWN . " seconds between ads\n\n";
    $response .= "📊 <b>Daily Progress:</b>\n";
    $response .= "• Watched today: <b>" . $ads_today . "/" . DAILY_AD_LIMIT . "</b> ads\n";
    $response .= "• Remaining: <b>" . $ads_remaining . "</b> ads\n\n";
    $response .= "💰 <b>Balance Stats:</b>\n";
    $response .= "• Current: <b>" . number_format($user['balance'], 6) . " TON</b>\n";
    $response .= "• Highest: <b>" . number_format($user['max_balance'], 6) . " TON</b>\n";
    $response .= "• Total Earned: <b>" . number_format($user['total_earned'], 6) . " TON<
    function processUpdate($update) {
    global $db;
    $db->resetDailyLimits();
    
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $username = $message['chat']['username'] ?? 'Unknown';
        
        if (strpos($text, '/') === 0) {
            $user = $db->getUser($chat_id);
            if (!$user) {
                processEnhancedStartCommand($chat_id, $text, $username);
                return;
            }
            if (!$user['channel_joined']) {
                $channel_joined = isUserInChannel($chat_id);
                if ($channel_joined) {
                    $db->updateUser($chat_id, array('channel_joined' => 1));
                    showMainMenu($chat_id, $user);
                } else {
                    sendMessage($chat_id, "📢 <b>Channel Membership Required</b>\n\nTo use this bot, you must join our official channel:\n" . CHANNEL_USERNAME . "\n\nAfter joining, click the '✅ I Joined' button below.", getChannelJoinKeyboard());
                }
                return;
            }
            showMainMenu($chat_id, $user);
            return;
        }
        
        $user = $db->getUser($chat_id);
        if (!$user) {
            processEnhancedStartCommand($chat_id, '/start', $username);
            return;
        }
        
        if (!$user['channel_joined']) {
            if (strpos($text, '/start') === 0) {
                $channel_joined = isUserInChannel($chat_id);
                if ($channel_joined) {
                    $db->updateUser($chat_id, array('channel_joined' => 1));
                    processEnhancedStartCommand($chat_id, $text, $username);
                } else {
                    sendMessage($chat_id, "📢 <b>Channel Membership Required</b>\n\nTo use this bot, you must join our official channel:\n" . CHANNEL_USERNAME . "\n\nAfter joining, click the '✅ I Joined' button below.", getChannelJoinKeyboard());
                }
            }
            return;
        }
        
        if (strpos($text, '/start') === 0) {
            processEnhancedStartCommand($chat_id, $text, $username);
        } elseif (isset($user['awaiting_ton_address'])) {
            $ton_address = trim($text);
            if (isValidTONAddress($ton_address)) {
                $db->updateUser($chat_id, array('ton_address' => $ton_address, 'awaiting_ton_address' => null));
                $response = "🔗 <b>TON Address Received</b>\n\n✅ <b>Valid TON Address</b>\n\nAddress: <code>" . $ton_address . "</code>\n\nYour withdrawal address has been saved!";
                sendMessage($chat_id, $response, getWithdrawKeyboard(true));
            } else {
                $response = "❌ <b>Invalid TON Address</b>\n\nPlease check your address and try again.\n\n📍 <b>Examples:</b>\n• <code>EQDhG2TjEqX_iNGuOdS_SP2GpvwOxMVupxf5mvMAKIid46HS</code>\n• <code>UQDhG2TjEqX_iNGuOdS_SP2GpvwOxMVupxf5mvMAKIid46HS</code>\n\n📍 <b>Your address:</b> <code>" . htmlspecialchars($ton_address) . "</code>";
                sendMessage($chat_id, $response);
            }
        } else {
            showMainMenu($chat_id, $user);
        }
    } elseif (isset($update['callback_query'])) {
        processCallbackQuery($update['callback_query']);
    }
}

// WebApp'ten gelen ad watch isteğini işleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['chat_id'])) {
    header('Content-Type: application/json');
    
    $chat_id = $_POST['chat_id'];
    $db->resetDailyLimits();
    
    $user = $db->getUser($chat_id);
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Cooldown kontrolü
    $current_time = time();
    $last_watch = $user['last_ad_watch'];
    if ($current_time - $last_watch < AD_COOLDOWN) {
        $remaining = AD_COOLDOWN - ($current_time - $last_watch);
        echo json_encode(['success' => false, 'message' => "Please wait $remaining seconds before watching another ad"]);
        exit;
    }
    
    // Günlük limit kontrolü
    $ads_today = $db->getTodayAdWatches($chat_id);
    if ($ads_today >= DAILY_AD_LIMIT) {
        echo json_encode(['success' => false, 'message' => "Daily ad limit reached. Come back tomorrow!"]);
        exit;
    }
    
    // Ödül ver
    $new_balance = $user['balance'] + AD_REWARD;
    $new_max_balance = max($user['max_balance'], $new_balance);
    
    $db->updateUser($chat_id, array(
        'balance' => $new_balance,
        'last_ad_watch' => $current_time,
        'max_balance' => $new_max_balance,
        'total_earned' => $user['total_earned'] + AD_REWARD
    ));
    
    $db->addAdWatch($chat_id, AD_REWARD);
    $db->addTransaction($chat_id, 'ad_watch', AD_REWARD, "Ad watch reward");
    
    echo json_encode([
        'success' => true,
        'reward' => AD_REWARD,
        'new_balance' => $new_balance,
        'ads_today' => $ads_today + 1,
        'ads_remaining' => DAILY_AD_LIMIT - ($ads_today + 1)
    ]);
    exit;
}

// Ana update işlemi
$input = file_get_contents("php://input");
if ($input) {
    $update = json_decode($input, true);
    if ($update) {
        processUpdate($update);
    }
}
?>

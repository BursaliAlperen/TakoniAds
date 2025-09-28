<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$bot_token = getenv('BOT_TOKEN');
if (!$bot_token) {
    logError("BOT_TOKEN not set");
    die("BOT_TOKEN not set");
}

define('BOT_TOKEN', $bot_token);
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
define('DB_FILE', '/var/www/html/bot.db');
define('ERROR_LOG', '/var/www/html/error.log');
define('AD_REWARD', 0.0005);
define('REF_REWARD', 0.02);
define('MIN_WITHDRAW_REF', 5);
define('MIN_WITHDRAW_AMOUNT', 1);
define('AD_COOLDOWN', 10);
define('DAILY_AD_LIMIT', 100);
define('CHANNEL_USERNAME', '@TakoniFinance');
define('CHANNEL_ID', '-1002855918077');
define('CHANNEL_URL', 'https://t.me/TakoniFinance');
define('BOT_USERNAME', 'takoniAdsBot');

// Basit log fonksiyonu
function logError($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, ERROR_LOG);
}

class Database {
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO("sqlite:" . DB_FILE);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->initTables();
        } catch (PDOException $e) {
            logError("DB Error: " . $e->getMessage());
        }
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
}

function sendMessage($chat_id, $text, $keyboard = null) {
    logError("Sending message to: $chat_id, text: " . substr($text, 0, 100));
    
    $params = array(
        'chat_id' => $chat_id, 
        'text' => $text, 
        'parse_mode' => 'HTML'
    );
    
    if ($keyboard) {
        $params['reply_markup'] = json_encode($keyboard);
    }
    
    $url = API_URL . 'sendMessage?' . http_build_query($params);
    logError("API URL: " . $url);
    
    $result = @file_get_contents($url);
    
    if ($result === false) {
        logError("Failed to send message to: $chat_id");
        return false;
    }
    
    logError("Message sent successfully to: $chat_id");
    return true;
}

function generateRefCode($chat_id) {
    return 'TAK' . substr(md5($chat_id . time()), 0, 7);
}

function isUserInChannel($chat_id) {
    logError("Checking channel membership for: $chat_id");
    
    // TEST MOD: Herkesi kanalda kabul et
    logError("TEST MOD: Channel check bypassed for: $chat_id");
    return true;
    
    /*
    $method = 'getChatMember';
    $params = array('chat_id' => CHANNEL_ID, 'user_id' => $chat_id);
    $url = API_URL . $method . '?' . http_build_query($params);
    
    $response = @file_get_contents($url);
    
    if ($response === false) {
        logError("Failed to check channel membership for: $chat_id");
        return false;
    }
    
    $data = json_decode($response, true);
    if (isset($data['ok']) && $data['ok'] === true) {
        $status = $data['result']['status'];
        $is_member = in_array($status, ['member', 'administrator', 'creator', 'restricted']);
        logError("Channel membership for $chat_id: $status -> " . ($is_member ? 'MEMBER' : 'NOT MEMBER'));
        return $is_member;
    }
    
    logError("Channel check failed for: $chat_id");
    return false;
    */
}

function getMainKeyboard() {
    return array('inline_keyboard' => array(
        array(
            array('text' => '💰 Earn TON', 'callback_data' => 'earn'),
            array('text' => '💳 Balance', 'callback_data' => 'balance')
        ),
        array(
            array('text' => '👥 Referrals', 'callback_data' => 'referrals'),
            array('text' => '🏧 Withdraw', 'callback_data' => 'withdraw')
        )
    ));
}

function getChannelJoinKeyboard() {
    return array('inline_keyboard' => array(
        array(array('text' => '📢 Join Channel', 'url' => CHANNEL_URL)),
        array(array('text' => '✅ I Joined', 'callback_data' => 'check_join'))
    ));
}

function showMainMenu($chat_id, $user) {
    logError("Showing main menu for: $chat_id");
    
    $response = "🤖 <b>Welcome to Takoni Ads Bot!</b>\n\n";
    $response .= "💰 <b>Your Balance:</b> " . number_format($user['balance'], 6) . " TON\n";
    $response .= "👥 <b>Referrals:</b> " . $user['total_referrals'] . "\n\n";
    $response .= "🎯 <b>Available Actions:</b>\n";
    $response .= "• <b>Earn TON:</b> Watch ads and earn " . AD_REWARD . " TON each\n";
    $response .= "• <b>Referrals:</b> Earn " . REF_REWARD . " TON per referral\n";
    $response .= "• <b>Withdraw:</b> Minimum " . MIN_WITHDRAW_AMOUNT . " TON (" . MIN_WITHDRAW_REF . " refs required)\n\n";
    $response .= "🚀 <b>Start earning now!</b>";
    
    $success = sendMessage($chat_id, $response, getMainKeyboard());
    
    if (!$success) {
        logError("FAILED to show main menu for: $chat_id");
        // Alternatif mesaj gönder
        sendMessage($chat_id, "Welcome! Use the buttons below to start earning TON.", getMainKeyboard());
    }
}

function processStartCommand($chat_id, $text, $username) {
    global $db;
    
    logError("Processing start command for: $chat_id, username: $username");
    
    // Önce kanal kontrolü - TEST MOD: herkes geçiyor
    $channel_joined = isUserInChannel($chat_id);
    
    if (!$channel_joined) {
        logError("User not in channel: $chat_id");
        sendMessage($chat_id, "📢 <b>Channel Membership Required</b>\n\nTo use this bot, you must join our official channel:\n" . CHANNEL_USERNAME . "\n\nAfter joining, click the '✅ I Joined' button below.", getChannelJoinKeyboard());
        return;
    }
    
    logError("User is in channel, proceeding: $chat_id");
    
    $user = $db->getUser($chat_id);
    $ref_code = generateRefCode($chat_id);
    
    if (!$user) {
        logError("Creating new user: $chat_id");
        
        $user_data = array(
            'chat_id' => $chat_id, 
            'username' => $username, 
            'balance' => 0,
            'total_referrals' => 0, 
            'ref_code' => $ref_code, 
            'last_ad_watch' => 0,
            'ads_watched_today' => 0, 
            'last_daily_reset' => date('Y-m-d'),
            'ton_address' => '', 
            'total_earned' => 0, 
            'created_at' => time(),
            'referred_by' => null, 
            'max_balance' => 0, 
            'channel_joined' => 1
        );
        
        // Referral kontrolü
        $parts = explode(' ', $text);
        if (count($parts) > 1) {
            $ref_code_param = $parts[1];
            logError("Referral code detected: $ref_code_param");
        }
        
        $db->createUser($user_data);
        $user = $user_data;
        logError("New user created: $chat_id");
    } else {
        logError("Existing user found: $chat_id");
    }
    
    // Ana menüyü göster
    logError("Calling showMainMenu for: $chat_id");
    showMainMenu($chat_id, $user);
}

function processCallbackQuery($callback) {
    global $db;
    
    $chat_id = $callback['message']['chat']['id'];
    $data = $callback['data'];
    
    logError("Callback received: $data from $chat_id");
    
    if ($data == 'check_join') {
        $channel_joined = isUserInChannel($chat_id);
        if ($channel_joined) {
            $db->updateUser($chat_id, array('channel_joined' => 1));
            $user = $db->getUser($chat_id);
            showMainMenu($chat_id, $user);
        } else {
            sendMessage($chat_id, "❌ <b>You haven't joined the channel yet!</b>\n\nPlease join " . CHANNEL_USERNAME . " first, then click '✅ I Joined'", getChannelJoinKeyboard());
        }
        return;
    }
    
    $user = $db->getUser($chat_id);
    if (!$user) {
        logError("User not found in callback: $chat_id");
        return;
    }
    
    // Basit menü işlemleri
    switch ($data) {
        case 'earn':
            sendMessage($chat_id, "💰 <b>Earn TON</b>\n\nClick the button below to watch ads and earn " . AD_REWARD . " TON per ad!", getMainKeyboard());
            break;
        case 'balance':
            sendMessage($chat_id, "💳 <b>Your Balance</b>\n\n💰 Available: " . number_format($user['balance'], 6) . " TON\n👥 Referrals: " . $user['total_referrals'], getMainKeyboard());
            break;
        case 'referrals':
            sendMessage($chat_id, "👥 <b>Referrals</b>\n\nYour referral code: <code>" . $user['ref_code'] . "</code>\nEarn " . REF_REWARD . " TON per referral!", getMainKeyboard());
            break;
        case 'withdraw':
            sendMessage($chat_id, "🏧 <b>Withdraw</b>\n\nMinimum withdrawal: " . MIN_WITHDRAW_AMOUNT . " TON\nRequired referrals: " . MIN_WITHDRAW_REF, getMainKeyboard());
            break;
        default:
            showMainMenu($chat_id, $user);
            break;
    }
}

function processUpdate($update) {
    global $db;
    
    logError("Raw update: " . json_encode($update));
    
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $username = $message['chat']['username'] ?? 'Unknown';
        
        logError("Message received from $chat_id: $text");
        
        if (strpos($text, '/start') === 0) {
            logError("Start command detected");
            processStartCommand($chat_id, $text, $username);
            return;
        }
        
        // Diğer mesajlar için ana menü göster
        $user = $db->getUser($chat_id);
        if ($user) {
            showMainMenu($chat_id, $user);
        } else {
            processStartCommand($chat_id, '/start', $username);
        }
        
    } elseif (isset($update['callback_query'])) {
        logError("Callback query detected");
        processCallbackQuery($update['callback_query']);
    } else {
        logError("Unknown update type received");
    }
}

// Database bağlantısı
$db = new Database();

// Ana update işlemi
$input = file_get_contents("php://input");
logError("Raw input received: " . $input);

if ($input) {
    $update = json_decode($input, true);
    if ($update) {
        processUpdate($update);
    } else {
        logError("Failed to decode JSON input");
    }
} else {
    logError("No input received");
}

// Test için basit bir endpoint
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo "Bot is running! Check error.log for details.";
}
?>

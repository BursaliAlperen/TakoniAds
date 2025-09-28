<?php
require_once 'Database.php';

// Constants from env for Render
define('BOT_TOKEN', getenv('BOT_TOKEN') ?: 'your_bot_token_here');
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
define('ERROR_LOG', 'error.log');
define('AD_REWARD', 0.0005);
define('REF_REWARD', 0.02);
define('MIN_WITHDRAW_REF', 5);
define('MIN_WITHDRAW_AMOUNT', 1);
define('AD_COOLDOWN', 10);
define('DAILY_AD_LIMIT', 100);
define('CHANNEL_USERNAME', getenv('CHANNEL_USERNAME') ?: '@TakoniFinance');
define('CHANNEL_ID', getenv('CHANNEL_ID') ?: '-1002855918077');
define('CHANNEL_URL', getenv('CHANNEL_URL') ?: 'https://t.me/TakoniFinance');
define('BOT_USERNAME', getenv('BOT_USERNAME') ?: 'takoniAdsBot');
define('WEBAPP_URL', getenv('WEBAPP_URL') ?: 'https://yourdomain.com/webapp.html');

// Initialize bot
function initializeBot() {
    try {
        file_get_contents(API_URL . 'setWebhook?url=');
        $db = new Database(); // Triggers DB initialization
        return true;
    } catch (Exception $e) {
        logError("Initialization failed: " . $e->getMessage());
        return false;
    }
}

// Log error
function logError($message) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(ERROR_LOG, "[$timestamp] $message\n", FILE_APPEND);
}

// Send message
function sendMessage($chat_id, $text, $keyboard = null) {
    try {
        $params = [
            'chat_id' => $chat_id,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];
        if ($keyboard) {
            $params['reply_markup'] = json_encode(['inline_keyboard' => $keyboard]);
        }
        $url = API_URL . 'sendMessage?' . http_build_query($params);
        file_get_contents($url);
        return true;
    } catch (Exception $e) {
        logError("Send message failed: " . $e->getMessage());
        return false;
    }
}

// Edit message
function editMessageText($chat_id, $message_id, $text, $keyboard = null) {
    try {
        $params = [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];
        if ($keyboard) {
            $params['reply_markup'] = json_encode(['inline_keyboard' => $keyboard]);
        }
        $url = API_URL . 'editMessageText?' . http_build_query($params);
        file_get_contents($url);
        return true;
    } catch (Exception $e) {
        logError("Edit message failed: " . $e->getMessage());
        return false;
    }
}

// Check channel membership
function isUserInChannel($chat_id) {
    try {
        $params = [
            'chat_id' => CHANNEL_ID,
            'user_id' => $chat_id
        ];
        $url = API_URL . 'getChatMember?' . http_build_query($params);
        $response = json_decode(file_get_contents($url), true);
        if ($response['ok']) {
            $status = $response['result']['status'];
            return in_array($status, ['member', 'administrator', 'creator']);
        }
        return false;
    } catch (Exception $e) {
        logError("Channel check failed: " . $e->getMessage());
        return false;
    }
}

// Validate TON address
function isValidTONAddress($address) {
    $address = trim($address);
    $patterns = [
        '/^EQ[0-9a-zA-Z_-]{48}$/',
        '/^UQ[0-9a-zA-Z_-]{48}$/',
        '/^0:[0-9a-fA-F]{64}$/',
        '/^[0-9a-zA-Z_-]{48}$/',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $address)) {
            return true;
        }
    }
    return false;
}

// Process multi-level referral
function processMultiLevelReferral($original_referrer_id, $new_user_id, $new_username, $current_level, $max_levels = 10) {
    if ($current_level > $max_levels) return;

    $db = new Database();
    $referrer = $db->getUser($original_referrer_id);
    if (!$referrer) return;

    $reward = REF_REWARD / $current_level;
    $new_balance = $referrer['balance'] + $reward;
    $new_total_earned = $referrer['total_earned'] + $reward;
    $new_max_balance = max($referrer['max_balance'], $new_balance);

    $db->updateUser($original_referrer_id, [
        'balance' => $new_balance,
        'total_earned' => $new_total_earned,
        'max_balance' => $new_max_balance,
        'total_referrals' => $referrer['total_referrals'] + 1
    ]);

    $db->addReferral($original_referrer_id, $new_user_id, $new_username, $current_level);
    $db->addTransaction($original_referrer_id, 'referral', $reward, "Referral from @$new_username (level $current_level)");

    if ($current_level <= 3) {
        $msg = "🎉 NEW REFERRAL #$current_level!\n👤 New User: @$new_username\n💰 Reward Received: $reward TON\n📊 Total Referrals: {$referrer['total_referrals'] + 1}\n💳 New Balance: $new_balance TON\nKeep inviting! 💪";
        sendMessage($original_referrer_id, $msg);
    }

    if ($referrer['referred_by']) {
        processMultiLevelReferral($referrer['referred_by'], $new_user_id, $new_username, $current_level + 1, $max_levels);
    }
}

// Process start command
function processStartCommand($chat_id, $text, $username) {
    $db = new Database();
    $user = $db->getUser($chat_id);

    if (!$user) {
        $ref_code = 'TAK' . substr(md5($chat_id . time()), 0, 6);
        $user_data = [
            'chat_id' => $chat_id,
            'username' => $username,
            'ref_code' => $ref_code,
            'created_at' => time(),
            'updated_at' => time()
        ];
        $db->createUser($user_data);
        $user = $db->getUser($chat_id);
    }

    if (!isUserInChannel($chat_id)) {
        $msg = "Please join our channel first: " . CHANNEL_URL;
        sendMessage($chat_id, $msg);
        return;
    }

    $db->updateUser($chat_id, ['channel_joined' => true]);

    $parts = explode(' ', $text);
    if (count($parts) > 1) {
        $ref_code = $parts[1];
        $referrer = $db->getUserByRefCode($ref_code);
        if ($referrer && !$user['referred_by']) {
            $db->updateUser($chat_id, ['referred_by' => $referrer['chat_id']]);
            processMultiLevelReferral($referrer['chat_id'], $chat_id, $username, 1);
        }
    }

    showMainMenu($chat_id, $user);
}

// Process referral system (called in multi-level)
function processReferralSystem($referrer_chat_id, $referred_chat_id, $referred_username) {
    // Handled in processMultiLevelReferral
}

// Show main menu
function showMainMenu($chat_id, $user) {
    $msg = "🤖 Welcome to Takoni Ads Bot!\n\n💰 Your Balance: {$user['balance']} TON\n👥 Referrals: {$user['total_referrals']}\n\n🎯 Available Actions:\n• Earn TON: Watch ads and earn 0.0005 TON each\n• Referrals: Earn 0.02 TON per referral\n• Withdraw: Minimum 1 TON (5 refs required)\n\n🚀 Start earning now!";
    $keyboard = [
        [['text' => '💰 Earn TON', 'callback_data' => 'earn'], ['text' => '💳 Balance', 'callback_data' => 'balance']],
        [['text' => '👥 Referrals', 'callback_data' => 'referrals'], ['text' => '🏧 Withdraw', 'callback_data' => 'withdraw']]
    ];
    sendMessage($chat_id, $msg, $keyboard);
}

// Show earn menu
function showEarnMenu($chat_id, $user) {
    $db = new Database();
    $db->resetDailyLimits();
    $ads_today = $user['ads_watched_today'];
    $remaining = DAILY_AD_LIMIT - $ads_today;
    $msg = "💰 Earn TON\n\n📱 Watch Ads & Earn 0.0005 TON Each\n\n🎬 How to earn:\n1. Click 'Watch Ad Now' button\n2. Watch the advertisement completely\n3. Get 0.0005 TON automatically!\n\n⏰ Cooldown: 10 seconds between ads\n\n📊 Daily Progress:\n• Watched today: $ads_today/" . DAILY_AD_LIMIT . " ads\n• Remaining: $remaining ads\n\n💰 Balance Stats:\n• Current: {$user['balance']} TON\n• Highest: {$user['max_balance']} TON\n• Total Earned: {$user['total_earned']} TON";
    $keyboard = [
        [['text' => '📱 Watch Ad (0.0005 TON)', 'web_app' => ['url' => WEBAPP_URL]]],
        [['text' => '🔄 Check Balance', 'callback_data' => 'balance'], ['text' => '⬅️ Back to Main', 'callback_data' => 'main']]
    ];
    sendMessage($chat_id, $msg, $keyboard);
}

// Show referrals menu
function showReferralsMenu($chat_id, $user) {
    $db = new Database();
    $history = $db->getReferralHistory($chat_id, 5);
    $recent = "";
    foreach ($history as $ref) {
        $date = date('M d', $ref['created_at']);
        $recent .= "• @{$ref['referred_username']} - $date\n";
    }
    $earned_from_refs = $user['total_referrals'] * REF_REWARD; // Approximate
    $msg = "👥 Referral System\n\n📊 Statistics:\n• Total Referrals: {$user['total_referrals']}\n• Earned from Referrals: $earned_from_refs TON\n\n🔗 Your Referral Code:\n{$user['ref_code']}\n\n🌐 Your Referral Link:\nhttps://t.me/" . BOT_USERNAME . "?start={$user['ref_code']}\n\n💰 Referral Reward: 0.02 TON per user\n\n📋 Recent Referrals:\n$recent\n\n💡 How it works:\n1. Share your referral link\n2. Earn 0.02 TON for each friend who joins\n3. Track all your referrals forever\n4. No limits - refer endlessly! 🚀";
    $keyboard = [
        [['text' => '📤 Share Referral', 'switch_inline_query' => 'Join and earn TON!']],
        [['text' => '📊 Full History', 'callback_data' => 'ref_history'], ['text' => '🔄 Refresh', 'callback_data' => 'referrals']],
        [['text' => '⬅️ Back to Main', 'callback_data' => 'main']]
    ];
    sendMessage($chat_id, $msg, $keyboard);
}

// Process callback query
function processCallbackQuery($callback) {
    $chat_id = $callback['message']['chat']['id'];
    $message_id = $callback['message']['message_id'];
    $data = $callback['data'];
    $db = new Database();
    $user = $db->getUser($chat_id);

    switch ($data) {
        case 'earn':
            showEarnMenu($chat_id, $user);
            break;
        case 'balance':
            $msg = "💳 Balance\nCurrent: {$user['balance']} TON\nTotal Earned: {$user['total_earned']} TON\nMax: {$user['max_balance']} TON";
            editMessageText($chat_id, $message_id, $msg, [
                [['text' => '💰 Earn TON', 'callback_data' => 'earn'], ['text' => '👥 Referrals', 'callback_data' => 'referrals']],
                [['text' => '🏧 Withdraw', 'callback_data' => 'withdraw'], ['text' => '⬅️ Back to Main', 'callback_data' => 'main']]
            ]);
            break;
        case 'referrals':
            showReferralsMenu($chat_id, $user);
            break;
        case 'withdraw':
            if ($user['total_referrals'] < MIN_WITHDRAW_REF || $user['balance'] < MIN_WITHDRAW_AMOUNT) {
                $msg = "🏧 Withdraw\nRequirements not met. Need 5 refs and 1 TON.";
            } elseif (!$user['ton_address']) {
                $db->updateUser($chat_id, ['awaiting_ton_address' => true]);
                $msg = "Please send your TON address.";
            } else {
                $amount = $user['balance'];
                $stmt = $db->pdo->prepare("INSERT INTO withdrawals (chat_id, amount, ton_address, created_at) VALUES (?, ?, ?, ?)");
                $stmt->execute([$chat_id, $amount, $user['ton_address'], time()]);
                $db->updateUser($chat_id, ['balance' => 0]);
                $db->addTransaction($chat_id, 'withdraw', -$amount, "Withdrawal to {$user['ton_address']}");
                $msg = "🏧 Withdrawal of $amount TON requested!";
            }
            sendMessage($chat_id, $msg);
            break;
        case 'main':
            showMainMenu($chat_id, $user);
            break;
    }
}

// Process update
function processUpdate($update) {
    $db = new Database();
    $db->resetDailyLimits();

    if (isset($update['message'])) {
        $chat_id = $update['message']['chat']['id'];
        $text = trim($update['message']['text'] ?? '');
        $username = $update['message']['from']['username'] ?? '';

        $user = $db->getUser($chat_id);
        if ($user['awaiting_ton_address']) {
            if (isValidTONAddress($text)) {
                $db->updateUser($chat_id, ['ton_address' => $text, 'awaiting_ton_address' => false]);
                sendMessage($chat_id, "✅ TON address set: $text");
            } else {
                sendMessage($chat_id, "Invalid TON address. Try again.");
            }
            return;
        }

        if (strpos($text, '/start') === 0) {
            processStartCommand($chat_id, $text, $username);
        } elseif (strpos($text, '/balance') === 0) {
            $msg = "💳 Balance\nCurrent: {$user['balance']} TON\nTotal Earned: {$user['total_earned']} TON\nMax: {$user['max_balance']} TON";
            sendMessage($chat_id, $msg);
        } elseif (strpos($text, '/referrals') === 0) {
            showReferralsMenu($chat_id, $user);
        }
    } elseif (isset($update['callback_query'])) {
        processCallbackQuery($update['callback_query']);
    }
}

// WebApp POST endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['chat_id'])) {
    header('Content-Type: application/json');
    try {
        $chat_id = $_POST['chat_id'];
        $db = new Database();
        $user = $db->getUser($chat_id);
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }

        $current_time = time();
        $remaining = AD_COOLDOWN - ($current_time - $user['last_ad_watch']);
        if ($remaining > 0) {
            echo json_encode(['success' => false, 'message' => "Please wait $remaining seconds"]);
            exit;
        }

        if ($user['ads_watched_today'] >= DAILY_AD_LIMIT) {
            echo json_encode(['success' => false, 'message' => 'Daily ad limit reached']);
            exit;
        }

        $new_balance = $user['balance'] + AD_REWARD;
        $new_total_earned = $user['total_earned'] + AD_REWARD;
        $new_max_balance = max($user['max_balance'], $new_balance);
        $new_ads_today = $user['ads_watched_today'] + 1;

        $db->updateUser($chat_id, [
            'balance' => $new_balance,
            'total_earned' => $new_total_earned,
            'max_balance' => $new_max_balance,
            'last_ad_watch' => $current_time,
            'ads_watched_today' => $new_ads_today
        ]);

        $db->addAdWatch($chat_id, AD_REWARD);
        $db->addTransaction($chat_id, 'ad_watch', AD_REWARD, 'Ad watch reward');

        echo json_encode([
            'success' => true,
            'reward' => AD_REWARD,
            'new_balance' => $new_balance,
            'ads_today' => $new_ads_today,
            'ads_remaining' => DAILY_AD_LIMIT - $new_ads_today
        ]);
    } catch (Exception $e) {
        logError("WebApp error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error']);
    }
    exit;
}

// Main polling loop
function runBot() {
    $offset = 0;
    initializeBot();
    echo "Bot started.\n";

    while (true) {
        try {
            $updates = file_get_contents(API_URL . "getUpdates?offset=$offset&timeout=30");
            $updates = json_decode($updates, true);

            if ($updates['ok'] && !empty($updates['result'])) {
                foreach ($updates['result'] as $update) {
                    $offset = $update['update_id'] + 1;
                    processUpdate($update);
                }
            }

            usleep(100000);
        } catch (Exception $e) {
            logError("Polling error: " . $e->getMessage());
            sleep(1);
        }
    }
}

// Start
try {
    runBot();
} catch (Exception $e) {
    logError("Fatal error: " . $e->getMessage());
}
?>

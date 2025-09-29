<?php
// index.php
require_once 'Database.php';

// Constants from env
define('BOT_TOKEN', getenv('BOT_TOKEN') ?: 'your_bot_token_here');
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
define('ERROR_LOG', 'error.log');
define('AD_REWARD', 0.0005); // TON per ad
define('REF_REWARD', 0.01);  // TON per referral
define('MIN_WITHDRAW_REF', 5);
define('MIN_WITHDRAW_AMOUNT', 1);
define('AD_COOLDOWN', 10); // Seconds
define('DAILY_AD_LIMIT', 100);
define('CHANNEL_USERNAME', getenv('CHANNEL_USERNAME') ?: '@TakoniFinance');
define('CHANNEL_ID', getenv('CHANNEL_ID') ?: '-1002855918077');
define('CHANNEL_URL', getenv('CHANNEL_URL') ?: 'https://t.me/TakoniFinance');
define('BOT_USERNAME', getenv('BOT_USERNAME') ?: '@TakoniAdsBot');
define('WEBAPP_URL', getenv('WEBAPP_URL') ?: 'https://your-mini-app.onrender.com/webapp.html');
define('AD_ZONE_ID', getenv('AD_ZONE_ID') ?: '3305');

// Initialize bot (set webhook on Render)
function initializeBot() {
    try {
        file_get_contents(API_URL . 'setWebhook?url=' . urlencode('https://' . $_SERVER['HTTP_HOST']));
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
            $params['reply_markup'] = json_encode($keyboard);
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
            $params['reply_markup'] = json_encode($keyboard);
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
        '/^EQ[0-9a-zA-Z_-]{46}$/',
        '/^UQ[0-9a-zA-Z_-]{46}$/',
        '/^0:[0-9a-fA-F]{64}$/',
        '/^[0-9a-zA-Z_-]{48}$/'
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $address)) {
            return true;
        }
    }
    return false;
}

// Process multi-level referral
function processMultiLevelReferral($db, $original_referrer_id, $new_user_id, $new_username, $current_level, $max_levels = 10) {
    if ($current_level > $max_levels) return;

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
        $msg = "🎉 NEW REFERRAL #$current_level!\n👤 New User: @$new_username\n💰 Reward: $reward TON\n📊 Total Referrals: {$referrer['total_referrals'] + 1}\n💳 Balance: $new_balance TON\nKeep inviting! 💪";
        sendMessage($original_referrer_id, $msg);
    }

    if ($referrer['referred_by']) {
        processMultiLevelReferral($db, $referrer['referred_by'], $new_user_id, $new_username, $current_level + 1, $max_levels);
    }
}

// Check ad cooldown
function checkAdCooldown($user) {
    $current_time = time();
    $remaining = AD_COOLDOWN - ($current_time - $user['last_ad_watch']);
    return $remaining <= 0;
}

// Check first ad for referral bonus
function checkFirstAdForReferralBonus($db, $user_id) {
    $stmt = $db->pdo->prepare("SELECT COUNT(*) FROM ad_watches WHERE user_id = ?");
    $stmt->execute([$user_id]);
    if ($stmt->fetchColumn() === 1) {
        $stmt = $db->pdo->prepare("SELECT referrer_id FROM referrals WHERE referred_id = ? AND level = 1");
        $stmt->execute([$user_id]);
        $referrer_id = $stmt->fetchColumn();
        if ($referrer_id) {
            $referrer = $db->getUser($referrer_id);
            $new_balance = $referrer['balance'] + 0.01;
            $new_total_earned = $referrer['total_earned'] + 0.01;
            $new_max_balance = max($referrer['max_balance'], $new_balance);
            $db->updateUser($referrer_id, [
                'balance' => $new_balance,
                'total_earned' => $new_total_earned,
                'max_balance' => $new_max_balance
            ]);
            $db->addTransaction($referrer_id, 'referral_tier_bonus', 0.01, "Tier bonus for referred user {$user_id}'s first ad");
            sendMessage($referrer_id, "🎉 Your referral @$user_id watched their first ad! +0.01 TON bonus!");
            return true;
        }
    }
    return false;
}

// Show main menu
function showMainMenu($chat_id, $user) {
    $msg = "🤖 Welcome to " . BOT_USERNAME . "!\n\n💰 Balance: {$user['balance']} TON\n👥 Referrals: {$user['total_referrals']}\n\n🎯 Actions:\n• Earn 0.0005 TON per ad (100/day max)\n• Earn 0.01 TON per referral\n• Claim 0.20 TON daily\n• Withdraw (min 1 TON, 5 refs)\n• Check stats & leaderboard\n\n🚀 Start earning!";
    $keyboard = [
        'keyboard' => [
            [['text' => '💰 Balance'], ['text' => '🔗 Referral']],
            [['text' => '📺 Watch Ad'], ['text' => '🏧 Withdraw']],
            [['text' => '📊 Statement'], ['text' => '💸 Payout']],
            [['text' => '📈 Stats'], ['text' => '🏆 Leaderboard']],
            [['text' => '🎁 Daily']]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => false
    ];
    sendMessage($chat_id, $msg, ['keyboard' => $keyboard]);
}

// Show earn menu
function showEarnMenu($chat_id, $user) {
    $db = new Database();
    $db->resetDailyLimits();
    $ads_today = $user['ads_watched_today'];
    $remaining = DAILY_AD_LIMIT - $ads_today;
    $msg = "💰 Earn TON\n\n📱 Watch Ads & Earn " . AD_REWARD . " TON Each\n\n🎬 How to earn:\n1. Click 'Watch Ad Now'\n2. Watch the ad fully\n3. Earn " . AD_REWARD . " TON!\n\n⏰ Cooldown: " . AD_COOLDOWN . "s\n\n📊 Progress:\n• Watched today: $ads_today/" . DAILY_AD_LIMIT . "\n• Remaining: $remaining\n\n💰 Stats:\n• Balance: {$user['balance']} TON\n• Highest: {$user['max_balance']} TON\n• Total Earned: {$user['total_earned']} TON";
    $keyboard = [
        'inline_keyboard' => [
            [['text' => "📺 Watch Ad ($ads_today/100)", 'web_app' => ['url' => WEBAPP_URL]]],
            [['text' => '🔄 Check Balance', 'callback_data' => 'balance'], ['text' => '⬅️ Back', 'callback_data' => 'main']]
        ]
    ];
    sendMessage($chat_id, $msg, $keyboard);
}

// Show referrals menu
function showReferralsMenu($chat_id, $user) {
    $db = new Database();
    $history = $db->getReferralHistory($chat_id, 5);
    $recent = "";
    foreach ($history as $ref) {
        $date = date('M d', strtotime($ref['created_at']));
        $recent .= "• @{$ref['referred_username']} - $date\n";
    }
    $earned_from_refs = $user['total_referrals'] * REF_REWARD;
    $link = "https://t.me/" . BOT_USERNAME . "?start={$user['referral_code']}";
    $msg = "👥 Referral System\n\n📊 Stats:\n• Total Referrals: {$user['total_referrals']}\n• Earned: $earned_from_refs TON\n\n🔗 Your Link:\n{$link}\n\n💰 Reward: " . REF_REWARD . " TON per user\n\n📋 Recent Referrals:\n$recent\n\n💡 How it works:\n1. Share your link\n2. Earn 0.01 TON per friend\n3. Multi-level rewards up to 10 levels!";
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '📤 Share Referral', 'switch_inline_query' => 'Join and earn TON!']],
            [['text' => '📊 Full History', 'callback_data' => 'ref_history'], ['text' => '🔄 Refresh', 'callback_data' => 'referrals']],
            [['text' => '⬅️ Back', 'callback_data' => 'main']]
        ]
    ];
    sendMessage($chat_id, $msg, $keyboard);
}

// Handle /start
function handleStart($db, $chat_id, $text, $username, $first_name) {
    $user = $db->getUser($chat_id);
    if (!$user) {
        $ref_code = 'TAK' . substr(md5($chat_id . time()), 0, 6);
        $user_data = [
            'chat_id' => $chat_id,
            'username' => $username,
            'first_name' => $first_name,
            'ref_code' => $ref_code,
            'created_at' => time(),
            'updated_at' => time()
        ];
        $db->createUser($user_data);
        $user = $db->getUser($chat_id);
    }

    if (!isUserInChannel($chat_id)) {
        $msg = "Please join our channel first: <a href='" . CHANNEL_URL . "'>" . CHANNEL_USERNAME . "</a>";
        sendMessage($chat_id, $msg);
        return;
    }

    $db->updateUser($chat_id, ['channel_joined' => 1]);

    $parts = explode(' ', trim($text));
    if (count($parts) > 1 && !$user['referred_by']) {
        $ref_code = $parts[1];
        $referrer = $db->getUserByRefCode($ref_code);
        if ($referrer && $referrer['telegram_id'] != $chat_id) {
            $db->updateUser($chat_id, ['referred_by' => $referrer['telegram_id']]);
            processMultiLevelReferral($db, $referrer['telegram_id'], $chat_id, $username, 1);
        }
    }

    if (empty($user['created_at']) || strtotime($user['created_at']) > time() - 60) {
        $msg = "Welcome, {$user['first_name']}! 🌟 You got a 0.05 TON bonus! 🎁\n\nYour referral link: {$link}\n\nCommands:\n💰 /balance - Check balance\n🔗 /referral - Share link\n📺 /watch_ad - Earn 0.0005 TON/ad (100/day)\n🏧 /withdraw <amount> - Withdraw to TON\n📊 /statement - View history\n💸 /payout [set <address>|withdraw <amount>] - Manage wallet\n📈 /stats - Detailed stats\n🏆 /leaderboard - Top referrers\n🎁 /daily - Claim 0.20 TON daily";
    } else {
        $msg = "You are already a member! 🚀 Here's the main menu 👇";
    }
    showMainMenu($chat_id, $user);
}

// Handle /balance
function handleBalance($db, $chat_id, $user) {
    $ads_today = $db->getTodayAdCount($user['telegram_id']);
    $remaining = DAILY_AD_LIMIT - $ads_today;
    $ton_info = $user['ton_address'] ? "🪙 TON Address: {$user['ton_address']}" : "⚠️ No TON address set. Use /payout set <address>";
    $msg = "💳 <b>Balance</b>\nCurrent: {$user['balance']} TON\n{$ton_info}\n📺 Ads today: $ads_today/100\n👥 Referrals: {$user['total_referrals']}\n💸 Total Earned: {$user['total_earned']} TON";
    sendMessage($chat_id, $msg, ['keyboard' => $main_menu]);
}

// Handle /referral
function handleReferral($db, $chat_id, $user) {
    showReferralsMenu($chat_id, $user);
}

// Handle /watch_ad
function handleWatchAd($db, $chat_id, $user) {
    global $main_menu;
    $db->resetDailyLimits();
    $ads_today = $user['ads_watched_today'];
    if ($ads_today >= DAILY_AD_LIMIT) {
        sendMessage($chat_id, "You've reached the daily ad limit! 📺 ($ads_today/100). Try tomorrow ⏰.", ['keyboard' => $main_menu]);
        return;
    }
    if (!checkAdCooldown($user)) {
        $remaining = AD_COOLDOWN - (time() - $user['last_ad_watch']);
        sendMessage($chat_id, "Please wait $remaining seconds before watching another ad ⏳.", ['keyboard' => $main_menu]);
        return;
    }
    showEarnMenu($chat_id, $user);
}

// Handle /withdraw
function handleWithdraw($db, $chat_id, $user, $parts) {
    global $main_menu;
    if ($user['total_referrals'] < MIN_WITHDRAW_REF || $user['balance'] < MIN_WITHDRAW_AMOUNT) {
        sendMessage($chat_id, "🏧 Withdraw\nRequirements not met. Need 5 referrals and 1 TON.", ['keyboard' => $main_menu]);
        return;
    }
    if (!$user['ton_address']) {
        sendMessage($chat_id, "Set your TON address first 💸 using /payout set <address>", ['keyboard' => $main_menu]);
        return;
    }
    if (count($parts) < 2 || !is_numeric($parts[1]) || $parts[1] < MIN_WITHDRAW_AMOUNT) {
        sendMessage($chat_id, "Invalid amount ❌. Use /withdraw <amount> (min 1 TON), e.g., /withdraw 1.0", ['keyboard' => $main_menu]);
        return;
    }
    $amount = (float)$parts[1];
    if ($amount > $user['balance']) {
        sendMessage($chat_id, "Insufficient balance ⚠️. You have {$user['balance']} TON", ['keyboard' => $main_menu]);
        return;
    }
    $db->pdo->prepare("INSERT INTO withdrawals (chat_id, amount, ton_address, created_at) VALUES (?, ?, ?, ?)")
        ->execute([$chat_id, $amount, $user['ton_address'], date('Y-m-d H:i:s')]);
    $db->updateUser($chat_id, ['balance' => $user['balance'] - $amount]);
    $db->addTransaction($chat_id, 'withdraw', -$amount, "Withdrawal to {$user['ton_address']}");
    sendMessage($chat_id, "🏧 Withdrawal of $amount TON requested to {$user['ton_address']}!", ['keyboard' => $main_menu]);
}

// Handle /statement
function handleStatement($db, $chat_id, $user) {
    global $main_menu;
    $stmt = $db->pdo->prepare("SELECT type, amount, description, created_at FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$user['telegram_id']]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($transactions)) {
        sendMessage($chat_id, "No transactions yet 📭. Earn with /watch_ad or /referral!", ['keyboard' => $main_menu]);
        return;
    }
    
    $msg = "📊 <b>Recent Statement</b>:\n";
    foreach ($transactions as $tx) {
        $emoji = match($tx['type']) {
            'referral' => '👥',
            'ad_watch' => '📺',
            'withdraw' => '🏧',
            'welcome_bonus' => '🎁',
            'daily_reward' => '🎉',
            'referral_tier_bonus' => '🌟'
        };
        $msg .= "$emoji {$tx['type']} {$tx['amount']} TON - {$tx['description']} ({$tx['created_at']})\n";
    }
    sendMessage($chat_id, $msg, ['keyboard' => $main_menu]);
}

// Handle /payout
function handlePayout($db, $chat_id, $user, $parts) {
    global $main_menu;
    if (count($parts) < 2) {
        $ton_info = $user['ton_address'] ? "🪙 Current TON address: {$user['ton_address']}" : "⚠️ No TON address set.";
        $msg = "💸 <b>Payout Options</b>:\n{$ton_info}\n\nCommands:\n/payout set <TON address> - Set/update wallet\n/payout withdraw <amount> - Withdraw (min 1 TON, 5 refs)\n\nExample: /payout set EQabc...";
        sendMessage($chat_id, $msg, ['keyboard' => $main_menu]);
        return;
    }

    $action = $parts[1];
    if ($action === 'set') {
        if (count($parts) < 3) {
            sendMessage($chat_id, "Provide a TON address ❌, e.g., /payout set EQabc...", ['keyboard' => $main_menu]);
            return;
        }
        $address = implode(' ', array_slice($parts, 2));
        if (!isValidTONAddress($address)) {
            sendMessage($chat_id, "Invalid TON address ⚠️. Must be a valid TON address (EQ/UQ/0: formats).", ['keyboard' => $main_menu]);
            return;
        }
        $db->updateUser($chat_id, ['ton_address' => $address, 'awaiting_ton_address' => 0]);
        sendMessage($chat_id, "TON address set 🪙: {$address}\nUse /payout withdraw <amount> to payout.", ['keyboard' => $main_menu]);
    } elseif ($action === 'withdraw') {
        handleWithdraw($db, $chat_id, $user, $parts);
    } else {
        sendMessage($chat_id, "Invalid action ❌. Use /payout set <address> or /payout withdraw <amount>", ['keyboard' => $main_menu]);
    }
}

// Handle /stats
function handleStats($db, $chat_id, $user) {
    global $main_menu;
    $ads_today = $db->getTodayAdCount($user['telegram_id']);
    $total_earnings = $user['total_earned'];
    $msg = "📈 <b>Stats</b>:\n💰 Balance: {$user['balance']} TON\n🪙 TON Address: " . ($user['ton_address'] ?: "Not set") . "\n📺 Ads today: $ads_today/100\n👥 Referrals: {$user['total_referrals']}\n💸 Total Earned: $total_earnings TON\n🏆 Max Balance: {$user['max_balance']} TON";
    sendMessage($chat_id, $msg, ['keyboard' => $main_menu]);
}

// Handle /leaderboard
function handleLeaderboard($db, $chat_id, $user) {
    global $main_menu;
    $stmt = $db->pdo->query("SELECT u.username, COUNT(r.id) AS ref_count FROM users u LEFT JOIN referrals r ON u.telegram_id = r.referrer_id GROUP BY u.telegram_id ORDER BY ref_count DESC LIMIT 10");
    $leaders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($leaders)) {
        sendMessage($chat_id, "No leaders yet 🏆. Be the first by referring friends!", ['keyboard' => $main_menu]);
        return;
    }
    
    $msg = "🏆 <b>Leaderboard (Top Referrers)</b>:\n";
    foreach ($leaders as $i => $leader) {
        $msg .= ($i + 1) . ". @{$leader['username']} - {$leader['ref_count']} referrals\n";
    }
    sendMessage($chat_id, $msg, ['keyboard' => $main_menu]);
}

// Handle /daily
function handleDaily($db, $chat_id, $user) {
    global $main_menu;
    if ($db->getTodayDailyClaim($user['telegram_id']) > 0) {
        sendMessage($chat_id, "You've claimed your daily reward today! ⏰ Try tomorrow.", ['keyboard' => $main_menu]);
        return;
    }
    
    $reward = 0.20;
    $new_balance = $user['balance'] + $reward;
    $new_total_earned = $user['total_earned'] + $reward;
    $new_max_balance = max($user['max_balance'], $new_balance);
    $db->updateUser($chat_id, [
        'balance' => $new_balance,
        'total_earned' => $new_total_earned,
        'max_balance' => $new_max_balance
    ]);
    $db->pdo->prepare("INSERT INTO daily_rewards (user_id, claimed_at) VALUES (?, ?)")
        ->execute([$chat_id, date('Y-m-d H:i:s')]);
    $db->addTransaction($chat_id, 'daily_reward', $reward, "Daily reward claim");
    sendMessage($chat_id, "Daily reward claimed! 🎉 +$reward TON added! Balance: $new_balance TON", ['keyboard' => $main_menu]);
}

// Process WebApp data
function processWebAppData($chat_id, $data) {
    header('Content-Type: application/json');
    try {
        $db = new Database();
        $user = $db->getUser($chat_id);
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }

        $db->resetDailyLimits();
        if ($user['ads_watched_today'] >= DAILY_AD_LIMIT) {
            echo json_encode(['success' => false, 'message' => 'Daily ad limit reached']);
            exit;
        }

        if (!checkAdCooldown($user)) {
            $remaining = AD_COOLDOWN - (time() - $user['last_ad_watch']);
            echo json_encode(['success' => false, 'message' => "Please wait $remaining seconds"]);
            exit;
        }

        $new_balance = $user['balance'] + $data['reward'];
        $new_total_earned = $user['total_earned'] + $data['reward'];
        $new_max_balance = max($user['max_balance'], $new_balance);
        $new_ads_today = $user['ads_watched_today'] + 1;

        $db->updateUser($chat_id, [
            'balance' => $new_balance,
            'total_earned' => $new_total_earned,
            'max_balance' => $new_max_balance,
            'last_ad_watch' => time(),
            'ads_watched_today' => $new_ads_today
        ]);

        $db->addAdWatch($chat_id, $data['reward'], AD_ZONE_ID);
        $db->addTransaction($chat_id, 'ad_watch', $data['reward'], "Ad watch reward via Zone ID " . AD_ZONE_ID);
        logError("Ad reward granted to user $chat_id via Zone ID " . AD_ZONE_ID . ": {$data['reward']} TON");

        checkFirstAdForReferralBonus($db, $chat_id);

        $link = "https://t.me/" . BOT_USERNAME . "?start={$user['referral_code']}";
        sendMessage($chat_id, "🎉 Ad watched! +{$data['reward']} TON! Balance: $new_balance TON\nShare your referral link to earn more: {$link}", ['keyboard' => $main_menu]);

        echo json_encode([
            'success' => true,
            'reward' => $data['reward'],
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

// Process update
$update = json_decode(file_get_contents('php://input'), true);
if (!$update || !isset($update['update_id'])) {
    http_response_code(400);
    exit;
}

$db = new Database();
$db->resetDailyLimits();

$chat_id = $update['message']['chat']['id'] ?? $update['callback_query']['message']['chat']['id'] ?? $update['web_app_data']['chat']['id'] ?? null;
$user_id = $update['message']['from']['id'] ?? $update['callback_query']['from']['id'] ?? $update['web_app_data']['chat']['id'] ?? null;
$username = $update['message']['from']['username'] ?? '';
$first_name = $update['message']['from']['first_name'] ?? '';
$text = trim($update['message']['text'] ?? '');
$callback_data = $update['callback_query']['data'] ?? '';

$user = $db->getUser($user_id);
if (!$user) {
    $user = $db->createUser([
        'chat_id' => $user_id,
        'username' => $username,
        'first_name' => $first_name,
        'ref_code' => 'TAK' . substr(md5($user_id . time()), 0, 6),
        'created_at' => time(),
        'updated_at' => time()
    ]);
    $user = $db->getUser($user_id);
}

if ($user['awaiting_ton_address'] && $text) {
    if (isValidTONAddress($text)) {
        $db->updateUser($user_id, ['ton_address' => $text, 'awaiting_ton_address' => 0]);
        sendMessage($chat_id, "✅ TON address set: $text", ['keyboard' => $main_menu]);
    } else {
        sendMessage($chat_id, "Invalid TON address ⚠️. Try again.", ['keyboard' => $main_menu]);
    }
    exit;
}

if (!isUserInChannel($chat_id)) {
    sendMessage($chat_id, "Please join our channel first: <a href='" . CHANNEL_URL . "'>" . CHANNEL_USERNAME . "</a>");
    exit;
}

$command = strtolower($text);
$parts = explode(' ', $command);

switch ($parts[0]) {
    case '/start':
        handleStart($db, $chat_id, $text, $username, $first_name);
        break;
    case '/balance':
    case '💰 balance':
        handleBalance($db, $chat_id, $user);
        break;
    case '/referral':
    case '🔗 referral':
        handleReferral($db, $chat_id, $user);
        break;
    case '/watch_ad':
    case '📺 watch ad':
        handleWatchAd($db, $chat_id, $user);
        break;
    case '/withdraw':
    case '🏧 withdraw':
        handleWithdraw($db, $chat_id, $user, $parts);
        break;
    case '/statement':
    case '📊 statement':
        handleStatement($db, $chat_id, $user);
        break;
    case '/payout':
    case '💸 payout':
        handlePayout($db, $chat_id, $user, $parts);
        break;
    case '/stats':
    case '📈 stats':
        handleStats($db, $chat_id, $user);
        break;
    case '/leaderboard':
    case '🏆 leaderboard':
        handleLeaderboard($db, $chat_id, $user);
        break;
    case '/daily':
    case '🎁 daily':
        handleDaily($db, $chat_id, $user);
        break;
    default:
        sendMessage($chat_id, "Unknown command ❌. Use the menu below 👇", ['keyboard' => $main_menu]);
}

if (isset($update['callback_query'])) {
    $callback = $update['callback_query'];
    $chat_id = $callback['message']['chat']['id'];
    $message_id = $callback['message']['message_id'];
    $data = $callback['data'];
    $user = $db->getUser($chat_id);

    switch ($data) {
        case 'earn':
            showEarnMenu($chat_id, $user);
            break;
        case 'balance':
            $msg = "💳 <b>Balance</b>\nCurrent: {$user['balance']} TON\nTotal Earned: {$user['total_earned']} TON\nMax: {$user['max_balance']} TON";
            editMessageText($chat_id, $message_id, $msg, [
                'inline_keyboard' => [
                    [['text' => '💰 Earn TON', 'callback_data' => 'earn'], ['text' => '👥 Referrals', 'callback_data' => 'referrals']],
                    [['text' => '🏧 Withdraw', 'callback_data' => 'withdraw'], ['text' => '⬅️ Back', 'callback_data' => 'main']]
                ]
            ]);
            break;
        case 'referrals':
            showReferralsMenu($chat_id, $user);
            break;
        case 'withdraw':
            handleWithdraw($db, $chat_id, $user, ['/withdraw', $user['balance']]);
            break;
        case 'main':
            showMainMenu($chat_id, $user);
            break;
        case 'ref_history':
            $history = $db->getReferralHistory($chat_id, 10);
            $msg = "📋 <b>Referral History</b>:\n";
            foreach ($history as $ref) {
                $date = date('M d', strtotime($ref['created_at']));
                $msg .= "• @{$ref['referred_username']} - $date\n";
            }
            editMessageText($chat_id, $message_id, $msg, [
                'inline_keyboard' => [
                    [['text' => '📤 Share Referral', 'switch_inline_query' => 'Join and earn TON!']],
                    [['text' => '🔄 Refresh', 'callback_data' => 'referrals'], ['text' => '⬅️ Back', 'callback_data' => 'main']]
                ]
            ]);
            break;
    }

    $answer_url = API_URL . "answerCallbackQuery?callback_query_id=" . $callback['id'];
    file_get_contents($answer_url);
}

if (isset($update['web_app_data'])) {
    $chat_id = $update['web_app_data']['chat']['id'];
    $data = json_decode($update['web_app_data']['data'], true);
    if ($data['action'] === 'ad_completed') {
        $data['reward'] = AD_REWARD;
        processWebAppData($chat_id, $data);
    }
}
?>

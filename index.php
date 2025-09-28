<?php
// Enable all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Bot configuration
$bot_token = getenv('BOT_TOKEN');
if (!$bot_token) {
    http_response_code(500);
    die("❌ BOT_TOKEN not set");
}

define('BOT_TOKEN', $bot_token);
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
define('USERS_FILE', '/var/www/html/users.json');
define('ERROR_LOG', '/var/www/html/error.log');

// TON Rewards
define('AD_REWARD', 0.0001);
define('REF_REWARD', 0.0005);
define('MIN_WITHDRAW_REF', 5);
define('MIN_WITHDRAW_AMOUNT', 0.01);
define('AD_COOLDOWN', 10);
define('DAILY_AD_LIMIT', 100);

// Initialize files
if (!file_exists(USERS_FILE)) {
    file_put_contents(USERS_FILE, '{}', LOCK_EX);
}
if (!file_exists(ERROR_LOG)) {
    file_put_contents(ERROR_LOG, '', LOCK_EX);
}

function logError($message) {
    file_put_contents(ERROR_LOG, date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND | LOCK_EX);
}

function loadUsers() {
    if (!file_exists(USERS_FILE)) {
        return [];
    }
    $data = file_get_contents(USERS_FILE);
    return $data ? json_decode($data, true) : [];
}

function saveUsers($users) {
    return file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT), LOCK_EX) !== false;
}

function resetDailyLimits() {
    $users = loadUsers();
    $today = date('Y-m-d');
    $reset_count = 0;
    
    foreach ($users as $chat_id => $user) {
        $last_reset = $user['last_daily_reset'] ?? '';
        if ($last_reset !== $today) {
            $users[$chat_id]['ads_watched_today'] = 0;
            $users[$chat_id]['last_daily_reset'] = $today;
            $reset_count++;
        }
    }
    
    if ($reset_count > 0 && saveUsers($users)) {
        logError("Daily limits reset for $reset_count users");
    }
}

function sendMessage($chat_id, $text, $keyboard = null) {
    $params = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    if ($keyboard) {
        $params['reply_markup'] = json_encode($keyboard);
    }
    
    $url = API_URL . 'sendMessage?' . http_build_query($params);
    $result = file_get_contents($url);
    return $result !== false;
}

function editMessageText($chat_id, $message_id, $text, $keyboard = null) {
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
    $result = file_get_contents($url);
    return $result !== false;
}

function generateRefCode($chat_id) {
    return 'TAK' . substr(md5($chat_id), 0, 7);
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
    resetDailyLimits();
    $users = loadUsers();
    
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $username = $message['chat']['username'] ?? 'Unknown';
        
        logError("Message from $chat_id: $text");
        
        // Create user if not exists
        if (!isset($users[$chat_id])) {
            $ref_code = generateRefCode($chat_id);
            $users[$chat_id] = [
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
                'referral_list' => [],
                'username' => $username
            ];
            saveUsers($users);
            logError("New user created: $chat_id");
        }
        
        if (strpos($text, '/start') === 0) {
            $parts = explode(' ', $text);
            $ref_code_param = $parts[1] ?? null;
            
            $user = $users[$chat_id];
            $welcome = "🚀 <b>Welcome to TAKONI ADS!</b>\n\n";
            
            // Handle referral
            if ($ref_code_param && $ref_code_param !== $user['ref_code'] && !isset($user['referred_by'])) {
                logError("Referral code detected: $ref_code_param");
                $referrer_found = false;
                $referrer_id = null;
                
                foreach ($users as $id => $u) {
                    if (isset($u['ref_code']) && $u['ref_code'] === $ref_code_param && $id != $chat_id) {
                        $referrer_found = true;
                        $referrer_id = $id;
                        logError("Referrer found: $referrer_id");
                        break;
                    }
                }
                
                if ($referrer_found && $referrer_id) {
                    $users[$chat_id]['referred_by'] = $referrer_id;
                    $users[$referrer_id]['referrals'] = ($users[$referrer_id]['referrals'] ?? 0) + 1;
                    $users[$referrer_id]['balance'] = ($users[$referrer_id]['balance'] ?? 0) + REF_REWARD;
                    $users[$referrer_id]['total_earned'] = ($users[$referrer_id]['total_earned'] ?? 0) + REF_REWARD;
                    $users[$referrer_id]['referral_list'][] = [
                        'user_id' => $chat_id,
                        'username' => $username,
                        'joined_at' => time(),
                        'earned_from' => REF_REWARD
                    ];
                    
                    if (saveUsers($users)) {
                        logError("Referral saved successfully");
                        $ref_message = "🎉 <b>New Referral!</b>\n\n👤 New user @$username joined using your referral link!\n💰 You earned: <b>" . REF_REWARD . " TON</b>\n👥 Total referrals: <b>" . $users[$referrer_id]['referrals'] . "</b>\n💳 New balance: <b>" . number_format($users[$referrer_id]['balance'], 6) . " TON</b>";
                        sendMessage($referrer_id, $ref_message);
                        $welcome = "🎉 <b>Welcome via Referral!</b>\n\nYou joined using @" . ($users[$referrer_id]['username'] ?? 'User') . "'s referral link!\n\n";
                    }
                }
            }
            
            $welcome .= "💰 <b>Earn TON</b> by watching ads\n👥 <b>Invite friends</b> for bonus TON\n🏧 <b>Withdraw</b> to TON wallet\n\n🔗 <b>Your referral code:</b>\n<code>" . $users[$chat_id]['ref_code'] . "</code>\n\n📊 <b>Rewards:</b>\n• Watch Ad: <b>" . AD_REWARD . " TON</b>\n• Per Referral: <b>" . REF_REWARD . " TON</b>\n\n⚠️ <b>Daily Limit:</b>\n• Maximum <b>" . DAILY_AD_LIMIT . " ads</b> per day\n\n⚠️ <b>Withdrawal Requirement:</b>\n• Minimum <b>" . MIN_WITHDRAW_REF . " referrals</b> needed";
            sendMessage($chat_id, $welcome, getMainKeyboard());
        }
        elseif (isset($users[$chat_id]['awaiting_ton_address'])) {
            $ton_address = trim($text);
            
            if (strlen($ton_address) >= 10) {
                $users[$chat_id]['ton_address_temp'] = $ton_address;
                unset($users[$chat_id]['awaiting_ton_address']);
                saveUsers($users);
                
                $response = "🔗 <b>TON Address Received</b>\n\nAddress: <code>$ton_address</code>\n\nDo you confirm this is your correct TON wallet address? You can change it anytime later.";
                sendMessage($chat_id, $response, getSaveAddressKeyboard());
            } else {
                sendMessage($chat_id, "❌ Invalid TON address. Please enter a valid TON wallet address:");
            }
        }
    }
    elseif (isset($update['callback_query'])) {
        $callback = $update['callback_query'];
        $chat_id = $callback['message']['chat']['id'];
        $message_id = $callback['message']['message_id'];
        $data = $callback['data'];
        
        logError("Callback from $chat_id: $data");
        
        $users = loadUsers();
        if (!isset($users[$chat_id])) {
            $ref_code = generateRefCode($chat_id);
            $users[$chat_id] = [
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
                'referral_list' => [],
                'username' => $callback['from']['username'] ?? 'Unknown'
            ];
            saveUsers($users);
        }
        
        $user = $users[$chat_id];
        
        switch ($data) {
            case 'earn':
                $ads_today = $user['ads_watched_today'] ?? 0;
                $ads_remaining = DAILY_AD_LIMIT - $ads_today;
                
                $response = "💰 <b>Earn TON</b>\n\n📱 <b>Watch Ads & Earn " . AD_REWARD . " TON Each</b>\n\n🎬 How to earn:\n1. Click 'Watch Ad Now' button\n2. Watch the advertisement completely\n3. Get " . AD_REWARD . " TON automatically!\n\n⏰ Cooldown: " . AD_COOLDOWN . " seconds between ads\n\n📊 <b>Daily Progress:</b>\n• Watched today: <b>$ads_today/" . DAILY_AD_LIMIT . "</b> ads\n• Remaining: <b>$ads_remaining</b> ads\n\n👥 <b>Referral Bonus:</b> " . REF_REWARD . " TON per friend";
                editMessageText($chat_id, $message_id, $response, getEarnKeyboard());
                break;
                
            case 'balance':
                $balance = $user['balance'] ?? 0;
                $referrals = $user['referrals'] ?? 0;
                $total_earned = $user['total_earned'] ?? 0;
                $ads_today = $user['ads_watched_today'] ?? 0;
                $ref_needed = max(0, MIN_WITHDRAW_REF - $referrals);
                
                $response = "💳 <b>Your TON Balance</b>\n\n💰 <b>Available Balance:</b> " . number_format($balance, 6) . " TON\n👥 <b>Total Referrals:</b> $referrals/" . MIN_WITHDRAW_REF . "\n📊 <b>Ads Watched Today:</b> $ads_today/" . DAILY_AD_LIMIT . "\n🏆 <b>Total Earned:</b> " . number_format($total_earned, 6) . " TON\n\n";
                $response .= $referrals < MIN_WITHDRAW_REF ? "❌ <b>Withdrawal Requirement:</b>\nYou need <b>$ref_needed more referrals</b> to withdraw\n\n" : "✅ <b>Withdrawal Requirement:</b>\nYou have enough referrals to withdraw!\n\n";
                $response .= "🔗 <b>Your TON Address:</b>\n<code>" . ($user['ton_address'] ?: 'Not set') . "</code>";
                editMessageText($chat_id, $message_id, $response, getBalanceKeyboard());
                break;
                
            case 'referrals':
                $ref_code = $user['ref_code'];
                $referrals = $user['referrals'] ?? 0;
                $ref_earnings = $referrals * REF_REWARD;
                $ref_needed = max(0, MIN_WITHDRAW_REF - $referrals);
                
                $response = "👥 <b>Referral System</b>\n\n🔗 <b>Your Referral Code:</b>\n<code>$ref_code</code>\n\n📊 <b>Your Referral Stats:</b>\n• Total Referrals: <b>$referrals/" . MIN_WITHDRAW_REF . "</b>\n• Referral Earnings: <b>" . number_format($ref_earnings, 6) . " TON</b>\n• Needed for withdrawal: <b>$ref_needed more</b>\n\n";
                
                if (!empty($user['referral_list'])) {
                    $response .= "📋 <b>Your Referrals:</b>\n";
                    $count = 0;
                    foreach ($user['referral_list'] as $ref) {
                        $count++;
                        $ref_username = $ref['username'] !== 'Unknown' ? "@" . $ref['username'] : "User" . $ref['user_id'];
                        $date = date('d.m.Y', $ref['joined_at']);
                        $response .= "$count. $ref_username - $date\n";
                        if ($count >= 5) break;
                    }
                    $response .= "\n";
                }
                
                $response .= "💰 <b>How it works:</b>\n• Share your referral link\n• Earn <b>" . REF_REWARD . " TON</b> per friend\n• Minimum <b>" . MIN_WITHDRAW_REF . " referrals</b> required\n\n📱 <b>Your Referral Link:</b>\nhttps://t.me/takoniAdsBot?start=$ref_code";
                editMessageText($chat_id, $message_id, $response, getReferralsKeyboard());
                break;
                
            case 'share_referral':
                $ref_code = $user['ref_code'];
                $ref_link = "https://t.me/takoniAdsBot?start=$ref_code";
                $share_text = "🎉 Join TAKONI ADS and earn TON cryptocurrency!\n\n💰 Watch ads and earn " . AD_REWARD . " TON each\n👥 Use my referral link for bonus: $ref_link\n\n🚀 Start earning now!";
                
                $keyboard = [
                    'inline_keyboard' => [
                        [['text' => '📤 Share', 'url' => "https://t.me/share/url?url=" . urlencode($ref_link) . "&text=" . urlencode($share_text)]],
                        [['text' => '⬅️ Back', 'callback_data' => 'referrals']]
                    ]
                ];
                editMessageText($chat_id, $message_id, "📤 <b>Share Referral Link</b>\n\nClick below to share:", $keyboard);
                break;
                
            case 'withdraw':
                $balance = $user['balance'] ?? 0;
                $ton_address = $user['ton_address'] ?? '';
                $referrals = $user['referrals'] ?? 0;
                
                $response = "🏧 <b>Withdraw TON</b>\n\n💰 <b>Available Balance:</b> " . number_format($balance, 6) . " TON\n👥 <b>Your Referrals:</b> $referrals/" . MIN_WITHDRAW_REF . "\n🔗 <b>TON Address:</b> " . ($ton_address ? "<code>$ton_address</code>\n\nYou can change your address anytime." : "Not set") . "\n\n";
                
                $errors = [];
                if ($balance < MIN_WITHDRAW_AMOUNT) {
                    $errors[] = "❌ Minimum withdrawal: " . MIN_WITHDRAW_AMOUNT . " TON";
                }
                if ($referrals < MIN_WITHDRAW_REF) {
                    $errors[] = "❌ Need " . (MIN_WITHDRAW_REF - $referrals) . " more referrals";
                }
                if (!$ton_address) {
                    $errors[] = "❌ Please set a TON address";
                }
                
                if ($errors) {
                    $response .= implode("\n", $errors) . "\n\n";
                } else {
                    $response .= "✅ Ready to withdraw! Click below to proceed.\n";
                }
                
                editMessageText($chat_id, $message_id, $response, getWithdrawKeyboard($ton_address !== ''));
                break;
                
            case 'enter_ton_address':
                $users[$chat_id]['awaiting_ton_address'] = true;
                saveUsers($users);
                sendMessage($chat_id, "💳 Please enter your TON wallet address:");
                break;
                
            case 'save_ton_address':
                if (isset($users[$chat_id]['ton_address_temp'])) {
                    $users[$chat_id]['ton_address'] = $users[$chat_id]['ton_address_temp'];
                    unset($users[$chat_id]['ton_address_temp']);
                    saveUsers($users);
                    sendMessage($chat_id, "✅ TON address saved successfully! You can change it anytime from the Withdraw menu.", getMainKeyboard());
                } else {
                    sendMessage($chat_id, "❌ No address provided. Please enter your TON address again.", getMainKeyboard());
                }
                break;
                
            case 'submit_withdrawal':
                $balance = $user['balance'] ?? 0;
                $referrals = $user['referrals'] ?? 0;
                $ton_address = $user['ton_address'] ?? '';
                
                if ($balance >= MIN_WITHDRAW_AMOUNT && $referrals >= MIN_WITHDRAW_REF && $ton_address) {
                    // Simulate withdrawal (replace with actual TON API call in production)
                    $users[$chat_id]['balance'] = 0;
                    saveUsers($users);
                    $response = "🏧 <b>Withdrawal Requested!</b>\n\nAmount: <b>" . number_format($balance, 6) . " TON</b>\nTo: <code>$ton_address</code>\n\nYour withdrawal will be processed soon.";
                    sendMessage($chat_id, $response, getMainKeyboard());
                    logError("Withdrawal requested by $chat_id: $balance TON to $ton_address");
                } else {
                    $response = "❌ Cannot process withdrawal:\n";
                    if ($balance < MIN_WITHDRAW_AMOUNT) $response .= "• Balance too low (min: " . MIN_WITHDRAW_AMOUNT . " TON)\n";
                    if ($referrals < MIN_WITHDRAW_REF) $response .= "• Need " . (MIN_WITHDRAW_REF - $referrals) . " more referrals\n";
                    if (!$ton_address) $response .= "• No TON address set";
                    sendMessage($chat_id, $response, getWithdrawKeyboard($ton_address !== ''));
                }
                break;
                
            case 'main_menu':
                $response = "🚀 <b>TAKONI ADS</b>\n\n💰 Earn TON by watching ads\n👥 Invite friends for bonus TON\n🏧 Withdraw to your TON wallet\n\nSelect an option below:";
                editMessageText($chat_id, $message_id, $response, getMainKeyboard());
                break;
        }
    }
}

// Handle incoming webhook
$update = json_decode(file_get_contents('php://input'), true);
if ($update) {
    processUpdate($update);
} else {
    http_response_code(200);
    echo "OK";
}

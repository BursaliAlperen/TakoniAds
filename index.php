<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$bot_token = getenv('BOT_TOKEN');
if (!$bot_token) {
    http_response_code(500);
    die("❌ BOT_TOKEN not set");
}

define('BOT_TOKEN', $bot_token);
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
define('USERS_FILE', 'users.json');
define('ERROR_LOG', 'error.log');
define('AD_REWARD', 0.0001);
define('REF_REWARD', 0.0005);
define('MIN_WITHDRAW_REF', 5);
define('MIN_WITHDRAW_AMOUNT', 0.01);
define('AD_COOLDOWN', 10);
define('DAILY_AD_LIMIT', 100);

// Kanal bilgileri - ID ile birlikte
define('CHANNEL_USERNAME', '@TakoniFinance');
define('CHANNEL_ID', '-1002855918077'); // Eksi işareti ile
define('CHANNEL_URL', 'https://t.me/TakoniFinance');

if (!file_exists(USERS_FILE)) file_put_contents(USERS_FILE, '{}');
if (!file_exists(ERROR_LOG)) file_put_contents(ERROR_LOG, '');

function logError($message) {
    @file_put_contents(ERROR_LOG, date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

function loadUsers() {
    if (!file_exists(USERS_FILE)) return array();
    $data = @file_get_contents(USERS_FILE);
    if ($data === false) return array();
    $users = json_decode($data, true);
    return is_array($users) ? $users : array();
}

function saveUsers($users) {
    return @file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT)) !== false;
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
    if ($reset_count > 0) {
        saveUsers($users);
        logError("Daily limits reset for " . $reset_count . " users");
    }
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
    return 'TAK' . substr(md5($chat_id), 0, 7);
}

// DÜZELTİLMİŞ KANAL KONTROL FONKSİYONU - ID ile
function isUserInChannel($chat_id) {
    // Önce ID ile dene
    $method = 'getChatMember';
    $params = array(
        'chat_id' => CHANNEL_ID, // ID ile deniyoruz
        'user_id' => $chat_id
    );
    
    $url = API_URL . $method . '?' . http_build_query($params);
    $response = @file_get_contents($url);
    
    logError("Channel check with ID for user " . $chat_id);
    
    if ($response !== false) {
        $data = json_decode($response, true);
        if (isset($data['ok']) && $data['ok'] === true) {
            $status = $data['result']['status'];
            logError("User status with ID: " . $status);
            
            $valid_statuses = ['member', 'administrator', 'creator', 'restricted'];
            if (in_array($status, $valid_statuses)) {
                logError("User is member with ID check");
                return true;
            }
        }
    }
    
    // ID ile olmazsa username ile dene
    logError("Trying with username...");
    $params = array(
        'chat_id' => CHANNEL_USERNAME,
        'user_id' => $chat_id
    );
    
    $url = API_URL . $method . '?' . http_build_query($params);
    $response = @file_get_contents($url);
    
    if ($response !== false) {
        $data = json_decode($response, true);
        if (isset($data['ok']) && $data['ok'] === true) {
            $status = $data['result']['status'];
            logError("User status with username: " . $status);
            
            $valid_statuses = ['member', 'administrator', 'creator', 'restricted'];
            if (in_array($status, $valid_statuses)) {
                logError("User is member with username check");
                return true;
            }
        }
    }
    
    logError("User is NOT member of channel");
    return false;
}

// KANAL KONTROLÜNÜ ATLA - TEST MODU
function skipChannelCheck($chat_id) {
    logError("Channel check SKIPPED for user: " . $chat_id);
    return true; // Her zaman true döndür
}

function isValidTONAddress($address) {
    $address = trim($address);
    $patterns = array(
        '/^EQ[0-9a-zA-Z_-]{48}$/',
        '/^UQ[0-9a-zA-Z_-]{48}$/',
        '/^Ef[0-9a-zA-Z_-]{48}$/',
        '/^Uf[0-9a-zA-Z_-]{48}$/',
        '/^0:[0-9a-fA-F]{64}$/',
    );
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $address)) return true;
    }
    return preg_match('/^EQ[a-zA-Z0-9_-]{44,50}$/', $address);
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
        array(array('text' => '📤 Share Referral', 'callback_data' => 'share_referral')),
        array(array('text' => '🔄 Refresh', 'callback_data' => 'referrals')),
        array(array('text' => '⬅️ Back to Main', 'callback_data' => 'main_menu'))
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

function getSaveAddressKeyboard() {
    return array('inline_keyboard' => array(
        array(array('text' => '💾 Save Address', 'callback_data' => 'save_ton_address')),
        array(array('text' => '❌ Cancel', 'callback_data' => 'main_menu'))
    ));
}

function getChannelJoinKeyboard() {
    return array('inline_keyboard' => array(
        array(array('text' => '📢 Join Channel', 'url' => CHANNEL_URL)),
        array(array('text' => '✅ I Joined', 'callback_data' => 'check_join'))
    ));
}

function processUpdate($update) {
    resetDailyLimits();
    $users = loadUsers();
    
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $username = $message['chat']['username'] ?? 'Unknown';
        
        logError("Message from " . $chat_id . ": " . $text);
        
        if (!isset($users[$chat_id])) {
            $ref_code = generateRefCode($chat_id);
            $users[$chat_id] = array(
                'balance' => 0, 'referrals' => 0, 'ref_code' => $ref_code,
                'last_ad_watch' => 0, 'ads_watched_today' => 0, 'last_daily_reset' => date('Y-m-d'),
                'ton_address' => '', 'total_earned' => 0, 'created_at' => time(),
                'referred_by' => null, 'referral_list' => array(), 'username' => $username,
                'max_balance' => 0, 'channel_joined' => false
            );
            saveUsers($users);
            logError("New user created: " . $chat_id);
        }
        
        // TEST MODU: Kanal kontrolünü atla - YORUM SATIRINI KALDIR
        if (!$users[$chat_id]['channel_joined']) {
            if (strpos($text, '/start') === 0) {
                // TEST: Kanal kontrolünü atla ve direkt kabul et
                $users[$chat_id]['channel_joined'] = true;
                saveUsers($users);
                logError("Channel check BYPASSED for user: " . $chat_id);
                processStartCommand($users, $chat_id, $text, $username);
                
                /*
                // NORMAL MOD: Kanal kontrolü yap
                $channel_joined = isUserInChannel($chat_id);
                if ($channel_joined) {
                    $users[$chat_id]['channel_joined'] = true;
                    saveUsers($users);
                    processStartCommand($users, $chat_id, $text, $username);
                } else {
                    sendMessage($chat_id, "📢 <b>Channel Membership Required</b>\n\nTo use this bot, you must join our official channel:\n" . CHANNEL_USERNAME . "\n\nAfter joining, click the '✅ I Joined' button below.", getChannelJoinKeyboard());
                }
                */
            }
            return;
        }
        
        if (strpos($text, '/start') === 0) {
            processStartCommand($users, $chat_id, $text, $username);
        } elseif (isset($users[$chat_id]['awaiting_ton_address'])) {
            $ton_address = trim($text);
            if (isValidTONAddress($ton_address)) {
                $users[$chat_id]['ton_address_temp'] = $ton_address;
                unset($users[$chat_id]['awaiting_ton_address']);
                saveUsers($users);
                $response = "🔗 <b>TON Address Received</b>\n\n✅ <b>Valid TON Address</b>\n\nAddress: <code>" . $ton_address . "</code>\n\nClick 'Save Address' to confirm:";
                sendMessage($chat_id, $response, getSaveAddressKeyboard());
            } else {
                sendMessage($chat_id, "❌ <b>Invalid TON Address</b>\n\nPlease check your address and try again.");
            }
        }
    } elseif (isset($update['callback_query'])) {
        $callback = $update['callback_query'];
        $chat_id = $callback['message']['chat']['id'];
        $message_id = $callback['message']['message_id'];
        $data = $callback['data'];
        
        logError("Callback from " . $chat_id . ": " . $data);
        
        $users = loadUsers();
        if (!isset($users[$chat_id])) {
            $ref_code = generateRefCode($chat_id);
            $users[$chat_id] = array(
                'balance' => 0, 'referrals' => 0, 'ref_code' => $ref_code,
                'last_ad_watch' => 0, 'ads_watched_today' => 0, 'last_daily_reset' => date('Y-m-d'),
                'ton_address' => '', 'total_earned' => 0, 'created_at' => time(),
                'referred_by' => null, 'referral_list' => array(), 'max_balance' => 0,
                'channel_joined' => false
            );
            saveUsers($users);
        }
        
        $user = $users[$chat_id];
        
        if ($data == 'check_join') {
            // TEST: Kanal kontrolünü atla ve direkt kabul et
            $users[$chat_id]['channel_joined'] = true;
            saveUsers($users);
            logError("Channel check BYPASSED in callback for user: " . $chat_id);
            editMessageText($chat_id, $message_id, "✅ <b>Thank you for joining!</b>\n\nNow you can start earning TON!", getMainKeyboard());
            
            /*
            // NORMAL: Gerçek kontrol yap
            $channel_joined = isUserInChannel($chat_id);
            if ($channel_joined) {
                $users[$chat_id]['channel_joined'] = true;
                saveUsers($users);
                editMessageText($chat_id, $message_id, "✅ <b>Thank you for joining!</b>\n\nNow you can start earning TON!", getMainKeyboard());
            } else {
                editMessageText($chat_id, $message_id, "❌ <b>You haven't joined the channel yet!</b>\n\nPlease join " . CHANNEL_USERNAME . " first, then click '✅ I Joined'", getChannelJoinKeyboard());
            }
            */
            return;
        }
        
        // Eğer kanala katılmamışsa ama callback gelmişse, direkt kabul et
        if (!$users[$chat_id]['channel_joined']) {
            $users[$chat_id]['channel_joined'] = true;
            saveUsers($users);
            logError("Auto-joined user in callback: " . $chat_id);
        }
        
        switch ($data) {
            case 'main_menu':
                $user = $users[$chat_id];
                $welcome = "🚀 <b>Welcome to TAKONI ADS!</b>\n\n";
                $welcome .= "💰 <b>Earn TON</b> by watching ads\n";
                $welcome .= "👥 <b>Invite friends</b> for bonus TON\n";
                $welcome .= "🏧 <b>Withdraw</b> to TON wallet\n\n";
                $welcome .= "🔗 <b>Your referral code:</b>\n";
                $welcome .= "<code>" . $user['ref_code'] . "</code>\n\n";
                $welcome .= "📊 <b>Rewards:</b>\n";
                $welcome .= "• Watch Ad: <b>" . AD_REWARD . " TON</b>\n";
                $welcome .= "• Per Referral: <b>" . REF_REWARD . " TON</b>\n\n";
                $welcome .= "⚠️ <b>Daily Limit:</b>\n";
                $welcome .= "• Maximum <b>" . DAILY_AD_LIMIT . " ads</b> per day\n\n";
                $welcome .= "⚠️ <b>Withdrawal Requirement:</b>\n";
                $welcome .= "• Minimum <b>" . MIN_WITHDRAW_REF . " referrals</b> needed";
                editMessageText($chat_id, $message_id, $welcome, getMainKeyboard());
                break;
                
            case 'earn':
                $ads_today = $user['ads_watched_today'] ?? 0;
                $ads_remaining = DAILY_AD_LIMIT - $ads_today;
                $max_balance = $user['max_balance'] ?? $user['balance'];
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
                $response .= "• Highest: <b>" . number_format($max_balance, 6) . " TON</b>\n";
                $response .= "• Total Earned: <b>" . number_format($user['total_earned'], 6) . " TON</b>\n\n";
                editMessageText($chat_id, $message_id, $response, getEarnKeyboard());
                break;
                
            case 'balance':
                $max_balance = $user['max_balance'] ?? $user['balance'];
                $response = "💳 <b>Your Balance</b>\n\n";
                $response .= "💰 Available: <b>" . number_format($user['balance'], 6) . " TON</b>\n";
                $response .= "🏆 Highest Balance: <b>" . number_format($max_balance, 6) . " TON</b>\n";
                $response .= "📈 Total Earned: <b>" . number_format($user['total_earned'], 6) . " TON</b>\n";
                $response .= "👥 Referrals: <b>" . $user['referrals'] . "</b>\n\n";
                editMessageText($chat_id, $message_id, $response, getBalanceKeyboard());
                break;
                
            case 'referrals':
                $ref_earnings = $user['referrals'] * REF_REWARD;
                $response = "👥 <b>Your Referrals</b>\n\n";
                $response .= "📊 <b>Statistics:</b>\n";
                $response .= "• Total Referrals: <b>" . $user['referrals'] . "</b>\n";
                $response .= "• Earned from Referrals: <b>" . number_format($ref_earnings, 6) . " TON</b>\n";
                $response .= "• Your Referral Code: <code>" . $user['ref_code'] . "</code>\n\n";
                $response .= "💡 <b>How to invite:</b>\n";
                $response .= "Share your referral link and earn " . REF_REWARD . " TON for each friend who joins!\n\n";
                editMessageText($chat_id, $message_id, $response, getReferralsKeyboard());
                break;
                
            case 'withdraw':
                $has_address = !empty($user['ton_address']);
                $response = "🏧 <b>Withdraw TON</b>\n\n";
                $response .= "📋 <b>Requirements:</b>\n";
                $response .= "• Minimum " . MIN_WITHDRAW_REF . " referrals\n";
                $response .= "• Minimum " . MIN_WITHDRAW_AMOUNT . " TON balance\n\n";
                $response .= "📊 <b>Your Stats:</b>\n";
                $response .= "• Referrals: <b>" . $user['referrals'] . "/" . MIN_WITHDRAW_REF . "</b>\n";
                $response .= "• Balance: <b>" . number_format($user['balance'], 6) . "/" . MIN_WITHDRAW_AMOUNT . " TON</b>\n\n";
                if ($has_address) {
                    $response .= "💳 <b>Your TON Address:</b>\n<code>" . $user['ton_address'] . "</code>\n\n";
                } else {
                    $response .= "❌ <b>No TON address set</b>\n\nPlease set your TON wallet address first.\n\n";
                }
                editMessageText($chat_id, $message_id, $response, getWithdrawKeyboard($has_address));
                break;
                
            case 'enter_ton_address':
                $users[$chat_id]['awaiting_ton_address'] = true;
                saveUsers($users);
                sendMessage($chat_id, "💳 <b>Enter TON Address</b>\n\nPlease send your TON wallet address now:");
                break;
                
            case 'save_ton_address':
                if (isset($user['ton_address_temp'])) {
                    $users[$chat_id]['ton_address'] = $user['ton_address_temp'];
                    unset($users[$chat_id]['ton_address_temp']);
                    saveUsers($users);
                    editMessageText($chat_id, $message_id, "✅ <b>TON Address Saved!</b>\n\nYour withdrawal address has been updated.", getWithdrawKeyboard(true));
                }
                break;
                
            case 'submit_withdrawal':
                if ($user['referrals'] < MIN_WITHDRAW_REF) {
                    $response = "❌ <b>Insufficient Referrals</b>\n\nYou need at least " . MIN_WITHDRAW_REF . " referrals to withdraw. You have " . $user['referrals'] . ".";
                    editMessageText($chat_id, $message_id, $response, getWithdrawKeyboard(!empty($user['ton_address'])));
                    break;
                }
                if ($user['balance'] < MIN_WITHDRAW_AMOUNT) {
                    $response = "❌ <b>Insufficient Balance</b>\n\nMinimum withdrawal amount is " . MIN_WITHDRAW_AMOUNT . " TON. You have " . number_format($user['balance'], 6) . " TON.";
                    editMessageText($chat_id, $message_id, $response, getWithdrawKeyboard(!empty($user['ton_address'])));
                    break;
                }
                if (empty($user['ton_address'])) {
                    editMessageText($chat_id, $message_id, "❌ <b>No TON address set</b>\n\nPlease set your TON wallet address first.", getWithdrawKeyboard(false));
                    break;
                }
                $withdraw_amount = $user['balance'];
                $users[$chat_id]['balance'] = 0;
                saveUsers($users);
                $response = "✅ <b>Withdrawal Request Submitted!</b>\n\nYour request for " . number_format($withdraw_amount, 6) . " TON has been received and will be processed within 24 hours.";
                editMessageText($chat_id, $message_id, $response, getMainKeyboard());
                break;
                
            case 'share_referral':
                $ref_link = "https://t.me/" . $callback['message']['chat']['username'] . "?start=" . $user['ref_code'];
                $response = "📤 <b>Your Referral Link:</b>\n\n" . $ref_link . "\n\nShare this link with your friends!";
                editMessageText($chat_id, $message_id, $response, getReferralsKeyboard());
                break;
        }
    }
}

function processStartCommand(&$users, $chat_id, $text, $username) {
    $parts = explode(' ', $text);
    $ref_code_param = $parts[1] ?? null;
    $user = $users[$chat_id];
    $welcome = "🚀 <b>Welcome to TAKONI ADS!</b>\n\n";
    
    if ($ref_code_param && $ref_code_param !== $user['ref_code'] && !isset($user['referred_by'])) {
        logError("Referral code detected: " . $ref_code_param);
        $referrer_found = false;
        $referrer_id = null;
        $referrer_username = '';
        
        foreach ($users as $id => $u) {
            if (isset($u['ref_code']) && $u['ref_code'] === $ref_code_param && $id != $chat_id) {
                $referrer_found = true;
                $referrer_id = $id;
                $referrer_username = $u['username'] ?? 'User';
                logError("Referrer found: " . $referrer_id);
                break;
            }
        }
        
        if ($referrer_found && $referrer_id) {
            $users[$chat_id]['referred_by'] = $referrer_id;
            $users[$chat_id]['username'] = $username;
            $current_referrals = $users[$referrer_id]['referrals'] ?? 0;
            $current_balance = $users[$referrer_id]['balance'] ?? 0;
            $current_total_earned = $users[$referrer_id]['total_earned'] ?? 0;
            $current_max_balance = $users[$referrer_id]['max_balance'] ?? 0;
            
            $users[$referrer_id]['referrals'] = $current_referrals + 1;
            $users[$referrer_id]['balance'] = $current_balance + REF_REWARD;
            $users[$referrer_id]['total_earned'] = $current_total_earned + REF_REWARD;
            
            $new_balance = $current_balance + REF_REWARD;
            if ($new_balance > $current_max_balance) {
                $users[$referrer_id]['max_balance'] = $new_balance;
            }
            
            if (!isset($users[$referrer_id]['referral_list'])) {
                $users[$referrer_id]['referral_list'] = array();
            }
            
            $users[$referrer_id]['referral_list'][] = array(
                'user_id' => $chat_id, 'username' => $username,
                'joined_at' => time(), 'earned_from' => REF_REWARD
            );
            
            if (saveUsers($users)) {
                logError("Referral saved successfully");
                $ref_message = "🎉 <b>New Referral!</b>\n\n👤 New user @" . $username . " joined using your referral link!\n💰 You earned: <b>" . REF_REWARD . " TON</b>\n👥 Total referrals: <b>" . $users[$referrer_id]['referrals'] . "</b>\n💳 New balance: <b>" . number_format($users[$referrer_id]['balance'], 6) . " TON</b>";
                sendMessage($referrer_id, $ref_message);
                $welcome = "🎉 <b>Welcome via Referral!</b>\n\nYou joined using @" . $referrer_username . "'s referral link!\n\n";
            }
        }
    }
    
    $welcome .= "💰 <b>Earn TON</b> by watching ads\n";
    $welcome .= "👥 <b>Invite friends</b> for bonus TON\n";
    $welcome .= "🏧 <b>Withdraw</b> to TON wallet\n\n";
    $welcome .= "🔗 <b>Your referral code:</b>\n";
    $welcome .= "<code>" . $users[$chat_id]['ref_code'] . "</code>\n\n";
    $welcome .= "📊 <b>Rewards:</b>\n";
    $welcome .= "• Watch Ad: <b>" . AD_REWARD . " TON</b>\n";
    $welcome .= "• Per Referral: <b>" . REF_REWARD . " TON</b>\n\n";
    $welcome .= "⚠️ <b>Daily Limit:</b>\n";
    $welcome .= "• Maximum <b>" . DAILY_AD_LIMIT . " ads</b> per day\n\n";
    $welcome .= "⚠️ <b>Withdrawal Requirement:</b>\n";
    $welcome .= "• Minimum <b>" . MIN_WITHDRAW_REF . " referrals</b> needed";
    
    sendMessage($chat_id, $welcome, getMainKeyboard());
}

$input = file_get_contents('php://input');
$update = json_decode($input, true);

if ($update) {
    processUpdate($update);
} else {
    http_response_code(400);
    die('Invalid update');
}
?>

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
define('USERS_FILE', 'users.json');
define('ERROR_LOG', 'error.log');

// TON Rewards
define('AD_REWARD', 0.0001);
define('REF_REWARD', 0.0005);
define('MIN_WITHDRAW_REF', 5);
define('MIN_WITHDRAW_AMOUNT', 0.01);
define('AD_COOLDOWN', 10);
define('DAILY_AD_LIMIT', 100);

// Initialize files
if (!file_exists(USERS_FILE)) {
    file_put_contents(USERS_FILE, '{}');
}
if (!file_exists(ERROR_LOG)) {
    file_put_contents(ERROR_LOG, '');
}

function logError($message) {
    @file_put_contents(ERROR_LOG, date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

function loadUsers() {
    if (!file_exists(USERS_FILE)) {
        return array();
    }
    $data = @file_get_contents(USERS_FILE);
    if ($data === false) {
        return array();
    }
    $users = json_decode($data, true);
    return is_array($users) ? $users : array();
}

function saveUsers($users) {
    $result = @file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT));
    if ($result === false) {
        logError("Failed to save users data");
        return false;
    }
    return true;
}

function resetDailyLimits() {
    $users = loadUsers();
    $today = date('Y-m-d');
    $reset_count = 0;
    
    foreach ($users as $chat_id => $user) {
        $last_reset = isset($user['last_daily_reset']) ? $user['last_daily_reset'] : '';
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
    $params = array(
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    );
    
    if ($keyboard) {
        $params['reply_markup'] = json_encode($keyboard);
    }
    
    $url = API_URL . 'sendMessage?' . http_build_query($params);
    $result = @file_get_contents($url);
    return $result !== false;
}

function editMessageText($chat_id, $message_id, $text, $keyboard = null) {
    $params = array(
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    );
    
    if ($keyboard) {
        $params['reply_markup'] = json_encode($keyboard);
    }
    
    $url = API_URL . 'editMessageText?' . http_build_query($params);
    $result = @file_get_contents($url);
    return $result !== false;
}

function generateRefCode($chat_id) {
    return 'TAK' . substr(md5($chat_id), 0, 7);
}

// TON Adresi Doğrulama Fonksiyonu
function isValidTONAddress($address) {
    $address = trim($address);
    
    // TON adres formatları
    $patterns = array(
        '/^EQ[0-9a-zA-Z]{48}$/', // EQ ile başlayan 48 karakter
        '/^UQ[0-9a-zA-Z]{48}$/', // UQ ile başlayan 48 karakter
        '/^Ef[0-9a-zA-Z]{48}$/', // Ef ile başlayan 48 karakter (bounceable)
        '/^Uf[0-9a-zA-Z]{48}$/', // Uf ile başlayan 48 karakter (bounceable)
    );
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $address)) {
            return true;
        }
    }
    
    return false;
}

function getMainKeyboard() {
    return array(
        'inline_keyboard' => array(
            array(
                array('text' => '💰 Earn TON', 'callback_data' => 'earn'),
                array('text' => '💳 Balance', 'callback_data' => 'balance')
            ),
            array(
                array('text' => '👥 Referrals', 'callback_data' => 'referrals'),
                array('text' => '🏧 Withdraw', 'callback_data' => 'withdraw')
            )
        )
    );
}

function getEarnKeyboard() {
    $webapp_url = "https://takoniads.onrender.com/webapp.html";
    
    return array(
        'inline_keyboard' => array(
            array(
                array('text' => '📱 Watch Ad (' . AD_REWARD . ' TON)', 'web_app' => array('url' => $webapp_url))
            ),
            array(
                array('text' => '🔄 Check Balance', 'callback_data' => 'balance')
            ),
            array(
                array('text' => '⬅️ Back to Main', 'callback_data' => 'main_menu')
            )
        )
    );
}

function getBalanceKeyboard() {
    return array(
        'inline_keyboard' => array(
            array(
                array('text' => '📱 Watch Another Ad', 'callback_data' => 'earn')
            ),
            array(
                array('text' => '🔄 Refresh Balance', 'callback_data' => 'balance')
            ),
            array(
                array('text' => '⬅️ Back to Main', 'callback_data' => 'main_menu')
            )
        )
    );
}

function getReferralsKeyboard() {
    return array(
        'inline_keyboard' => array(
            array(
                array('text' => '📤 Share Referral', 'callback_data' => 'share_referral')
            ),
            array(
                array('text' => '🔄 Refresh', 'callback_data' => 'referrals')
            ),
            array(
                array('text' => '⬅️ Back to Main', 'callback_data' => 'main_menu')
            )
        )
    );
}

function getWithdrawKeyboard($has_address = false) {
    if ($has_address) {
        return array(
            'inline_keyboard' => array(
                array(
                    array('text' => '🚀 Submit Withdrawal', 'callback_data' => 'submit_withdrawal')
                ),
                array(
                    array('text' => '✏️ Change Address', 'callback_data' => 'enter_ton_address')
                ),
                array(
                    array('text' => '⬅️ Back to Main', 'callback_data' => 'main_menu')
                )
            )
        );
    } else {
        return array(
            'inline_keyboard' => array(
                array(
                    array('text' => '💳 Enter TON Address', 'callback_data' => 'enter_ton_address')
                ),
                array(
                    array('text' => '⬅️ Back to Main', 'callback_data' => 'main_menu')
                )
            )
        );
    }
}

function getSaveAddressKeyboard() {
    return array(
        'inline_keyboard' => array(
            array(
                array('text' => '💾 Save Address', 'callback_data' => 'save_ton_address')
            ),
            array(
                array('text' => '❌ Cancel', 'callback_data' => 'main_menu')
            )
        )
    );
}

function processUpdate($update) {
    // Günlük limitleri sıfırla
    resetDailyLimits();
    
    $users = loadUsers();
    
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $username = $message['chat']['username'] ?? 'Unknown';
        
        logError("Message from " . $chat_id . ": " . $text);
        
        // Create user if not exists
        if (!isset($users[$chat_id])) {
            $ref_code = generateRefCode($chat_id);
            $users[$chat_id] = array(
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
                'referral_list' => array(),
                'username' => $username
            );
            saveUsers($users);
            logError("New user created: " . $chat_id);
        }
        
        if (strpos($text, '/start') === 0) {
            $parts = explode(' ', $text);
            $ref_code_param = isset($parts[1]) ? $parts[1] : null;
            
            $user = $users[$chat_id];
            $welcome = "🚀 <b>Welcome to TAKONI ADS!</b>\n\n";
            
            // REFERANS KONTROLÜ
            if ($ref_code_param && $ref_code_param !== $user['ref_code'] && !isset($user['referred_by'])) {
                logError("Referral code detected: " . $ref_code_param);
                
                $referrer_found = false;
                $referrer_id = null;
                $referrer_username = '';
                
                foreach ($users as $id => $u) {
                    if (isset($u['ref_code']) && $u['ref_code'] === $ref_code_param && $id != $chat_id) {
                        $referrer_found = true;
                        $referrer_id = $id;
                        $referrer_username = isset($u['username']) ? $u['username'] : 'User';
                        logError("Referrer found: " . $referrer_id . " with username: " . $referrer_username);
                        break;
                    }
                }
                
                if ($referrer_found && $referrer_id) {
                    logError("Processing referral for referrer: " . $referrer_id);
                    
                    // Yeni kullanıcıyı güncelle
                    $users[$chat_id]['referred_by'] = $referrer_id;
                    $users[$chat_id]['username'] = $username;
                    
                    // Referans vereni güncelle
                    $current_referrals = isset($users[$referrer_id]['referrals']) ? $users[$referrer_id]['referrals'] : 0;
                    $current_balance = isset($users[$referrer_id]['balance']) ? $users[$referrer_id]['balance'] : 0;
                    $current_total_earned = isset($users[$referrer_id]['total_earned']) ? $users[$referrer_id]['total_earned'] : 0;
                    
                    $users[$referrer_id]['referrals'] = $current_referrals + 1;
                    $users[$referrer_id]['balance'] = $current_balance + REF_REWARD;
                    $users[$referrer_id]['total_earned'] = $current_total_earned + REF_REWARD;
                    
                    // Referans listesine ekle
                    if (!isset($users[$referrer_id]['referral_list'])) {
                        $users[$referrer_id]['referral_list'] = array();
                    }
                    
                    $users[$referrer_id]['referral_list'][] = array(
                        'user_id' => $chat_id,
                        'username' => $username,
                        'joined_at' => time(),
                        'earned_from' => REF_REWARD
                    );
                    
                    if (saveUsers($users)) {
                        logError("Referral saved successfully - Referrer: " . $referrer_id . " now has " . $users[$referrer_id]['referrals'] . " referrals");
                        
                        // Referans vereni bilgilendir
                        $ref_message = "🎉 <b>New Referral!</b>\n\n";
                        $ref_message .= "👤 New user @" . $username . " joined using your referral link!\n";
                        $ref_message .= "💰 You earned: <b>" . REF_REWARD . " TON</b>\n";
                        $ref_message .= "👥 Total referrals: <b>" . $users[$referrer_id]['referrals'] . "</b>\n";
                        $ref_message .= "💳 New balance: <b>" . number_format($users[$referrer_id]['balance'], 6) . " TON</b>";
                        
                        if (sendMessage($referrer_id, $ref_message)) {
                            logError("Referral notification sent to: " . $referrer_id);
                        } else {
                            logError("Failed to send referral notification to: " . $referrer_id);
                        }
                        
                        $welcome = "🎉 <b>Welcome via Referral!</b>\n\n";
                        $welcome .= "You joined using @" . $referrer_username . "'s referral link!\n\n";
                    } else {
                        logError("FAILED to save referral data");
                    }
                } else {
                    logError("Referrer NOT FOUND for code: " . $ref_code_param);
                }
            } else {
                if (isset($user['referred_by'])) {
                    logError("User already referred by: " . $user['referred_by']);
                }
                if ($ref_code_param === $user['ref_code']) {
                    logError("User used own ref code");
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
        } elseif (isset($users[$chat_id]['awaiting_ton_address'])) {
            $ton_address = trim($text);
            
            // TON ADRESİ DOĞRULAMA
            if (isValidTONAddress($ton_address)) {
                $users[$chat_id]['ton_address_temp'] = $ton_address;
                unset($users[$chat_id]['awaiting_ton_address']);
                saveUsers($users);
                
                $response = "🔗 <b>TON Address Received</b>\n\n";
                $response .= "✅ <b>Valid TON Address</b>\n\n";
                $response .= "Address: <code>" . $ton_address . "</code>\n\n";
                $response .= "Click 'Save Address' to confirm:";
                
                sendMessage($chat_id, $response, getSaveAddressKeyboard());
            } else {
                $response = "❌ <b>Invalid TON Address</b>\n\n";
                $response .= "Please enter a valid TON wallet address.\n\n";
                $response .= "📍 <b>Valid TON Address Formats:</b>\n";
                $response .= "• <code>EQxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx</code>\n";
                $response .= "• <code>UQxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx</code>\n";
                $response .= "• <code>Efxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx</code>\n";
                $response .= "• <code>Ufxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx</code>\n\n";
                $response .= "📍 <b>Where to find your TON address?</b>\n";
                $response .= "• @wallet bot\n• Tonkeeper app\n• Trust Wallet\n• MyTonWallet\n\n";
                $response .= "Please try again:";
                
                sendMessage($chat_id, $response);
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
                'referral_list' => array()
            );
            saveUsers($users);
        }
        
        $user = $users[$chat_id];
        
        switch ($data) {
            case 'earn':
                $ads_today = isset($user['ads_watched_today']) ? $user['ads_watched_today'] : 0;
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
                $response .= "👥 <b>Referral Bonus:</b> " . REF_REWARD . " TON per friend";
                
                editMessageText($chat_id, $message_id, $response, getEarnKeyboard());
                break;
                
            case 'balance':
                $balance = $user['balance'];
                $referrals = $user['referrals'];
                $total_earned = $user['total_earned'];
                $ads_today = isset($user['ads_watched_today']) ? $user['ads_watched_today'] : 0;
                $ref_needed = max(0, MIN_WITHDRAW_REF - $referrals);
                
                $response = "💳 <b>Your TON Balance</b>\n\n";
                $response .= "💰 <b>Available Balance:</b> " . number_format($balance, 6) . " TON\n";
                $response .= "👥 <b>Total Referrals:</b> " . $referrals . "/" . MIN_WITHDRAW_REF . "\n";
                $response .= "📊 <b>Ads Watched Today:</b> " . $ads_today . "/" . DAILY_AD_LIMIT . "\n";
                $response .= "🏆 <b>Total Earned:</b> " . number_format($total_earned, 6) . " TON\n\n";
                
                if ($referrals < MIN_WITHDRAW_REF) {
                    $response .= "❌ <b>Withdrawal Requirement:</b>\n";
                    $response .= "You need <b>" . $ref_needed . " more referrals</b> to withdraw\n\n";
                } else {
                    $response .= "✅ <b>Withdrawal Requirement:</b>\n";
                    $response .= "You have enough referrals to withdraw!\n\n";
                }
                
                $response .= "🔗 <b>Your TON Address:</b>\n";
                $response .= "<code>" . ($user['ton_address'] ?: 'Not set') . "</code>";
                editMessageText($chat_id, $message_id, $response, getBalanceKeyboard());
                break;
                
            case 'referrals':
                $ref_code = $user['ref_code'];
                $referrals = $user['referrals'];
                $ref_earnings = $referrals * REF_REWARD;
                $ref_needed = max(0, MIN_WITHDRAW_REF - $referrals);
                
                $response = "👥 <b>Referral System</b>\n\n";
                $response .= "🔗 <b>Your Referral Code:</b>\n";
                $response .= "<code>" . $ref_code . "</code>\n\n";
                $response .= "📊 <b>Your Referral Stats:</b>\n";
                $response .= "• Total Referrals: <b>" . $referrals . "/" . MIN_WITHDRAW_REF . "</b>\n";
                $response .= "• Referral Earnings: <b>" . number_format($ref_earnings, 6) . " TON</b>\n";
                $response .= "• Needed for withdrawal: <b>" . $ref_needed . " more</b>\n\n";
                
                if (!empty($user['referral_list'])) {
                    $response .= "📋 <b>Your Referrals:</b>\n";
                    $count = 0;
                    foreach ($user['referral_list'] as $ref) {
                        $count++;
                        $ref_username = $ref['username'] !== 'Unknown' ? "@" . $ref['username'] : "User" . $ref['user_id'];
                        $date = date('d.m.Y', $ref['joined_at']);
                        $response .= $count . ". " . $ref_username . " - " . $date . "\n";
                        if ($count >= 10) break;
                    }
                    if (count($user['referral_list']) > 10) {
                        $response .= "... and " . (count($user['referral_list']) - 10) . " more referrals\n";
                    }
                    $response .= "\n";
                } else {
                    $response .= "📋 <b>Your Referrals:</b>\n";
                    $response .= "No referrals yet. Share your link to start earning!\n\n";
                }
                
                $response .= "💰 <b>How it works:</b>\n";
                $response .= "• Share your referral link\n";
                $response .= "• Earn <b>" . REF_REWARD . " TON</b> per friend\n";
                $response .= "• Minimum <b>" . MIN_WITHDRAW_REF . " referrals</b> required\n\n";
                $response .= "📱 <b>Your Referral Link:</b>\n";
                $response .= "https://t.me/takoniAdsBot?start=" . $ref_code;
                editMessageText($chat_id, $message_id, $response, getReferralsKeyboard());
                break;
                
            case 'share_referral':
                $ref_code = $user['ref_code'];
                $ref_link = "https://t.me/takoniAdsBot?start=" . $ref_code;
                $share_text = "🎉 Join TAKONI ADS and earn TON cryptocurrency!\n\n💰 Watch ads and earn " . AD_REWARD . " TON each\n👥 Use my referral link for bonus: " . $ref_link . "\n\n🚀 Start earning now!";
                
                $keyboard = array(
                    'inline_keyboard' => array(
                        array(
                            array('text' => '📤 Share', 'url' => "https://t.me/share/url?url=" . urlencode($ref_link) . "&text=" . urlencode($share_text))
                        ),
                        array(
                            array('text' => '⬅️ Back', 'callback_data' => 'referrals')
                        )
                    )
                );
                editMessageText($chat_id, $message_id, "📤 <b>Share Referral Link</b>\n\nClick below to share:", $keyboard);
                break;
                
            case 'withdraw':
                $balance = $user['balance'];
                $ton_address = $user['ton_address'];
                $referrals = $user['referrals'];
                
                $response = "🏧 <b>Withdraw TON</b>\n\n";
                $response .= "💰 <b>Available Balance:</b> " . number_format($balance, 6) . " TON\n";
                $response .= "👥 <b>Your Referrals:</b> " . $referrals . "/" . MIN_WITHDRAW_REF . "\n";
                $response .= "🔗 <b>TON Address:</b> " . ($ton_address ? "<code>" . $ton_address . "</code>" : "Not set") . "\n\n";
                
                $errors = array();
                if ($balance < MIN_WITHDRAW_AMOUNT) {
                    $errors[] = "❌ Minimum withdrawal: " . MIN_WITHDRAW_AMOUNT . " TON";
                }
                if ($referrals < MIN_WITHDRAW_REF) {
                    $needed = MIN_WITHDRAW_REF - $referrals;
                    $errors[] = "❌ Minimum " . MIN_WITHDRAW_REF . " referrals needed (missing: " . $needed . ")";
                }
                if (!$ton_address) {
                    $errors[] = "❌ TON address not set";
                }
                
                if (empty($errors)) {
                    $response .= "✅ <b>Ready to withdraw!</b>\n";
                    $response .= "💡 Click 'Submit Withdrawal' to request your TON.";
                    editMessageText($chat_id, $message_id, $response, getWithdrawKeyboard(true));
                } else {
                    $response .= "🚫 <b>Withdrawal Requirements:</b>\n";
                    foreach ($errors as $error) {
                        $response .= $error . "\n";
                    }
                    editMessageText($chat_id, $message_id, $response, getWithdrawKeyboard(false));
                }
                break;
                
            case 'enter_ton_address':
                $users[$chat_id]['awaiting_ton_address'] = true;
                saveUsers($users);
                
                $response = "🔗 <b>Enter TON Wallet Address</b>\n\n";
                $response .= "Please send your TON wallet address.\n\n";
                $response .= "📍 <b>Valid TON Address Formats:</b>\n";
                $response .= "• <code>EQxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx</code>\n";
                $response .= "• <code>UQxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx</code>\n";
                $response .= "• <code>Efxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx</code>\n";
                $response .= "• <code>Ufxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx</code>\n\n";
                $response .= "📍 <b>Where to find your TON address?</b>\n";
                $response .= "• @wallet bot\n• Tonkeeper app\n• Trust Wallet\n• MyTonWallet";
                
                editMessageText($chat_id, $message_id, $response);
                break;
                
            case 'save_ton_address':
                if (isset($users[$chat_id]['ton_address_temp'])) {
                    $users[$chat_id]['ton_address'] = $users[$chat_id]['ton_address_temp'];
                    unset($users[$chat_id]['ton_address_temp']);
                    saveUsers($users);
                    
                    $response = "✅ <b>TON Address Saved Successfully!</b>\n\n";
                    $response .= "🔗 Your TON address:\n";
                    $response .= "<code>" . $users[$chat_id]['ton_address'] . "</code>\n\n";
                    $response .= "You can now submit withdrawal requests.";
                    editMessageText($chat_id, $message_id, $response, getWithdrawKeyboard(true));
                } else {
                    editMessageText($chat_id, $message_id, "❌ No address to save. Please enter your TON address first.", getWithdrawKeyboard(false));
                }
                break;
                
            case 'submit_withdrawal':
                $balance = $user['balance'];
                $ton_address = $user['ton_address'];
                $referrals = $user['referrals'];
                
                if ($balance < MIN_WITHDRAW_AMOUNT) {
                    $response = "❌ <b>Insufficient Balance</b>\n\nMinimum withdrawal: " . MIN_WITHDRAW_AMOUNT . " TON";
                    editMessageText($chat_id, $message_id, $response, getWithdrawKeyboard(true));
                } elseif ($referrals < MIN_WITHDRAW_REF) {
                    $needed = MIN_WITHDRAW_REF - $referrals;
                    $response = "❌ <b>Insufficient Referrals</b>\n\nMinimum referrals: " . MIN_WITHDRAW_REF . "\nYou need " . $needed . " more";
                    editMessageText($chat_id, $message_id, $response, getWithdrawKeyboard(true));
                } elseif (!$ton_address) {
                    $response = "❌ <b>TON Address Not Set</b>\n\nPlease set your TON address first.";
                    editMessageText($chat_id, $message_id, $response, getWithdrawKeyboard(false));
                } else {
                    $users[$chat_id]['balance'] = 0;
                    saveUsers($users);
                    
                    $response = "✅ <b>Withdrawal Request Submitted!</b>\n\n";
                    $response .= "💰 <b>Amount:</b> " . number_format($balance, 6) . " TON\n";
                    $response .= "👥 <b>Referrals:</b> " . $referrals . "/" . MIN_WITHDRAW_REF . " ✅\n";
                    $response .= "🔗 <b>Address:</b> <code>" . $ton_address . "</code>\n\n";
                    $response .= "⏰ <b>Processing time:</b> 24-48 hours\n";
                    $response .= "📢 You will be notified when sent.";
                    editMessageText($chat_id, $message_id, $response, getMainKeyboard());
                }
                break;
                
            case 'main_menu':
                $ads_today = isset($user['ads_watched_today']) ? $user['ads_watched_today'] : 0;
                $ads_remaining = DAILY_AD_LIMIT - $ads_today;
                
                $response = "🚀 <b>TAKONI ADS</b>\n\n";
                $response .= "💰 Earn TON by watching ads\n";
                $response .= "👥 Invite friends for bonuses\n";
                $response .= "🏧 Withdraw to your wallet\n\n";
                $response .= "📊 <b>Daily Progress:</b>\n";
                $response .= "• Watched today: <b>" . $ads_today . "/" . DAILY_AD_LIMIT . "</b> ads\n";
                $response .= "• Remaining: <b>" . $ads_remaining . "</b> ads\n\n";
                $response .= "⚠️ <b>Withdrawal Requirement:</b>\n";
                $response .= "• Minimum <b>" . MIN_WITHDRAW_REF . " referrals</b> needed";
                editMessageText($chat_id, $message_id, $response, getMainKeyboard());
                break;
        }
    }
    
    // Web app data processing
    if (isset($update['web_app_data'])) {
        $web_app_data = $update['web_app_data'];
        $chat_id = $web_app_data['user_id'];
        $data = json_decode($web_app_data['data'], true);
        
        logError("Web app data from " . $chat_id . ": " . $web_app_data['data']);
        
        $users = loadUsers();
        if (!isset($users[$chat_id])) {
            return;
        }
        
        $user = $users[$chat_id];
        $current_time = time();
        $ads_today = isset($user['ads_watched_today']) ? $user['ads_watched_today'] : 0;
        
        // Günlük limit kontrolü
        if ($ads_today >= DAILY_AD_LIMIT) {
            sendMessage($chat_id, "❌ <b>Daily Limit Reached!</b>\n\nYou have reached the daily limit of " . DAILY_AD_LIMIT . " ads.\nPlease come back tomorrow!");
            return;
        }
        
        // Cooldown kontrolü
        if ($current_time - $user['last_ad_watch'] < AD_COOLDOWN) {
            $remaining = AD_COOLDOWN - ($current_time - $user['last_ad_watch']);
            sendMessage($chat_id, "⏰ Please wait " . $remaining . " seconds before watching another ad.");
            return;
        }
        
        // Reklam ödülü ver
        $users[$chat_id]['balance'] += AD_REWARD;
        $users[$chat_id]['total_earned'] += AD_REWARD;
        $users[$chat_id]['last_ad_watch'] = $current_time;
        $users[$chat_id]['ads_watched_today'] = $ads_today + 1;
        
        saveUsers($users);
        
        $ads_remaining = DAILY_AD_LIMIT - ($ads_today + 1);
        
        $response = "🎉 <b>Ad Watched Successfully!</b>\n\n";
        $response .= "💰 You earned: <b>" . AD_REWARD . " TON</b>\n";
        $response .= "💳 New balance: <b>" . number_format($users[$chat_id]['balance'], 6) . " TON</b>\n\n";
        $response .= "📊 <b>Daily Progress:</b>\n";
        $response .= "• Watched today: <b>" . ($ads_today + 1) . "/" . DAILY_AD_LIMIT . "</b> ads\n";
        $response .= "• Remaining: <b>" . $ads_remaining . "</b> ads\n\n";
        $response .= "🔄 Ready for next ad in " . AD_COOLDOWN . " seconds";
        
        sendMessage($chat_id, $response, getEarnKeyboard());
    }
}

// Get update from Telegram
$input = file_get_contents('php://input');
if ($input) {
    $update = json_decode($input, true);
    if ($update) {
        processUpdate($update);
    }
}

echo "🤖 TAKONI ADS BOT IS RUNNING | " . date('Y-m-d H:i:s');
?>

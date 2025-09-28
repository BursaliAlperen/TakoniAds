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

// Channel configuration
define('CHANNEL_USERNAME', '@TakoniFinance');
define('CHANNEL_URL', 'https://t.me/TakoniFinance');

// Language texts
$lang = array(
    'en' => array(
        'welcome' => "🚀 <b>Welcome to TAKONI ADS!</b>\n\n",
        'welcome_ref' => "🎉 <b>Welcome via Referral!</b>\n\nYou joined using @%s's referral link!\n\n",
        'earn_info' => "💰 <b>Earn TON</b> by watching ads\n👥 <b>Invite friends</b> for bonus TON\n🏧 <b>Withdraw</b> to TON wallet\n\n",
        'your_ref_code' => "🔗 <b>Your referral code:</b>\n<code>%s</code>\n\n",
        'rewards' => "📊 <b>Rewards:</b>\n• Watch Ad: <b>" . AD_REWARD . " TON</b>\n• Per Referral: <b>" . REF_REWARD . " TON</b>\n\n",
        'daily_limit' => "⚠️ <b>Daily Limit:</b>\n• Maximum <b>" . DAILY_AD_LIMIT . " ads</b> per day\n\n",
        'withdraw_req' => "⚠️ <b>Withdrawal Requirement:</b>\n• Minimum <b>" . MIN_WITHDRAW_REF . " referrals</b> needed",
        'channel_required' => "📢 <b>Channel Membership Required</b>\n\nTo use this bot, you must join our official channel:\n" . CHANNEL_USERNAME . "\n\nAfter joining, click the '✅ I Joined' button below.",
        'not_joined' => "❌ <b>You haven't joined the channel yet!</b>\n\nPlease join " . CHANNEL_USERNAME . " first, then click '✅ I Joined'",
        'joined_success' => "✅ <b>Thank you for joining!</b>\n\nNow you can start earning TON!",
        'earn_title' => "💰 <b>Earn TON</b>\n\n",
        'earn_instructions' => "📱 <b>Watch Ads & Earn " . AD_REWARD . " TON Each</b>\n\n🎬 How to earn:\n1. Click 'Watch Ad Now' button\n2. Watch the advertisement completely\n3. Get " . AD_REWARD . " TON automatically!\n\n⏰ Cooldown: " . AD_COOLDOWN . " seconds between ads\n\n",
        'daily_progress' => "📊 <b>Daily Progress:</b>\n• Watched today: <b>%d/%d</b> ads\n• Remaining: <b>%d</b> ads\n\n",
        'balance_stats' => "💰 <b>Balance Stats:</b>\n• Current: <b>%s TON</b>\n• Highest: <b>%s TON</b>\n• Total Earned: <b>%s TON</b>\n\n",
        'balance_title' => "💳 <b>Your Balance</b>\n\n",
        'balance_details' => "💰 Available: <b>%s TON</b>\n🏆 Highest Balance: <b>%s TON</b>\n📈 Total Earned: <b>%s TON</b>\n👥 Referrals: <b>%d</b>\n\n",
        'ref_title' => "👥 <b>Your Referrals</b>\n\n",
        'ref_stats' => "📊 <b>Statistics:</b>\n• Total Referrals: <b>%d</b>\n• Earned from Referrals: <b>%s TON</b>\n• Your Referral Code: <code>%s</code>\n\n",
        'ref_instructions' => "💡 <b>How to invite:</b>\nShare your referral link and earn " . REF_REWARD . " TON for each friend who joins!\n\n",
        'withdraw_title' => "🏧 <b>Withdraw TON</b>\n\n",
        'withdraw_requirements' => "📋 <b>Requirements:</b>\n• Minimum " . MIN_WITHDRAW_REF . " referrals\n• Minimum " . MIN_WITHDRAW_AMOUNT . " TON balance\n\n",
        'withdraw_stats' => "📊 <b>Your Stats:</b>\n• Referrals: <b>%d/%d</b>\n• Balance: <b>%s/%s TON</b>\n\n",
        'withdraw_address' => "💳 <b>Your TON Address:</b>\n%s\n\n",
        'no_address' => "❌ <b>No TON address set</b>\n\nPlease set your TON wallet address first.",
        'enter_address' => "💳 <b>Enter TON Address</b>\n\nPlease send your TON wallet address now:",
        'invalid_address' => "❌ <b>Invalid TON Address</b>\n\nPlease check your address and try again. Make sure it's a valid TON wallet address.",
        'address_saved' => "✅ <b>TON Address Saved!</b>\n\nYour withdrawal address has been updated.",
        'withdraw_success' => "✅ <b>Withdrawal Request Submitted!</b>\n\nYour request for %s TON has been received and will be processed within 24 hours.",
        'insufficient_refs' => "❌ <b>Insufficient Referrals</b>\n\nYou need at least %d referrals to withdraw. You have %d.",
        'insufficient_balance' => "❌ <b>Insufficient Balance</b>\n\nMinimum withdrawal amount is %s TON. You have %s TON.",
        'new_ref' => "🎉 <b>New Referral!</b>\n\n👤 New user @%s joined using your referral link!\n💰 You earned: <b>" . REF_REWARD . " TON</b>\n👥 Total referrals: <b>%d</b>\n💳 New balance: <b>%s TON</b>"
    ),
    'tr' => array(
        'welcome' => "🚀 <b>TAKONI ADS'e Hoş Geldin!</b>\n\n",
        'welcome_ref' => "🎉 <b>Referans ile Hoş Geldin!</b>\n\n@%s'nin referans linki ile katıldın!\n\n",
        'earn_info' => "💰 <b>TON Kazan</b> - reklam izleyerek\n👥 <b>Arkadaşlarını davet et</b> - bonus TON kazan\n🏧 <b>Çekim yap</b> - TON cüzdanına\n\n",
        'your_ref_code' => "🔗 <b>Referans kodun:</b>\n<code>%s</code>\n\n",
        'rewards' => "📊 <b>Ödüller:</b>\n• Reklam İzle: <b>" . AD_REWARD . " TON</b>\n• Her Referans: <b>" . REF_REWARD . " TON</b>\n\n",
        'daily_limit' => "⚠️ <b>Günlük Limit:</b>\n• Günlük maksimum <b>" . DAILY_AD_LIMIT . " reklam</b>\n\n",
        'withdraw_req' => "⚠️ <b>Çekim Gereksinimi:</b>\n• Minimum <b>" . MIN_WITHDRAW_REF . " referans</b> gerekli",
        'channel_required' => "📢 <b>Kanal Üyeliği Gerekli</b>\n\nBu botu kullanmak için resmi kanalımıza katılmalısın:\n" . CHANNEL_USERNAME . "\n\nKatıldıktan sonra aşağıdaki '✅ Katıldım' butonuna tıkla.",
        'not_joined' => "❌ <b>Henüz kanala katılmadın!</b>\n\nLütfen önce " . CHANNEL_USERNAME . " katıl, sonra '✅ Katıldım' butonuna tıkla",
        'joined_success' => "✅ <b>Katıldığın için teşekkürler!</b>\n\nŞimdi TON kazanmaya başlayabilirsin!",
        'earn_title' => "💰 <b>TON Kazan</b>\n\n",
        'earn_instructions' => "📱 <b>Reklam İzle & Her Seferinde " . AD_REWARD . " TON Kazan</b>\n\n🎬 Nasıl kazanılır:\n1. 'Reklam İzle' butonuna tıkla\n2. Reklamı tamamen izle\n3. Otomatik olarak " . AD_REWARD . " TON kazan!\n\n⏰ Bekleme süresi: reklamlar arası " . AD_COOLDOWN . " saniye\n\n",
        'daily_progress' => "📊 <b>Günlük Durum:</b>\n• Bugün izlenen: <b>%d/%d</b> reklam\n• Kalan: <b>%d</b> reklam\n\n",
        'balance_stats' => "💰 <b>Bakiye İstatistikleri:</b>\n• Mevcut: <b>%s TON</b>\n• En Yüksek: <b>%s TON</b>\n• Toplam Kazanç: <b>%s TON</b>\n\n",
        'balance_title' => "💳 <b>Bakiyen</b>\n\n",
        'balance_details' => "💰 Mevcut: <b>%s TON</b>\n🏆 En Yüksek Bakiye: <b>%s TON</b>\n📈 Toplam Kazanç: <b>%s TON</b>\n👥 Referanslar: <b>%d</b>\n\n",
        'ref_title' => "👥 <b>Referansların</b>\n\n",
        'ref_stats' => "📊 <b>İstatistikler:</b>\n• Toplam Referans: <b>%d</b>\n• Referanslardan Kazanç: <b>%s TON</b>\n• Referans Kodun: <code>%s</code>\n\n",
        'ref_instructions' => "💡 <b>Nasıl davet edilir:</b>\nReferans linkini paylaş ve katılan her arkadaş için " . REF_REWARD . " TON kazan!\n\n",
        'withdraw_title' => "🏧 <b>TON Çek</b>\n\n",
        'withdraw_requirements' => "📋 <b>Gereksinimler:</b>\n• Minimum " . MIN_WITHDRAW_REF . " referans\n• Minimum " . MIN_WITHDRAW_AMOUNT . " TON bakiye\n\n",
        'withdraw_stats' => "📊 <b>İstatistiklerin:</b>\n• Referanslar: <b>%d/%d</b>\n• Bakiye: <b>%s/%s TON</b>\n\n",
        'withdraw_address' => "💳 <b>TON Adresin:</b>\n%s\n\n",
        'no_address' => "❌ <b>TON adresi ayarlanmadı</b>\n\nLütfen önce TON cüzdan adresinizi ayarlayın.",
        'enter_address' => "💳 <b>TON Adresi Gir</b>\n\nLütfen TON cüzdan adresinizi şimdi gönderin:",
        'invalid_address' => "❌ <b>Geçersiz TON Adresi</b>\n\nLütfen adresinizi kontrol edin ve tekrar deneyin. Geçerli bir TON cüzdan adresi olduğundan emin olun.",
        'address_saved' => "✅ <b>TON Adresi Kaydedildi!</b>\n\nÇekim adresiniz güncellendi.",
        'withdraw_success' => "✅ <b>Çekim Talebi Alındı!</b>\n\n%s TON çekim talebiniz alındı ve 24 saat içinde işleme alınacak.",
        'insufficient_refs' => "❌ <b>Yetersiz Referans</b>\n\nÇekim yapmak için en az %d referans gerekiyor. Sizde %d referans var.",
        'insufficient_balance' => "❌ <b>Yetersiz Bakiye</b>\n\nMinimum çekim miktarı %s TON. Sizde %s TON var.",
        'new_ref' => "🎉 <b>Yeni Referans!</b>\n\n👤 @%s kullanıcısı senin referans linkinle katıldı!\n💰 Kazandın: <b>" . REF_REWARD . " TON</b>\n👥 Toplam referans: <b>%d</b>\n💳 Yeni bakiye: <b>%s TON</b>"
    ),
    'ru' => array(
        'welcome' => "🚀 <b>Добро пожаловать в TAKONI ADS!</b>\n\n",
        'welcome_ref' => "🎉 <b>Добро пожаловать по реферальной ссылке!</b>\n\nВы присоединились по ссылке @%s!\n\n",
        'earn_info' => "💰 <b>Зарабатывайте TON</b> - просматривая рекламу\n👥 <b>Приглашайте друзей</b> - получайте бонусные TON\n🏧 <b>Выводите</b> - на TON кошелек\n\n",
        'your_ref_code' => "🔗 <b>Ваш реферальный код:</b>\n<code>%s</code>\n\n",
        'rewards' => "📊 <b>Награды:</b>\n• Просмотр рекламы: <b>" . AD_REWARD . " TON</b>\n• За реферала: <b>" . REF_REWARD . " TON</b>\n\n",
        'daily_limit' => "⚠️ <b>Дневной лимит:</b>\n• Максимум <b>" . DAILY_AD_LIMIT . " реклам</b> в день\n\n",
        'withdraw_req' => "⚠️ <b>Требования для вывода:</b>\n• Минимум <b>" . MIN_WITHDRAW_REF . " рефералов</b> необходимо",
        'channel_required' => "📢 <b>Требуется участие в канале</b>\n\nДля использования этого бота вы должны присоединиться к нашему официальному каналу:\n" . CHANNEL_USERNAME . "\n\nПосле присоединения нажмите кнопку '✅ Я вступил' ниже.",
        'not_joined' => "❌ <b>Вы еще не присоединились к каналу!</b>\n\nПожалуйста, сначала присоединитесь к " . CHANNEL_USERNAME . ", затем нажмите '✅ Я вступил'",
        'joined_success' => "✅ <b>Спасибо за присоединение!</b>\n\nТеперь вы можете начать зарабатывать TON!",
        'earn_title' => "💰 <b>Заработать TON</b>\n\n",
        'earn_instructions' => "📱 <b>Смотрите рекламу и зарабатывайте " . AD_REWARD . " TON за каждую</b>\n\n🎬 Как заработать:\n1. Нажмите кнопку 'Смотреть рекламу'\n2. Просмотрите рекламу полностью\n3. Получите " . AD_REWARD . " TON автоматически!\n\n⏰ Перерыв: " . AD_COOLDOWN . " секунд между рекламой\n\n",
        'daily_progress' => "📊 <b>Дневной прогресс:</b>\n• Просмотрено сегодня: <b>%d/%d</b> реклам\n• Осталось: <b>%d</b> реклам\n\n",
        'balance_stats' => "💰 <b>Статистика баланса:</b>\n• Текущий: <b>%s TON</b>\n• Наивысший: <b>%s TON</b>\n• Всего заработано: <b>%s TON</b>\n\n",
        'balance_title' => "💳 <b>Ваш баланс</b>\n\n",
        'balance_details' => "💰 Доступно: <b>%s TON</b>\n🏆 Наивысший баланс: <b>%s TON</b>\n📈 Всего заработано: <b>%s TON</b>\n👥 Рефералы: <b>%d</b>\n\n",
        'ref_title' => "👥 <b>Ваши рефералы</b>\n\n",
        'ref_stats' => "📊 <b>Статистика:</b>\n• Всего рефералов: <b>%d</b>\n• Заработано с рефералов: <b>%s TON</b>\n• Ваш реферальный код: <code>%s</code>\n\n",
        'ref_instructions' => "💡 <b>Как приглашать:</b>\nПоделитесь своей реферальной ссылкой и зарабатывайте " . REF_REWARD . " TON за каждого друга, который присоединится!\n\n",
        'withdraw_title' => "🏧 <b>Вывод TON</b>\n\n",
        'withdraw_requirements' => "📋 <b>Требования:</b>\n• Минимум " . MIN_WITHDRAW_REF . " рефералов\n• Минимум " . MIN_WITHDRAW_AMOUNT . " TON на балансе\n\n",
        'withdraw_stats' => "📊 <b>Ваша статистика:</b>\n• Рефералы: <b>%d/%d</b>\n• Баланс: <b>%s/%s TON</b>\n\n",
        'withdraw_address' => "💳 <b>Ваш TON адрес:</b>\n%s\n\n",
        'no_address' => "❌ <b>TON адрес не установлен</b>\n\nПожалуйста, сначала установите адрес вашего TON кошелька.",
        'enter_address' => "💳 <b>Введите TON адрес</b>\n\nПожалуйста, отправьте адрес вашего TON кошелька сейчас:",
        'invalid_address' => "❌ <b>Неверный TON адрес</b>\n\nПожалуйста, проверьте ваш адрес и попробуйте снова. Убедитесь, что это действительный TON адрес кошелька.",
        'address_saved' => "✅ <b>TON адрес сохранен!</b>\n\nВаш адрес для вывода обновлен.",
        'withdraw_success' => "✅ <b>Запрос на вывод отправлен!</b>\n\nВаш запрос на вывод %s TON получен и будет обработан в течение 24 часов.",
        'insufficient_refs' => "❌ <b>Недостаточно рефералов</b>\n\nВам нужно как минимум %d рефералов для вывода. У вас %d.",
        'insufficient_balance' => "❌ <b>Недостаточно средств</b>\n\nМинимальная сумма вывода %s TON. У вас %s TON.",
        'new_ref' => "🎉 <b>Новый реферал!</b>\n\n👤 Новый пользователь @%s присоединился по вашей реферальной ссылке!\n💰 Вы заработали: <b>" . REF_REWARD . " TON</b>\n👥 Всего рефералов: <b>%d</b>\n💳 Новый баланс: <b>%s TON</b>"
    ),
    'hi' => array(
        'welcome' => "🚀 <b>TAKONI ADS में आपका स्वागत है!</b>\n\n",
        'welcome_ref' => "🎉 <b>रेफरल के माध्यम से स्वागत है!</b>\n\nआप @%s के रेफरल लिंक से जुड़े!\n\n",
        'earn_info' => "💰 <b>TON कमाएं</b> - विज्ञापन देखकर\n👥 <b>दोस्तों को आमंत्रित करें</b> - बोनस TON पाएं\n🏧 <b>निकासी करें</b> - TON वॉलेट में\n\n",
        'your_ref_code' => "🔗 <b>आपका रेफरल कोड:</b>\n<code>%s</code>\n\n",
        'rewards' => "📊 <b>पुरस्कार:</b>\n• विज्ञापन देखें: <b>" . AD_REWARD . " TON</b>\n• प्रति रेफरल: <b>" . REF_REWARD . " TON</b>\n\n",
        'daily_limit' => "⚠️ <b>दैनिक सीमा:</b>\n• प्रतिदिन अधिकतम <b>" . DAILY_AD_LIMIT . " विज्ञापन</b>\n\n",
        'withdraw_req' => "⚠️ <b>निकासी आवश्यकता:</b>\n• न्यूनतम <b>" . MIN_WITHDRAW_REF . " रेफरल</b> आवश्यक",
        'channel_required' => "📢 <b>चैनल सदस्यता आवश्यक</b>\n\nइस बॉट का उपयोग करने के लिए, आपको हमारे आधिकारिक चैनल से जुड़ना होगा:\n" . CHANNEL_USERNAME . "\n\nजुड़ने के बाद, नीचे '✅ मैं शामिल हो गया' बटन पर क्लिक करें।",
        'not_joined' => "❌ <b>आप अभी तक चैनल से नहीं जुड़े हैं!</b>\n\nकृपया पहले " . CHANNEL_USERNAME . " से जुड़ें, फिर '✅ मैं शामिल हो गया' बटन पर क्लिक करें",
        'joined_success' => "✅ <b>जुड़ने के लिए धन्यवाद!</b>\n\nअब आप TON कमाना शुरू कर सकते हैं!",
        'earn_title' => "💰 <b>TON कमाएं</b>\n\n",
        'earn_instructions' => "📱 <b>विज्ञापन देखें और प्रत्येक से " . AD_REWARD . " TON कमाएं</b>\n\n🎬 कैसे कमाएं:\n1. 'विज्ञापन देखें' बटन पर क्लिक करें\n2. विज्ञापन को पूरी तरह देखें\n3. स्वचालित रूप से " . AD_REWARD . " TON प्राप्त करें!\n\n⏰ कोल्डाउन: विज्ञापनों के बीच " . AD_COOLDOWN . " सेकंड\n\n",
        'daily_progress' => "📊 <b>दैनिक प्रगति:</b>\n• आज देखे गए: <b>%d/%d</b> विज्ञापन\n• शेष: <b>%d</b> विज्ञापन\n\n",
        'balance_stats' => "💰 <b>बैलेंस स्टैटिस्टिक्स:</b>\n• वर्तमान: <b>%s TON</b>\n• उच्चतम: <b>%s TON</b>\n• कुल कमाया: <b>%s TON</b>\n\n",
        'balance_title' => "💳 <b>आपका बैलेंस</b>\n\n",
        'balance_details' => "💰 उपलब्ध: <b>%s TON</b>\n🏆 उच्चतम बैलेंस: <b>%s TON</b>\n📈 कुल कमाया: <b>%s TON</b>\n👥 रेफरल: <b>%d</b>\n\n",
        'ref_title' => "👥 <b>आपके रेफरल</b>\n\n",
        'ref_stats' => "📊 <b>आँकड़े:</b>\n• कुल रेफरल: <b>%d</b>\n• रेफरल से कमाया: <b>%s TON</b>\n• आपका रेफरल कोड: <code>%s</code>\n\n",
        'ref_instructions' => "💡 <b>कैसे आमंत्रित करें:</b>\nअपना रेफरल लिंक साझा करें और जुड़ने वाले प्रत्येक मित्र के लिए " . REF_REWARD . " TON कमाएं!\n\n",
        'withdraw_title' => "🏧 <b>TON निकासी</b>\n\n",
        'withdraw_requirements' => "📋 <b>आवश्यकताएँ:</b>\n• न्यूनतम " . MIN_WITHDRAW_REF . " रेफरल\n• न्यूनतम " . MIN_WITHDRAW_AMOUNT . " TON बैलेंस\n\n",
        'withdraw_stats' => "📊 <b>आपके आँकड़े:</b>\n• रेफरल: <b>%d/%d</b>\n• बैलेंस: <b>%s/%s TON</b>\n\n",
        'withdraw_address' => "💳 <b>आपका TON पता:</b>\n%s\n\n",
        'no_address' => "❌ <b>TON पता सेट नहीं है</b>\n\nकृपया पहले अपना TON वॉलेट पता सेट करें।",
        'enter_address' => "💳 <b>TON पता दर्ज करें</b>\n\nकृपया अब अपना TON वॉलेट पता भेजें:",
        'invalid_address' => "❌ <b>अमान्य TON पता</b>\n\nकृपया अपना पता जांचें और पुनः प्रयास करें। सुनिश्चित करें कि यह एक मान्य TON वॉलेट पता है।",
        'address_saved' => "✅ <b>TON पता सहेजा गया!</b>\n\nआपका निकासी पता अपडेट किया गया है।",
        'withdraw_success' => "✅ <b>निकासी अनुरोध सबमिट किया गया!</b>\n\n%s TON के आपके अनुरोध को प्राप्त कर लिया गया है और 24 घंटों के भीतर संसाधित किया जाएगा।",
        'insufficient_refs' => "❌ <b>अपर्याप्त रेफरल</b>\n\nनिकासी के लिए आपको कम से कम %d रेफरल की आवश्यकता है। आपके पास %d हैं।",
        'insufficient_balance' => "❌ <b>अपर्याप्त बैलेंस</b>\n\nन्यूनतम निकासी राशि %s TON है। आपके पास %s TON है।",
        'new_ref' => "🎉 <b>नया रेफरल!</b>\n\n👤 नया उपयोगकर्ता @%s आपके रेफरल लिंक से जुड़ा!\n💰 आपने कमाया: <b>" . REF_REWARD . " TON</b>\n👥 कुल रेफरल: <b>%d</b>\n💳 नया बैलेंस: <b>%s TON</b>"
    )
);

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
    return $result !== false;
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
    global $lang;
    
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

// KANAL KONTROL FONKSİYONU
function isUserInChannel($chat_id) {
    $method = 'getChatMember';
    $params = array(
        'chat_id' => CHANNEL_USERNAME,
        'user_id' => $chat_id
    );
    
    $url = API_URL . $method . '?' . http_build_query($params);
    $response = @file_get_contents($url);
    
    if ($response === false) {
        logError("Failed to check channel membership for user: " . $chat_id);
        return false;
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['ok']) && $data['ok'] === true) {
        $status = $data['result']['status'];
        // Kullanıcı kanalda mı kontrol et
        return in_array($status, ['member', 'administrator', 'creator']);
    }
    
    return false;
}

function isValidTONAddress($address) {
    $address = trim($address);
    
    logError("Checking TON address: " . $address);
    
    // Tüm TON adres formatları
    $patterns = array(
        '/^EQ[0-9a-zA-Z_-]{44,48}$/i',
        '/^UQ[0-9a-zA-Z_-]{44,48}$/i',
        '/^Ef[0-9a-zA-Z_-]{44,48}$/i',
        '/^Uf[0-9a-zA-Z_-]{44,48}$/i',
        '/^0:[0-9a-fA-F]{64}$/i',
        '/^[0-9a-zA-Z_-]{48}$/i',
    );
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $address)) {
            logError("Valid TON address detected with pattern: " . $pattern);
            return true;
        }
    }
    
    if (preg_match('/^EQ[a-zA-Z0-9_-]{44,48}$/i', $address)) {
        logError("Valid EQ TON address detected");
        return true;
    }
    
    if (preg_match('/^UQ[a-zA-Z0-9_-]{44,48}$/i', $address)) {
        logError("Valid UQ TON address detected");
        return true;
    }
    
    logError("Invalid TON address: " . $address);
    return false;
}

function getLanguageKeyboard() {
    return array(
        'inline_keyboard' => array(
            array(
                array('text' => '🇺🇸 English', 'callback_data' => 'lang_en'),
                array('text' => '🇹🇷 Türkçe', 'callback_data' => 'lang_tr')
            ),
            array(
                array('text' => '🇷🇺 Русский', 'callback_data' => 'lang_ru'),
                array('text' => '🇮🇳 हिन्दी', 'callback_data' => 'lang_hi')
            )
        )
    );
}

function getChannelJoinKeyboard($lang_code = 'en') {
    global $lang;
    
    return array(
        'inline_keyboard' => array(
            array(
                array('text' => '📢 Join Channel', 'url' => CHANNEL_URL)
            ),
            array(
                array('text' => '✅ I Joined', 'callback_data' => 'check_join')
            )
        )
    );
}

function getMainKeyboard($lang_code = 'en') {
    global $lang;
    
    return array(
        'inline_keyboard' => array(
            array(
                array('text' => '💰 ' . ($lang_code == 'tr' ? 'TON Kazan' : ($lang_code == 'ru' ? 'Заработать TON' : ($lang_code == 'hi' ? 'TON कमाएं' : 'Earn TON'))), 'callback_data' => 'earn'),
                array('text' => '💳 ' . ($lang_code == 'tr' ? 'Bakiye' : ($lang_code == 'ru' ? 'Баланс' : ($lang_code == 'hi' ? 'बैलेंस' : 'Balance'))), 'callback_data' => 'balance')
            ),
            array(
                array('text' => '👥 ' . ($lang_code == 'tr' ? 'Referanslar' : ($lang_code == 'ru' ? 'Рефералы' : ($lang_code == 'hi' ? 'रेफरल' : 'Referrals'))), 'callback_data' => 'referrals'),
                array('text' => '🏧 ' . ($lang_code == 'tr' ? 'Çekim' : ($lang_code == 'ru' ? 'Вывод' : ($lang_code == 'hi' ? 'निकासी' : 'Withdraw'))), 'callback_data' => 'withdraw')
            ),
            array(
                array('text' => '🌐 ' . ($lang_code == 'tr' ? 'Dil' : ($lang_code == 'ru' ? 'Язык' : ($lang_code == 'hi' ? 'भाषा' : 'Language'))), 'callback_data' => 'change_lang')
            )
        )
    );
}

function getEarnKeyboard($lang_code = 'en') {
    global $lang;
    $webapp_url = "https://takoniads.onrender.com/webapp.html";
    
    return array(
        'inline_keyboard' => array(
            array(
                array('text' => '📱 ' . ($lang_code == 'tr' ? 'Reklam İzle' : ($lang_code == 'ru' ? 'Смотреть рекламу' : ($lang_code == 'hi' ? 'विज्ञापन देखें' : 'Watch Ad'))) . ' (' . AD_REWARD . ' TON)', 'web_app' => array('url' => $webapp_url))
            ),
            array(
                array('text' => '🔄 ' . ($lang_code == 'tr' ? 'Bakiye Kontrol' : ($lang_code == 'ru' ? 'Проверить баланс' : ($lang_code == 'hi' ? 'बैलेंस चेक' : 'Check Balance'))), 'callback_data' => 'balance')
            ),
            array(
                array('text' => '⬅️ ' . ($lang_code == 'tr' ? 'Ana Menü' : ($lang_code == 'ru' ? 'Главное меню' : ($lang_code == 'hi' ? 'मुख्य मेनू' : 'Main Menu'))), 'callback_data' => 'main_menu')
            )
        )
    );
}

function getBalanceKeyboard($lang_code = 'en') {
    global $lang;
    
    return array(
        'inline_keyboard' => array(
            array(
                array('text' => '📱 ' . ($lang_code == 'tr' ? 'Reklam İzle' : ($lang_code == 'ru' ? 'Смотреть рекламу' : ($lang_code == 'hi' ? 'विज्ञापन देखें' : 'Watch Ad'))), 'callback_data' => 'earn')
            ),
            array(
                array('text' => '🔄 ' . ($lang_code == 'tr' ? 'Yenile' : ($lang_code == 'ru' ? 'Обновить' : ($lang_code == 'hi' ? 'रिफ्रेश' : 'Refresh'))), 'callback_data' => 'balance')
            ),
            array(
                array('text' => '⬅️ ' . ($lang_code == 'tr' ? 'Ana Menü' : ($lang_code == 'ru' ? 'Главное меню' : ($lang_code == 'hi' ? 'मुख्य मेनू' : 'Main Menu'))), 'callback_data' => 'main_menu')
            )
        )
    );
}

function getReferralsKeyboard($lang_code = 'en') {
    global $lang;
    
    return array(
        'inline_keyboard' => array(
            array(
                array('text' => '📤 ' . ($lang_code == 'tr' ? 'Referans Paylaş' : ($lang_code == 'ru' ? 'Поделиться ссылкой' : ($lang_code == 'hi' ? 'रेफरल शेयर' : 'Share Referral'))), 'callback_data' => 'share_referral')
            ),
            array(
                array('text' => '🔄 ' . ($lang_code == 'tr' ? 'Yenile' : ($lang_code == 'ru' ? 'Обновить' : ($lang_code == 'hi' ? 'रिफ्रेश' : 'Refresh'))), 'callback_data' => 'referrals')
            ),
            array(
                array('text' => '⬅️ ' . ($lang_code == 'tr' ? 'Ana Menü' : ($lang_code == 'ru' ? 'Главное меню' : ($lang_code == 'hi' ? 'मुख्य मेनू' : 'Main Menu'))), 'callback_data' => 'main_menu')
            )
        )
    );
}

function getWithdrawKeyboard($has_address = false, $lang_code = 'en') {
    global $lang;
    
    if ($has_address) {
        return array(
            'inline_keyboard' => array(
                array(
                    array('text' => '🚀 ' . ($lang_code == 'tr' ? 'Çekim Yap' : ($lang_code == 'ru' ? 'Вывести' : ($lang_code == 'hi' ? 'निकासी करें' : 'Submit Withdrawal'))), 'callback_data' => 'submit_withdrawal')
                ),
                array(
                    array('text' => '✏️ ' . ($lang_code == 'tr' ? 'Adres Değiştir' : ($lang_code == 'ru' ? 'Изменить адрес' : ($lang_code == 'hi' ? 'पता बदलें' : 'Change Address'))), 'callback_data' => 'enter_ton_address')
                ),
                array(
                    array('text' => '⬅️ ' . ($lang_code == 'tr' ? 'Ana Menü' : ($lang_code == 'ru' ? 'Главное меню' : ($lang_code == 'hi' ? 'मुख्य मेनू' : 'Main Menu'))), 'callback_data' => 'main_menu')
                )
            )
        );
    } else {
        return array(
            'inline_keyboard' => array(
                array(
                    array('text' => '💳 ' . ($lang_code == 'tr' ? 'TON Adresi Gir' : ($lang_code == 'ru' ? 'Ввести TON адрес' : ($lang_code == 'hi' ? 'TON पता दर्ज करें' : 'Enter TON Address'))), 'callback_data' => 'enter_ton_address')
                ),
                array(
                    array('text' => '⬅️ ' . ($lang_code == 'tr' ? 'Ana Menü' : ($lang_code == 'ru' ? 'Главное меню' : ($lang_code == 'hi' ? 'मुख्य मेनू' : 'Main Menu'))), 'callback_data' => 'main_menu')
                )
            )
        );
    }
}

function getSaveAddressKeyboard($lang_code = 'en') {
    global $lang;
    
    return array(
        'inline_keyboard' => array(
            array(
                array('text' => '💾 ' . ($lang_code == 'tr' ? 'Adresi Kaydet' : ($lang_code == 'ru' ? 'Сохранить адрес' : ($lang_code == 'hi' ? 'पता सहेजें' : 'Save Address')), 'callback_data' => 'save_ton_address')
            ),
            array(
                array('text' => '❌ ' . ($lang_code == 'tr' ? 'İptal' : ($lang_code == 'ru' ? 'Отмена' : ($lang_code == 'hi' ? 'रद्द करें' : 'Cancel'))), 'callback_data' => 'main_menu')
            )
        )
    );
}

function processUpdate($update) {
    global $lang;
    
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
                'username' => $username,
                'max_balance' => 0,
                'language' => 'en',
                'channel_joined' => false
            );
            saveUsers($users);
            logError("New user created: " . $chat_id);
        }
        
        $user_lang = isset($users[$chat_id]['language']) ? $users[$chat_id]['language'] : 'en';
        
        // Kanal kontrolü - eğer kanala katılmamışsa
        if (!$users[$chat_id]['channel_joined']) {
            if (strpos($text, '/start') === 0) {
                $channel_joined = isUserInChannel($chat_id);
                
                if ($channel_joined) {
                    $users[$chat_id]['channel_joined'] = true;
                    saveUsers($users);
                    
                    // Normal start işlemine devam et
                    processStartCommand($update, $users, $chat_id, $text, $username, $user_lang);
                } else {
                    sendMessage($chat_id, $lang[$user_lang]['channel_required'], getChannelJoinKeyboard($user_lang));
                }
            }
            return;
        }
        
        if (strpos($text, '/start') === 0) {
            processStartCommand($update, $users, $chat_id, $text, $username, $user_lang);
        } elseif (isset($users[$chat_id]['awaiting_ton_address'])) {
            $ton_address = trim($text);
            
            if (isValidTONAddress($ton_address)) {
                $users[$chat_id]['ton_address_temp'] = $ton_address;
                unset($users[$chat_id]['awaiting_ton_address']);
                saveUsers($users);
                
                $response = "🔗 <b>" . ($user_lang == 'tr' ? 'TON Adresi Alındı' : ($user_lang == 'ru' ? 'TON адрес получен' : ($user_lang == 'hi' ? 'TON पता प्राप्त' : 'TON Address Received'))) . "</b>\n\n";
                $response .= "✅ <b>" . ($user_lang == 'tr' ? 'Geçerli TON Adresi' : ($user_lang == 'ru' ? 'Действительный TON адрес' : ($user_lang == 'hi' ? 'मान्य TON पता' : 'Valid TON Address'))) . "</b>\n\n";
                $response .= ($user_lang == 'tr' ? 'Adres:' : ($user_lang == 'ru' ? 'Адрес:' : ($user_lang == 'hi' ? 'पता:' : 'Address:'))) . " <code>" . $ton_address . "</code>\n\n";
                $response .= ($user_lang == 'tr' ? 'Kaydetmek için "Adresi Kaydet"e tıkla:' : ($user_lang == 'ru' ? 'Нажмите "Сохранить адрес" для подтверждения:' : ($user_lang == 'hi' ? 'पुष्टि करने के लिए \'पता सहेजें\' पर क्लिक करें:' : 'Click \'Save Address\' to confirm:')));
                
                sendMessage($chat_id, $response, getSaveAddressKeyboard($user_lang));
            } else {
                sendMessage($chat_id, $lang[$user_lang]['invalid_address']);
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
                'referral_list' => array(),
                'max_balance' => 0,
                'language' => 'en',
                'channel_joined' => false
            );
            saveUsers($users);
        }
        
        $user_lang = isset($users[$chat_id]['language']) ? $users[$chat_id]['language'] : 'en';
        $user = $users[$chat_id];
        
        // Kanal kontrolü için özel callback
        if ($data == 'check_join') {
            $channel_joined = isUserInChannel($chat_id);
            
            if ($channel_joined) {
                $users[$chat_id]['channel_joined'] = true;
                saveUsers($users);
                
                editMessageText($chat_id, $message_id, $lang[$user_lang]['joined_success'], getMainKeyboard($user_lang));
            } else {
                editMessageText($chat_id, $message_id, $lang[$user_lang]['not_joined'], getChannelJoinKeyboard($user_lang));
            }
            return;
        }
        
        // Eğer kanala katılmamışsa ve ana menü callback'i değilse
        if (!$users[$chat_id]['channel_joined'] && $data != 'change_lang' && !str_starts_with($data, 'lang_')) {
            editMessageText($chat_id, $message_id, $lang[$user_lang]['channel_required'], getChannelJoinKeyboard($user_lang));
            return;
        }
        
        switch ($data) {
            case 'change_lang':
                $response = "🌐 <b>Select Language / Dil Seçin / Выберите язык / भाषा चुनें</b>\n\n";
                $response .= "Please choose your preferred language:";
                editMessageText($chat_id, $message_id, $response, getLanguageKeyboard());
                break;
                
            case 'lang_en':
            case 'lang_tr':
            case 'lang_ru':
            case 'lang_hi':
                $selected_lang = substr($data, 5);
                $users[$chat_id]['language'] = $selected_lang;
                saveUsers($users);
                
                $response = "✅ <b>" . ($selected_lang == 'tr' ? 'Dil Türkçe olarak ayarlandı!' : ($selected_lang == 'ru' ? 'Язык установлен на Русский!' : ($selected_lang == 'hi' ? 'भाषा हिन्दी पर सेट की गई!' : 'Language set to English!'))) . "</b>";
                editMessageText($chat_id, $message_id, $response, getMainKeyboard($selected_lang));
                break;
                
            case 'main_menu':
                $response = $lang[$user_lang]['welcome'] . $lang[$user_lang]['earn_info'] . 
                           sprintf($lang[$user_lang]['your_ref_code'], $user['ref_code']) . 
                           $lang[$user_lang]['rewards'] . $lang[$user_lang]['daily_limit'] . 
                           $lang[$user_lang]['withdraw_req'];
                editMessageText($chat_id, $message_id, $response, getMainKeyboard($user_lang));
                break;
                
            case 'earn':
                $ads_today = isset($user['ads_watched_today']) ? $user['ads_watched_today'] : 0;
                $ads_remaining = DAILY_AD_LIMIT - $ads_today;
                $max_balance = isset($user['max_balance']) ? $user['max_balance'] : $user['balance'];
                
                $response = $lang[$user_lang]['earn_title'] . $lang[$user_lang]['earn_instructions'] . 
                           sprintf($lang[$user_lang]['daily_progress'], $ads_today, DAILY_AD_LIMIT, $ads_remaining) . 
                           sprintf($lang[$user_lang]['balance_stats'], 
                                   number_format($user['balance'], 6), 
                                   number_format($max_balance, 6), 
                                   number_format($user['total_earned'], 6));
                editMessageText($chat_id, $message_id, $response, getEarnKeyboard($user_lang));
                break;
                
            case 'balance':
                $max_balance = isset($user['max_balance']) ? $user['max_balance'] : $user['balance'];
                $response = $lang[$user_lang]['balance_title'] . 
                           sprintf($lang[$user_lang]['balance_details'], 
                                   number_format($user['balance'], 6), 
                                   number_format($max_balance, 6), 
                                   number_format($user['total_earned'], 6), 
                                   $user['referrals']);
                editMessageText($chat_id, $message_id, $response, getBalanceKeyboard($user_lang));
                break;
                
            case 'referrals':
                $ref_earnings = $user['referrals'] * REF_REWARD;
                $response = $lang[$user_lang]['ref_title'] . 
                           sprintf($lang[$user_lang]['ref_stats'], 
                                   $user['referrals'], 
                                   number_format($ref_earnings, 6), 
                                   $user['ref_code']) . 
                           $lang[$user_lang]['ref_instructions'];
                editMessageText($chat_id, $message_id, $response, getReferralsKeyboard($user_lang));
                break;
                
            case 'withdraw':
                $has_address = !empty($user['ton_address']);
                $response = $lang[$user_lang]['withdraw_title'] . 
                           $lang[$user_lang]['withdraw_requirements'] . 
                           sprintf($lang[$user_lang]['withdraw_stats'], 
                                   $user['referrals'], MIN_WITHDRAW_REF,
                                   number_format($user['balance'], 6), MIN_WITHDRAW_AMOUNT);
                
                if ($has_address) {
                    $response .= sprintf($lang[$user_lang]['withdraw_address'], "<code>" . $user['ton_address'] . "</code>");
                } else {
                    $response .= $lang[$user_lang]['no_address'];
                }
                
                editMessageText($chat_id, $message_id, $response, getWithdrawKeyboard($has_address, $user_lang));
                break;
                
            case 'enter_ton_address':
                $users[$chat_id]['awaiting_ton_address'] = true;
                saveUsers($users);
                sendMessage($chat_id, $lang[$user_lang]['enter_address']);
                break;
                
            case 'save_ton_address':
                if (isset($user['ton_address_temp'])) {
                    $users[$chat_id]['ton_address'] = $user['ton_address_temp'];
                    unset($users[$chat_id]['ton_address_temp']);
                    saveUsers($users);
                    
                    editMessageText($chat_id, $message_id, $lang[$user_lang]['address_saved'], getWithdrawKeyboard(true, $user_lang));
                }
                break;
                
            case 'submit_withdrawal':
                if ($user['referrals'] < MIN_WITHDRAW_REF) {
                    $response = sprintf($lang[$user_lang]['insufficient_refs'], MIN_WITHDRAW_REF, $user['referrals']);
                    editMessageText($chat_id, $message_id, $response, getWithdrawKeyboard(!empty($user['ton_address']), $user_lang));
                    break;
                }
                
                if ($user['balance'] < MIN_WITHDRAW_AMOUNT) {
                    $response = sprintf($lang[$user_lang]['insufficient_balance'], MIN_WITHDRAW_AMOUNT, number_format($user['balance'], 6));
                    editMessageText($chat_id, $message_id, $response, getWithdrawKeyboard(!empty($user['ton_address']), $user_lang));
                    break;
                }
                
                if (empty($user['ton_address'])) {
                    editMessageText($chat_id, $message_id, $lang[$user_lang]['no_address'], getWithdrawKeyboard(false, $user_lang));
                    break;
                }
                
                // Simulate withdrawal
                $withdraw_amount = $user['balance'];
                $users[$chat_id]['balance'] = 0;
                saveUsers($users);
                
                $response = sprintf($lang[$user_lang]['withdraw_success'], number_format($withdraw_amount, 6));
                editMessageText($chat_id, $message_id, $response, getMainKeyboard($user_lang));
                break;
        }
    }
}

function processStartCommand($update, &$users, $chat_id, $text, $username, $user_lang) {
    global $lang;
    
    $parts = explode(' ', $text);
    $ref_code_param = isset($parts[1]) ? $parts[1] : null;
    
    $user = $users[$chat_id];
    $welcome = $lang[$user_lang]['welcome'];
    
    // REFERANS KONTROLÜ - DÜZELTİLMİŞ
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
            
            // Referans vereni güncelle - REFERANS SAYISI DÜZELTİLDİ
            $current_referrals = isset($users[$referrer_id]['referrals']) ? $users[$referrer_id]['referrals'] : 0;
            $current_balance = isset($users[$referrer_id]['balance']) ? $users[$referrer_id]['balance'] : 0;
            $current_total_earned = isset($users[$referrer_id]['total_earned']) ? $users[$referrer_id]['total_earned'] : 0;
            $current_max_balance = isset($users[$referrer_id]['max_balance']) ? $users[$referrer_id]['max_balance'] : 0;
            
            // REFERANS SAYISINI +1 ARTIR ve BAKİYE EKLE - DÜZELTİLDİ
            $users[$referrer_id]['referrals'] = $current_referrals + 1; // ✅ BU SATIR DÜZELTİLDİ
            $users[$referrer_id]['balance'] = $current_balance + REF_REWARD;
            $users[$referrer_id]['total_earned'] = $current_total_earned + REF_REWARD;
            
            // ✅ EN YÜKSEK BAKİYEYİ GÜNCELLE
            $new_balance = $current_balance + REF_REWARD;
            if ($new_balance > $current_max_balance) {
                $users[$referrer_id]['max_balance'] = $new_balance;
            }
            
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
                $ref_message = sprintf($lang[$users[$referrer_id]['language']]['new_ref'], 
                                      $username, 
                                      $users[$referrer_id]['referrals'], 
                                      number_format($users[$referrer_id]['balance'], 6));
                
                if (sendMessage($referrer_id, $ref_message)) {
                    logError("Referral notification sent to: " . $referrer_id);
                } else {
                    logError("Failed to send referral notification to: " . $referrer_id);
                }
                
                $welcome = sprintf($lang[$user_lang]['welcome_ref'], $referrer_username);
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
    
    $welcome .= $lang[$user_lang]['earn_info'] . 
               sprintf($lang[$user_lang]['your_ref_code'], $users[$chat_id]['ref_code']) . 
               $lang[$user_lang]['rewards'] . $lang[$user_lang]['daily_limit'] . 
               $lang[$user_lang]['withdraw_req'];
    
    sendMessage($chat_id, $welcome, getMainKeyboard($user_lang));
}

// Main webhook handler
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if ($update) {
    processUpdate($update);
} else {
    http_response_code(400);
    die('Invalid update');
}
?>

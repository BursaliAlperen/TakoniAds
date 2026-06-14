<?php
/**
 * NINOCOIN - AAA Multi-Coin Claimer
 * Premium faucet automation bot
 * Version: 3.0
 * Author: NINOCOIN Team
 */

// Hata raporlamayı kapat (production)
error_reporting(0);
date_default_timezone_set('Asia/Jakarta');

// ============================================================
// YAPILANDIRMA SABİTLERİ
// ============================================================
define('CONFIG_FILE', 'config.json');
define('HOST', 'https://99faucet.com');
define('API_IN', 'https://api.waryono.my.id/in.php');
define('MAX_RETRY', 3);
define('CAPTCHA_TIMEOUT', 60);

// ============================================================
// RENK TANIMLARI (Mor, Pembe, Sarı, Yeşil, Kırmızı, Gri)
// ============================================================
const COLOR_PURPLE  = "\033[38;5;129m";
const COLOR_PINK    = "\033[38;5;213m";
const COLOR_YELLOW  = "\033[38;5;226m";
const COLOR_GREEN   = "\033[38;5;46m";
const COLOR_RED     = "\033[38;5;196m";
const COLOR_GRAY    = "\033[38;5;244m";
const COLOR_CYAN    = "\033[38;5;51m";
const COLOR_WHITE   = "\033[0;37m";
const COLOR_RESET   = "\033[0m";
const COLOR_BOLD    = "\033[1m";

// ============================================================
// YARDIMCI FONKSİYONLAR
// ============================================================

/**
 * Terminal ekranını temizle
 */
function clearScreen(): void {
    echo "\033[2J\033[H";
}

/**
 * Benzersiz ID oluştur
 */
function generateUF(): string {    return md5(uniqid(mt_rand(), true));
}

/**
 * Zaman dilimi al
 */
function getTimezone(): string {
    return date_default_timezone_get();
}

/**
 * NINOCOIN Banner göster
 */
function showBanner(): void {
    echo COLOR_PURPLE . COLOR_BOLD . "
 ███╗   ██╗███████╗ ██████╗ ██████╗  █████╗  ██████╗ ███████╗██╗   ██╗
 ████╗  ██║██╔════╝██╔═══██╗██╔══██╗██╔══██╗██╔════╝ ██╔════╝╚██╗ ██╔╝
 ██╔██╗ ██║█████╗  ██║   ██║██████╔╝███████║██║  ███╗█████╗   ╚████╔╝ 
 ██║╚██╗██║██╔══╝  ██║   ██║██╔══██╗██╔══██║██║   ██║██╔══╝    ╚██╔╝  
 ██║ ████║███████╗╚██████╝██║  ██║██║  ██║╚██████╔╝███████╗   ██║   
 ╚═╝  ╚═══╝╚══════╝ ═════╝ ╚═╝  ╚═╝╚═╝  ╚═╝ ╚═════╝ ╚══════╝   ╚═╝   
" . COLOR_RESET;
    
    echo COLOR_PINK . COLOR_BOLD . "        ═══════════════════════════════════════" . COLOR_RESET . "\n";
    echo COLOR_PINK . COLOR_BOLD . "              AAA MULTI-COIN CLAIMER" . COLOR_RESET . "\n";
    echo COLOR_PINK . COLOR_BOLD . "        ═══════════════════════════════════════" . COLOR_RESET . "\n\n";
}

/**
 * Log mesajı yaz
 */
function logMessage(string $type, string $message): void {
    $prefix = match($type) {
        'info'    => COLOR_GRAY . "[*]" . COLOR_RESET,
        'success' => COLOR_GREEN . "[+]" . COLOR_RESET,
        'error'   => COLOR_RED . "[-]" . COLOR_RESET,
        'warn'    => COLOR_YELLOW . "[!]" . COLOR_RESET,
        default   => COLOR_GRAY . "[*]" . COLOR_RESET,
    };
    
    echo "{$prefix} {$message}\n";
}

/**
 * Geri sayım timer'ı
 */
function countdown(int $seconds, string $prefix = "[*] Bekleniyor"): void {
    $waitTime = (int)$seconds;
    while ($waitTime > 0) {
        $hours = floor($waitTime / 3600);        $minutes = floor(($waitTime % 3600) / 60);
        $secs = $waitTime % 60;
        $timeFormatted = sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        
        echo COLOR_GRAY . "{$prefix} " . COLOR_PINK . "{$timeFormatted}" . COLOR_GRAY . "...\r" . COLOR_RESET;
        sleep(1);
        $waitTime--;
    }
    
    echo str_repeat(" ", 50) . "\r";
}

// ============================================================
// HTTP İŞLEMLERİ
// ============================================================

/**
 * HTTP isteği gönder (curl wrapper)
 * PHP 8.5 uyumlu - curl_close kullanılmıyor
 */
function httpRequest(string $url, string $method = 'GET', array $data = [], array $headers = []): string {
    $ch = curl_init();
    
    $finalHeaders = [];
    foreach ($headers as $header) {
        $finalHeaders[] = $header;
    }
    
    $options = [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => $finalHeaders,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_TIMEOUT        => 30,
    ];
    
    if (strtoupper($method) === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = $data;
    }
    
    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    
    if ($response) {
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);        $body = substr($response, $headerSize);
        // PHP 8.5 uyumluluk: curl_close çağrılmıyor
        return $body;
    }
    
    // PHP 8.5 uyumluluk: curl_close çağrılmıyor
    return "ERROR_CONNECTION";
}

// ============================================================
// CAPTCHA ÇÖZÜCÜ
// ============================================================

/**
 * RSCaptcha slider çöz
 */
function solveCaptcha(string $appId, string $publicKey, string $version, string $referer, string $apikey): array|false {
    $headers = ["Content-Type: application/json"];
    $body = json_encode([
        "apikey"     => $apikey,
        "app_id"     => $appId,
        "methods"    => "rslider",
        "public_key" => $publicKey,
        "version"    => $version,
        "referer"    => $referer,
        "json"       => 1
    ]);
    
    $request = httpRequest(API_IN, "POST", $body, $headers);
    
    if (strpos($request, "ERROR") !== false) {
        logMessage('error', "Captcha API hatası");
        return false;
    }
    
    $json = json_decode($request, true);
    if (!isset($json["request"])) {
        logMessage('error', "Geçersiz captcha yanıtı");
        return false;
    }
    
    $captchaId = $json["request"];
    $startTime = time();
    
    // Captcha çözümünü bekle
    while (time() - $startTime < CAPTCHA_TIMEOUT) {
        echo COLOR_GRAY . "[*] Captcha çözülüyor... (" . (time() - $startTime) . "s)" . COLOR_RESET . "\r";
        sleep(2);
        
        $resultUrl = "https://api.waryono.my.id/res.php?apikey={$apikey}&id={$captchaId}&json=1";        $result = httpRequest($resultUrl, "GET");
        
        if (strpos($result, "CAPCHA_NOT_READY") !== false || 
            strpos($result, "ERROR_SOLVE_PENDING") !== false) {
            continue;
        }
        
        if (strpos($result, "ERROR") !== false) {
            logMessage('error', "Captcha çözüm hatası");
            return false;
        }
        
        $jsonResult = json_decode($result, true);
        if (isset($jsonResult["request"])) {
            $res = $jsonResult["request"];
            if (preg_match('/rs_token:(\d+),rs_res:([^,]+)/', $res, $match)) {
                echo str_repeat(" ", 50) . "\r";
                logMessage('success', "Captcha çözüldü!");
                return [
                    "rs_token" => $match[1],
                    "rs_res"   => $match[2]
                ];
            }
        }
    }
    
    logMessage('error', "Captcha zaman aşımı!");
    return false;
}

// ============================================================
// CLOUDFLARE BYPASS
// ============================================================

/**
 * Cloudflare bypass dene
 */
function bypassCloudflare(array &$config, string $configFile, string $target): bool {
    logMessage('warn', "Cloudflare tespit edildi, bypass deneniyor...");
    
    $pythonCommands = ["python3", "python"];
    
    foreach ($pythonCommands as $pythonCmd) {
        $cmd = "{$pythonCmd} exec.py " . escapeshellarg($target) . " 2>&1";
        $output = exec($cmd, $outputArray, $returnCode);
        
        if (empty($output) && !empty($outputArray)) {
            $output = implode("\n", $outputArray);
        }
                $dataBypass = json_decode($output, true);
        
        if (isset($dataBypass['cf_clearance']) && !empty($dataBypass['cf_clearance'])) {
            $fullNewCf = $dataBypass['cf_clearance'];
            $newUa = $dataBypass['user_agent'] ?? $config['user_agent'] ?? 
                     "Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 " .
                     "(KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36";
            $oldCookie = $config['cookie'] ?? '';
            
            $newTokenValue = strpos($fullNewCf, '=') !== false 
                ? explode('=', $fullNewCf)[1] 
                : $fullNewCf;
            
            $pattern = '/cf_clearance=[^;]+/';
            $replacement = "cf_clearance=" . $newTokenValue;
            
            if (preg_match($pattern, $oldCookie)) {
                $newCookieStr = preg_replace($pattern, $replacement, $oldCookie);
            } else {
                $newCookieStr = rtrim($oldCookie, "; ") . 
                               (empty($oldCookie) ? "" : "; ") . $replacement;
            }
            
            $config['cookie'] = $newCookieStr;
            $config['user_agent'] = $newUa;
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
            
            logMessage('success', "Cloudflare bypass başarılı!");
            sleep(1);
            return true;
        }
    }
    
    logMessage('error', "Tüm bypass yöntemleri başarısız!");
    return false;
}

// ============================================================
// YAPILANDIRMA YÖNETİMİ
// ============================================================

/**
 * Config dosyasını yükle veya oluştur
 */
function loadConfig(string $configFile): array {
    if (!file_exists($configFile)) {
        echo COLOR_PURPLE . "API Key girin: " . COLOR_PINK;
        $apikey = trim(fgets(STDIN));
        
        echo COLOR_PURPLE . "Cookie girin: " . COLOR_PINK;        $cookie = trim(fgets(STDIN));
        
        if (empty($apikey) || empty($cookie)) {
            logMessage('error', "API Key veya Cookie boş olamaz!");
            exit(1);
        }
        
        $data = [
            "apikey"     => $apikey,
            "cookie"     => $cookie,
            "user_agent" => "Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 " .
                           "(KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36"
        ];
        
        file_put_contents($configFile, json_encode($data, JSON_PRETTY_PRINT));
        logMessage('success', "Config kaydedildi: {$configFile}");
        sleep(2);
        
        return $data;
    }
    
    $config = json_decode(file_get_contents($configFile), true);
    
    if (!$config || empty($config['apikey']) || empty($config['cookie'])) {
        logMessage('error', "Config dosyası hatalı, yeniden oluşturuluyor...");
        unlink($configFile);
        sleep(2);
        return loadConfig($configFile);
    }
    
    return $config;
}

// ============================================================
// COIN İŞLEMLERİ
// ============================================================

/**
 * Dashboard'dan tüm coinleri çek
 */
function getAllCurrencies(array &$config, string $configFile): array {
    $ua = $config['user_agent'] ?? "Mozilla/5.0 (Linux; Android 10; K)";
    $cookie = $config['cookie'] ?? '';
    
    $headers = [
        "host: 99faucet.com",
        "user-agent: {$ua}",
        "accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        "referer: " . HOST . "/faucet/pepe",
        "cookie: {$cookie}"    ];
    
    $dashUrl = HOST . "/dashboard";
    $dash = httpRequest($dashUrl, "GET", [], $headers);
    
    // Cloudflare kontrolü
    if ($dash === "ERROR_CONNECTION" || strpos($dash, "Just a moment") !== false) {
        if (!bypassCloudflare($config, $configFile, $dashUrl)) {
            return [];
        }
        $ua = $config['user_agent'];
        $cookie = $config['cookie'];
        $headers = [
            "host: 99faucet.com",
            "user-agent: {$ua}",
            "accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "referer: " . HOST . "/faucet/pepe",
            "cookie: {$cookie}"
        ];
        $dash = httpRequest($dashUrl, "GET", [], $headers);
    }
    
    // Dashboard kontrolü
    if (strpos($dash, "Dashboard | 99Faucet") === false) {
        return [];
    }
    
    // Tüm coinleri çek
    preg_match_all('/<a href="https:\/\/99faucet\.com\/faucet\/([^"]+)" class="">/', $dash, $matches);
    return array_unique($matches[1] ?? []);
}

/**
 * Tek bir coin için claim işlemi
 */
function claimCoin(string $coin, int $current, int $total, array &$config, string $configFile, string $apikey): bool {
    $ua = $config['user_agent'] ?? "Mozilla/5.0 (Linux; Android 10; K)";
    $cookie = $config['cookie'] ?? '';
    
    // Coin bilgilerini göster
    echo COLOR_PURPLE . COLOR_BOLD . "╔═══════════════════════════════════════════╗\n" . COLOR_RESET;
    echo COLOR_PURPLE . COLOR_BOLD . "║  COIN: " . COLOR_PINK . str_pad(strtoupper($coin), 35) . COLOR_PURPLE . " ║\n" . COLOR_RESET;
    echo COLOR_PURPLE . COLOR_BOLD . "║  İLERLEME: " . COLOR_YELLOW . str_pad("{$current}/{$total}", 29) . COLOR_PURPLE . " ║\n" . COLOR_RESET;
    echo COLOR_PURPLE . COLOR_BOLD . "╚═══════════════════════════════════════════╝\n\n" . COLOR_RESET;
    
    // Faucet sayfasına git
    $headers = [
        "host: 99faucet.com",
        "user-agent: {$ua}",
        "accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",        "referer: " . HOST . "/dashboard",
        "cookie: {$cookie}"
    ];
    
    $faucetUrl = HOST . "/faucet/{$coin}";
    $faucet = httpRequest($faucetUrl, "GET", [], $headers);
    
    // Cloudflare kontrolü
    if ($faucet === "ERROR_CONNECTION" || strpos($faucet, "Just a moment") !== false) {
        if (!bypassCloudflare($config, $configFile, $faucetUrl)) {
            logMessage('error', "Bypass başarısız, atlanıyor...");
            return false;
        }
        $ua = $config['user_agent'];
        $cookie = $config['cookie'];
        $headers = [
            "host: 99faucet.com",
            "user-agent: {$ua}",
            "accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "referer: " . HOST . "/dashboard",
            "cookie: {$cookie}"
        ];
        $faucet = httpRequest($faucetUrl, "GET", [], $headers);
    }
    
    // Shortlink kontrolü
    if (strpos($faucet, "Shortlinks | 99Faucet") !== false) {
        logMessage('warn', "Shortlink gerekli, atlanıyor...");
        return false;
    }
    
    // Token al
    $token = '';
    if (preg_match('/<input type="hidden" name="token" value="([^"]+)"/', $faucet, $matches)) {
        $token = $matches[1];
    }
    
    if (empty($token)) {
        logMessage('error', "Token bulunamadı, atlanıyor...");
        return false;
    }
    
    // Captcha çöz
    $captchaResult = solveCaptcha("1044", "ws1WNm5E0xjtnezLT8r9", "v5", HOST . "/", $apikey);
    
    if (!$captchaResult) {
        logMessage('error', "Captcha çözülemedi, atlanıyor...");
        return false;
    }
        // Claim gönder
    $postData = http_build_query([
        "ci_csrf_token"      => "",
        "token"              => $token,
        "currency"           => $coin,
        "captcha"            => "rscaptchav37",
        "rscaptcha_token"    => $captchaResult["rs_token"],
        "rscaptcha_response" => $captchaResult["rs_res"],
        "uf"                 => generateUF(),
        "utt"                => getTimezone(),
        "ls"                 => "id,en-US,en,ms,ru"
    ]);
    
    $postHeaders = [
        "host: 99faucet.com",
        "origin: " . HOST,
        "content-type: application/x-www-form-urlencoded",
        "user-agent: {$ua}",
        "accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        "referer: {$faucetUrl}",
        "cookie: {$cookie}"
    ];
    
    $claimUrl = HOST . "/faucet/verify";
    $claim = httpRequest($claimUrl, "POST", $postData, $postHeaders);
    
    // Sonuç kontrolü
    if (strpos($claim, "Good job!") !== false) {
        $msg = '';
        if (preg_match("/text: '([^']+)'/", $claim, $msgMatch)) {
            $msg = $msgMatch[1];
        }
        logMessage('success', $msg ?: "Claim başarılı!");
        
        // Timer al
        $timerVal = 60;
        if (preg_match('/let wait = (\d+)/', $claim, $timerMatch)) {
            $timerVal = (int)$timerMatch[1];
        }
        
        if ($timerVal > 0) {
            countdown($timerVal, "[*] Sonraki claim");
        }
        
        return true;
    } elseif (strpos($claim, "sufficient funds") !== false) {
        logMessage('warn', "Faucet boş!");
        return false;
    } elseif (strpos($claim, "Invalid") !== false) {
        logMessage('error', "Geçersiz captcha veya claim!");        return false;
    } else {
        logMessage('error', "Bilinmeyen hata!");
        return false;
    }
}

// ============================================================
// ANA PROGRAM
// ============================================================

// Başlangıç
clearScreen();
showBanner();

logMessage('info', "Config yükleniyor...");
$config = loadConfig(CONFIG_FILE);

$apikey = $config['apikey'] ?? '';
logMessage('success', "API Key: " . substr($apikey, 0, 10) . "...");
sleep(1);

// Ana döngü
while (true) {
    clearScreen();
    showBanner();
    
    logMessage('info', "Dashboard çekiliyor...");
    $currencies = getAllCurrencies($config, CONFIG_FILE);
    
    if (empty($currencies)) {
        logMessage('error', "Coin bulunamadı veya oturum süresi doldu!");
        logMessage('warn', "Config siliniyor, yeniden giriş yapın...");
        @unlink(CONFIG_FILE);
        sleep(3);
        $config = loadConfig(CONFIG_FILE);
        continue;
    }
    
    $totalCoins = count($currencies);
    logMessage('success', "{$totalCoins} coin bulundu!");
    echo COLOR_GRAY . "═══════════════════════════════════════════" . COLOR_RESET . "\n\n";
    
    // Coin listesini göster
    echo COLOR_CYAN . COLOR_BOLD . "COIN LİSTESİ:\n" . COLOR_RESET;
    foreach ($currencies as $index => $currency) {
        $num = $index + 1;
        echo COLOR_GRAY . sprintf("  %2d. ", $num) . COLOR_PINK . strtoupper($currency) . "\n";
    }
    echo COLOR_GRAY . "═══════════════════════════════════════════\n\n" . COLOR_RESET;    
    // Multi-coin claim döngüsü
    $successCount = 0;
    $failCount = 0;
    
    foreach ($currencies as $index => $currency) {
        $coin = strtolower($currency);
        $currentNum = $index + 1;
        
        if (claimCoin($coin, $currentNum, $totalCoins, $config, CONFIG_FILE, $apikey)) {
            $successCount++;
        } else {
            $failCount++;
        }
        
        sleep(1);
    }
    
    // Özet
    echo COLOR_GRAY . "═══════════════════════════════════════════" . COLOR_RESET . "\n";
    echo COLOR_GREEN . COLOR_BOLD . "[+] DÖNGÜ TAMAMLANDI!\n" . COLOR_RESET;
    echo COLOR_GREEN . "    Başarılı: {$successCount}\n" . COLOR_RESET;
    echo COLOR_RED . "    Başarısız: {$failCount}\n" . COLOR_RESET;
    echo COLOR_GRAY . "═══════════════════════════════════════════\n\n" . COLOR_RESET;
    
    logMessage('info', "10 saniye sonra yeni döngü başlıyor...");
    countdown(10, "[*] Yeniden başlama");
}

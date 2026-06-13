<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Jakarta');

$configFile = "config.json";

// Renkler
const pink   = "\033[38;5;213m";
const purple = "\033[38;5;129m";
const gray   = "\033[38;5;244m";
const green  = "\033[38;5;46m";
const red    = "\033[38;5;196m";
const yellow = "\033[38;5;226m";
const reset  = "\033[0m";
const bold   = "\033[1m";

const host = "https://99faucet.com";
const api_in = "https://api.waryono.my.id/in.php";

function clear() {
    echo "\033[2J\033[H";
}

function banner() {
    echo purple . bold . "
 _   _ ______ _____  _____  _   _ 
| \ | || ___ \_   _|/  __ \| | | |
|  \| || |_/ / | |  | /  \/| |_| |
| . ` || ___ \ | |  | |    |  _  |
| |\  || |_/ / | |  | \__/\| | | |
\_| \_/\____/  \_/   \____/\_| |_/
" . reset . "\n";
    echo gray . "        [ AAA Multi-Coin Claimer ]" . reset . "\n";
    echo gray . "----------------------------------------" . reset . "\n\n";
}

function getConfig($configFile) {
    if (!file_exists($configFile)) {
        echo purple . "[!] Config dosyası bulunamadı. Yeni oluşturuluyor...\n\n" . reset;
        echo purple . "API Key girin: " . reset;
        $apikey = trim(fgets(STDIN));
        
        echo purple . "Cookie girin: " . reset;
        $cookie = trim(fgets(STDIN));
        
        if (empty($apikey) || empty($cookie)) {
            echo red . "[!] API Key veya Cookie boş olamaz!\n" . reset;
            exit(1);
        }        
        $data = [
            "apikey" => $apikey,
            "cookie" => $cookie,
            "user_agent" => "Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36"
        ];
        
        file_put_contents($configFile, json_encode($data, JSON_PRETTY_PRINT));
        echo green . "[+] Config kaydedildi: $configFile\n" . reset;
        sleep(2);
        return $data;
    }
    
    $config = json_decode(file_get_contents($configFile), true);
    if (!$config || empty($config['apikey']) || empty($config['cookie'])) {
        echo red . "[!] Config dosyası hatalı! Siliniyor...\n" . reset;
        unlink($configFile);
        sleep(2);
        return getConfig($configFile);
    }
    
    return $config;
}

function skibidixxx($url, $method = 'GET', $data = [], $headers = []) {
    $ch = curl_init();
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_TIMEOUT => 30
    ];
    
    if (strtoupper($method) === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = $data;
    }
    
    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response && $httpCode >= 200 && $httpCode < 400) {
        return $response;    }
    return "error";
}

function bypassCloudflare(&$config, $configFile, $target) {
    echo yellow . "[!] Cloudflare tespit edildi. Bypass deneniyor...\n" . reset;
    
    $python_cmd = "python3 exec.py " . escapeshellarg($target) . " 2>&1";
    $output = exec($python_cmd, $output_array, $return_code);
    
    if (empty($output) && !empty($output_array)) {
        $output = implode("\n", $output_array);
    }
    
    $data_bypass = json_decode($output, true);
    
    if (isset($data_bypass['cf_clearance']) && !empty($data_bypass['cf_clearance'])) {
        $new_cf = $data_bypass['cf_clearance'];
        $new_ua = $data_bypass['user_agent'] ?? $config['user_agent'];
        
        $old_cookie = $config['cookie'];
        $new_token = strpos($new_cf, '=') !== false ? explode('=', $new_cf)[1] : $new_cf;
        
        if (preg_match('/cf_clearance=[^;]+/', $old_cookie, $matches)) {
            $new_cookie = str_replace($matches[0], "cf_clearance=" . $new_token, $old_cookie);
        } else {
            $new_cookie = $old_cookie . "; cf_clearance=" . $new_token;
        }
        
        $config['cookie'] = $new_cookie;
        $config['user_agent'] = $new_ua;
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
        
        echo green . "[+] Cloudflare bypass başarılı!\n" . reset;
        sleep(2);
        return true;
    }
    
    echo red . "[-] Cloudflare bypass başarısız!\n" . reset;
    echo gray . "Debug: $output\n" . reset;
    return false;
}

function slider($app_id, $public_key, $version, $reff, $apikey) {
    $headers = ["Content-Type: application/json"];
    $body = json_encode([
        "apikey" => $apikey,
        "app_id" => $app_id,
        "methods" => "rslider",
        "public_key" => $public_key,        "version" => $version,
        "referer" => $reff,
        "json" => 1
    ]);
    
    $request = skibidixxx(api_in, "POST", $body, $headers);
    $json = json_decode($request, true);
    
    if (!isset($json["request"])) {
        echo red . "[-] Slider hatası: " . ($json['error'] ?? 'Unknown') . "\n" . reset;
        return false;
    }
    
    $id = $json["request"];
    
    for ($i = 0; $i < 30; $i++) {
        echo gray . "[*] Captcha çözülüyor... (" . ($i + 1) . "/30)\r" . reset;
        sleep(2);
        
        $result = skibidixxx("https://api.waryono.my.id/res.php?apikey=$apikey&id=$id&json=1", "GET");
        $json_res = json_decode($result, true);
        
        if (isset($json_res["request"]) && strpos($json_res["request"], "rs_token:") !== false) {
            preg_match('/rs_token:(\d+),rs_res:([^,]+)/', $json_res["request"], $match);
            echo green . "[+] Captcha çözüldü!            \n" . reset;
            return ["rs_token" => $match[1], "rs_res" => $match[2]];
        }
        
        if (isset($json_res['error']) && strpos($json_res['error'], 'ERROR') !== false) {
            echo red . "[-] Captcha hatası: " . $json_res['error'] . "\n" . reset;
            return false;
        }
    }
    
    echo red . "[-] Captcha timeout!\n" . reset;
    return false;
}

// Ana program
clear();
banner();

echo gray . "[*] Config kontrol ediliyor...\n" . reset;
$config = getConfig($configFile);

$apikey = $config['apikey'];
$cookie = $config['cookie'];
$ua = $config['user_agent'] ?? "Mozilla/5.0 (Linux; Android 10; K)";

echo green . "[+] API Key: " . substr($apikey, 0, 10) . "...\n" . reset;echo green . "[+] Cookie loaded\n" . reset;
sleep(2);

// Ana döngü
while (true) {
    clear();
    banner();
    
    echo gray . "[*] Dashboard çekiliyor...\n" . reset;
    
    $headers = [
        "host: 99faucet.com",
        "user-agent: $ua",
        "accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        "referer: " . host . "/faucet/pepe",
        "cookie: $cookie"
    ];
    
    $dash = skibidixxx(host . "/dashboard", "GET", [], $headers);
    
    if ($dash == "error" || strpos($dash, "Just a moment") !== false) {
        if (bypassCloudflare($config, $configFile, host . "/dashboard")) {
            $cookie = $config['cookie'];
            $ua = $config['user_agent'];
            continue;
        } else {
            echo red . "[!] Bypass başarısız. 5 saniye bekleniyor...\n" . reset;
            sleep(5);
            continue;
        }
    }
    
    if (strpos($dash, "Dashboard") === false) {
        echo red . "[!] Login başarısız! Cookie süresi dolmuş olabilir.\n" . reset;
        echo yellow . "[*] Config siliniyor...\n" . reset;
        unlink($configFile);
        sleep(3);
        $config = getConfig($configFile);
        $cookie = $config['cookie'];
        $ua = $config['user_agent'];
        continue;
    }
    
    // Coinleri bul
    preg_match_all('/<a href="https:\/\/99faucet\.com\/faucet\/([^"]+)"/', $dash, $matches);
    $currencies = array_unique($matches[1]);
    
    if (empty($currencies)) {
        echo red . "[!] Hiç coin bulunamadı!\n" . reset;
        sleep(5);        continue;
    }
    
    echo green . "[+] " . count($currencies) . " coin bulundu!\n" . reset;
    echo gray . "----------------------------------------\n" . reset;
    
    // Tüm coinleri claim et
    foreach ($currencies as $index => $currency) {
        $coin = strtolower($currency);
        echo purple . bold . "\n>> CLAIMING: " . pink . strtoupper($coin) . " (" . ($index + 1) . "/" . count($currencies) . ")\n" . reset;
        echo gray . "----------------------------------------\n" . reset;
        
        $headers = [
            "host: 99faucet.com",
            "user-agent: $ua",
            "accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "referer: " . host . "/dashboard",
            "cookie: $cookie"
        ];
        
        $url = host . "/faucet/$coin";
        $faucet = skibidixxx($url, "GET", [], $headers);
        
        if ($faucet == "error" || strpos($faucet, "Just a moment") !== false) {
            bypassCloudflare($config, $configFile, $url);
            $cookie = $config['cookie'];
            $ua = $config['user_agent'];
        }
        
        // Token al
        preg_match('/<input type="hidden" name="token" value="([^"]+)"/', $faucet, $token_match);
        $token = $token_match[1] ?? '';
        
        if (empty($token)) {
            echo yellow . "[!] Token bulunamadı, geçiliyor...\n" . reset;
            continue;
        }
        
        // Captcha çöz
        echo gray . "[*] Captcha çözülüyor...\n" . reset;
        $bypass = slider("1044", "ws1WNm5E0xjtnezLT8r9", "v5", host . "/", $apikey);
        
        if (!$bypass) {
            echo red . "[-] Captcha çözülemedi!\n" . reset;
            continue;
        }
        
        // Claim gönder
        $post_data = http_build_query([
            "ci_csrf_token" => "",            "token" => $token,
            "currency" => $coin,
            "captcha" => "rscaptchav37",
            "rscaptcha_token" => $bypass["rs_token"],
            "rscaptcha_response" => $bypass["rs_res"],
            "uf" => md5(uniqid()),
            "utt" => "Asia/Jakarta",
            "ls" => "id,en-US,en"
        ]);
        
        $post_headers = [
            "host: 99faucet.com",
            "origin: " . host,
            "content-type: application/x-www-form-urlencoded",
            "user-agent: $ua",
            "referer: $url",
            "cookie: $cookie"
        ];
        
        $claim = skibidixxx(host . "/faucet/verify", "POST", $post_data, $post_headers);
        
        if (strpos($claim, "Good job!") !== false) {
            preg_match("/text: '([^']+)'/", $claim, $msg_match);
            $msg = $msg_match[1] ?? "Başarılı!";
            echo green . "[+] $msg\n" . reset;
            
            preg_match('/let wait = (\d+)/', $claim, $timer_match);
            $wait_time = (int)($timer_match[1] ?? 60);
            
            if ($wait_time > 0) {
                echo gray . "[*] Sonraki claim: $wait_time saniye\n" . reset;
                sleep($wait_time);
            }
        } elseif (strpos($claim, "sufficient funds") !== false) {
            echo yellow . "[!] Faucet boş!\n" . reset;
        } else {
            echo red . "[-] Claim başarısız!\n" . reset;
        }
        
        sleep(2);
    }
    
    echo green . bold . "\n[+] Tüm coinler tamamlandı! 10 saniye sonra yeniden başlıyor...\n" . reset;
    sleep(10);
}
?>

import sys
import json
import time
import os
import warnings
warnings.filterwarnings('ignore')
os.environ["PYTHONWARNINGS"] = "ignore"

def log(msg):
    print(msg, file=sys.stderr)

def bypass_with_curl_cffi(url):
    try:
        from curl_cffi import requests as cf_requests
        session = cf_requests.Session(impersonate="chrome124")
        session.get("https://99faucet.com", timeout=15)
        time.sleep(2)
        response = session.get(url, timeout=20)
        cookies = session.cookies.get_dict()
        cf_clearance = cookies.get('cf_clearance', '')
        uagent = session.headers.get('User-Agent', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36')
        if cf_clearance:
            return {"cf_clearance": f"cf_clearance={cf_clearance}", "user_agent": uagent}
    except ImportError:
        log("curl_cffi not installed")
    except Exception as e:
        log(f"curl_cffi error: {str(e)}")
    return None

def bypass_with_cloudscraper(url):
    try:
        import cloudscraper
        scraper = cloudscraper.create_scraper(browser={'browser': 'chrome', 'platform': 'android', 'desktop': False})
        scraper.get("https://99faucet.com", timeout=15)
        time.sleep(2)
        response = scraper.get(url, timeout=20)
        cookies = scraper.cookies.get_dict()
        cf_clearance = cookies.get('cf_clearance', '')
        if cf_clearance:
            return {"cf_clearance": f"cf_clearance={cf_clearance}", "user_agent": 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36'}
    except ImportError:
        log("cloudscraper not installed")
    except Exception as e:
        log(f"cloudscraper error: {str(e)}")
    return None

def bypass_with_requests(url):
    try:
        import requests
        headers = {            'User-Agent': 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36',
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        }
        session = requests.Session()
        session.get("https://99faucet.com", headers=headers, timeout=15)
        time.sleep(3)
        response = session.get(url, headers=headers, timeout=20, allow_redirects=True)
        cookies = session.cookies.get_dict()
        cf_clearance = cookies.get('cf_clearance', '')
        if cf_clearance:
            return {"cf_clearance": f"cf_clearance={cf_clearance}", "user_agent": headers['User-Agent']}
    except ImportError:
        log("requests not installed")
    except Exception as e:
        log(f"requests error: {str(e)}")
    return None

def main():
    if len(sys.argv) < 2:
        log("Usage: python exec.py <url>")
        print(json.dumps({"cf_clearance": "", "user_agent": ""}))
        sys.exit(1)
    
    TARGET_URL = sys.argv[1]
    result = None
    
    log("[NINOCOIN] Trying curl_cffi...")
    result = bypass_with_curl_cffi(TARGET_URL)
    if result:
        log("[NINOCOIN] curl_cffi success!")
        print(json.dumps(result))
        sys.exit(0)
    
    log("[NINOCOIN] Trying cloudscraper...")
    result = bypass_with_cloudscraper(TARGET_URL)
    if result:
        log("[NINOCOIN] cloudscraper success!")
        print(json.dumps(result))
        sys.exit(0)
    
    log("[NINOCOIN] Trying requests...")
    result = bypass_with_requests(TARGET_URL)
    if result:
        log("[NINOCOIN] requests success!")
        print(json.dumps(result))
        sys.exit(0)
    
    log("[NINOCOIN] All bypass failed!")
    print(json.dumps({"cf_clearance": "", "user_agent": "Mozilla/5.0 (Linux; Android 10; K)"}))
if __name__ == "__main__":
    main()

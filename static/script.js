import { gsap } from 'gsap';

// --- Global State ---
let userId = null;
let currentBalance = 0.00;
let currentTotalEarned = 0.00;
let currentWithdrawnAmount = 0.00;
let myReferralCode = "";
let isAdPlaying = false;
let audioContext;
let backgroundMusicBuffer;
let clickSfxBuffer;
let backgroundMusicSource;

// --- DOM Elements ---
const userBalanceSpan = document.getElementById('user-balance');
const totalEarnedSpan = document.getElementById('total-earned');
const withdrawnAmountSpan = document.getElementById('withdrawn-amount');
const watchAdButton = document.getElementById('watch-ad-button');
const adPlaceholder = document.getElementById('ad-placeholder');
const openWithdrawalModalBtn = document.getElementById('open-withdrawal-modal');
const withdrawalModal = document.getElementById('withdrawal-modal');
const modalCurrentBalanceSpan = document.getElementById('modal-current-balance');
const withdrawalAmountInput = document.getElementById('withdrawal-amount');
const tonWalletAddressInput = document.getElementById('ton-wallet-address');
const confirmWithdrawalBtn = document.getElementById('confirm-withdrawal');
const cancelWithdrawalBtn = document.getElementById('cancel-withdrawal');
const withdrawalMessage = document.getElementById('withdrawal-message');
const myReferralCodeSpan = document.getElementById('my-referral-code');
const shareReferralButton = document.getElementById('share-referral-button');
const initialReferralModal = document.getElementById('initial-referral-modal');
const initialReferralInput = document.getElementById('initial-referral-input');
const submitInitialReferralBtn = document.getElementById('submit-initial-referral');
const initialReferralMessage = document.getElementById('initial-referral-message');

const MIN_WITHDRAWAL = 0.05;
const AD_EARNING_RANGE = { min: 0.0001, max: 0.0005 };

// --- Audio Functions ---
async function initAudioContext() {
    if (!audioContext) {
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
        await loadAudio('background_music.mp3', 'music');
        await loadAudio('click_sfx.mp3', 'sfx');
        playBackgroundMusic();
    }
}

async function loadAudio(url, type) {
    try {
        const response = await fetch(url);
        const arrayBuffer = await response.arrayBuffer();
        const audioBuffer = await audioContext.decodeAudioData(arrayBuffer);
        if (type === 'music') backgroundMusicBuffer = audioBuffer;
        else if (type === 'sfx') clickSfxBuffer = audioBuffer;
    } catch (error) {
        console.error('Error loading audio:', url, error);
    }
}

function playBackgroundMusic() {
    if (backgroundMusicBuffer && audioContext) {
        if (backgroundMusicSource) {
            backgroundMusicSource.stop();
            backgroundMusicSource.disconnect();
        }
        backgroundMusicSource = audioContext.createBufferSource();
        backgroundMusicSource.buffer = backgroundMusicBuffer;
        backgroundMusicSource.loop = true;
        backgroundMusicSource.connect(audioContext.destination);
        backgroundMusicSource.start(0);
    }
}

function playClickSound() {
    if (clickSfxBuffer && audioContext) {
        const source = audioContext.createBufferSource();
        source.buffer = clickSfxBuffer;
        source.connect(audioContext.destination);
        source.start(0);
    }
}

// --- Backend API Functions ---
async function initUserAPI(referralCode = null) {
    let uid = localStorage.getItem('takoniAdsUserId');
    if (!uid) {
        // Generate temporary UID
        uid = 'user_' + Date.now() + Math.random().toString(36).substring(2, 10);
        localStorage.setItem('takoniAdsUserId', uid);
    }

    // Send user info to backend
    const telegramUser = window.Telegram.WebApp.initDataUnsafe.user;
    const fullname = telegramUser.first_name + " " + (telegramUser.last_name || "");
    try {
        const res = await fetch('/save_user', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                telegram_id: telegramUser.id,
                username: telegramUser.username,
                fullname,
                referrer_id: referralCode
            })
        });
        if (!res.ok) console.error("Error saving user to backend");
        return telegramUser.id; // Use Telegram ID as userId
    } catch (err) {
        console.error("Error in initUserAPI:", err);
        return telegramUser.id;
    }
}

async function updateUserDataAPI(data) {
    try {
        await fetch('/save_user', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                telegram_id: userId,
                username: window.Telegram.WebApp.initDataUnsafe.user.username,
                fullname: window.Telegram.WebApp.initDataUnsafe.user.first_name,
                ...data
            })
        });
    } catch (error) {
        console.error("Error updating user data via API:", error);
    }
}

// --- UI Update ---
function updateBalanceUI() {
    userBalanceSpan.textContent = `${currentBalance.toFixed(4)} TON`;
    totalEarnedSpan.textContent = `${currentTotalEarned.toFixed(4)} TON`;
    withdrawnAmountSpan.textContent = `${currentWithdrawnAmount.toFixed(4)} TON`;
    modalCurrentBalanceSpan.textContent = `${currentBalance.toFixed(4)} TON`;
    myReferralCodeSpan.textContent = myReferralCode || 'YÜKLENİYOR...';
}

// --- Ad Watching ---
async function watchAd() {
    if (isAdPlaying || !userId) return;
    initAudioContext();
    playClickSound();

    isAdPlaying = true;
    watchAdButton.disabled = true;
    adPlaceholder.classList.remove('hidden');

    setTimeout(async () => {
        const earnings = Math.random()*(AD_EARNING_RANGE.max-AD_EARNING_RANGE.min)+AD_EARNING_RANGE.min;
        currentBalance += earnings;
        currentTotalEarned += earnings;

        await updateUserDataAPI({ balance: currentBalance, totalEarned: currentTotalEarned });
        adPlaceholder.classList.add('hidden');
        watchAdButton.disabled = false;
        isAdPlaying = false;
        updateBalanceUI();
    }, 3000);
}

// --- Withdrawal ---
function openWithdrawalModal() { withdrawalModal.classList.add('visible'); }
function closeWithdrawalModal() { withdrawalModal.classList.remove('visible'); }

async function confirmWithdrawal() {
    const amount = parseFloat(withdrawalAmountInput.value);
    if (amount < MIN_WITHDRAWAL || amount > currentBalance) return;
    currentBalance -= amount;
    currentWithdrawnAmount += amount;
    await updateUserDataAPI({ balance: currentBalance, withdrawnAmount: currentWithdrawnAmount });
    updateBalanceUI();
    closeWithdrawalModal();
}

// --- Referral ---
async function handleSubmitInitialReferral() {
    const referralCode = initialReferralInput.value.trim() || null;
    userId = await initUserAPI(referralCode);
    myReferralCode = userId;
    updateBalanceUI();
    initialReferralModal.classList.remove('visible');
}

function shareReferralCode() {
    if (!myReferralCode) return;
    const shareText = `TakoniAds uygulamasını deneyin! Kodum: ${myReferralCode}`;
    navigator.clipboard.writeText(shareText).then(() => alert('Referans kodunuz panoya kopyalandı!'));
}

// --- Event Listeners ---
watchAdButton.addEventListener('click', watchAd);
openWithdrawalModalBtn.addEventListener('click', openWithdrawalModal);
confirmWithdrawalBtn.addEventListener('click', confirmWithdrawal);
cancelWithdrawalBtn.addEventListener('click', closeWithdrawalModal);
submitInitialReferralBtn.addEventListener('click', handleSubmitInitialReferral);
shareReferralButton.addEventListener('click', shareReferralCode);

// --- Init App ---
async function initApp() {
    userId = localStorage.getItem('takoniAdsUserId');
    if (!userId) initialReferralModal.classList.add('visible');
    else {
        myReferralCode = userId;
        updateBalanceUI();
    }
    document.addEventListener('click', initAudioContext, { once: true });
    document.addEventListener('touchstart', initAudioContext, { once: true });
}

initApp();

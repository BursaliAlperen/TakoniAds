import { gsap } from 'gsap';
import { initUser, updateUserData, listenToUserData } from './referralLogic.js';

// --- Global State ---
let userId = null;
let currentBalance = 0.00; // Will be updated by Firestore
let currentTotalEarned = 0.00;
let currentWithdrawnAmount = 0.00;
let myReferralCode = "";
let isAdPlaying = false;
let audioContext;
let backgroundMusicBuffer;
let clickSfxBuffer;
let backgroundMusicSource; // To control loop and stopping
let unsubscribeFromUserData = null; // To store the Firestore unsubscribe function

const MIN_WITHDRAWAL = 0.05;
const AD_EARNING_RANGE = { min: 0.0001, max: 0.0005 }; // Simulate small earnings

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
const myReferralCodeSpan = document.getElementById('my-referral-code'); // New
const shareReferralButton = document.getElementById('share-referral-button'); // New
const initialReferralModal = document.getElementById('initial-referral-modal'); // New
const initialReferralInput = document.getElementById('initial-referral-input'); // New
const submitInitialReferralBtn = document.getElementById('submit-initial-referral'); // New
const initialReferralMessage = document.getElementById('initial-referral-message'); // New

// --- Audio Functions ---

async function initAudioContext() {
    if (!audioContext) {
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
        await loadAudio('background_music.mp3', 'music');
        await loadAudio('click_sfx.mp3', 'sfx');
        playBackgroundMusic(); // Start playing after user interaction
    }
}

async function loadAudio(url, type) {
    try {
        const response = await fetch(url);
        const arrayBuffer = await response.arrayBuffer();
        const audioBuffer = await audioContext.decodeAudioData(arrayBuffer);
        if (type === 'music') {
            backgroundMusicBuffer = audioBuffer;
        } else if (type === 'sfx') {
            clickSfxBuffer = audioBuffer;
        }
    } catch (error) {
        console.error('Error loading audio:', url, error);
    }
}

function playBackgroundMusic() {
    if (backgroundMusicBuffer && audioContext) {
        // Stop any existing music to avoid multiple layers
        if (backgroundMusicSource) {
            backgroundMusicSource.stop();
            backgroundMusicSource.disconnect();
        }

        backgroundMusicSource = audioContext.createBufferSource();
        backgroundMusicSource.buffer = backgroundMusicBuffer;
        backgroundMusicSource.loop = true;
        backgroundMusicSource.gainNode = audioContext.createGain(); // Create a gain node
        backgroundMusicSource.gainNode.gain.value = 0.3; // Set volume (e.g., 30%)
        backgroundMusicSource.connect(backgroundMusicSource.gainNode);
        backgroundMusicSource.gainNode.connect(audioContext.destination);
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

// --- UI Update Functions ---
function updateBalanceUI() {
    userBalanceSpan.textContent = `${currentBalance.toFixed(4)} TON`;
    totalEarnedSpan.textContent = `${currentTotalEarned.toFixed(4)} TON`;
    withdrawnAmountSpan.textContent = `${currentWithdrawnAmount.toFixed(4)} TON`;
    modalCurrentBalanceSpan.textContent = `${currentBalance.toFixed(4)} TON`;
    myReferralCodeSpan.textContent = myReferralCode || 'YÜKLENİYOR...';
}

// --- Ad Watching Logic ---
async function watchAd() {
    if (isAdPlaying || !userId) return;

    initAudioContext(); // Ensure audio context is initialized and music plays on first interaction
    playClickSound();

    isAdPlaying = true;
    gsap.to(watchAdButton, {
        scale: 0.9,
        duration: 0.1,
        onComplete: () => {
            gsap.to(watchAdButton, { scale: 1, duration: 0.2, ease: "elastic.out(1, 0.3)" });
        }
    });

    watchAdButton.disabled = true;
    adPlaceholder.classList.remove('hidden');

    // Simulate ad playback duration
    setTimeout(async () => {
        const earnings = Math.random() * (AD_EARNING_RANGE.max - AD_EARNING_RANGE.min) + AD_EARNING_RANGE.min;
        const newBalance = currentBalance + earnings;
        const newTotalEarned = currentTotalEarned + earnings;

        try {
            await updateUserData(userId, {
                balance: newBalance,
                totalEarned: newTotalEarned
            });
            console.log("Ad earnings updated in Firestore.");
        } catch (error) {
            console.error("Error updating ad earnings:", error);
            // Revert local state if Firestore update fails, though onSnapshot will eventually correct it
        }

        adPlaceholder.classList.add('hidden');
        watchAdButton.disabled = false;
        isAdPlaying = false;

        // Visual feedback for earning
        gsap.to(userBalanceSpan, {
            scale: 1.1,
            color: '#a7ffeb', // A bright teal for visual pop
            duration: 0.2,
            yoyo: true,
            repeat: 1,
            onComplete: () => {
                userBalanceSpan.style.color = ''; // Reset color
            }
        });

    }, 3000); // Simulate 3-second ad
}

// --- Withdrawal Logic ---
function openWithdrawalModal() {
    initAudioContext(); // Ensure audio context is initialized
    playClickSound();

    modalCurrentBalanceSpan.textContent = `${currentBalance.toFixed(4)} TON`;
    withdrawalAmountInput.value = ''; // Clear previous input
    tonWalletAddressInput.value = '';
    withdrawalMessage.textContent = '';
    withdrawalMessage.classList.remove('success', 'error');

    withdrawalModal.classList.add('visible');
    gsap.fromTo(withdrawalModal.querySelector('.modal-content'),
        { y: 50, opacity: 0 },
        { y: 0, opacity: 1, duration: 0.3, ease: "power2.out" }
    );
}

function closeWithdrawalModal() {
    gsap.to(withdrawalModal.querySelector('.modal-content'),
        {
            y: 50, opacity: 0, duration: 0.3, ease: "power2.in",
            onComplete: () => {
                withdrawalModal.classList.remove('visible');
            }
        }
    );
}

async function confirmWithdrawal() {
    playClickSound();

    const amount = parseFloat(withdrawalAmountInput.value);
    const walletAddress = tonWalletAddressInput.value.trim();

    withdrawalMessage.classList.remove('success', 'error');

    if (isNaN(amount) || amount < MIN_WITHDRAWAL) {
        withdrawalMessage.textContent = `Çekim miktarı en az ${MIN_WITHDRAWAL} TON olmalı.`;
        withdrawalMessage.classList.add('error');
        return;
    }

    if (amount > currentBalance) {
        withdrawalMessage.textContent = `Yetersiz bakiye. Mevcut: ${currentBalance.toFixed(4)} TON`;
        withdrawalMessage.classList.add('error');
        return;
    }

    if (!walletAddress) {
        withdrawalMessage.textContent = 'Lütfen TON cüzdan adresinizi girin.';
        withdrawalMessage.classList.add('error');
        return;
    }

    // Basic TON address validation (very simple, real validation would be more complex)
    if (!/^UQ[A-Za-z0-9\-_]{46}$/.test(walletAddress)) {
        withdrawalMessage.textContent = 'Geçersiz TON cüzdan adresi formatı. (Örn: UQ...)';
        withdrawalMessage.classList.add('error');
        return;
    }

    // Simulate withdrawal processing
    const newBalance = currentBalance - amount;
    const newWithdrawnAmount = currentWithdrawnAmount + amount;

    try {
        await updateUserData(userId, {
            balance: newBalance,
            withdrawnAmount: newWithdrawnAmount
        });
        console.log("Withdrawal updated in Firestore.");
        withdrawalMessage.textContent = `${amount.toFixed(4)} TON başarıyla çekim talebi oluşturuldu!`;
        withdrawalMessage.classList.add('success');
        setTimeout(() => {
            closeWithdrawalModal();
        }, 2000);
    } catch (error) {
        console.error("Error confirming withdrawal:", error);
        withdrawalMessage.textContent = 'Çekim işlemi sırasında bir hata oluştu.';
        withdrawalMessage.classList.add('error');
    }
}

// --- Referral Logic ---
function openInitialReferralModal() {
    initialReferralModal.classList.add('visible');
    gsap.fromTo(initialReferralModal.querySelector('.modal-content'),
        { y: 50, opacity: 0 },
        { y: 0, opacity: 1, duration: 0.3, ease: "power2.out" }
    );
}

function closeInitialReferralModal() {
    gsap.to(initialReferralModal.querySelector('.modal-content'),
        {
            y: 50, opacity: 0, duration: 0.3, ease: "power2.in",
            onComplete: () => {
                initialReferralModal.classList.remove('visible');
                initialReferralMessage.textContent = ''; // Clear message
            }
        }
    );
}

async function handleSubmitInitialReferral() {
    playClickSound();
    const referralCode = initialReferralInput.value.trim();
    initialReferralMessage.classList.remove('success', 'error');

    try {
        userId = await initUser(referralCode);
        localStorage.setItem('takoniAdsUserId', userId); // Ensure userId is persisted
        if (unsubscribeFromUserData) {
            unsubscribeFromUserData(); // Unsubscribe from any previous listener if existing
        }
        unsubscribeFromUserData = listenToUserData(userId, handleUserDataUpdate);
        closeInitialReferralModal();
    } catch (error) {
        console.error("Error initializing user with referral:", error);
        initialReferralMessage.textContent = 'Kullanıcı oluşturulurken bir hata oluştu.';
        initialReferralMessage.classList.add('error');
    }
}

function handleUserDataUpdate(userData) {
    if (userData) {
        currentBalance = userData.balance || 0;
        currentTotalEarned = userData.totalEarned || 0;
        currentWithdrawnAmount = userData.withdrawnAmount || 0;
        myReferralCode = userData.referralCode || "";
        updateBalanceUI();
        console.log("User data updated:", userData);
    } else {
        console.log("User data not found or removed.");
        // If user data is unexpectedly not found, you might want to reset the app state
        // and prompt for new user registration or show an error.
        // For this project, we assume user data always exists after initUser.
    }
}

function shareReferralCode() {
    playClickSound();
    if (myReferralCode) {
        const shareText = `TakoniAds uygulamasını deneyin ve para kazanın! Benim referans kodum: ${myReferralCode}`;
        if (navigator.share) {
            navigator.share({
                title: 'TakoniAds Referans Kodum',
                text: shareText,
                url: window.location.href // Or a specific app link for sharing
            }).then(() => {
                console.log('Referral code shared successfully');
            }).catch((error) => {
                console.error('Error sharing referral code:', error);
                // Fallback to clipboard if native sharing fails
                navigator.clipboard.writeText(shareText).then(() => {
                    alert('Referans kodunuz panoya kopyalandı:\n' + myReferralCode);
                }).catch(err => {
                    alert('Referans kodu kopyalanamadı: ' + myReferralCode);
                });
            });
        } else if (navigator.clipboard) {
            navigator.clipboard.writeText(shareText).then(() => {
                alert('Referans kodunuz panoya kopyalandı:\n' + myReferralCode);
            }).catch(err => {
                alert('Referans kodu kopyalanamadı: ' + myReferralCode);
            });
        } else {
            alert('Referans kodunuz: ' + myReferralCode + '\nLütfen manuel olarak kopyalayın.');
        }
    } else {
        alert('Referans kodu henüz yüklenmedi.');
    }
}

// --- Event Listeners ---
watchAdButton.addEventListener('click', watchAd);
openWithdrawalModalBtn.addEventListener('click', openWithdrawalModal);
confirmWithdrawalBtn.addEventListener('click', confirmWithdrawal);
cancelWithdrawalBtn.addEventListener('click', closeWithdrawalModal);
shareReferralButton.addEventListener('click', shareReferralCode); // New listener
submitInitialReferralBtn.addEventListener('click', handleSubmitInitialReferral); // New listener

// Close modal if clicking outside (optional, but good UX)
withdrawalModal.addEventListener('click', (event) => {
    if (event.target === withdrawalModal) {
        closeWithdrawalModal();
    }
});
initialReferralModal.addEventListener('click', (event) => {
    if (event.target === initialReferralModal) {
        // You might want to prevent closing the initial referral modal by clicking outside
        // to ensure user makes a choice. If you want to allow it, uncomment the line below:
        // closeInitialReferralModal();
    }
});

// --- Initial Setup ---
async function initApp() {
    // Check if user already has an ID in localStorage
    userId = localStorage.getItem('takoniAdsUserId');

    if (!userId) {
        // If no user ID, show the initial referral modal to create a user
        openInitialReferralModal();
    } else {
        // If user ID exists, initialize user and start listening to data
        try {
            // Re-call initUser with null to ensure document exists and listener is set up.
            // This is safe even if the user document already exists; it won't overwrite data.
            userId = await initUser(null);
            unsubscribeFromUserData = listenToUserData(userId, handleUserDataUpdate);
        } catch (error) {
            console.error("Error during initial user setup (existing user):", error);
            // Fallback: If existing userId fails, perhaps clear it and prompt new user
            localStorage.removeItem('takoniAdsUserId');
            openInitialReferralModal();
        }
    }

    // Pre-load audio on initial interaction (e.g., first tap on button or any part of the screen)
    // This allows background music to play immediately after the first user interaction.
    document.addEventListener('touchstart', initAudioContext, { once: true });
    document.addEventListener('click', initAudioContext, { once: true });
}

initApp();

// Optional: Clean up listener on page unload
window.addEventListener('beforeunload', () => {
    if (unsubscribeFromUserData) {
        unsubscribeFromUserData();
    }
});
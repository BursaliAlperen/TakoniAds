
```
import { db } from './firebaseConfig.js'; 
import { doc, setDoc, getDoc, updateDoc, query, collection, where, getDocs, onSnapshot } from "https://www.gstatic.com/firebasejs/10.12.2/firebase-firestore.js";

// Function to get or generate a unique ID for guest users
const getOrCreateGuestId = () => {
    let uid = localStorage.getItem('takoniAdsUserId');
    if (!uid) {
        // Generate a unique ID for guest user
        uid = 'guest_' + Date.now() + Math.random().toString(36).substring(2, 15);
        localStorage.setItem('takoniAdsUserId', uid);
    }
    return uid;
};

export const initUser = async (inputReferralCode) => {
    const uid = getOrCreateGuestId(); 

    const userRef = doc(db, "users", uid);
    const docSnap = await getDoc(userRef);

    if (!docSnap.exists()) {
        const myCode = uid.substring(0, 6).toUpperCase(); 
        await setDoc(userRef, {
            balance: 0,
            totalEarned: 0, 
            withdrawnAmount: 0, 
            referralCode: myCode,
            referredBy: inputReferralCode || null,
            createdAt: new Date().toISOString()
        });

        if (inputReferralCode) {
            // Check if the input referral code actually exists in the database
            const q = query(collection(db, "users"), where("referralCode", "==", inputReferralCode));
            const querySnapshot = await getDocs(q);

            if (!querySnapshot.empty) {
                querySnapshot.forEach(async (referrerDoc) => {
                    // Only apply bonus if the referrer is not the new user themselves (edge case)
                    if (referrerDoc.id !== uid) {
                        const currentBalance = referrerDoc.data().balance || 0;
                        await updateDoc(doc(db, "users", referrerDoc.id), {
                            balance: currentBalance + 0.1 
                        });
                        console.log(`Referral bonus of 0.1 TON given to ${referrerDoc.id}`);
                    }
                });
            } else {
                console.warn(`Referral code ${inputReferralCode} not found.`);
                // Could update the new user's `referredBy` to null or show a message
            }
        }
        console.log(`New user ${uid} created with referral code ${myCode}`);
    } else {
        console.log(`User ${uid} already exists.`);
    }
    return uid; 
};

// Function to update user data in Firestore
export const updateUserData = async (uid, data) => {
    const userRef = doc(db, "users", uid);
    await updateDoc(userRef, {
        ...data,
        updatedAt: new Date().toISOString()
    });
};

// Function to listen for real-time updates to user data
export const listenToUserData = (uid, callback) => {
    const userRef = doc(db, "users", uid);
    const unsubscribe = onSnapshot(userRef, (docSnap) => {
        if (docSnap.exists()) {
            callback({ id: docSnap.id, ...docSnap.data() });
        } else {
            console.log("No such user document!");
            callback(null);
        }
    });
    return unsubscribe; 
};
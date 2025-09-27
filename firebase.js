import { initializeApp } from "firebase/app";
import { getFirestore } from "firebase/firestore";
import { getAnalytics } from "firebase/analytics";

const firebaseConfig = {
  apiKey: "AIzaSyCwsNNz8mLlvRqCTxycfTFMGFBnCQ1oJ8s",
  authDomain: "takoniads.firebaseapp.com",
  databaseURL: "https://takoniads-default-rtdb.europe-west1.firebasedatabase.app",
  projectId: "takoniads",
  storageBucket: "takoniads.firebasestorage.app",
  messagingSenderId: "921718345986",
  appId: "1:921718345986:web:a3484dceee0defaaf4b21b",
  measurementId: "G-4S0B4YQCK7"
};

const app = initializeApp(firebaseConfig);
const analytics = getAnalytics(app);
export const db = getFirestore(app); // Firestore başlatıldı

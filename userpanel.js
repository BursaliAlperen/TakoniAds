import { useEffect, useState } from "react";
import { doc, getDoc, setDoc, updateDoc, increment, collection, addDoc } from "firebase/firestore";
import { db } from "./firebase";

export default function UserPanel({ user }) {
  const [balance, setBalance] = useState(0);
  const [adsWatched, setAdsWatched] = useState(0);
  const [address, setAddress] = useState("");
  const [withdrawAmount, setWithdrawAmount] = useState("");

  useEffect(() => {
    const initUser = async () => {
      const userRef = doc(db, "users", String(user.id));
      const snap = await getDoc(userRef);
      if (!snap.exists()) {
        await setDoc(userRef, {
          name: user.first_name,
          balanceTon: 0,
          adsWatched: 0,
          wallet: { address: "", withdrawRequests: {} }
        });
      } else {
        const data = snap.data();
        setBalance(data.balanceTon);
        setAdsWatched(data.adsWatched);
        setAddress(data.wallet?.address || "");
      }
    };
    initUser();
  }, [user.id, user.first_name]);

  const watchAd = async () => {
    const userRef = doc(db, "users", String(user.id));
    await updateDoc(userRef, {
      balanceTon: increment(0.0005),
      adsWatched: increment(1)
    });
    const snap = await getDoc(userRef);
    setBalance(snap.data().balanceTon);
    setAdsWatched(snap.data().adsWatched);
  };

  const saveAddress = async () => {
    const userRef = doc(db, "users", String(user.id));
    await updateDoc(userRef, { "wallet.address": address });
    alert("Adres kaydedildi ✅");
  };

  const requestWithdraw = async () => {
    if (!withdrawAmount || parseFloat(withdrawAmount) <= 0) return alert("Geçerli bir miktar girin.");
    const withdrawRef = collection(db, `users/${user.id}/wallet/withdrawRequests`);
    await addDoc(withdrawRef, { amount: parseFloat(withdrawAmount), address, status: "pending", date: new Date().toISOString() });
    alert("Çekim talebi alındı ✅");
    setWithdrawAmount("");
  };

  return (
    <div className="p-4 max-w-md mx-auto bg-white rounded-xl shadow-md mt-4">
      <h1 className="text-2xl font-bold text-center text-blue-700 mb-4">Reklam İzle & Para Kazan</h1>
      <p className="text-center text-gray-900 mb-2">Merhaba <span className="font-semibold">{user.first_name}</span> 👋</p>
      <p className="text-center text-gray-900 mb-2">Bakiye: <span className="font-semibold">{balance.toFixed(6)} TON</span></p>
      <p className="text-center text-gray-900 mb-4">Reklamlar: <span className="font-semibold">{adsWatched}</span></p>
      <button onClick={watchAd} className="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded-lg mb-4 transition">Reklam İzle 🎬</button>

      <div className="border-t pt-4">
        <h2 className="text-xl font-semibold text-blue-700 mb-2">Wallet 💳</h2>
        <input type="text" placeholder="Ton adresi" value={address} onChange={(e) => setAddress(e.target.value)} className="border border-gray-300 p-2 w-full rounded mb-2"/>
        <button onClick={saveAddress} className="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded mb-4 transition">Adres Kaydet</button>

        <input type="number" placeholder="Çekilecek miktar" value={withdrawAmount} onChange={(e) => setWithdrawAmount(e.target.value)} className="border border-gray-300 p-2 w-full rounded mb-2"/>
        <button onClick={requestWithdraw} className="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded transition">Çekim Talebi Gönder</button>
      </div>
    </div>
  );
}

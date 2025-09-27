import { useEffect, useState } from "react";
import { collection, getDocs, doc, updateDoc } from "firebase/firestore";
import { db } from "./firebase";

export default function AdminPanel() {
  const [users, setUsers] = useState([]);

  useEffect(() => {
    const fetchUsers = async () => {
      const usersCol = collection(db, "users");
      const usersSnap = await getDocs(usersCol);
      const list = [];
      for (let u of usersSnap.docs) {
        const data = u.data();
        data.id = u.id;
        list.push(data);
      }
      setUsers(list);
    };
    fetchUsers();
  }, []);

  const updateRequest = async (userId, requestId, status) => {
    const reqRef = doc(db, `users/${userId}/wallet/withdrawRequests/${requestId}`);
    await updateDoc(reqRef, { status });
    alert(`Talep ${status}`);
  };

  return (
    <div className="p-4 max-w-2xl mx-auto">
      <h1 className="text-2xl font-bold text-center text-blue-700 mb-4">Admin Panel</h1>
      {users.map((u) => (
        <div key={u.id} className="border p-4 rounded-xl mb-4 shadow-md bg-white">
          <p className="font-semibold text-gray-900">{u.name}</p>
          <p className="text-gray-900">Bakiye: {u.balanceTon.toFixed(6)} TON | Reklamlar: {u.adsWatched}</p>
          <p className="text-gray-900">Adres: {u.wallet.address}</p>
          <h2 className="font-semibold text-blue-700 mt-2 mb-1">Çekim Talepleri:</h2>
          {u.wallet.withdrawRequests ? Object.entries(u.wallet.withdrawRequests).map(([rid, req]) => (
            <div key={rid} className="flex flex-col md:flex-row md:items-center gap-2 mb-1">
              <p className="text-gray-900">{req.amount} TON | {req.status}</p>
              {req.status === "pending" && (
                <div className="flex gap-2">
                  <button onClick={() => updateRequest(u.id, rid, "approved")} className="bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded transition">Onayla</button>
                  <button onClick={() => updateRequest(u.id, rid, "denied")} className="bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded transition">Reddet</button>
                </div>
              )}
            </div>
          )) : <p className="text-gray-500">

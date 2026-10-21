import React, { useEffect, useState } from 'react';
import { AlertCircle, CheckCircle, ClipboardList, FileEdit, Printer, RefreshCw, Search } from 'lucide-react';
import { api } from '../../api/client';
import { useAuth } from '../../context/AuthContext';
import Modal from '../../components/Modal';
import ReceiptModal from '../../components/ReceiptModal';

const today = () => {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
};

export default function TransactionHistoryPage() {
  const { isOperator } = useAuth();
  const [filters, setFilters] = useState({ q: '', start_date: '', end_date: '' });
  const [history, setHistory] = useState({ items: [], total: 0, page: 1, total_pages: 1 });
  const [areas, setAreas] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [selected, setSelected] = useState(null);
  const [paymentOpen, setPaymentOpen] = useState(false);
  const [correctionOpen, setCorrectionOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [cashReceived, setCashReceived] = useState('');
  const [correction, setCorrection] = useState(null);
  const [receipt, setReceipt] = useState(null);
  const [receiptOpen, setReceiptOpen] = useState(false);

  const fetchHistory = async (page = 1) => {
    setLoading(true);
    setError('');
    const params = new URLSearchParams({ ...filters, page: String(page), per_page: '20' });
    try {
      const response = await api.get(`/operator/transactions/history?${params.toString()}`);
      setHistory(response.data);
    } catch (err) {
      setError(err.message || 'Gagal memuat riwayat transaksi.');
    } finally {
      setLoading(false);
    }
  };

  const fetchAreas = async () => {
    try {
      const response = await api.get(isOperator ? '/operator/areas' : '/admin/areas');
      setAreas(response.data.areas || []);
    } catch (err) {
      setError(err.message || 'Gagal memuat daftar area.');
    }
  };

  useEffect(() => {
    fetchHistory();
    fetchAreas();
  }, []);

  const openPayment = (transaction) => {
    setSelected(transaction);
    setCashReceived(transaction.biaya_total || '');
    setPaymentOpen(true);
  };

  const submitPayment = async (event) => {
    event.preventDefault();
    if (!selected) return;
    setSaving(true);
    setError('');
    try {
      await api.post(`/operator/transactions/${selected.id_parkir}/payment`, {
        uang_diterima: cashReceived,
      });
      setPaymentOpen(false);
      setNotice(`Pembayaran tiket #${selected.id_parkir} berhasil dicatat.`);
      await fetchHistory(history.page);
    } catch (err) {
      setError(err.message || 'Gagal mencatat pembayaran.');
    } finally {
      setSaving(false);
    }
  };

  const openCorrection = (transaction) => {
    setSelected(transaction);
    setCorrection({
      plat_nomor: transaction.plat_nomor,
      jenis_kendaraan: transaction.jenis_kendaraan,
      warna: transaction.warna || '',
      id_area: String(transaction.id_area),
      reason: '',
    });
    setCorrectionOpen(true);
  };

  const submitCorrection = async (event) => {
    event.preventDefault();
    if (!selected || !correction) return;
    setSaving(true);
    setError('');
    try {
      await api.patch(`/operator/transactions/${selected.id_parkir}/correction`, {
        ...correction,
        id_area: Number(correction.id_area),
      });
      setCorrectionOpen(false);
      setNotice(`Koreksi tiket #${selected.id_parkir} tersimpan dan tercatat di audit.`);
      await fetchHistory(history.page);
    } catch (err) {
      setError(err.message || 'Gagal mengoreksi transaksi.');
    } finally {
      setSaving(false);
    }
  };

  const printReceipt = async (transaction) => {
    setError('');
    try {
      const response = await api.get(`/operator/transactions/${transaction.id_parkir}/receipt`);
      setReceipt(response.data.receipt);
      setReceiptOpen(true);
    } catch (err) {
      setError(err.message || 'Gagal memuat struk.');
    }
  };

  const paymentLabel = (status) => {
    if (status === 'lunas') return 'Lunas';
    if (status === 'belum_lunas') return 'Belum lunas';
    return 'Status pembayaran lama belum diketahui';
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Riwayat Transaksi</h1>
          <p className="text-sm text-slate-500">Cari transaksi, catat pembayaran tunai, cetak ulang struk, atau koreksi data dengan alasan.</p>
        </div>
        <button onClick={() => fetchHistory(history.page)} disabled={loading} className="inline-flex items-center gap-2 px-3.5 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 disabled:opacity-50">
          <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} /> Segarkan
        </button>
      </div>

      {(error || notice) && (
        <div className={`p-4 rounded-xl text-sm flex items-center gap-2 border ${error ? 'bg-red-50 text-red-700 border-red-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'}`}>
          {error ? <AlertCircle className="w-5 h-5 shrink-0" /> : <CheckCircle className="w-5 h-5 shrink-0" />}
          <span>{error || notice}</span>
          <button className="ml-auto text-xs font-semibold" onClick={() => { setError(''); setNotice(''); }}>Tutup</button>
        </div>
      )}

      <form onSubmit={(event) => { event.preventDefault(); fetchHistory(1); }} className="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        <label className="md:col-span-2 text-xs font-semibold text-slate-600">
          Nomor tiket atau plat nomor
          <div className="relative mt-1">
            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-3" />
            <input value={filters.q} onChange={(event) => setFilters({ ...filters, q: event.target.value })} placeholder="Contoh: 1042 atau B1234ABC" className="w-full pl-9 pr-3 py-2.5 rounded-lg border border-slate-200 text-sm font-normal" />
          </div>
        </label>
        <label className="text-xs font-semibold text-slate-600">
          Tanggal mulai
          <input type="date" value={filters.start_date} onChange={(event) => setFilters({ ...filters, start_date: event.target.value })} className="mt-1 w-full px-3 py-2 rounded-lg border border-slate-200 text-sm font-normal" />
        </label>
        <label className="text-xs font-semibold text-slate-600">
          Tanggal selesai
          <input type="date" value={filters.end_date} onChange={(event) => setFilters({ ...filters, end_date: event.target.value })} className="mt-1 w-full px-3 py-2 rounded-lg border border-slate-200 text-sm font-normal" />
        </label>
        <button type="submit" disabled={loading} className="md:col-span-4 justify-self-start px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold disabled:opacity-50">
          Cari transaksi
        </button>
      </form>

      <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div className="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
          <ClipboardList className="w-4 h-4 text-blue-600" />
          <span className="font-semibold text-sm">Hasil pencarian ({history.total || 0})</span>
        </div>
        <div className="overflow-x-auto">
          <table className="min-w-full text-left text-sm">
            <thead className="bg-slate-50 text-slate-500 text-xs uppercase">
              <tr>
                <th className="px-4 py-3">Tiket / Plat</th>
                <th className="px-4 py-3">Area</th>
                <th className="px-4 py-3">Waktu masuk / keluar</th>
                <th className="px-4 py-3">Tagihan</th>
                <th className="px-4 py-3">Pembayaran</th>
                <th className="px-4 py-3">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {loading ? (
                <tr><td colSpan="6" className="px-4 py-12 text-center text-slate-400"><RefreshCw className="w-5 h-5 animate-spin inline mr-2" />Memuat riwayat...</td></tr>
              ) : history.items.length === 0 ? (
                <tr><td colSpan="6" className="px-4 py-12 text-center text-slate-400">Tidak ada transaksi yang cocok.</td></tr>
              ) : history.items.map((transaction) => (
                <tr key={transaction.id_parkir} className="align-top">
                  <td className="px-4 py-3">
                    <div className="font-mono font-bold text-blue-700">#{String(transaction.id_parkir).padStart(6, '0')}</div>
                    <div className="font-semibold text-slate-900">{transaction.plat_nomor}</div>
                    <div className="text-xs text-slate-500 capitalize">{transaction.jenis_kendaraan}</div>
                  </td>
                  <td className="px-4 py-3 text-slate-700">{transaction.nama_area}</td>
                  <td className="px-4 py-3 text-xs text-slate-600 whitespace-nowrap">
                    <div>Masuk: {transaction.waktu_masuk}</div>
                    <div>Keluar: {transaction.waktu_keluar || 'Masih parkir'}</div>
                  </td>
                  <td className="px-4 py-3 font-semibold text-slate-800">
                    {transaction.biaya_total === null ? '-' : `Rp${Number(transaction.biaya_total).toLocaleString('id-ID')}`}
                  </td>
                  <td className="px-4 py-3">
                    <span className={`text-xs font-semibold ${transaction.status_pembayaran === 'lunas' ? 'text-emerald-700' : 'text-amber-700'}`}>
                      {paymentLabel(transaction.status_pembayaran)}
                    </span>
                    {transaction.status_pembayaran === 'lunas' && <div className="text-xs text-slate-500 mt-1">{transaction.metode_pembayaran} · kembalian Rp{Number(transaction.uang_kembalian || 0).toLocaleString('id-ID')}</div>}
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex flex-wrap gap-2">
                      <button onClick={() => printReceipt(transaction)} className="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md bg-slate-100 text-slate-700 text-xs font-semibold"><Printer className="w-3.5 h-3.5" /> Struk</button>
                      {isOperator && transaction.status === 'keluar' && transaction.status_pembayaran === 'belum_lunas' && (
                        <button onClick={() => openPayment(transaction)} className="px-2.5 py-1.5 rounded-md bg-emerald-600 text-white text-xs font-semibold">Catat tunai</button>
                      )}
                      <button onClick={() => openCorrection(transaction)} className="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md bg-amber-100 text-amber-800 text-xs font-semibold"><FileEdit className="w-3.5 h-3.5" /> Koreksi</button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {history.total_pages > 1 && (
          <div className="px-5 py-3 border-t border-slate-100 flex justify-between items-center text-xs">
            <span>Halaman {history.page} dari {history.total_pages}</span>
            <div className="flex gap-2">
              <button disabled={history.page <= 1 || loading} onClick={() => fetchHistory(history.page - 1)} className="px-3 py-1.5 rounded bg-slate-100 disabled:opacity-40">Sebelumnya</button>
              <button disabled={history.page >= history.total_pages || loading} onClick={() => fetchHistory(history.page + 1)} className="px-3 py-1.5 rounded bg-slate-100 disabled:opacity-40">Berikutnya</button>
            </div>
          </div>
        )}
      </div>

      <Modal isOpen={paymentOpen} onClose={() => setPaymentOpen(false)} title={`Pembayaran Tunai #${selected?.id_parkir}`}>
        <form onSubmit={submitPayment} className="space-y-4">
          <p className="text-sm text-slate-600">Tagihan: <strong>Rp{Number(selected?.biaya_total || 0).toLocaleString('id-ID')}</strong></p>
          <label className="block text-sm font-semibold text-slate-700">
            Uang diterima
            <input type="number" min={selected?.biaya_total || 0} step="0.01" required value={cashReceived} onChange={(event) => setCashReceived(event.target.value)} className="mt-1 w-full px-3 py-2.5 border border-slate-200 rounded-lg font-normal" />
          </label>
          <p className="text-xs text-slate-500">Kembalian dihitung dan disimpan oleh server. Status pembayaran berubah menjadi lunas setelah berhasil.</p>
          <div className="flex justify-end gap-2">
            <button type="button" onClick={() => setPaymentOpen(false)} className="px-4 py-2 rounded-lg border border-slate-200 text-sm">Batal</button>
            <button disabled={saving} className="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold disabled:opacity-50">{saving ? 'Menyimpan...' : 'Simpan pembayaran'}</button>
          </div>
        </form>
      </Modal>

      <Modal isOpen={correctionOpen} onClose={() => setCorrectionOpen(false)} title={`Koreksi transaksi #${selected?.id_parkir}`} maxWidth="max-w-xl">
        {correction && (
          <form onSubmit={submitCorrection} className="space-y-3">
            <p className="text-xs text-amber-700 bg-amber-50 p-3 rounded-lg">Hanya data kendaraan dan area yang dapat dikoreksi. Waktu, tarif, biaya, dan pembayaran tidak dapat diubah. Perubahan disimpan di audit trail.</p>
            <div className="grid sm:grid-cols-2 gap-3">
              <label className="text-xs font-semibold text-slate-600">Plat nomor
                <input required maxLength="20" value={correction.plat_nomor} onChange={(event) => setCorrection({ ...correction, plat_nomor: event.target.value.toUpperCase() })} className="mt-1 w-full px-3 py-2 border rounded-lg text-sm font-normal" />
              </label>
              <label className="text-xs font-semibold text-slate-600">Jenis kendaraan
                <select value={correction.jenis_kendaraan} onChange={(event) => setCorrection({ ...correction, jenis_kendaraan: event.target.value })} className="mt-1 w-full px-3 py-2 border rounded-lg text-sm font-normal">
                  <option value="motor">Motor</option><option value="mobil">Mobil</option><option value="lainnya">Lainnya</option>
                </select>
              </label>
              <label className="text-xs font-semibold text-slate-600">Warna
                <input maxLength="50" value={correction.warna} onChange={(event) => setCorrection({ ...correction, warna: event.target.value })} className="mt-1 w-full px-3 py-2 border rounded-lg text-sm font-normal" />
              </label>
              <label className="text-xs font-semibold text-slate-600">Area parkir
                <select value={correction.id_area} onChange={(event) => setCorrection({ ...correction, id_area: event.target.value })} className="mt-1 w-full px-3 py-2 border rounded-lg text-sm font-normal">
                  {!areas.some((area) => String(area.id_area) === correction.id_area) && <option value={correction.id_area}>{selected?.nama_area} (area saat ini)</option>}
                  {areas.map((area) => <option key={area.id_area} value={area.id_area}>{area.nama_area} ({area.sisa_kapasitas} slot kosong)</option>)}
                </select>
              </label>
            </div>
            <label className="block text-xs font-semibold text-slate-600">Alasan koreksi (wajib)
              <textarea required minLength="5" maxLength="500" value={correction.reason} onChange={(event) => setCorrection({ ...correction, reason: event.target.value })} className="mt-1 w-full px-3 py-2 border rounded-lg text-sm font-normal" rows="3" />
            </label>
            <div className="flex justify-end gap-2">
              <button type="button" onClick={() => setCorrectionOpen(false)} className="px-4 py-2 rounded-lg border border-slate-200 text-sm">Batal</button>
              <button disabled={saving} className="px-4 py-2 rounded-lg bg-amber-600 text-white text-sm font-semibold disabled:opacity-50">{saving ? 'Menyimpan...' : 'Simpan koreksi'}</button>
            </div>
          </form>
        )}
      </Modal>

      <ReceiptModal isOpen={receiptOpen} onClose={() => setReceiptOpen(false)} data={receipt} />
    </div>
  );
}

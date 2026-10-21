import React, { useState, useEffect } from 'react';
import { api } from '../../api/client';
import { LogOut, Search, Clock, Car, AlertCircle, CheckCircle, RefreshCw, Printer } from 'lucide-react';
import ReceiptModal from '../../components/ReceiptModal';
import Modal from '../../components/Modal';

export default function ExitPage() {
  const [activeList, setActiveList] = useState([]);
  const [loading, setLoading] = useState(true);
  const [keyword, setKeyword] = useState('');
  const [message, setMessage] = useState({ text: '', type: '' });

  // Confirmation modal
  const [selectedTrx, setSelectedTrx] = useState(null);
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [processing, setProcessing] = useState(false);

  // Receipt Modal
  const [receiptData, setReceiptData] = useState(null);
  const [receiptOpen, setReceiptOpen] = useState(false);

  const fetchActive = async (kw = keyword) => {
    setLoading(true);
    try {
      const q = kw ? `?q=${encodeURIComponent(kw)}` : '';
      const res = await api.get(`/operator/transactions/active${q}`);
      setActiveList(res.data.transactions || []);
    } catch (err) {
      setMessage({ text: err.message || 'Gagal memuat kendaraan aktif.', type: 'error' });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchActive();
  }, []);

  const handleSearch = (e) => {
    e.preventDefault();
    fetchActive(keyword);
  };

  const handleOpenConfirm = (trx) => {
    setSelectedTrx(trx);
    setConfirmOpen(true);
  };

  const handleProcessExit = async () => {
    if (!selectedTrx) return;

    setProcessing(true);
    try {
      const res = await api.post(`/operator/transactions/${selectedTrx.id_parkir}/exit`);
      setConfirmOpen(false);
      setReceiptData(res.data);
      setReceiptOpen(true);
      setMessage({
        text: `Kendaraan ${selectedTrx.plat_nomor} berhasil keluar. Biaya: Rp${Number(res.data.biaya_total).toLocaleString('id-ID')}`,
        type: 'success',
      });
      fetchActive();
    } catch (err) {
      setConfirmOpen(false);
      setMessage({ text: err.message || 'Gagal memproses transaksi keluar.', type: 'error' });
    } finally {
      setProcessing(false);
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Proses Kendaraan Keluar</h1>
          <p className="text-sm text-slate-500">Cari kendaraan terparkir, hitung durasi & tarif otomatis, dan cetak struk pembayaran</p>
        </div>
        <button
          onClick={() => fetchActive()}
          disabled={loading}
          className="inline-flex items-center gap-2 px-3.5 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors shadow-sm self-start sm:self-auto"
        >
          <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
          Segarkan Data
        </button>
      </div>

      {message.text && (
        <div className={`p-4 rounded-xl text-sm flex items-center justify-between border ${message.type === 'error' ? 'bg-red-50 text-red-700 border-red-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'}`}>
          <div className="flex items-center gap-2">
            {message.type === 'error' ? <AlertCircle className="w-5 h-5 text-red-500 shrink-0" /> : <CheckCircle className="w-5 h-5 text-emerald-500 shrink-0" />}
            <span>{message.text}</span>
          </div>
          <button onClick={() => setMessage({ text: '', type: '' })} className="text-xs font-semibold hover:underline">Tutup</button>
        </div>
      )}

      {/* Search Input Box */}
      <div className="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-3">
        <form onSubmit={handleSearch} className="flex-1 relative">
          <Search className="w-5 h-5 text-slate-400 absolute left-4 top-3.5" />
          <input
            type="text"
            value={keyword}
            onChange={(e) => setKeyword(e.target.value)}
            placeholder="Cari plat nomor kendaraan atau nomor tiket (contoh: B 1234 ABC, #001042)..."
            className="w-full pl-12 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white uppercase transition-all"
          />
        </form>
        <button
          onClick={() => fetchActive(keyword)}
          className="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl shadow-md shadow-blue-500/20 transition-all flex items-center justify-center gap-2"
        >
          <Search className="w-4 h-4" /> Cari Kendaraan
        </button>
      </div>

      {/* Active Vehicles Table */}
      <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div className="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
          <h3 className="font-semibold text-sm text-slate-900 flex items-center gap-2">
            <Car className="w-4 h-4 text-blue-600" />
            Daftar Kendaraan Sedang Parkir ({activeList.length})
          </h3>
        </div>

        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-slate-200 text-left text-sm">
            <thead className="bg-slate-50 text-slate-500 font-semibold uppercase text-xs tracking-wider">
              <tr>
                <th className="px-6 py-4">No. Tiket</th>
                <th className="px-6 py-4">Plat Nomor</th>
                <th className="px-6 py-4">Jenis</th>
                <th className="px-6 py-4">Area Parkir</th>
                <th className="px-6 py-4">Waktu Masuk</th>
                <th className="px-6 py-4">Tarif Terkunci</th>
                <th className="px-6 py-4 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 text-slate-700">
              {loading ? (
                <tr>
                  <td colSpan="7" className="px-6 py-12 text-center text-slate-400">
                    <RefreshCw className="w-6 h-6 animate-spin mx-auto mb-2 text-blue-600" />
                    Mencari transaksi aktif...
                  </td>
                </tr>
              ) : activeList.length === 0 ? (
                <tr>
                  <td colSpan="7" className="px-6 py-12 text-center text-slate-400">
                    Tidak ada kendaraan yang sedang parkir saat ini.
                  </td>
                </tr>
              ) : (
                activeList.map((trx) => (
                  <tr key={trx.id_parkir} className="hover:bg-slate-50/60 transition-colors">
                    <td className="px-6 py-4 font-mono font-bold text-xs text-blue-600">
                      #{String(trx.id_parkir).padStart(6, '0')}
                    </td>
                    <td className="px-6 py-4 font-bold text-sm text-slate-900 tracking-wider">
                      {trx.plat_nomor}
                      {trx.warna && <span className="block text-[11px] font-normal text-slate-400">{trx.warna}</span>}
                    </td>
                    <td className="px-6 py-4 uppercase text-xs font-semibold text-slate-700">
                      {trx.jenis_kendaraan}
                    </td>
                    <td className="px-6 py-4 text-xs font-medium text-slate-800">
                      {trx.nama_area}
                    </td>
                    <td className="px-6 py-4 text-xs font-mono text-slate-600">
                      {trx.waktu_masuk}
                    </td>
                    <td className="px-6 py-4 text-xs font-mono text-slate-600">
                      Rp{Number(trx.tarif_per_jam_snapshot).toLocaleString('id-ID')}/jam
                    </td>
                    <td className="px-6 py-4 text-right">
                      <button
                        onClick={() => handleOpenConfirm(trx)}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm transition-all"
                      >
                        <LogOut className="w-3.5 h-3.5" /> Proses Keluar
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Confirmation Modal */}
      <Modal
        isOpen={confirmOpen}
        onClose={() => setConfirmOpen(false)}
        title="Konfirmasi Kendaraan Keluar"
      >
        {selectedTrx && (
          <div className="space-y-4">
            <div className="p-4 bg-slate-50 rounded-xl border border-slate-200 text-sm space-y-2">
              <div className="flex justify-between">
                <span className="text-slate-500">Nomor Tiket:</span>
                <span className="font-mono font-bold text-slate-900">#{String(selectedTrx.id_parkir).padStart(6, '0')}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Plat Nomor:</span>
                <span className="font-bold text-slate-900 text-base">{selectedTrx.plat_nomor}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Jenis Kendaraan:</span>
                <span className="uppercase font-semibold text-slate-800">{selectedTrx.jenis_kendaraan}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Area Parkir:</span>
                <span className="text-slate-800">{selectedTrx.nama_area}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Waktu Masuk:</span>
                <span className="font-mono text-slate-800">{selectedTrx.waktu_masuk}</span>
              </div>
            </div>

            <p className="text-xs text-slate-500">
              Biaya total dan durasi akhir dihitung secara otomatis oleh sistem server saat transaksi ditutup.
            </p>

            <div className="pt-2 flex justify-end gap-3 border-t border-slate-100">
              <button
                type="button"
                onClick={() => setConfirmOpen(false)}
                className="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-xl"
              >
                Batal
              </button>
              <button
                type="button"
                disabled={processing}
                onClick={handleProcessExit}
                className="px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-md shadow-blue-500/20 disabled:opacity-60 flex items-center gap-2"
              >
                {processing ? (
                  <span className="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                ) : (
                  <>
                    <LogOut className="w-4 h-4" /> Konfirmasi & Selesaikan
                  </>
                )}
              </button>
            </div>
          </div>
        )}
      </Modal>

      {/* Receipt View Modal with Print Button */}
      <ReceiptModal
        isOpen={receiptOpen}
        onClose={() => setReceiptOpen(false)}
        data={receiptData}
        isEntryOnly={false}
      />
    </div>
  );
}


import React, { useState, useEffect } from 'react';
import { api } from '../../api/client';
import { LogIn, Car, AlertCircle, CheckCircle, RefreshCw, Printer } from 'lucide-react';
import ReceiptModal from '../../components/ReceiptModal';

export default function EntryPage() {
  const [areas, setAreas] = useState([]);
  const [tariffs, setTariffs] = useState([]);
  const [loading, setLoading] = useState(true);

  // Form
  const [platNomor, setPlatNomor] = useState('');
  const [jenisKendaraan, setJenisKendaraan] = useState('motor');
  const [warna, setWarna] = useState('');
  const [idArea, setIdArea] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');

  // Receipt Modal
  const [ticketData, setTicketData] = useState(null);
  const [isTicketOpen, setIsTicketOpen] = useState(false);

  const fetchMasterData = async () => {
    setLoading(true);
    try {
      const [areaRes, tariffRes] = await Promise.all([
        api.get('/operator/areas'),
        api.get('/operator/tariffs'),
      ]);
      const activeAreas = areaRes.data.areas || [];
      setAreas(activeAreas);
      setTariffs(tariffRes.data.tariffs || []);

      // Auto select first available area
      const availableArea = activeAreas.find(a => a.sisa_kapasitas > 0);
      if (availableArea) {
        setIdArea(String(availableArea.id_area));
      } else if (activeAreas.length > 0) {
        setIdArea(String(activeAreas[0].id_area));
      }
    } catch (err) {
      setError(err.message || 'Gagal memuat data area dan tarif.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchMasterData();
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!platNomor.trim()) {
      setError('Plat nomor wajib diisi.');
      return;
    }
    if (!idArea) {
      setError('Pilih area parkir.');
      return;
    }

    setError('');
    setSubmitting(true);
    try {
      const res = await api.post('/operator/transactions/entry', {
        plat_nomor: platNomor.trim().toUpperCase(),
        jenis_kendaraan: jenisKendaraan,
        warna: warna.trim(),
        id_area: Number(idArea),
      });

      setTicketData(res.data);
      setIsTicketOpen(true);
      setPlatNomor('');
      setWarna('');
      fetchMasterData(); // Segarkan kuota area
    } catch (err) {
      setError(err.message || 'Gagal mencatat kendaraan masuk.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Pencatatan Kendaraan Masuk</h1>
          <p className="text-sm text-slate-500">Input data kendaraan masuk dan cetak karcis / bukti parkir</p>
        </div>
        <button
          onClick={fetchMasterData}
          disabled={loading}
          className="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 shadow-sm self-start"
        >
          <RefreshCw className={`w-3.5 h-3.5 ${loading ? 'animate-spin' : ''}`} />
          Segarkan Kuota
        </button>
      </div>

      {error && (
        <div className="p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm flex items-center gap-2">
          <AlertCircle className="w-5 h-5 text-red-500 shrink-0" />
          <span>{error}</span>
        </div>
      )}

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        {/* Entry Form */}
        <div className="md:col-span-2 bg-white p-6 sm:p-8 rounded-2xl border border-slate-200 shadow-sm">
          <form onSubmit={handleSubmit} className="space-y-5">
            <div>
              <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                Plat Nomor Kendaraan
              </label>
              <div className="relative">
                <input
                  type="text"
                  value={platNomor}
                  onChange={(e) => setPlatNomor(e.target.value.toUpperCase())}
                  placeholder="B 1234 ABC"
                  required
                  autoFocus
                  className="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-lg font-bold font-mono tracking-widest text-slate-900 focus:outline-none focus:border-blue-600 focus:bg-white uppercase transition-all"
                />
              </div>
              <p className="text-[11px] text-slate-400 mt-1">Gunakan format plat nomor yang jelas (contoh: B 1234 ABC, AB 5678 CD)</p>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                  Jenis Kendaraan
                </label>
                <select
                  value={jenisKendaraan}
                  onChange={(e) => setJenisKendaraan(e.target.value)}
                  className="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white capitalize"
                >
                  {tariffs.map((t) => (
                    <option key={t.id_tarif} value={t.jenis_kendaraan}>
                      {t.jenis_kendaraan} (Rp{Number(t.tarif_per_jam).toLocaleString('id-ID')}/jam)
                    </option>
                  ))}
                  {tariffs.length === 0 && (
                    <>
                      <option value="motor">Motor</option>
                      <option value="mobil">Mobil</option>
                      <option value="lainnya">Lainnya</option>
                    </>
                  )}
                </select>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                  Warna (Opsional)
                </label>
                <input
                  type="text"
                  value={warna}
                  onChange={(e) => setWarna(e.target.value)}
                  placeholder="Hitam, Putih, Merah..."
                  className="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white"
                />
              </div>
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                Pilih Area Parkir
              </label>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {areas.map((area) => {
                  const isFull = area.sisa_kapasitas <= 0;
                  const isSelected = String(area.id_area) === idArea;

                  return (
                    <div
                      key={area.id_area}
                      onClick={() => !isFull && setIdArea(String(area.id_area))}
                      className={`p-3.5 rounded-xl border-2 transition-all cursor-pointer ${
                        isSelected
                          ? 'border-blue-600 bg-blue-50/50 ring-2 ring-blue-500/20'
                          : isFull
                          ? 'border-slate-200 bg-slate-50 opacity-60 cursor-not-allowed'
                          : 'border-slate-200 hover:border-slate-300 bg-white'
                      }`}
                    >
                      <div className="flex justify-between items-center mb-1">
                        <span className="font-semibold text-sm text-slate-900">{area.nama_area}</span>
                        {isFull ? (
                          <span className="text-[10px] font-bold text-red-600 bg-red-100 px-1.5 py-0.5 rounded">
                            PENUH
                          </span>
                        ) : (
                          <span className="text-[10px] font-semibold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded">
                            {area.sisa_kapasitas} Kosong
                          </span>
                        )}
                      </div>
                      <div className="text-xs text-slate-400">
                        Terisi {area.terisi} dari {area.kapasitas} slot
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>

            <div className="pt-4">
              <button
                type="submit"
                disabled={submitting || areas.length === 0}
                className="w-full py-3.5 px-4 bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm rounded-xl shadow-lg shadow-blue-500/25 transition-all flex items-center justify-center gap-2 disabled:opacity-60"
              >
                {submitting ? (
                  <span className="inline-block w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                ) : (
                  <>
                    <LogIn className="w-5 h-5" /> Catat Masuk & Buat Tiket
                  </>
                )}
              </button>
            </div>
          </form>
        </div>

        {/* Live Capacity Info Sidebar */}
        <div className="space-y-4">
          <div className="bg-gradient-to-br from-blue-900 to-indigo-950 p-6 rounded-2xl text-white shadow-md">
            <h3 className="font-bold text-base mb-2 flex items-center gap-2">
              <Car className="w-5 h-5 text-blue-400" />
              Petunjuk Petugas
            </h3>
            <ul className="text-xs text-blue-200 space-y-2 list-disc list-inside">
              <li>Pastikan plat nomor diisi dengan benar sesuai fisik kendaraan.</li>
              <li>Sistem akan secara otomatis menolak kendaraan jika plat nomor masih aktif di dalam area.</li>
              <li>Area parkir yang penuh ditolak secara otomatis untuk mencegah overcapacity.</li>
              <li>Karcis parkir dapat langsung dicetak setelah berhasil disimpan.</li>
            </ul>
          </div>
        </div>
      </div>

      {/* Instant Ticket Print Modal */}
      <ReceiptModal
        isOpen={isTicketOpen}
        onClose={() => setIsTicketOpen(false)}
        data={ticketData}
        isEntryOnly={true}
      />
    </div>
  );
}


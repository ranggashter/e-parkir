import React, { useState, useEffect } from 'react';
import { api } from '../../api/client';
import { DollarSign, Plus, Edit2, AlertCircle, CheckCircle, RefreshCw, Car } from 'lucide-react';
import Modal from '../../components/Modal';

export default function TariffsPage() {
  const [tariffs, setTariffs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [message, setMessage] = useState({ text: '', type: '' });

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingTariff, setEditingTariff] = useState(null);
  const [formData, setFormData] = useState({ jenis_kendaraan: '', tarif_per_jam: '' });
  const [formLoading, setFormLoading] = useState(false);
  const [formError, setFormError] = useState('');

  const fetchTariffs = async () => {
    setLoading(true);
    try {
      const res = await api.get('/admin/tariffs');
      setTariffs(res.data.tariffs || []);
    } catch (err) {
      setMessage({ text: err.message || 'Gagal memuat tarif.', type: 'error' });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchTariffs();
  }, []);

  const openCreateModal = () => {
    setEditingTariff(null);
    setFormData({ jenis_kendaraan: '', tarif_per_jam: '' });
    setFormError('');
    setIsModalOpen(true);
  };

  const openEditModal = (t) => {
    setEditingTariff(t);
    setFormData({
      jenis_kendaraan: t.jenis_kendaraan,
      tarif_per_jam: t.tarif_per_jam,
    });
    setFormError('');
    setIsModalOpen(true);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setFormLoading(true);
    setFormError('');

    try {
      if (editingTariff) {
        await api.patch(`/admin/tariffs/${editingTariff.id_tarif}`, {
          tarif_per_jam: formData.tarif_per_jam,
        });
        setMessage({ text: `Tarif ${editingTariff.jenis_kendaraan} berhasil diperbarui.`, type: 'success' });
      } else {
        await api.post('/admin/tariffs', formData);
        setMessage({ text: 'Tarif baru berhasil ditambahkan.', type: 'success' });
      }
      setIsModalOpen(false);
      fetchTariffs();
    } catch (err) {
      setFormError(err.message || 'Gagal menyimpan tarif.');
    } finally {
      setFormLoading(false);
    }
  };

  const formatRupiah = (val) => {
    const num = Number(val) || 0;
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num);
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Pengaturan Tarif Parkir</h1>
          <p className="text-sm text-slate-500">Kelola besaran tarif per jam berdasarkan jenis kendaraan</p>
        </div>
        <button
          onClick={openCreateModal}
          className="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-md shadow-blue-500/20 transition-all self-start sm:self-auto"
        >
          <Plus className="w-4 h-4" /> Tambah Tarif
        </button>
      </div>

      {message.text && (
        <div className={`p-4 rounded-xl text-sm flex items-center justify-between border ${message.type === 'error' ? 'bg-red-50 text-red-700 border-red-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'}`}>
          <div className="flex items-center gap-2">
            {message.type === 'error' ? <AlertCircle className="w-5 h-5 text-red-500" /> : <CheckCircle className="w-5 h-5 text-emerald-500" />}
            <span>{message.text}</span>
          </div>
          <button onClick={() => setMessage({ text: '', type: '' })} className="text-xs font-semibold hover:underline">Tutup</button>
        </div>
      )}

      {/* Tariffs Cards Grid */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        {loading ? (
          <div className="col-span-3 text-center py-12 text-slate-400">
            <RefreshCw className="w-6 h-6 animate-spin mx-auto mb-2 text-blue-600" />
            Memuat daftar tarif...
          </div>
        ) : tariffs.map((t) => (
          <div key={t.id_tarif} className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm relative overflow-hidden flex flex-col justify-between">
            <div>
              <div className="flex items-center justify-between mb-4">
                <span className="text-xs font-bold uppercase tracking-wider px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 border border-blue-200">
                  {t.jenis_kendaraan}
                </span>
                <span className={`text-xs px-2 py-0.5 rounded-full ${t.status_aktif ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'}`}>
                  {t.status_aktif ? 'Aktif' : 'Nonaktif'}
                </span>
              </div>

              <div className="text-2xl font-extrabold text-slate-900 mt-2">
                {formatRupiah(t.tarif_per_jam)}
                <span className="text-xs font-normal text-slate-400 block mt-1">per jam pertama & berikutnya</span>
              </div>
            </div>

            <div className="mt-6 pt-4 border-t border-slate-100 flex justify-end">
              <button
                onClick={() => openEditModal(t)}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors"
              >
                <Edit2 className="w-3.5 h-3.5" /> Ubah Tarif
              </button>
            </div>
          </div>
        ))}
      </div>

      {/* Modal Form */}
      <Modal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        title={editingTariff ? `Ubah Tarif: ${editingTariff.jenis_kendaraan}` : 'Tambah Jenis Tarif Baru'}
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          {formError && (
            <div className="p-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl flex items-center gap-2">
              <AlertCircle className="w-4 h-4 text-red-500 shrink-0" />
              <span>{formError}</span>
            </div>
          )}

          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">Jenis Kendaraan</label>
            <input
              type="text"
              value={formData.jenis_kendaraan}
              disabled={!!editingTariff}
              onChange={(e) => setFormData({ ...formData, jenis_kendaraan: e.target.value })}
              placeholder="Contoh: motor, mobil, bus, truk"
              required
              className="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white disabled:opacity-60"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">Tarif per Jam (Rp)</label>
            <input
              type="number"
              step="500"
              min="0"
              value={formData.tarif_per_jam}
              onChange={(e) => setFormData({ ...formData, tarif_per_jam: e.target.value })}
              placeholder="Contoh: 3000"
              required
              className="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white"
            />
          </div>

          <div className="pt-4 flex justify-end gap-3 border-t border-slate-100">
            <button
              type="button"
              onClick={() => setIsModalOpen(false)}
              className="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-xl"
            >
              Batal
            </button>
            <button
              type="submit"
              disabled={formLoading}
              className="px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-xl disabled:opacity-60"
            >
              {formLoading ? 'Menyimpan...' : 'Simpan Tarif'}
            </button>
          </div>
        </form>
      </Modal>
    </div>
  );
}


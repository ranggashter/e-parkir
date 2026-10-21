import React, { useState, useEffect } from 'react';
import { api } from '../../api/client';
import { MapPin, Plus, Edit2, AlertCircle, CheckCircle, RefreshCw } from 'lucide-react';
import Modal from '../../components/Modal';

export default function AreasPage() {
  const [areas, setAreas] = useState([]);
  const [loading, setLoading] = useState(true);
  const [message, setMessage] = useState({ text: '', type: '' });

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingArea, setEditingArea] = useState(null);
  const [formData, setFormData] = useState({ nama_area: '', kapasitas: '' });
  const [formLoading, setFormLoading] = useState(false);
  const [formError, setFormError] = useState('');

  const fetchAreas = async () => {
    setLoading(true);
    try {
      const res = await api.get('/admin/areas');
      setAreas(res.data.areas || []);
    } catch (err) {
      setMessage({ text: err.message || 'Gagal memuat area parkir.', type: 'error' });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchAreas();
  }, []);

  const openCreateModal = () => {
    setEditingArea(null);
    setFormData({ nama_area: '', kapasitas: '' });
    setFormError('');
    setIsModalOpen(true);
  };

  const openEditModal = (a) => {
    setEditingArea(a);
    setFormData({
      nama_area: a.nama_area,
      kapasitas: a.kapasitas,
    });
    setFormError('');
    setIsModalOpen(true);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setFormLoading(true);
    setFormError('');

    try {
      if (editingArea) {
        if (Number(formData.kapasitas) < Number(editingArea.terisi)) {
          throw new Error(`Kapasitas tidak boleh lebih kecil dari kendaraan yang sedang parkir (${editingArea.terisi}).`);
        }
        await api.patch(`/admin/areas/${editingArea.id_area}`, formData);
        setMessage({ text: `Area ${editingArea.nama_area} berhasil diperbarui.`, type: 'success' });
      } else {
        await api.post('/admin/areas', formData);
        setMessage({ text: 'Area parkir baru berhasil dibuat.', type: 'success' });
      }
      setIsModalOpen(false);
      fetchAreas();
    } catch (err) {
      setFormError(err.message || 'Gagal menyimpan area parkir.');
    } finally {
      setFormLoading(false);
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Manajemen Area & Kapasitas Parkir</h1>
          <p className="text-sm text-slate-500">Pantau dan kelola batas daya tampung kendaraan pada tiap zona</p>
        </div>
        <button
          onClick={openCreateModal}
          className="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-md shadow-blue-500/20 transition-all self-start sm:self-auto"
        >
          <Plus className="w-4 h-4" /> Tambah Area
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

      {/* Grid of Areas */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        {loading ? (
          <div className="col-span-3 text-center py-12 text-slate-400">
            <RefreshCw className="w-6 h-6 animate-spin mx-auto mb-2 text-blue-600" />
            Memuat data area...
          </div>
        ) : areas.map((a) => {
          const pct = a.kapasitas > 0 ? Math.round((a.terisi / a.kapasitas) * 100) : 0;
          const isFull = a.terisi >= a.kapasitas;
          const barColor = isFull ? 'bg-red-500' : pct >= 75 ? 'bg-amber-500' : 'bg-blue-600';

          return (
            <div key={a.id_area} className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
              <div>
                <div className="flex justify-between items-start mb-3">
                  <div>
                    <h3 className="font-bold text-lg text-slate-900">{a.nama_area}</h3>
                    <span className={`inline-block text-xs px-2 py-0.5 rounded-full mt-1 ${a.status_aktif ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'}`}>
                      {a.status_aktif ? 'Aktif Beroperasi' : 'Ditutup / Nonaktif'}
                    </span>
                  </div>
                  {isFull && (
                    <span className="text-xs font-bold text-red-600 bg-red-50 px-2 py-1 rounded-md border border-red-200">
                      PENUH
                    </span>
                  )}
                </div>

                <div className="my-5">
                  <div className="flex justify-between text-sm mb-1.5 font-semibold text-slate-700">
                    <span>Terisi: {a.terisi}</span>
                    <span>Kapasitas: {a.kapasitas}</span>
                  </div>
                  <div className="w-full bg-slate-100 rounded-full h-3 overflow-hidden">
                    <div
                      className={`h-3 rounded-full transition-all duration-500 ${barColor}`}
                      style={{ width: `${Math.min(100, pct)}%` }}
                    />
                  </div>
                  <div className="flex justify-between text-xs text-slate-500 mt-2">
                    <span>Tersisa: {a.sisa_kapasitas} Slot</span>
                    <span>{pct}% Terpakai</span>
                  </div>
                </div>
              </div>

              <div className="pt-4 border-t border-slate-100 flex justify-end">
                <button
                  onClick={() => openEditModal(a)}
                  className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors"
                >
                  <Edit2 className="w-3.5 h-3.5" /> Atur Kapasitas
                </button>
              </div>
            </div>
          );
        })}
      </div>

      {/* Modal Form */}
      <Modal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        title={editingArea ? `Edit Area: ${editingArea.nama_area}` : 'Tambah Area Parkir Baru'}
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          {formError && (
            <div className="p-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl flex items-center gap-2">
              <AlertCircle className="w-4 h-4 text-red-500 shrink-0" />
              <span>{formError}</span>
            </div>
          )}

          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">Nama Area Parkir</label>
            <input
              type="text"
              value={formData.nama_area}
              onChange={(e) => setFormData({ ...formData, nama_area: e.target.value })}
              placeholder="Contoh: Gedung Timur Lt. 1"
              required
              className="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">
              Kapasitas Maksimal (Slot)
              {editingArea && (
                <span className="block text-[10px] text-slate-400 font-normal">
                  Minimal {editingArea.terisi} slot (jumlah kendaraan yang saat ini sedang parkir)
                </span>
              )}
            </label>
            <input
              type="number"
              min={editingArea ? Math.max(1, editingArea.terisi) : 1}
              value={formData.kapasitas}
              onChange={(e) => setFormData({ ...formData, kapasitas: e.target.value })}
              placeholder="Contoh: 50"
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
              {formLoading ? 'Menyimpan...' : 'Simpan Area'}
            </button>
          </div>
        </form>
      </Modal>
    </div>
  );
}


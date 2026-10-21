import React, { useState, useEffect } from 'react';
import { api } from '../../api/client';
import { Users, UserPlus, Search, Edit2, Power, AlertCircle, CheckCircle, RefreshCw } from 'lucide-react';
import Modal from '../../components/Modal';

export default function UsersPage() {
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [message, setMessage] = useState({ text: '', type: '' });

  // Modal states
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingUser, setEditingUser] = useState(null);
  const [formData, setFormData] = useState({
    nama_lengkap: '',
    username: '',
    password: '',
    role: 'petugas',
  });
  const [formLoading, setFormLoading] = useState(false);
  const [formError, setFormError] = useState('');

  const fetchUsers = async () => {
    setLoading(true);
    try {
      const q = search ? `&search=${encodeURIComponent(search)}` : '';
      const res = await api.get(`/admin/users?page=${page}&per_page=10${q}`);
      setUsers(res.data.items || []);
      setTotalPages(res.data.total_pages || 1);
    } catch (err) {
      setMessage({ text: err.message || 'Gagal memuat daftar user.', type: 'error' });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchUsers();
  }, [page]);

  const handleSearch = (e) => {
    e.preventDefault();
    setPage(1);
    fetchUsers();
  };

  const openCreateModal = () => {
    setEditingUser(null);
    setFormData({ nama_lengkap: '', username: '', password: '', role: 'petugas' });
    setFormError('');
    setIsModalOpen(true);
  };

  const openEditModal = (u) => {
    setEditingUser(u);
    setFormData({
      nama_lengkap: u.nama_lengkap,
      username: u.username,
      password: '',
      role: u.role,
    });
    setFormError('');
    setIsModalOpen(true);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setFormLoading(true);
    setFormError('');

    try {
      if (editingUser) {
        await api.patch(`/admin/users/${editingUser.id_user}`, formData);
        setMessage({ text: `User ${editingUser.username} berhasil diperbarui.`, type: 'success' });
      } else {
        await api.post('/admin/users', formData);
        setMessage({ text: 'User baru berhasil dibuat.', type: 'success' });
      }
      setIsModalOpen(false);
      fetchUsers();
    } catch (err) {
      setFormError(err.message || 'Gagal menyimpan data user.');
    } finally {
      setFormLoading(false);
    }
  };

  const handleToggleStatus = async (u) => {
    try {
      await api.post(`/admin/users/${u.id_user}/deactivate`);
      setMessage({
        text: `Status user ${u.username} berhasil diubah.`,
        type: 'success',
      });
      fetchUsers();
    } catch (err) {
      setMessage({ text: err.message || 'Gagal mengubah status user.', type: 'error' });
    }
  };

  const roleColors = {
    admin: 'bg-purple-100 text-purple-700 border-purple-200',
    petugas: 'bg-blue-100 text-blue-700 border-blue-200',
    owner: 'bg-emerald-100 text-emerald-700 border-emerald-200',
  };

  return (
    <div className="space-y-6">
      {/* Header & Action */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Manajemen Pengguna (User)</h1>
          <p className="text-sm text-slate-500">Kelola akun administrator, petugas loket, dan owner sistem</p>
        </div>
        <button
          onClick={openCreateModal}
          className="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-md shadow-blue-500/20 transition-all self-start sm:self-auto"
        >
          <UserPlus className="w-4 h-4" /> Tambah User
        </button>
      </div>

      {/* Alert banner */}
      {message.text && (
        <div
          className={`p-4 rounded-xl text-sm flex items-center justify-between border ${
            message.type === 'error'
              ? 'bg-red-50 text-red-700 border-red-200'
              : 'bg-emerald-50 text-emerald-700 border-emerald-200'
          }`}
        >
          <div className="flex items-center gap-2">
            {message.type === 'error' ? (
              <AlertCircle className="w-5 h-5 text-red-500 shrink-0" />
            ) : (
              <CheckCircle className="w-5 h-5 text-emerald-500 shrink-0" />
            )}
            <span>{message.text}</span>
          </div>
          <button
            onClick={() => setMessage({ text: '', type: '' })}
            className="text-xs font-semibold hover:underline"
          >
            Tutup
          </button>
        </div>
      )}

      {/* Search Bar */}
      <div className="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-3">
        <form onSubmit={handleSearch} className="flex-1 relative">
          <Search className="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5" />
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Cari berdasarkan nama atau username..."
            className="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all"
          />
        </form>
        <button
          onClick={fetchUsers}
          className="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-xl flex items-center gap-2 transition-colors justify-center"
        >
          <RefreshCw className="w-4 h-4" /> Segarkan
        </button>
      </div>

      {/* Table */}
      <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-slate-200 text-left text-sm">
            <thead className="bg-slate-50 text-slate-500 font-semibold uppercase text-xs tracking-wider">
              <tr>
                <th className="px-6 py-4">Nama Lengkap</th>
                <th className="px-6 py-4">Username</th>
                <th className="px-6 py-4">Role</th>
                <th className="px-6 py-4">Status</th>
                <th className="px-6 py-4 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 text-slate-700">
              {loading ? (
                <tr>
                  <td colSpan="5" className="px-6 py-12 text-center text-slate-400">
                    <RefreshCw className="w-6 h-6 animate-spin mx-auto mb-2 text-blue-600" />
                    Memuat data user...
                  </td>
                </tr>
              ) : users.length === 0 ? (
                <tr>
                  <td colSpan="5" className="px-6 py-12 text-center text-slate-400">
                    Tidak ada data pengguna yang cocok.
                  </td>
                </tr>
              ) : (
                users.map((u) => (
                  <tr key={u.id_user} className="hover:bg-slate-50/60 transition-colors">
                    <td className="px-6 py-4 font-semibold text-slate-900">{u.nama_lengkap}</td>
                    <td className="px-6 py-4 font-mono text-xs text-slate-600">@{u.username}</td>
                    <td className="px-6 py-4">
                      <span className={`text-xs px-2.5 py-1 rounded-full font-medium border uppercase tracking-wider ${roleColors[u.role]}`}>
                        {u.role}
                      </span>
                    </td>
                    <td className="px-6 py-4">
                      <span className={`inline-flex items-center gap-1.5 text-xs font-semibold px-2 py-0.5 rounded-full ${u.status_aktif ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'}`}>
                        <span className={`w-1.5 h-1.5 rounded-full ${u.status_aktif ? 'bg-emerald-500' : 'bg-red-500'}`}></span>
                        {u.status_aktif ? 'Aktif' : 'Nonaktif'}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-right space-x-2">
                      <button
                        onClick={() => openEditModal(u)}
                        className="p-1.5 text-slate-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                        title="Edit Data User"
                      >
                        <Edit2 className="w-4 h-4" />
                      </button>
                      <button
                        onClick={() => handleToggleStatus(u)}
                        className={`p-1.5 rounded-lg transition-colors ${
                          u.status_aktif
                            ? 'text-slate-500 hover:text-red-600 hover:bg-red-50'
                            : 'text-slate-500 hover:text-emerald-600 hover:bg-emerald-50'
                        }`}
                        title={u.status_aktif ? 'Nonaktifkan User' : 'Aktifkan User'}
                      >
                        <Power className="w-4 h-4" />
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {totalPages > 1 && (
          <div className="px-6 py-4 border-t border-slate-100 flex items-center justify-between">
            <span className="text-xs text-slate-500">Halaman {page} dari {totalPages}</span>
            <div className="flex gap-2">
              <button
                disabled={page <= 1}
                onClick={() => setPage(p => p - 1)}
                className="px-3 py-1 bg-slate-100 text-slate-700 text-xs rounded-lg disabled:opacity-50"
              >
                Sebelumnya
              </button>
              <button
                disabled={page >= totalPages}
                onClick={() => setPage(p => p + 1)}
                className="px-3 py-1 bg-slate-100 text-slate-700 text-xs rounded-lg disabled:opacity-50"
              >
                Selanjutnya
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Modal Form Tambah / Edit */}
      <Modal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        title={editingUser ? `Edit User: @${editingUser.username}` : 'Tambah User Baru'}
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          {formError && (
            <div className="p-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl flex items-center gap-2">
              <AlertCircle className="w-4 h-4 text-red-500 shrink-0" />
              <span>{formError}</span>
            </div>
          )}

          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">Nama Lengkap</label>
            <input
              type="text"
              value={formData.nama_lengkap}
              onChange={(e) => setFormData({ ...formData, nama_lengkap: e.target.value })}
              required
              className="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">Username</label>
            <input
              type="text"
              value={formData.username}
              disabled={!!editingUser}
              onChange={(e) => setFormData({ ...formData, username: e.target.value })}
              required
              className="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white disabled:opacity-60"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">
              Password {editingUser && <span className="font-normal text-slate-400">(Kosongkan bila tidak diubah)</span>}
            </label>
            <input
              type="password"
              value={formData.password}
              onChange={(e) => setFormData({ ...formData, password: e.target.value })}
              required={!editingUser}
              placeholder="••••••••"
              className="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">Peran / Role</label>
            <select
              value={formData.role}
              onChange={(e) => setFormData({ ...formData, role: e.target.value })}
              className="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white"
            >
              <option value="petugas">Petugas (Operasional Parkir)</option>
              <option value="admin">Admin (Manajemen Sistem)</option>
              <option value="owner">Owner (Laporan Rekap)</option>
            </select>
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
              {formLoading ? 'Menyimpan...' : 'Simpan User'}
            </button>
          </div>
        </form>
      </Modal>
    </div>
  );
}


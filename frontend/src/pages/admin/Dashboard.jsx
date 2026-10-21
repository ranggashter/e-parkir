import React, { useState, useEffect } from 'react';
import { api } from '../../api/client';
import { 
  TrendingUp, 
  MapPin, 
  Clock, 
  ArrowDownLeft, 
  ArrowUpRight, 
  DollarSign, 
  ShieldAlert,
  Activity,
  RefreshCw
} from 'lucide-react';

export default function Dashboard() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const fetchDashboard = async () => {
    setLoading(true);
    setError('');
    try {
      const res = await api.get('/admin/dashboard');
      setData(res.data);
    } catch (err) {
      setError(err.message || 'Gagal memuat data dashboard.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchDashboard();
  }, []);

  const formatRupiah = (val) => {
    const num = Number(val) || 0;
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num);
  };

  if (loading && !data) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <div className="flex flex-col items-center gap-3 text-slate-500">
          <RefreshCw className="w-8 h-8 animate-spin text-blue-600" />
          <p className="text-sm">Memuat ringkasan dashboard...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="p-6 bg-red-50 border border-red-200 rounded-2xl text-red-700 flex items-center justify-between">
        <div>{error}</div>
        <button onClick={fetchDashboard} className="px-3 py-1.5 bg-red-100 hover:bg-red-200 rounded-lg text-xs font-semibold">
          Coba Lagi
        </button>
      </div>
    );
  }

  const { stats, areas, recent_logs } = data || {};
  const occupancyPercent = stats?.total_kapasitas > 0 
    ? Math.round((stats.total_terisi / stats.total_kapasitas) * 100) 
    : 0;

  return (
    <div className="space-y-6">
      {/* Top Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Dashboard Administrator</h1>
          <p className="text-sm text-slate-500">Ringkasan kapasitas area, operasional, dan aktivitas sistem</p>
        </div>
        <button
          onClick={fetchDashboard}
          disabled={loading}
          className="inline-flex items-center gap-2 px-3.5 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors shadow-sm self-start sm:self-auto"
        >
          <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
          Segarkan Data
        </button>
      </div>

      {/* KPI Stats Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Total Terisi vs Kapasitas */}
        <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
          <div>
            <div className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Kendaraan Terparkir</div>
            <div className="mt-1 text-2xl font-extrabold text-slate-900">
              {stats?.total_terisi} <span className="text-sm font-normal text-slate-400">/ {stats?.total_kapasitas} Slot</span>
            </div>
            <div className="mt-1 text-xs text-blue-600 font-semibold">{occupancyPercent}% Terisi</div>
          </div>
          <div className="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
            <MapPin className="w-6 h-6" />
          </div>
        </div>

        {/* Masuk Hari Ini */}
        <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
          <div>
            <div className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Masuk Hari Ini</div>
            <div className="mt-1 text-2xl font-extrabold text-slate-900">{stats?.total_masuk_hari_ini}</div>
            <div className="mt-1 text-xs text-emerald-600 font-semibold">Transaksi Aktif & Selesai</div>
          </div>
          <div className="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
            <ArrowDownLeft className="w-6 h-6" />
          </div>
        </div>

        {/* Keluar Hari Ini */}
        <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
          <div>
            <div className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Keluar Hari Ini</div>
            <div className="mt-1 text-2xl font-extrabold text-slate-900">{stats?.total_keluar_hari_ini}</div>
            <div className="mt-1 text-xs text-purple-600 font-semibold">Transaksi Sukses</div>
          </div>
          <div className="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
            <ArrowUpRight className="w-6 h-6" />
          </div>
        </div>

        {/* Pendapatan Hari Ini */}
        <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
          <div>
            <div className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Pendapatan Hari Ini</div>
            <div className="mt-1 text-2xl font-extrabold text-slate-900">{formatRupiah(stats?.pendapatan_hari_ini)}</div>
            <div className="mt-1 text-xs text-slate-400">Pembayaran lunas hari ini</div>
          </div>
          <div className="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
            <DollarSign className="w-6 h-6" />
          </div>
        </div>
      </div>

      {/* Area Status & Progress Section */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div className="lg:col-span-2 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
          <h2 className="text-base font-semibold text-slate-900 mb-4 flex items-center gap-2">
            <MapPin className="w-5 h-5 text-blue-600" />
            Status Kapasitas Area Parkir
          </h2>

          <div className="space-y-4">
            {areas?.map((area) => {
              const pct = area.kapasitas > 0 ? Math.round((area.terisi / area.kapasitas) * 100) : 0;
              const barColor = pct >= 90 ? 'bg-red-500' : pct >= 70 ? 'bg-amber-500' : 'bg-blue-600';

              return (
                <div key={area.id_area} className="p-4 rounded-xl border border-slate-100 bg-slate-50/50">
                  <div className="flex justify-between items-center mb-2">
                    <div>
                      <span className="font-semibold text-slate-900">{area.nama_area}</span>
                      <span className={`ml-2 text-xs px-2 py-0.5 rounded-full ${area.status_aktif ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'}`}>
                        {area.status_aktif ? 'Aktif' : 'Nonaktif'}
                      </span>
                    </div>
                    <div className="text-sm font-bold text-slate-700">
                      {area.terisi} <span className="font-normal text-slate-400">/ {area.kapasitas} Slot</span>
                    </div>
                  </div>

                  <div className="w-full bg-slate-200 rounded-full h-2.5 overflow-hidden">
                    <div
                      className={`h-2.5 rounded-full transition-all duration-500 ${barColor}`}
                      style={{ width: `${Math.min(100, pct)}%` }}
                    />
                  </div>

                  <div className="flex justify-between text-xs text-slate-500 mt-2">
                    <span>Sisa: {area.sisa_kapasitas} Slot Kosong</span>
                    <span className="font-medium">{pct}% Penuh</span>
                  </div>
                </div>
              );
            })}
          </div>
        </div>

        {/* Recent Audit Logs */}
        <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
          <h2 className="text-base font-semibold text-slate-900 mb-4 flex items-center gap-2">
            <Activity className="w-5 h-5 text-purple-600" />
            Log Aktivitas Terbaru
          </h2>

          <div className="space-y-3">
            {recent_logs && recent_logs.length > 0 ? (
              recent_logs.slice(0, 6).map((log) => (
                <div key={log.id_log} className="text-xs p-3 rounded-xl bg-slate-50 border border-slate-100">
                  <div className="font-medium text-slate-800 line-clamp-2">{log.aktivitas}</div>
                  <div className="flex justify-between items-center text-slate-400 mt-2">
                    <span>{log.nama_lengkap || log.username || 'Sistem'}</span>
                    <span>{log.waktu_aktivitas?.split(' ')[1] || log.waktu_aktivitas}</span>
                  </div>
                </div>
              ))
            ) : (
              <div className="text-center py-8 text-slate-400 text-xs">Belum ada log aktivitas.</div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

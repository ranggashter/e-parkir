import React, { useState, useEffect } from 'react';
import { api, downloadReport } from '../../api/client';
import { 
  TrendingUp, 
  Calendar, 
  Printer, 
  DollarSign, 
  Car, 
  Clock, 
  AlertCircle, 
  RefreshCw,
  FileText
} from 'lucide-react';
import { Download } from 'lucide-react';

const localDate = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

export default function ReportsPage() {
  const today = localDate(new Date());
  const firstDay = localDate(new Date(new Date().getFullYear(), new Date().getMonth(), 1));

  const [startDate, setStartDate] = useState(firstDay);
  const [endDate, setEndDate] = useState(today);
  const [report, setReport] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const fetchReport = async () => {
    setLoading(true);
    setError('');
    try {
      const res = await api.get(`/owner/reports/revenue?start_date=${startDate}&end_date=${endDate}`);
      setReport(res.data);
    } catch (err) {
      setError(err.message || 'Gagal memuat rekap laporan.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchReport();
  }, []);

  const handleApplyFilter = (e) => {
    e.preventDefault();
    fetchReport();
  };

  const setPreset = (type) => {
    const now = new Date();
    const todayStr = localDate(now);

    if (type === 'today') {
      setStartDate(todayStr);
      setEndDate(todayStr);
    } else if (type === 'week') {
      const lastWeek = new Date();
      lastWeek.setDate(lastWeek.getDate() - 7);
      setStartDate(localDate(lastWeek));
      setEndDate(todayStr);
    } else if (type === 'month') {
      const startMonth = new Date(now.getFullYear(), now.getMonth(), 1);
      setStartDate(localDate(startMonth));
      setEndDate(todayStr);
    }
  };

  const handlePrint = () => {
    window.print();
  };

  const handleExportCsv = async () => {
    setError('');
    try {
      await downloadReport(
        `/owner/reports/revenue.csv?start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}`,
        `rekap-parkir-${startDate}-${endDate}.csv`
      );
    } catch (err) {
      setError(err.message || 'Gagal mengunduh CSV.');
    }
  };

  const formatRupiah = (val) => {
    const num = Number(val) || 0;
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num);
  };

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 no-print">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Rekap Pendapatan & Eksekutif</h1>
          <p className="text-sm text-slate-500">Laporan agregat pendapatan dan statistik transaksi parkir terverifikasi</p>
        </div>
        <div className="flex gap-2">
          <button onClick={handleExportCsv} className="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-xl self-start sm:self-auto">
            <Download className="w-4 h-4" /> Ekspor CSV
          </button>
          <button
            onClick={handlePrint}
            className="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-md shadow-emerald-600/20 transition-all self-start sm:self-auto"
          >
            <Printer className="w-4 h-4" /> Cetak Laporan
          </button>
        </div>
      </div>

      {/* Date Filter Box */}
      <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm no-print">
        <form onSubmit={handleApplyFilter} className="flex flex-col md:flex-row md:items-end justify-between gap-4">
          <div className="flex flex-col sm:flex-row gap-4 flex-1">
            <div>
              <label className="block text-xs font-semibold text-slate-600 mb-1">Tanggal Mulai</label>
              <input
                type="date"
                value={startDate}
                onChange={(e) => setStartDate(e.target.value)}
                required
                className="px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-slate-600 mb-1">Tanggal Selesai</label>
              <input
                type="date"
                value={endDate}
                onChange={(e) => setEndDate(e.target.value)}
                required
                className="px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
              />
            </div>
            <div className="flex items-end">
              <button
                type="submit"
                disabled={loading}
                className="w-full sm:w-auto px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm rounded-xl shadow-md shadow-emerald-500/20 transition-all flex items-center justify-center gap-2"
              >
                <Calendar className="w-4 h-4" /> Terapkan Filter
              </button>
            </div>
          </div>

          {/* Quick Presets */}
          <div className="flex items-center gap-2 pt-2 md:pt-0 border-t md:border-t-0 border-slate-100">
            <span className="text-xs text-slate-400 font-medium">Preset:</span>
            <button
              type="button"
              onClick={() => { setPreset('today'); }}
              className="px-2.5 py-1 text-xs font-medium rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200"
            >
              Hari Ini
            </button>
            <button
              type="button"
              onClick={() => { setPreset('week'); }}
              className="px-2.5 py-1 text-xs font-medium rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200"
            >
              7 Hari
            </button>
            <button
              type="button"
              onClick={() => { setPreset('month'); }}
              className="px-2.5 py-1 text-xs font-medium rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200"
            >
              Bulan Ini
            </button>
          </div>
        </form>
      </div>

      {error && (
        <div className="p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm flex items-center gap-2 no-print">
          <AlertCircle className="w-5 h-5 text-red-500 shrink-0" />
          <span>{error}</span>
        </div>
      )}

      {/* Printable Report Document Wrapper */}
      <div className="printable-area space-y-6">
        {/* Printable Header (Visible only on print) */}
        <div className="hidden print:block text-center pb-4 border-b-2 border-slate-800 mb-6">
          <h1 className="text-xl font-bold text-slate-900 uppercase tracking-wider">Laporan Rekapitulasi Pendapatan E-Parking</h1>
          <p className="text-xs text-slate-600">SMKN Penilaian UKK Rekayasa Perangkat Lunak 2026</p>
          <p className="text-xs text-slate-500 mt-1 font-mono">
            Periode: {report?.start_date} s/d {report?.end_date}
          </p>
        </div>

        {/* Executive Summary Cards */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
              <div className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Pendapatan</div>
              <div className="mt-1 text-3xl font-extrabold text-emerald-600">
                {formatRupiah(report?.total_revenue)}
              </div>
              <div className="mt-1 text-xs text-slate-400">Pembayaran lunas pada periode terpilih</div>
            </div>
            <div className="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
              <DollarSign className="w-6 h-6" />
            </div>
          </div>

          <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
              <div className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Kendaraan Selesai</div>
              <div className="mt-1 text-3xl font-extrabold text-slate-900">
                {report?.completed_transactions || 0}
              </div>
              <div className="mt-1 text-xs text-slate-400">Total transaksi tertutup</div>
            </div>
            <div className="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
              <Car className="w-6 h-6" />
            </div>
          </div>

          <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
              <div className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Rata-rata Durasi</div>
              <div className="mt-1 text-3xl font-extrabold text-slate-900">
                {report?.avg_duration_hours || 0} <span className="text-base font-normal text-slate-400">Jam</span>
              </div>
              <div className="mt-1 text-xs text-slate-400">Per kendaraan</div>
            </div>
            <div className="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
              <Clock className="w-6 h-6" />
            </div>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div className="bg-white p-5 rounded-2xl border border-slate-200">
            <div className="text-xs font-semibold uppercase text-slate-500">Total tagihan</div>
            <div className="mt-1 text-xl font-bold text-slate-900">{formatRupiah(report?.total_charged)}</div>
          </div>
          <div className="bg-white p-5 rounded-2xl border border-amber-200">
            <div className="text-xs font-semibold uppercase text-amber-700">Belum lunas</div>
            <div className="mt-1 text-xl font-bold text-amber-800">{formatRupiah(report?.outstanding_balance)}</div>
            <div className="text-xs text-amber-700">{report?.unpaid_transactions || 0} transaksi</div>
          </div>
          <div className="bg-white p-5 rounded-2xl border border-slate-200">
            <div className="text-xs font-semibold uppercase text-slate-500">Lunas</div>
            <div className="mt-1 text-xl font-bold text-emerald-700">{report?.paid_transactions || 0} transaksi</div>
            <div className="text-xs text-slate-500">Status lama belum diketahui: {report?.legacy_unknown_transactions || 0} transaksi ({formatRupiah(report?.unknown_payment_amount)})</div>
          </div>
        </div>

        {/* Breakdown by Vehicle Type */}
        <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
          <h2 className="text-base font-semibold text-slate-900 mb-4 flex items-center gap-2">
            <Car className="w-5 h-5 text-emerald-600" />
            Rincian Pendapatan Berdasarkan Jenis Kendaraan
          </h2>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            {report?.by_vehicle_type?.map((vt) => {
              const totalRev = Number(report.total_revenue) || 1;
              const vtRev = Number(vt.total_revenue) || 0;
              const pct = Math.round((vtRev / totalRev) * 100);

              return (
                <div key={vt.jenis_kendaraan} className="p-4 rounded-xl bg-slate-50 border border-slate-100 flex flex-col justify-between">
                  <div>
                    <div className="flex justify-between items-center mb-1">
                      <span className="font-bold text-sm uppercase text-slate-800">{vt.jenis_kendaraan}</span>
                      <span className="text-xs font-semibold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded">
                        {vt.completed_transactions} Kendaraan
                      </span>
                    </div>
                    <div className="text-xl font-bold text-slate-900 mt-2">
                      {formatRupiah(vt.total_revenue)}
                    </div>
                  </div>

                  <div className="mt-4">
                    <div className="w-full bg-slate-200 rounded-full h-2">
                      <div
                        className="bg-emerald-600 h-2 rounded-full"
                        style={{ width: `${pct}%` }}
                      />
                    </div>
                    <div className="text-right text-[11px] text-slate-400 mt-1">{pct}% kontribusi</div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>

        {/* Daily Breakdown Table */}
        <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
          <div className="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
            <h3 className="font-semibold text-sm text-slate-900 flex items-center gap-2">
              <Calendar className="w-4 h-4 text-emerald-600" />
              Rincian Harian Transaksi
            </h3>
          </div>

          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-slate-200 text-left text-sm">
              <thead className="bg-slate-50 text-slate-500 font-semibold uppercase text-xs tracking-wider">
                <tr>
                  <th className="px-6 py-3.5">Tanggal</th>
                  <th className="px-6 py-3.5 text-center">Jumlah Kendaraan Keluar</th>
                  <th className="px-6 py-3.5 text-right">Tagihan</th>
                  <th className="px-6 py-3.5 text-right">Terbayar</th>
                  <th className="px-6 py-3.5 text-right">Belum lunas</th>
                  <th className="px-6 py-3.5 text-right">Status lama tidak diketahui</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 text-slate-700">
                {loading ? (
                  <tr>
                    <td colSpan="6" className="px-6 py-8 text-center text-slate-400">
                      <RefreshCw className="w-5 h-5 animate-spin mx-auto mb-2 text-emerald-600" />
                      Memuat laporan...
                    </td>
                  </tr>
                ) : !report?.daily_breakdown || report.daily_breakdown.length === 0 ? (
                  <tr>
                    <td colSpan="3" className="px-6 py-8 text-center text-slate-400">
                      Tidak ada transaksi selesai pada rentang tanggal yang dipilih.
                    </td>
                  </tr>
                ) : (
                  report.daily_breakdown.map((row) => (
                    <tr key={row.date} className="hover:bg-slate-50/60 transition-colors">
                      <td className="px-6 py-3.5 font-mono text-slate-800 font-medium">{row.date}</td>
                      <td className="px-6 py-3.5 text-center">{row.completed_transactions}</td>
                      <td className="px-6 py-3.5 text-right font-medium text-slate-700">
                        {formatRupiah(row.total_charged)}
                      </td>
                      <td className="px-6 py-3.5 text-right font-bold text-emerald-600">
                        {formatRupiah(row.total_revenue)}
                      </td>
                      <td className="px-6 py-3.5 text-right font-semibold text-amber-700">
                        {formatRupiah(row.outstanding_balance)}
                      </td>
                      <td className="px-6 py-3.5 text-right text-slate-500">
                        {formatRupiah(row.unknown_payment_amount)}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>

        {/* Printable Signature Footer (Visible only on print) */}
        <div className="hidden print:flex justify-between pt-12 text-xs text-slate-800">
          <div className="text-center">
            <div>Mengetahui,</div>
            <div className="font-bold mt-16">Pimpinan / Owner</div>
          </div>
          <div className="text-center">
            <div>Dicetak pada: {new Date().toLocaleString('id-ID')}</div>
            <div className="font-bold mt-16">Petugas Administrasi</div>
          </div>
        </div>
      </div>
    </div>
  );
}

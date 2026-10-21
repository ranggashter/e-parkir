import React, { useState, useEffect } from 'react';
import { api } from '../../api/client';
import { ClipboardList, RefreshCw, AlertCircle } from 'lucide-react';

export default function LogsPage() {
  const [logs, setLogs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [error, setError] = useState('');

  const fetchLogs = async () => {
    setLoading(true);
    setError('');
    try {
      const res = await api.get(`/admin/activity-logs?page=${page}&per_page=20`);
      setLogs(res.data.items || []);
      setTotalPages(res.data.total_pages || 1);
    } catch (err) {
      setError(err.message || 'Gagal memuat log aktivitas.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchLogs();
  }, [page]);

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Audit Trail & Log Aktivitas</h1>
          <p className="text-sm text-slate-500">Rekam jejak setiap tindakan operasional dan perubahan data pada sistem</p>
        </div>
        <button
          onClick={fetchLogs}
          disabled={loading}
          className="inline-flex items-center gap-2 px-3.5 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors shadow-sm self-start sm:self-auto"
        >
          <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
          Segarkan Log
        </button>
      </div>

      {error && (
        <div className="p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm flex items-center gap-2">
          <AlertCircle className="w-5 h-5 text-red-500 shrink-0" />
          <span>{error}</span>
        </div>
      )}

      <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-slate-200 text-left text-sm">
            <thead className="bg-slate-50 text-slate-500 font-semibold uppercase text-xs tracking-wider">
              <tr>
                <th className="px-6 py-4">Waktu</th>
                <th className="px-6 py-4">Pengguna</th>
                <th className="px-6 py-4">Role</th>
                <th className="px-6 py-4">Aktivitas</th>
                <th className="px-6 py-4">IP Address</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 text-slate-700">
              {loading ? (
                <tr>
                  <td colSpan="5" className="px-6 py-12 text-center text-slate-400">
                    <RefreshCw className="w-6 h-6 animate-spin mx-auto mb-2 text-blue-600" />
                    Memuat riwayat log...
                  </td>
                </tr>
              ) : logs.length === 0 ? (
                <tr>
                  <td colSpan="5" className="px-6 py-12 text-center text-slate-400">
                    Belum ada riwayat aktivitas tercatat.
                  </td>
                </tr>
              ) : (
                logs.map((log) => (
                  <tr key={log.id_log} className="hover:bg-slate-50/60 transition-colors text-xs">
                    <td className="px-6 py-3.5 font-mono text-slate-500 whitespace-nowrap">
                      {log.waktu_aktivitas}
                    </td>
                    <td className="px-6 py-3.5 font-medium text-slate-900">
                      {log.nama_lengkap || log.username || 'Sistem'}
                    </td>
                    <td className="px-6 py-3.5">
                      <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase bg-slate-100 text-slate-600">
                        {log.role || 'system'}
                      </span>
                    </td>
                    <td className="px-6 py-3.5 font-sans text-slate-800">
                      {log.aktivitas}
                      {log.metadata && (
                        <pre className="mt-1 max-w-lg whitespace-pre-wrap break-words text-[10px] text-slate-500">
                          {typeof log.metadata === 'string' ? log.metadata : JSON.stringify(log.metadata)}
                        </pre>
                      )}
                    </td>
                    <td className="px-6 py-3.5 font-mono text-slate-400">
                      {log.ip_address || '-'}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

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
    </div>
  );
}

import React, { useRef, useState } from 'react';
import { AlertTriangle, CheckCircle2, Database, Download, RefreshCw, ShieldCheck, Upload } from 'lucide-react';
import { downloadDatabaseBackup, restoreDatabaseBackup } from '../../api/client';

const RESTORE_CONFIRMATION = 'PULIHKAN e_parking_ukk_app';

export default function DatabaseBackupPage() {
  const fileInput = useRef(null);
  const [file, setFile] = useState(null);
  const [confirmation, setConfirmation] = useState('');
  const [backupDownloaded, setBackupDownloaded] = useState(false);
  const [downloading, setDownloading] = useState(false);
  const [restoring, setRestoring] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  async function handleDownloadBackup() {
    setDownloading(true);
    setError('');
    setSuccess('');
    try {
      await downloadDatabaseBackup();
      setBackupDownloaded(true);
      setSuccess('Backup berhasil dibuat dan unduhan dimulai. Simpan file di lokasi yang aman.');
    } catch (err) {
      setError(err.message || 'Gagal membuat backup database.');
    } finally {
      setDownloading(false);
    }
  }

  function handleFileChange(event) {
    const selected = event.target.files?.[0] || null;
    setFile(selected);
    setError('');
    setSuccess('');
  }

  async function handleRestore(event) {
    event.preventDefault();
    if (!file || !backupDownloaded || confirmation !== RESTORE_CONFIRMATION) {
      setError('Unduh backup kondisi saat ini, pilih file backup, lalu ketik teks konfirmasi dengan tepat.');
      return;
    }
    if (!window.confirm('Pemulihan akan mengganti seluruh data aplikasi dengan isi file backup. Lanjutkan?')) {
      return;
    }

    setRestoring(true);
    setError('');
    setSuccess('');
    try {
      const response = await restoreDatabaseBackup(file, confirmation);
      setSuccess(response.message || 'Database berhasil dipulihkan.');
      setFile(null);
      setConfirmation('');
      setBackupDownloaded(false);
      if (fileInput.current) {
        fileInput.current.value = '';
      }
    } catch (err) {
      setError(err.message || 'Gagal memulihkan database.');
    } finally {
      setRestoring(false);
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight text-slate-900">Backup dan Pemulihan Database</h1>
        <p className="mt-1 text-sm text-slate-500">
          Buat salinan data aplikasi atau pulihkan dari backup E-Parking yang pernah diunduh.
        </p>
      </div>

      {error && (
        <div role="alert" className="flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
          <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0" />
          <span>{error}</span>
        </div>
      )}
      {success && (
        <div role="status" className="flex items-start gap-2 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">
          <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0" />
          <span>{success}</span>
        </div>
      )}

      <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div className="flex items-start gap-4">
          <div className="rounded-xl bg-blue-50 p-3 text-blue-700">
            <Download className="h-6 w-6" />
          </div>
          <div className="flex-1">
            <h2 className="font-semibold text-slate-900">Unduh Backup</h2>
            <p className="mt-1 text-sm text-slate-600">
              Mengunduh data user, tarif, area, kendaraan, transaksi, dan log aktivitas sebagai file JSON.
              File dibuat saat diminta dan tidak disimpan di server.
            </p>
            <button
              type="button"
              onClick={handleDownloadBackup}
              disabled={downloading || restoring}
              className="mt-4 inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
              {downloading ? <RefreshCw className="h-4 w-4 animate-spin" /> : <Download className="h-4 w-4" />}
              {downloading ? 'Menyiapkan backup...' : 'Unduh Backup'}
            </button>
          </div>
        </div>
      </section>

      <section className="rounded-2xl border border-red-200 bg-white p-6 shadow-sm">
        <div className="flex items-start gap-4">
          <div className="rounded-xl bg-red-50 p-3 text-red-700">
            <Database className="h-6 w-6" />
          </div>
          <div className="flex-1">
            <h2 className="font-semibold text-slate-900">Pulihkan dari Backup</h2>
            <p className="mt-1 text-sm text-slate-600">
              Pemulihan mengganti seluruh data aplikasi. Hanya file JSON backup resmi E-Parking versi yang sama
              yang diterima; berkas SQL bebas tidak dapat dijalankan.
            </p>

            <div className="mt-4 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
              <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0" />
              <p>
                Sebelum memulihkan, unduh backup kondisi saat ini dan simpan file-nya. File backup memuat data pengguna
                termasuk hash password; perlakukan sebagai berkas rahasia.
              </p>
            </div>

            <form onSubmit={handleRestore} className="mt-5 space-y-4">
              <label className="block text-sm font-medium text-slate-700">
                File backup JSON
                <input
                  ref={fileInput}
                  type="file"
                  accept=".json,application/json"
                  onChange={handleFileChange}
                  disabled={restoring || downloading}
                  className="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium"
                />
                {file && <span className="mt-1 block text-xs text-slate-500">{file.name} · {(file.size / 1024).toFixed(1)} KB</span>}
              </label>

              <label className="block text-sm font-medium text-slate-700">
                Untuk konfirmasi, ketik <code className="rounded bg-slate-100 px-1.5 py-0.5">{RESTORE_CONFIRMATION}</code>
                <input
                  type="text"
                  value={confirmation}
                  onChange={(event) => setConfirmation(event.target.value)}
                  autoComplete="off"
                  disabled={restoring || downloading}
                  className="mt-2 block w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-100"
                />
              </label>

              <button
                type="submit"
                disabled={
                  restoring
                  || downloading
                  || !backupDownloaded
                  || !file
                  || confirmation !== RESTORE_CONFIRMATION
                  || file.size > 50 * 1024 * 1024
                }
                className="inline-flex items-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
              >
                {restoring ? <RefreshCw className="h-4 w-4 animate-spin" /> : <Upload className="h-4 w-4" />}
                {restoring ? 'Memulihkan database...' : 'Pulihkan Database'}
              </button>
              {!backupDownloaded && (
                <p className="text-xs text-slate-500">Unduh backup kondisi saat ini terlebih dahulu untuk mengaktifkan pemulihan.</p>
              )}
              {file && file.size > 50 * 1024 * 1024 && (
                <p className="text-xs text-red-600">Ukuran file melebihi batas 50 MB.</p>
              )}
            </form>
          </div>
        </div>
      </section>

      <div className="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-600">
        <ShieldCheck className="h-4 w-4 shrink-0 text-slate-500" />
        <p>
          Fitur ini hanya tersedia bagi admin dan dibatasi ke database <code>e_parking_ukk_app</code>.
          Database lama <code>parkir</code> tidak dapat dipulihkan atau diubah dari halaman ini.
        </p>
      </div>
    </div>
  );
}

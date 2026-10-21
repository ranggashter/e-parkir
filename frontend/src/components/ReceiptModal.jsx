import React from 'react';
import { Printer, CheckCircle, Car, ArrowRight } from 'lucide-react';
import Modal from './Modal';

export default function ReceiptModal({ isOpen, onClose, data, isEntryOnly = false }) {
  if (!isOpen || !data) return null;

  const handlePrint = () => {
    window.print();
  };

  const formatRupiah = (val) => {
    const num = Number(val) || 0;
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num);
  };

  return (
    <>
      <Modal isOpen={isOpen} onClose={onClose} title={isEntryOnly ? "Karcis Parkir Masuk" : "Struk Pembayaran Parkir"}>
        <div className="space-y-6">
          {/* Receipt Preview Paper */}
          <div className="bg-slate-50 p-6 rounded-xl border border-slate-200 font-mono text-xs text-slate-800 printable-area shadow-inner">
            <div className="text-center pb-4 border-b border-dashed border-slate-300">
              <div className="font-bold text-base tracking-wider text-slate-900">E-PARKING UKK RPL</div>
              <div className="text-slate-500 text-[10px]">Jl. Pendidikan No. 1, SMKN Kota</div>
              <div className="text-slate-500 text-[10px]">Telp: (021) 555-0199</div>
              <div className="mt-2 text-xs font-semibold uppercase px-2 py-0.5 rounded bg-slate-200 inline-block">
                {isEntryOnly ? '--- TIKET MASUK ---' : '--- BUKTI PEMBAYARAN ---'}
              </div>
            </div>

            <div className="py-4 space-y-2 border-b border-dashed border-slate-300">
              <div className="flex justify-between">
                <span className="text-slate-500">No. Tiket:</span>
                <span className="font-bold text-slate-900">#{String(data.id_parkir).padStart(6, '0')}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Plat Nomor:</span>
                <span className="font-bold text-sm text-slate-900 tracking-wider">{data.plat_nomor}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Jenis Kendaraan:</span>
                <span className="uppercase text-slate-900">{data.jenis_kendaraan}</span>
              </div>
              {data.warna && (
                <div className="flex justify-between">
                  <span className="text-slate-500">Warna:</span>
                  <span className="text-slate-900">{data.warna}</span>
                </div>
              )}
              <div className="flex justify-between">
                <span className="text-slate-500">Area Parkir:</span>
                <span className="text-slate-900">{data.nama_area}</span>
              </div>
            </div>

            <div className="py-4 space-y-2 border-b border-dashed border-slate-300">
              <div className="flex justify-between">
                <span className="text-slate-500">Waktu Masuk:</span>
                <span className="text-slate-900">{data.waktu_masuk}</span>
              </div>
              {!isEntryOnly && data.waktu_keluar && (
                <>
                  <div className="flex justify-between">
                    <span className="text-slate-500">Waktu Keluar:</span>
                    <span className="text-slate-900">{data.waktu_keluar}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-500">Durasi Ditagih:</span>
                    <span className="font-bold text-slate-900">{data.durasi_jam} Jam</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-500">Tarif / Jam:</span>
                    <span className="text-slate-900">{formatRupiah(data.tarif_per_jam || data.tarif_per_jam_snapshot)}</span>
                  </div>
                </>
              )}
            </div>

            {!isEntryOnly && (
              <div className="pt-4 pb-2 space-y-1">
                <div className="flex justify-between items-center text-sm font-bold text-slate-900">
                  <span>TOTAL BIAYA:</span>
                  <span className="text-base text-blue-600">{formatRupiah(data.biaya_total)}</span>
                </div>
                <div className={`text-[10px] font-semibold text-right ${data.status_pembayaran === 'lunas' ? 'text-emerald-700' : 'text-amber-700'}`}>
                  {data.status_pembayaran === 'lunas'
                    ? 'LUNAS / TUNAI'
                    : data.status_pembayaran === 'legacy_unknown'
                      ? 'STATUS PEMBAYARAN BELUM DIREKONSILIASI'
                      : 'BELUM LUNAS'}
                </div>
                {data.status_pembayaran === 'lunas' && (
                  <>
                    <div className="flex justify-between text-[10px] text-slate-500">
                      <span>Uang diterima:</span>
                      <span>{formatRupiah(data.uang_diterima)}</span>
                    </div>
                    <div className="flex justify-between text-[10px] text-slate-500">
                      <span>Kembalian:</span>
                      <span>{formatRupiah(data.uang_kembalian)}</span>
                    </div>
                  </>
                )}
              </div>
            )}

            <div className="text-center pt-4 text-[10px] text-slate-500 border-t border-dashed border-slate-300 mt-2">
              <div>Simpan karcis ini dengan baik.</div>
              <div>Terima kasih atas kunjungan Anda.</div>
            </div>
          </div>

          {/* Action Buttons */}
          <div className="flex gap-3 justify-end no-print">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-xl hover:bg-slate-50 transition-colors"
            >
              Tutup
            </button>
            <button
              type="button"
              onClick={handlePrint}
              className="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-md shadow-blue-500/20 transition-all flex items-center gap-2"
            >
              <Printer className="w-4 h-4" /> Cetak Struk
            </button>
          </div>
        </div>
      </Modal>
    </>
  );
}

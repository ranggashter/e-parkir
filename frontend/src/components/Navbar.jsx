import React from 'react';
import { useAuth } from '../context/AuthContext';
import { 
  Car, 
  Users, 
  DollarSign, 
  MapPin, 
  ClipboardList, 
  LogIn, 
  LogOut, 
  TrendingUp, 
  ShieldCheck,
  UserCheck,
  History,
  Database
} from 'lucide-react';

export default function Navbar({ activeTab, setActiveTab }) {
  const { user, logout, isAdmin, isOperator, isOwner } = useAuth();

  const roleBadgeColor = {
    admin: 'bg-purple-100 text-purple-700 border-purple-200',
    petugas: 'bg-blue-100 text-blue-700 border-blue-200',
    owner: 'bg-emerald-100 text-emerald-700 border-emerald-200',
  }[user?.role] || 'bg-slate-100 text-slate-700 border-slate-200';

  return (
    <header className="bg-white border-b border-slate-200 sticky top-0 z-30 no-print">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between items-center h-16">
          {/* Brand Logo */}
          <div className="flex items-center space-x-3 cursor-pointer" onClick={() => setActiveTab('default')}>
            <div className="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center text-white shadow-md shadow-blue-500/20">
              <Car className="w-6 h-6" />
            </div>
            <div>
              <span className="font-bold text-lg text-slate-900 tracking-tight flex items-center gap-1.5">
                E-PARKING <span className="text-xs px-2 py-0.5 rounded-full bg-blue-50 text-blue-600 font-semibold border border-blue-200">UKK RPL</span>
              </span>
              <p className="text-xs text-slate-500 hidden sm:block">Sistem Manajemen Parkir Terintegrasi</p>
            </div>
          </div>

          {/* Navigation Links by Role */}
          <nav className="hidden md:flex space-x-1">
            {isAdmin && (
              <>
                <button
                  onClick={() => setActiveTab('dashboard')}
                  className={`px-3 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2 ${
                    activeTab === 'dashboard' ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'
                  }`}
                >
                  <TrendingUp className="w-4 h-4" /> Dashboard
                </button>
                <button
                  onClick={() => setActiveTab('users')}
                  className={`px-3 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2 ${
                    activeTab === 'users' ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'
                  }`}
                >
                  <Users className="w-4 h-4" /> Kelola User
                </button>
                <button
                  onClick={() => setActiveTab('tariffs')}
                  className={`px-3 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2 ${
                    activeTab === 'tariffs' ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'
                  }`}
                >
                  <DollarSign className="w-4 h-4" /> Tarif
                </button>
                <button
                  onClick={() => setActiveTab('areas')}
                  className={`px-3 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2 ${
                    activeTab === 'areas' ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'
                  }`}
                >
                  <MapPin className="w-4 h-4" /> Area Parkir
                </button>
                <button
                  onClick={() => setActiveTab('logs')}
                  className={`px-3 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2 ${
                    activeTab === 'logs' ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'
                  }`}
                >
                  <ClipboardList className="w-4 h-4" /> Log Audit
                </button>
                <button
                  onClick={() => setActiveTab('history')}
                  className={`px-3 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2 ${
                    activeTab === 'history' ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'
                  }`}
                >
                  <History className="w-4 h-4" /> Riwayat Transaksi
                </button>
                <button
                  onClick={() => setActiveTab('database')}
                  className={`px-3 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2 ${
                    activeTab === 'database' ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'
                  }`}
                >
                  <Database className="w-4 h-4" /> Backup
                </button>
              </>
            )}

            {isOperator && (
              <>
                <button
                  onClick={() => setActiveTab('entry')}
                  className={`px-3 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2 ${
                    activeTab === 'entry' ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'
                  }`}
                >
                  <LogIn className="w-4 h-4" /> Kendaraan Masuk
                </button>
                <button
                  onClick={() => setActiveTab('exit')}
                  className={`px-3 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2 ${
                    activeTab === 'exit' ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'
                  }`}
                >
                  <LogOut className="w-4 h-4" /> Kendaraan Keluar
                </button>
                <button
                  onClick={() => setActiveTab('history')}
                  className={`px-3 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2 ${
                    activeTab === 'history' ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'
                  }`}
                >
                  <History className="w-4 h-4" /> Riwayat
                </button>
              </>
            )}

            {isOwner && (
              <button
                onClick={() => setActiveTab('reports')}
                className={`px-3 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2 ${
                  activeTab === 'reports' ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'
                }`}
              >
                <TrendingUp className="w-4 h-4" /> Rekap Pendapatan
              </button>
            )}
          </nav>

          {/* User Profile Pill & Logout */}
          <div className="flex items-center space-x-3">
            <div className="flex items-center space-x-2 pl-2">
              <div className="text-right hidden sm:block">
                <div className="text-sm font-semibold text-slate-800 leading-none">{user?.nama_lengkap}</div>
                <div className="text-xs text-slate-500 mt-0.5">@{user?.username}</div>
              </div>
              <span className={`text-xs px-2.5 py-1 rounded-full font-medium border uppercase tracking-wider ${roleBadgeColor}`}>
                {user?.role}
              </span>
            </div>

            <button
              onClick={logout}
              title="Keluar dari sistem"
              className="p-2 text-slate-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors border border-transparent hover:border-red-200"
            >
              <LogOut className="w-5 h-5" />
            </button>
          </div>
        </div>

        {/* Mobile Navigation bar */}
        <div className="md:hidden flex overflow-x-auto py-2 border-t border-slate-100 space-x-2">
          {isAdmin && (
            <>
              <button onClick={() => setActiveTab('dashboard')} className={`px-2.5 py-1.5 text-xs rounded-md ${activeTab === 'dashboard' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700'}`}>Dashboard</button>
              <button onClick={() => setActiveTab('users')} className={`px-2.5 py-1.5 text-xs rounded-md ${activeTab === 'users' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700'}`}>User</button>
              <button onClick={() => setActiveTab('tariffs')} className={`px-2.5 py-1.5 text-xs rounded-md ${activeTab === 'tariffs' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700'}`}>Tarif</button>
              <button onClick={() => setActiveTab('areas')} className={`px-2.5 py-1.5 text-xs rounded-md ${activeTab === 'areas' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700'}`}>Area</button>
              <button onClick={() => setActiveTab('logs')} className={`px-2.5 py-1.5 text-xs rounded-md ${activeTab === 'logs' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700'}`}>Log</button>
              <button onClick={() => setActiveTab('history')} className={`px-2.5 py-1.5 text-xs rounded-md ${activeTab === 'history' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700'}`}>Riwayat</button>
              <button onClick={() => setActiveTab('database')} className={`px-2.5 py-1.5 text-xs rounded-md ${activeTab === 'database' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700'}`}>Backup</button>
            </>
          )}
          {isOperator && (
            <>
              <button onClick={() => setActiveTab('entry')} className={`px-2.5 py-1.5 text-xs rounded-md ${activeTab === 'entry' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700'}`}>Masuk</button>
              <button onClick={() => setActiveTab('exit')} className={`px-2.5 py-1.5 text-xs rounded-md ${activeTab === 'exit' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700'}`}>Keluar</button>
              <button onClick={() => setActiveTab('history')} className={`px-2.5 py-1.5 text-xs rounded-md ${activeTab === 'history' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700'}`}>Riwayat</button>
            </>
          )}
          {isOwner && (
            <button onClick={() => setActiveTab('reports')} className={`px-2.5 py-1.5 text-xs rounded-md ${activeTab === 'reports' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-700'}`}>Rekap Laporan</button>
          )}
        </div>
      </div>
    </header>
  );
}

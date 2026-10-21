import React, { useState, useEffect } from 'react';
import { AuthProvider, useAuth } from './context/AuthContext';
import LoginPage from './pages/LoginPage';
import Navbar from './components/Navbar';

// Admin Pages
import Dashboard from './pages/admin/Dashboard';
import UsersPage from './pages/admin/UsersPage';
import TariffsPage from './pages/admin/TariffsPage';
import AreasPage from './pages/admin/AreasPage';
import LogsPage from './pages/admin/LogsPage';
import DatabaseBackupPage from './pages/admin/DatabaseBackupPage';

// Operator Pages
import EntryPage from './pages/operator/EntryPage';
import ExitPage from './pages/operator/ExitPage';
import TransactionHistoryPage from './pages/operator/TransactionHistoryPage';

// Owner Pages
import ReportsPage from './pages/owner/ReportsPage';

import { Car, RefreshCw } from 'lucide-react';

function MainApp() {
  const { user, loading, isAdmin, isOperator, isOwner } = useAuth();
  const [activeTab, setActiveTab] = useState('default');

  // Set default tab based on user role when user changes
  useEffect(() => {
    if (user) {
      if (isAdmin) {
        setActiveTab('dashboard');
      } else if (isOperator) {
        setActiveTab('entry');
      } else if (isOwner) {
        setActiveTab('reports');
      }
    }
  }, [user, isAdmin, isOperator, isOwner]);

  if (loading) {
    return (
      <div className="min-h-screen bg-slate-900 flex flex-col items-center justify-center text-white">
        <div className="w-12 h-12 rounded-2xl bg-blue-600 flex items-center justify-center mb-4 shadow-lg shadow-blue-500/30">
          <Car className="w-7 h-7" />
        </div>
        <div className="flex items-center gap-2 text-sm text-slate-400">
          <RefreshCw className="w-4 h-4 animate-spin text-blue-500" />
          Memeriksa sesi login...
        </div>
      </div>
    );
  }

  if (!user) {
    return <LoginPage />;
  }

  const renderContent = () => {
    // Admin Module
    if (isAdmin) {
      switch (activeTab) {
        case 'dashboard':
        case 'default':
          return <Dashboard />;
        case 'users':
          return <UsersPage />;
        case 'tariffs':
          return <TariffsPage />;
        case 'areas':
          return <AreasPage />;
        case 'logs':
          return <LogsPage />;
        case 'database':
          return <DatabaseBackupPage />;
        case 'history':
          return <TransactionHistoryPage />;
        default:
          return <Dashboard />;
      }
    }

    // Operator / Petugas Module
    if (isOperator) {
      switch (activeTab) {
        case 'entry':
        case 'default':
          return <EntryPage />;
        case 'exit':
          return <ExitPage />;
        case 'history':
          return <TransactionHistoryPage />;
        default:
          return <EntryPage />;
      }
    }

    // Owner Module
    if (isOwner) {
      switch (activeTab) {
        case 'reports':
        case 'default':
          return <ReportsPage />;
        default:
          return <ReportsPage />;
      }
    }

    return <div>Role tidak dikenali.</div>;
  };

  return (
    <div className="min-h-screen bg-slate-50 flex flex-col">
      <Navbar activeTab={activeTab} setActiveTab={setActiveTab} />
      <main className="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {renderContent()}
      </main>
      <footer className="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-400 no-print">
        E-Parking UKK RPL &copy; 2026 — Aplikasi Manajemen Parkir Berbasis Web Terintegrasi
      </footer>
    </div>
  );
}

export default function App() {
  return (
    <AuthProvider>
      <MainApp />
    </AuthProvider>
  );
}

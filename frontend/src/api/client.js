const BASE_URL = '/api/v1';

async function getCsrfToken() {
  const response = await fetch(`${BASE_URL}/auth/csrf`, {
    credentials: 'include',
    headers: { Accept: 'application/json' },
  });
  const payload = await response.json();
  if (!response.ok || !payload?.data?.token) {
    throw new Error(payload?.message || 'Gagal mengambil token keamanan.');
  }
  return payload.data.token;
}

export async function apiRequest(endpoint, options = {}) {
  const url = `${BASE_URL}${endpoint}`;
  const method = (options.method || 'GET').toUpperCase();

  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    ...(options.headers || {}),
  };

  if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
    headers['X-CSRF-Token'] = await getCsrfToken();
  }

  const config = {
    ...options,
    method,
    headers,
    credentials: 'include', // penting untuk sesi cookie PHP
  };

  if (options.body && typeof options.body === 'object') {
    config.body = JSON.stringify(options.body);
  }

  try {
    const res = await fetch(url, config);
    const data = await res.json().catch(() => null);

    if (!res.ok) {
      const errorMsg = data?.message || `Terjadi kesalahan (Kode: ${res.status})`;
      const err = new Error(errorMsg);
      err.status = res.status;
      err.data = data;
      throw err;
    }

    return data;
  } catch (err) {
    if (err.status) {
      throw err;
    }
    const networkErr = new Error('Gagal terhubung ke server backend PHP. Pastikan server API berjalan.');
    networkErr.status = 503;
    throw networkErr;
  }
}

export async function downloadReport(endpoint, filename) {
  const response = await fetch(`${BASE_URL}${endpoint}`, {
    credentials: 'include',
    headers: { Accept: 'text/csv, application/json' },
  });
  if (!response.ok) {
    const payload = await response.json().catch(() => null);
    const error = new Error(payload?.message || `Gagal mengunduh laporan (${response.status}).`);
    error.status = response.status;
    throw error;
  }
  const blob = await response.blob();
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

export async function downloadDatabaseBackup() {
  const response = await fetch(`${BASE_URL}/admin/database/backup`, {
    credentials: 'include',
    headers: { Accept: 'application/json' },
  });
  if (!response.ok) {
    const payload = await response.json().catch(() => null);
    const error = new Error(payload?.message || `Gagal membuat backup (${response.status}).`);
    error.status = response.status;
    throw error;
  }
  const blob = await response.blob();
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `e-parking-backup-${new Date().toISOString().replace(/[:.]/g, '-')}.json`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

export async function restoreDatabaseBackup(file, confirmation) {
  const formData = new FormData();
  formData.append('backup_file', file);
  formData.append('confirmation', confirmation);
  const csrfToken = await getCsrfToken();
  const response = await fetch(`${BASE_URL}/admin/database/restore`, {
    method: 'POST',
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      'X-CSRF-Token': csrfToken,
    },
    body: formData,
  });
  const payload = await response.json().catch(() => null);
  if (!response.ok) {
    const error = new Error(payload?.message || `Gagal memulihkan backup (${response.status}).`);
    error.status = response.status;
    throw error;
  }
  return payload;
}

export const api = {
  get: (endpoint) => apiRequest(endpoint, { method: 'GET' }),
  post: (endpoint, body) => apiRequest(endpoint, { method: 'POST', body }),
  patch: (endpoint, body) => apiRequest(endpoint, { method: 'PATCH', body }),
  delete: (endpoint) => apiRequest(endpoint, { method: 'DELETE' }),
};

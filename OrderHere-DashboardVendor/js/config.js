// ═══════════════════════════════════════════════════════════════
// KONFIGURASI API ORDERHERE - DASHBOARD VENDOR
// ═══════════════════════════════════════════════════════════════

const API_CONFIG = {
  // ✅ GANTI dengan IP/domain backend kamu
  BASE_URL: "http://103.185.52.14/api",
  // Atau kalau pakai DuckDNS:
  // BASE_URL: 'http://universitasciputraorderhere.duckdns.org/api',

  ENDPOINTS: {
    // ── AUTH ──────────────────────────────────────────
    LOGIN: "/vendor/login",
    REGISTER: "/vendor/register",
    LOGOUT: "/vendor/logout",
    ME: "/vendor/me",

    // ── FOODS (PUBLIC) ────────────────────────────────
    FOODS: "/foods", // GET semua foods
    FOODS_BY_VENDOR: "/vendors", // + /{vendor_id}/foods
    FOOD_DETAIL: "/foods", // + /{id}

    // ── FOODS (AUTH REQUIRED) ─────────────────────────
    FOOD_CREATE: "/foods", // POST
    FOOD_UPDATE: "/foods", // PUT/PATCH + /{id}
    FOOD_DELETE: "/foods", // DELETE + /{id}
    FOOD_ADD_ADDON: "/foods", // POST + /{id}/addons

    // ── ORDERS ────────────────────────────────────────
    ORDERS: "/vendor/orders", // GET (untuk vendor lihat order sendiri)
    ORDER_DETAIL: "/orders", // GET + /{id}
    ORDER_UPDATE_STATUS: "/orders", // PATCH + /{id}/status
    ORDER_CREATE: "/orders", // POST (public - untuk customer)

    // ── PAYMENT ───────────────────────────────────────
    PAYMENT_CREATE: "/payment/create",
    PAYMENT_QRIS: "/payment/generate-qris",
    PAYMENT_NOTIFICATION: "/payment/notification",
  },
};

// ═══════════════════════════════════════════════════════════════
// TOKEN MANAGEMENT
// ═══════════════════════════════════════════════════════════════

function getToken() {
  return localStorage.getItem("vendor_token");
}

function setToken(token) {
  localStorage.setItem("vendor_token", token);
}

function removeToken() {
  localStorage.removeItem("vendor_token");
  localStorage.removeItem("vendor_data");
}

// ═══════════════════════════════════════════════════════════════
// VENDOR DATA MANAGEMENT
// ═══════════════════════════════════════════════════════════════

function setVendorData(vendor) {
  localStorage.setItem("vendor_data", JSON.stringify(vendor));
}

function getVendorData() {
  const data = localStorage.getItem("vendor_data");
  return data ? JSON.parse(data) : null;
}

// ═══════════════════════════════════════════════════════════════
// HELPER FUNCTIONS
// ═══════════════════════════════════════════════════════════════

// Format Rupiah
function formatRp(amount) {
  if (!amount && amount !== 0) return "Rp. 0";
  return "Rp. " + Number(amount).toLocaleString("id-ID");
}

// Format tanggal ke Indonesia
function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString("id-ID", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

// Format tanggal singkat
function formatDateShort(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString("id-ID", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });
}

// ═══════════════════════════════════════════════════════════════
// FETCH WITH AUTH
// ═══════════════════════════════════════════════════════════════

async function fetchWithAuth(url, options = {}) {
  const token = getToken();
  const headers = {
    Accept: "application/json",
    ...options.headers,
  };

  // Tambahkan Authorization header jika ada token
  if (token) {
    headers["Authorization"] = `Bearer ${token}`;
  }

  // Jangan set Content-Type untuk FormData (biar browser handle boundary)
  if (!(options.body instanceof FormData)) {
    headers["Content-Type"] = "application/json";
  }

  const config = {
    ...options,
    headers,
  };

  try {
    const response = await fetch(`${API_CONFIG.BASE_URL}${url}`, config);
    const data = await response.json();

    // Handle unauthorized - redirect ke login
    if (response.status === 401) {
      removeToken();
      window.location.href = "/pages/login.html";
      throw new Error("Unauthorized - Silakan login kembali");
    }

    return { response, data };
  } catch (error) {
    console.error("Fetch error:", error);
    throw error;
  }
}

// ═══════════════════════════════════════════════════════════════
// URL BUILDER - Untuk endpoint dengan ID
// ═══════════════════════════════════════════════════════════════

// Contoh penggunaan:
// buildUrl(API_CONFIG.ENDPOINTS.FOOD_DETAIL, 123) → '/foods/123'
// buildUrl(API_CONFIG.ENDPOINTS.ORDER_UPDATE_STATUS, 456) → '/orders/456/status'
function buildUrl(endpoint, ...params) {
  let url = endpoint;
  params.forEach((param) => {
    url += "/" + param;
  });
  return url;
}

// ═══════════════════════════════════════════════════════════════
// AUTH CHECK
// ═══════════════════════════════════════════════════════════════

function isAuthenticated() {
  return !!getToken();
}

function requireAuth() {
  if (!isAuthenticated()) {
    window.location.href = "pages/login.html";
    return false;
  }
  return true;
}

// ═══════════════════════════════════════════════════════════════
// TOAST NOTIFICATION
// ═══════════════════════════════════════════════════════════════

function showToast(message, type = "info") {
  // Buat toast element jika belum ada
  let toast = document.getElementById("global-toast");
  if (!toast) {
    toast = document.createElement("div");
    toast.id = "global-toast";
    toast.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 16px 24px;
      border-radius: 8px;
      color: white;
      font-weight: 600;
      z-index: 9999;
      opacity: 0;
      transform: translateX(400px);
      transition: all 0.3s ease;
      max-width: 400px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;
    document.body.appendChild(toast);
  }

  // Set warna berdasarkan tipe
  const colors = {
    success: "#10b981",
    error: "#ef4444",
    warning: "#f59e0b",
    info: "#3b82f6",
  };

  toast.style.background = colors[type] || colors.info;
  toast.textContent = message;

  // Show toast
  setTimeout(() => {
    toast.style.opacity = "1";
    toast.style.transform = "translateX(0)";
  }, 10);

  // Hide after 3 seconds
  setTimeout(() => {
    toast.style.opacity = "0";
    toast.style.transform = "translateX(400px)";
  }, 3000);
}

// ═══════════════════════════════════════════════════════════════
// STATUS HELPERS
// ═══════════════════════════════════════════════════════════════

function getStatusColor(status) {
  const colors = {
    PENDING: "#f59e0b",
    ONPROGRESS: "#3b82f6",
    DIANTAR: "#8b5cf6",
    DONE: "#10b981",
    CANCELLED: "#ef4444",
  };
  return colors[status] || "#6b7280";
}

function getStatusLabel(status) {
  const labels = {
    PENDING: "Menunggu",
    ONPROGRESS: "Diproses",
    DIANTAR: "Diantar",
    DONE: "Selesai",
    CANCELLED: "Dibatalkan",
  };
  return labels[status] || status;
}

// ═══════════════════════════════════════════════════════════════
// LOGOUT FUNCTION
// ═══════════════════════════════════════════════════════════════

async function handleLogout() {
  if (!confirm("Yakin ingin logout?")) return;

  try {
    // Panggil endpoint logout di backend
    await fetchWithAuth(API_CONFIG.ENDPOINTS.LOGOUT, {
      method: "POST",
    });
  } catch (error) {
    console.error("Logout error:", error);
  } finally {
    // Hapus token lokal
    removeToken();
    window.location.href = "/pages/login.html";
  }
}

// ═══════════════════════════════════════════════════════════════
// DEBUG HELPER (untuk development)
// ═══════════════════════════════════════════════════════════════

function debugConfig() {
  console.log("═══════════════════════════════════════");
  console.log("🔧 API CONFIG:");
  console.log("BASE_URL:", API_CONFIG.BASE_URL);
  console.log("Token:", getToken() ? "✅ Ada" : "❌ Tidak ada");
  console.log("Vendor:", getVendorData());
  console.log("═══════════════════════════════════════");
}

// Jalankan debug di console (opsional)
// debugConfig();

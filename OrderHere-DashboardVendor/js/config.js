// ═══════════════════════════════════════════════════════════════
// KONFIGURASI API ORDERHERE - DASHBOARD VENDOR
// ═══════════════════════════════════════════════════════════════

const API_CONFIG = {
  BASE_URL: "http://103.185.52.14/api",
  ENDPOINTS: {
    LOGIN: "/vendor/login",
    REGISTER: "/vendor/register",
    LOGOUT: "/vendor/logout",
    ME: "/vendor/me",
    FOODS: "/foods",
    FOODS_BY_VENDOR: "/vendors",
    FOOD_DETAIL: "/foods",
    FOOD_CREATE: "/foods",
    FOOD_UPDATE: "/foods",
    FOOD_DELETE: "/foods",
    FOOD_ADD_ADDON: "/foods",
    ORDERS: "/vendor/orders",
    ORDER_DETAIL: "/orders",
    ORDER_UPDATE_STATUS: "/orders",
    ORDER_CREATE: "/orders",
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

function formatRp(amount) {
  if (!amount && amount !== 0) return "Rp. 0";
  return "Rp. " + Number(amount).toLocaleString("id-ID");
}

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

function formatDateShort(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString("id-ID", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });
}

// ═══════════════════════════════════════════════════════════════
// FETCH WITH AUTH - IMPROVED
// ═══════════════════════════════════════════════════════════════

async function fetchWithAuth(url, options = {}) {
  const token = getToken();
  const headers = {
    Accept: "application/json",
    ...options.headers,
  };

  if (token) {
    headers["Authorization"] = `Bearer ${token}`;
  }

  if (!(options.body instanceof FormData)) {
    headers["Content-Type"] = "application/json";
  }

  const config = {
    ...options,
    headers,
  };

  try {
    const response = await fetch(`${API_CONFIG.BASE_URL}${url}`, config);

    // ✅ Handle network error
    if (!response.ok && response.status === 0) {
      throw new Error("Network error - Tidak bisa terhubung ke server");
    }

    const data = await response.json();

    if (response.status === 401) {
      removeToken();
      // ✅ Cek path untuk hindari infinite redirect
      const currentPath = window.location.pathname;
      if (
        !currentPath.includes("login.html") &&
        !currentPath.includes("signup.html")
      ) {
        // Kalau di index.html (root)
        if (currentPath.endsWith("/") || currentPath.endsWith("index.html")) {
          window.location.href = "pages/login.html";
        } else {
          // Kalau di dalam folder pages/
          window.location.href = "login.html";
        }
      }
      throw new Error("Sesi berakhir - Silakan login kembali");
    }

    return { response, data };
  } catch (error) {
    console.error("Fetch error:", error);

    // ✅ Better error message untuk network error
    if (error.message === "Failed to fetch" || error.name === "TypeError") {
      throw new Error(
        "Tidak bisa terhubung ke server. Periksa koneksi internet atau CORS setup.",
      );
    }

    throw error;
  }
}

// ═══════════════════════════════════════════════════════════════
// URL BUILDER
// ═══════════════════════════════════════════════════════════════

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

  const colors = {
    success: "#10b981",
    error: "#ef4444",
    warning: "#f59e0b",
    info: "#3b82f6",
  };

  toast.style.background = colors[type] || colors.info;
  toast.textContent = message;

  setTimeout(() => {
    toast.style.opacity = "1";
    toast.style.transform = "translateX(0)";
  }, 10);

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
    await fetchWithAuth(API_CONFIG.ENDPOINTS.LOGOUT, {
      method: "POST",
    });
  } catch (error) {
    console.error("Logout error:", error);
  } finally {
    removeToken();
    window.location.href = "pages/login.html";
  }
}

// ═══════════════════════════════════════════════════════════════
// DEBUG HELPER
// ═══════════════════════════════════════════════════════════════

function debugConfig() {
  console.log("═══════════════════════════════════════");
  console.log("🔧 API CONFIG:");
  console.log("BASE_URL:", API_CONFIG.BASE_URL);
  console.log("Token:", getToken() ? "✅ Ada" : "❌ Tidak ada");
  console.log("Vendor:", getVendorData());
  console.log("═══════════════════════════════════════");
}

const API_CONFIG = {
  BASE_URL: 'http://127.0.0.1:8000/api',
};

// Helper: Fetch dengan error handling
async function apiFetch(endpoint) {
  try {
    const response = await fetch(`${API_CONFIG.BASE_URL}${endpoint}`);
    const data = await response.json();
    
    if (!data.success) {
      throw new Error(data.message || 'API error');
    }
    
    return data;
  } catch (error) {
    console.error(`API Error [${endpoint}]:`, error);
    throw error;
  }
}

// Helper: Format Rupiah
function formatRp(amount) {
  return 'Rp. ' + Number(amount).toLocaleString('id-ID');
}

// Mapping kategori backend → frontend
const CATEGORY_MAP = {
  'FOOD': 'makanan',
  'DRINK': 'minuman',
  'SNACK': 'snack',
  'PRASMANAN': 'paket'
};

// ✅ Helper: Get Full Image URL (untuk gambar dari database)
function getImageUrl(imagePath) {
  if (!imagePath) return null;
  
  // Jika path sudah http/https, langsung return
  if (imagePath.startsWith('http')) return imagePath;
  
  // Jika tidak, tambahkan storage path Laravel
  // Asumsi: gambar disimpan di storage/app/public/foods/
  return `${API_CONFIG.BASE_URL.replace('/api', '')}/storage/${imagePath}`;
}

// ✅ STATE MANAGEMENT UNTUK DINING TYPE & CART
function setDiningType(type) {
  localStorage.setItem('diningType', type);
}

function getDiningType() {
  return localStorage.getItem('diningType') || 'TAKEAWAY';
}

function saveCart(cart) {
  localStorage.setItem('cart', JSON.stringify(cart));
}

function getCart() {
  return JSON.parse(localStorage.getItem('cart')) || [];
}

function clearCart() {
  localStorage.removeItem('cart');
}
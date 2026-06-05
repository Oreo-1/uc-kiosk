const API_CONFIG = {
  BASE_URL: 'https://backend.orderhere.dpdns.org/api',
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

// Mapping kategori backend → frontend (masih dipakai untuk fallback)
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

// ─────────────────────────────────────────────────────
// ✅ FITUR BARU: AUTO-DETECT KATEGORI BERDASARKAN NAMA
// ─────────────────────────────────────────────────────

// Database kata kunci untuk deteksi otomatis
const CATEGORY_KEYWORDS = {
  'Karbo': ['nasi', 'mie', 'pasta', 'roti', 'kentang', 'bubur', 'lontong', 'ketupat', 'bihun', 'spaghetti', 'makaroni', 'quinoa', 'jagung', 'umbi'],
  'Lauk': ['ayam', 'ikan', 'daging', 'sapi', 'telur', 'bakso', 'nugget', 'sosis', 'udang', 'cumi', 'bebek', 'kambing', 'lele', 'empal', 'rendang', 'opor', 'gulai', 'sate', 'pepes', 'ikan bakar', 'ayam goreng', 'telur dadar', 'tempe', 'tahu'],
  'Sayur': ['sayur', 'sop', 'tumis', 'capcay', 'gado', 'urap', 'lalap', 'bayam', 'kangkung', 'asem', 'lodeh', 'plecing', 'pecel', 'sayur asem', 'oseng', 'cah', 'tumis kangkung', 'sup', 'brokoli', 'wortel', 'buncis'],
  'Minuman': ['es', 'jus', 'kopi', 'teh', 'soda', 'air', 'susu', 'jeruk', 'mango', 'lemon', 'coffe', 'latte', 'matcha', 'cappuccino', 'americano', 'espresso', 'coklat', 'choco', 'sirup', 'bandung', 'campur', 'cincau', 'alpukat', 'melon', 'semangka', 'stroberi'],
  'Snack': ['gorengan', 'keripik', 'kue', 'sandwich', 'salad', 'martabak', 'cilok', 'cimol', 'siomay', 'batagor', 'pisang goreng', 'donat', 'lumpia', 'pastel', 'risoles', 'bakwan', 'tempe mendoan', 'tahu isi', 'otak-otak', 'pempek', 'cireng', 'maklon', 'brownies', 'cookies', 'pudding', 'jelly', 'es krim', 'ice cream']
};

// Fungsi untuk menentukan kategori berdasarkan Nama atau Type Database
function getSmartCategory(food) {
  // 1. Cek dulu, kalau di database sudah spesifik (Karbo, Lauk, dll), pakai itu
  const dbType = food.type;
  if (['Karbo', 'Lauk', 'Sayur', 'Minuman', 'Snack'].includes(dbType)) {
    return dbType;
  }

  // 2. Kalau type database umum (FOOD, DRINK, SNACK) atau kosong, cek berdasarkan NAMA
  const foodName = food.name.toLowerCase();

  for (const [category, keywords] of Object.entries(CATEGORY_KEYWORDS)) {
    // Cek apakah ada kata kunci yang cocok di nama makanan
    if (keywords.some(keyword => foodName.includes(keyword))) {
      return category;
    }
  }

  // 3. Fallback: Jika tidak ada yang cocok, kembalikan ke type asli atau 'Snack'
  if (dbType === 'DRINK') return 'Minuman';
  if (dbType === 'FOOD') return 'Lauk'; // Default untuk makanan umum
  if (dbType === 'SNACK') return 'Snack';
  
  return 'Snack'; // Default terakhir
}
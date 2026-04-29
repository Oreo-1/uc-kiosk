/* =========================
   GLOBAL STATE
========================= */
let overlay = null;
let editIndex = null;

/* =========================
   SPA PAGE LOADER
========================= */
async function loadPage(page, btn) {
  const res = await fetch(page);
  const html = await res.text();

  document.getElementById("content").innerHTML = html;

  // sidebar active
  document.querySelectorAll(".nav").forEach((item) => {
    item.classList.remove("active");
  });

  if (btn) btn.classList.add("active");

  // jalankan script halaman
  const scripts = document.querySelectorAll("#content script");

  scripts.forEach((oldScript) => {
    const newScript = document.createElement("script");

    if (oldScript.src) {
      newScript.src = oldScript.src;
    } else {
      newScript.textContent = oldScript.textContent;
    }

    document.body.appendChild(newScript);
    oldScript.remove();
  });

  // auto init page
  requestAnimationFrame(() => {
    if (typeof window.initMenuPage === "function") {
      window.initMenuPage();
    }

    if (typeof window.initOrderPage === "function") {
      window.initOrderPage();
    }
  });
}

/* =========================
   GLOBAL OVERLAY
========================= */
function bindOverlayGlobal(el, closeFn) {
  if (!el) return;
  el.onclick = closeFn;
}

/* =========================
   FIRST LOAD
========================= */
window.onload = () => {
  loadPage("pages/order.html", document.querySelector(".nav"));
};

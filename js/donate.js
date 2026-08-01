/* ── donate.js ── Adhaar Donate Page ── */

/* Scroll reveal */
const revealEls = document.querySelectorAll('.reveal');
const ro = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) { e.target.classList.add('show'); ro.unobserve(e.target); }
  });
}, { threshold: 0.12 });
revealEls.forEach(el => ro.observe(el));

/* Tab switching: food / cloth */
function switchTab(type) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === type));
  document.querySelectorAll('.form-panel').forEach(p => p.classList.toggle('active', p.id === type + 'Form'));
}

/* File preview */
function previewFile(inputId, previewId) {
  const inp  = document.getElementById(inputId);
  const prev = document.getElementById(previewId);
  if (!inp || !prev) return;
  inp.addEventListener('change', () => {
    const file = inp.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
      prev.src = e.target.result;
      prev.style.display = 'block';
    };
    reader.readAsDataURL(file);
  });
}
previewFile('foodImage', 'foodPreview');
previewFile('clothImage', 'clothPreview');

/* Character counter */
document.querySelectorAll('textarea[data-max]').forEach(ta => {
  const max = parseInt(ta.dataset.max, 10);
  const counter = document.createElement('div');
  counter.className = 'char-count';
  ta.parentNode.appendChild(counter);
  ta.addEventListener('input', () => {
    const len = ta.value.length;
    counter.textContent = len + ' / ' + max;
    counter.style.color = len > max * 0.9 ? '#ef4444' : '#9a8f5c';
  });
});

/* CSRF auto-inject */
fetch('../api/get_csrf.php').then(r => r.json()).then(d => {
  document.querySelectorAll('form[method="POST"],form[method="post"]').forEach(f => {
    if (!f.querySelector('[name="csrf_token"]')) {
      const i = document.createElement('input');
      i.type = 'hidden'; i.name = 'csrf_token'; i.value = d.token;
      f.prepend(i);
    }
  });
}).catch(() => {});

/* Form submit loading state */
document.querySelectorAll('form.donate-form').forEach(form => {
  form.addEventListener('submit', () => {
    const btn = form.querySelector('button[type="submit"]');
    if (btn) { btn.disabled = true; btn.textContent = 'Submitting…'; }
  });
});

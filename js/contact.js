/* ── contact.js ── Adhaar Contact Page ── */

/* Scroll reveal */
const revealEls = document.querySelectorAll('.reveal');
const ro = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) { e.target.classList.add('show'); ro.unobserve(e.target); }
  });
}, { threshold: 0.13 });
revealEls.forEach(el => ro.observe(el));

/* Success / error banner from URL param */
const params = new URLSearchParams(window.location.search);
const banner = document.getElementById('statusBanner');
if (banner) {
  if (params.get('sent') === '1') {
    banner.textContent = '✅ Message sent! We will get back to you within 24–48 hours.';
    banner.className = 'status-banner success';
    banner.style.display = 'flex';
  } else if (params.get('error') === '1') {
    banner.textContent = '⚠ Something went wrong. Please try again.';
    banner.className = 'status-banner error';
    banner.style.display = 'flex';
  }
}

/* Character counter for textarea */
const msg = document.getElementById('msgArea');
const counter = document.getElementById('charCount');
if (msg && counter) {
  msg.addEventListener('input', () => {
    const len = msg.value.length;
    counter.textContent = len + ' / 1000';
    counter.style.color = len > 900 ? '#ef4444' : '#9a8f5c';
  });
}

/* Form field focus animation */
document.querySelectorAll('.contact-form input, .contact-form textarea').forEach(inp => {
  inp.addEventListener('focus', () => inp.closest('.input-group')?.classList.add('focused'));
  inp.addEventListener('blur',  () => inp.closest('.input-group')?.classList.remove('focused'));
});

/* CSRF auto-inject for all POST forms */
fetch('../api/get_csrf.php').then(r => r.json()).then(d => {
  document.querySelectorAll('form[method="POST"],form[method="post"]').forEach(f => {
    if (!f.querySelector('[name="csrf_token"]')) {
      const i = document.createElement('input');
      i.type = 'hidden'; i.name = 'csrf_token'; i.value = d.token;
      f.prepend(i);
    }
  });
});

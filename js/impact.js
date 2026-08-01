/* ── impact.js ── Adhaar Impact Page ── */

/* Scroll reveal */
const revealEls = document.querySelectorAll('.reveal');
const ro = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) { e.target.classList.add('show'); ro.unobserve(e.target); }
  });
}, { threshold: 0.12 });
revealEls.forEach(el => ro.observe(el));

/* Count-up for stat numbers */
const counters = document.querySelectorAll('[data-count]');
const co = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (!e.isIntersecting) return;
    const el = e.target;
    const target = parseInt(el.dataset.count, 10);
    const suffix = el.dataset.suffix || '+';
    let current = 0;
    const step = Math.max(1, Math.ceil(target / 70));
    const timer = setInterval(() => {
      current = Math.min(current + step, target);
      el.textContent = current.toLocaleString('en-IN') + (current >= target ? suffix : '');
      if (current >= target) clearInterval(timer);
    }, 20);
    co.unobserve(el);
  });
}, { threshold: 0.5 });
counters.forEach(c => co.observe(c));

/* Progress bars */
const bars = document.querySelectorAll('.prog-fill[data-width]');
const bo = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.style.width = e.target.dataset.width;
      bo.unobserve(e.target);
    }
  });
}, { threshold: 0.3 });
bars.forEach(b => bo.observe(b));

/* SDG card hover */
document.querySelectorAll('.sdg-card').forEach(c => {
  c.addEventListener('mouseenter', () => c.style.transform = 'scale(1.04) translateY(-4px)');
  c.addEventListener('mouseleave', () => c.style.transform = '');
});

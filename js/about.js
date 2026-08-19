/* ── about.js ── Adhaar About Page ── */

/* Scroll reveal */
const revealEls = document.querySelectorAll('.reveal');
const ro = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) { e.target.classList.add('show'); ro.unobserve(e.target); }
  });
}, { threshold: 0.13 });
revealEls.forEach(el => ro.observe(el));

/* Timeline animated dots */
const tlItems = document.querySelectorAll('.tl-item');
const tlo = new IntersectionObserver(entries => {
  entries.forEach((e, i) => {
    if (e.isIntersecting) {
      setTimeout(() => e.target.classList.add('visible'), i * 120);
      tlo.unobserve(e.target);
    }
  });
}, { threshold: 0.2 });
tlItems.forEach(el => tlo.observe(el));

/* Value cards hover lift (extra JS touch) */
document.querySelectorAll('.value-card').forEach(card => {
  card.addEventListener('mouseenter', () => card.style.transform = 'translateY(-8px)');
  card.addEventListener('mouseleave', () => card.style.transform = '');
});

/* Team card subtle parallax on mouse */
document.querySelectorAll('.team-card').forEach(card => {
  card.addEventListener('mousemove', e => {
    const r = card.getBoundingClientRect();
    const x = ((e.clientX - r.left) / r.width  - 0.5) * 12;
    const y = ((e.clientY - r.top)  / r.height - 0.5) * 8;
    card.style.transform = `translateY(-6px) rotateX(${-y}deg) rotateY(${x}deg)`;
  });
  card.addEventListener('mouseleave', () => card.style.transform = '');
});

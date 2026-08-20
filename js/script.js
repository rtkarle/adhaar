/* ================================================================
   SoulServe Global Script v4.0
   - Toast notifications
   - Navbar scroll + mobile menu
   - Scroll reveal (IntersectionObserver)
   - Counter animation (countUp)
   - Stories slider
   - CSRF fetch
   - Form AJAX helpers
   ================================================================ */

/* ── TOAST SYSTEM ── */
(function(){
  const container = document.createElement('div');
  container.className = 'toast-container';
  document.body.appendChild(container);

  window.showToast = function(msg, type='info', duration=3200){
    const icons = { success:'✓', error:'✕', info:'ℹ' };
    const t = document.createElement('div');
    t.className = `toast toast-${type}`;
    t.innerHTML = `<span class="toast-icon">${icons[type]||'ℹ'}</span><span style="flex:1">${msg}</span><button class="toast-close" onclick="this.parentElement.remove()">✕</button>`;
    container.appendChild(t);
    setTimeout(()=>{ if(t.parentElement) t.remove(); }, duration);
  };
})();

/* ── NAVBAR: scroll shadow ── */
const _hdr = document.getElementById('header');
if(_hdr){
  window.addEventListener('scroll', ()=> _hdr.classList.toggle('scrolled', scrollY > 60), {passive:true});
}

/* ── MOBILE MENU ── */
const _toggle = document.getElementById('menuToggle');
const _menu   = document.getElementById('mobileMenu');
if(_toggle && _menu){
  _toggle.addEventListener('click', ()=>{
    const open = _menu.classList.toggle('show');
    _toggle.textContent = open ? '✕' : '☰';
  });
  _menu.querySelectorAll('.nav-link').forEach(l => l.addEventListener('click', ()=>{
    _menu.classList.remove('show');
    _toggle.textContent = '☰';
  }));
}

/* ── SCROLL REVEAL ── */
(function(){
  const ro = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if(e.isIntersecting){ e.target.classList.add('show'); ro.unobserve(e.target); }
    });
  }, { threshold: 0.10 });
  document.querySelectorAll('.reveal, .reveal-l, .reveal-r').forEach(el => ro.observe(el));
})();

/* ── COUNT-UP ANIMATION ── */
window.animateCount = function(el, target, suffix, duration){
  if(!el) return;
  duration = duration || 2000;
  suffix   = suffix   || '';
  const steps = 60;
  const inc   = target / steps;
  let cur     = 0;
  const timer = setInterval(()=>{
    cur += inc;
    if(cur >= target){ el.textContent = target + suffix; clearInterval(timer); }
    else { el.textContent = Math.floor(cur) + suffix; }
  }, duration / steps);
};

/* ── STORIES SLIDER (homepage) ── */
(function(){
  const track = document.getElementById('storiesTrack');
  const dots  = document.querySelectorAll('#storyDots .slider-dot');
  if(!track || !dots.length) return;

  let idx = 0;
  const total = dots.length;

  function goTo(i){
    idx = (i + total) % total;
    const card = track.firstElementChild;
    const w    = card ? card.offsetWidth + 24 : 344;
    track.style.transform = `translateX(-${idx * w}px)`;
    dots.forEach((d,j) => d.classList.toggle('active', j === idx));
  }

  window.goToStory    = goTo;
  window.slideStories = dir => goTo(idx + dir);

  // Auto-play
  let auto = setInterval(()=> goTo(idx + 1), 5000);
  track.addEventListener('mouseenter', ()=> clearInterval(auto));
  track.addEventListener('mouseleave', ()=>{ auto = setInterval(()=> goTo(idx+1), 5000); });

  // Touch / swipe
  let sx = 0;
  track.addEventListener('touchstart', e => { sx = e.touches[0].clientX; }, {passive:true});
  track.addEventListener('touchend',   e => {
    const dx = e.changedTouches[0].clientX - sx;
    if(Math.abs(dx) > 50) goTo(dx < 0 ? idx+1 : idx-1);
  });
})();

/* ── CSRF INJECTION ── */
(function(){
  // Detect path depth: pages/ and auth/ need ../api/, root needs api/
  const depth = location.pathname.split('/').filter(Boolean).length;
  const base  = depth >= 3 ? '../api/' : 'api/';
  fetch(base + 'get_csrf.php').then(r=>r.json()).then(d=>{
    if(!d.token) return;
    document.querySelectorAll('[name="csrf_token"]').forEach(el => el.value = d.token);
    // Also target named IDs used in donate.html
    ['foodCsrf','clothCsrf','volCsrf','contactCsrf'].forEach(id=>{
      const el = document.getElementById(id);
      if(el) el.value = d.token;
    });
  }).catch(()=>{});
})();

/* ── LOGIN-GATE: hide if already logged in ── */
(function(){
  const gate = document.getElementById('loginBanner');
  if(!gate) return;
  fetch('api/get_csrf.php').then(r=>r.json()).then(d=>{
    if(d.logged_in) gate.style.display = 'none';
  }).catch(()=>{});
})();

/* ── GENERIC AJAX FORM HELPER ── */
window.ajaxForm = function(formId, successMsg){
  const form = document.getElementById(formId);
  if(!form) return;
  form.addEventListener('submit', function(e){
    e.preventDefault();
    const btn  = form.querySelector('[type=submit]');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="loading-spinner"></span>Sending…';
    fetch(form.action, { method:'POST', body: new FormData(form) })
      .then(r => r.ok ? r.json().catch(()=>({})) : Promise.reject(r))
      .then(()=>{
        showToast(successMsg || 'Submitted successfully!', 'success');
        form.reset();
        // hide login gate if shown inside donate page after success
        const gate = document.getElementById('loginBanner');
        if(gate) gate.style.display = 'none';
      })
      .catch(()=> showToast('Something went wrong. Please try again.', 'error'))
      .finally(()=>{ btn.disabled = false; btn.innerHTML = orig; });
  });
};

/* ── VOLUNTEER FORM ── */
ajaxForm('volForm', '🎉 Application submitted! We\'ll contact you soon.');

/* ── CONTACT FORM ── */
ajaxForm('contactForm', '✓ Message sent! We\'ll respond within 24 hours.');

/* ── FOOD DONATE FORM ── */
ajaxForm('foodForm', '🍱 Food donation submitted! A volunteer will contact you for pickup.');

/* ── CLOTH DONATE FORM ── */
ajaxForm('clothForm', '👕 Clothing donation submitted! Pickup will be scheduled soon.');

/* ── FILE INPUT PREVIEW LABEL ── */
document.querySelectorAll('input[type=file]').forEach(inp=>{
  inp.addEventListener('change', function(){
    const labelId = this.id + 'Name';
    const label   = document.getElementById(labelId);
    if(label) label.textContent = this.files[0] ? this.files[0].name : '';
    // Image preview
    const previewId = this.id + 'Preview';
    const preview   = document.getElementById(previewId);
    if(preview && this.files[0]){
      const reader = new FileReader();
      reader.onload = e => {
        preview.src = e.target.result;
        preview.style.display = 'block';
      };
      reader.readAsDataURL(this.files[0]);
    }
  });
});

/* ── LIVE STATS FROM API (homepage) ── */
(function(){
  const ids = ['stat-donations','stat-volunteers','stat-lives','s1','s2','s3','fc-meals','fc-vols'];
  if(!ids.some(id => document.getElementById(id))) return;

  fetch('api/ai_stats.php')
    .then(r => r.json())
    .then(d => {
      const fd   = d.food_delivered  || 0;
      const cd   = d.cloth_delivered || 0;
      const vols = d.volunteers      || 0;
      animateCount(document.getElementById('stat-donations'), fd + cd,          '+');
      animateCount(document.getElementById('stat-volunteers'), vols,             '+');
      animateCount(document.getElementById('stat-lives'),      Math.round((fd+cd)*3.2), '+');
      animateCount(document.getElementById('s1'),  fd,   '+');
      animateCount(document.getElementById('s2'),  cd,   '+');
      animateCount(document.getElementById('s3'),  vols, '+');
      animateCount(document.getElementById('fc-meals'), fd,   '+');
      animateCount(document.getElementById('fc-vols'),  vols, '+');
      const s4 = document.getElementById('s4');
      if(s4) s4.textContent = '15+';
    })
    .catch(()=>{
      // Fallback display values
      const fallbacks = {
        'stat-donations':'500+','stat-volunteers':'80+','stat-lives':'1.6K+',
        's1':'500+','s2':'300+','s3':'80+','s4':'15+','fc-meals':'500+','fc-vols':'80+'
      };
      Object.entries(fallbacks).forEach(([id,val])=>{
        const el = document.getElementById(id);
        if(el) el.textContent = val;
      });
    });
})();

/* ── FAQ ACCORDION ── */
window.toggleFaq = function(i){
  const item = document.getElementById('faq-' + i);
  if(!item) return;
  const isOpen = item.classList.contains('open');
  document.querySelectorAll('.faq-item.open').forEach(el => el.classList.remove('open'));
  if(!isOpen) item.classList.add('open');
};

/* ── DONATE TAB SWITCH ── */
window.switchTab = function(name, btn){
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.donate-tab').forEach(b => b.classList.remove('active'));
  const panel = document.getElementById('tab-' + name);
  if(panel) panel.classList.add('active');
  if(btn)   btn.classList.add('active');
};

/* ── PASSWORD TOGGLE ── */
window.togglePwd = function(fieldId, btnId){
  fieldId = fieldId || 'pwdField';
  btnId   = btnId   || 'eyeBtn';
  const f = document.getElementById(fieldId);
  const b = document.getElementById(btnId);
  if(!f) return;
  if(f.type === 'password'){ f.type = 'text';     if(b) b.innerHTML = '🙈'; }
  else                     { f.type = 'password'; if(b) b.innerHTML = '👁'; }
};

/* ── SHOP PREVIEW CARDS (homepage static fallback) ── */
(function(){
  const grid = document.getElementById('shopPreviewGrid');
  if(!grid) return;
  const fallback = [
    { icon:'🧵', name:'Handloom Saree', price:'₹899', seller:'Savita D., Nashik' },
    { icon:'🍯', name:'Organic Honey', price:'₹349', seller:'Ramesh K., Pune' },
    { icon:'🛍️', name:'Jute Handbag', price:'₹450', seller:'Meena S., Nashik' },
    { icon:'🧆', name:'Artisan Pottery', price:'₹599', seller:'Arjun P., Kolhapur' },
  ];
  grid.innerHTML = fallback.map(p=>`
    <a href="../shop/shop.php" class="shop-card">
      <div class="shop-card-img">${p.icon}</div>
      <div class="shop-card-body">
        <div class="shop-card-name">${p.name}</div>
        <div class="shop-card-price">${p.price}</div>
        <div class="shop-card-seller">by ${p.seller}</div>
      </div>
    </a>`).join('');
})();

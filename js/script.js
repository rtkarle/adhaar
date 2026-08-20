/* ================================================================
   SoulServe Global Script v5.0
   All stats and shop products pulled from real DB APIs.
   No dummy / hardcoded fallback numbers.
================================================================ */

/* ── PATH HELPER ── */
const _apiBase = (function(){
  const parts = location.pathname.split('/').filter(Boolean);
  // /adhaar/pages/... → depth ≥ 3 → ../api/
  // /adhaar/auth/...  → depth ≥ 3 → ../api/
  // /adhaar/index.html → depth ≤ 2 → api/
  return parts.length >= 3 ? '../api/' : 'api/';
})();

/* ── TOAST SYSTEM ── */
(function(){
  const container = document.createElement('div');
  container.className = 'toast-container';
  document.body.appendChild(container);

  window.showToast = function(msg, type='info', duration=3200){
    const icons = { success:'✓', error:'✕', info:'ℹ' };
    const t = document.createElement('div');
    t.className = `toast toast-${type}`;
    t.innerHTML = `<span class="toast-icon">${icons[type]||'ℹ'}</span>`
                + `<span style="flex:1">${msg}</span>`
                + `<button class="toast-close" onclick="this.parentElement.remove()">✕</button>`;
    container.appendChild(t);
    setTimeout(()=>{ if(t.parentElement) t.remove(); }, duration);
  };
})();

/* ── NAVBAR SCROLL SHADOW ── */
const _hdr = document.getElementById('header');
if(_hdr){
  window.addEventListener('scroll',
    ()=> _hdr.classList.toggle('scrolled', scrollY > 60), {passive:true});
}

/* ── MOBILE MENU (public navbar) ── */
(function(){
  const toggle = document.getElementById('menuToggle');
  const menu   = document.getElementById('mobileMenu');
  if(!toggle || !menu) return;

  function openMenu(){
    menu.classList.add('show');
    toggle.textContent = '✕';
  }
  function closeMenu(){
    menu.classList.remove('show');
    toggle.textContent = '☰';
  }

  ['click','touchstart'].forEach(ev=>{
    toggle.addEventListener(ev, e=>{
      e.preventDefault(); e.stopPropagation();
      menu.classList.contains('show') ? closeMenu() : openMenu();
    }, {passive:false});
  });

  menu.querySelectorAll('.nav-link').forEach(l=>{
    l.addEventListener('click', closeMenu);
  });

  document.addEventListener('touchstart', e=>{
    if(menu.classList.contains('show')
       && !menu.contains(e.target)
       && e.target !== toggle) closeMenu();
  }, {passive:true});
})();

/* ── SCROLL REVEAL ── */
(function(){
  const ro = new IntersectionObserver(entries=>{
    entries.forEach(e=>{
      if(e.isIntersecting){ e.target.classList.add('show'); ro.unobserve(e.target); }
    });
  }, { threshold: 0.10 });
  document.querySelectorAll('.reveal, .reveal-l, .reveal-r').forEach(el=>ro.observe(el));
})();

/* ── COUNT-UP ANIMATION ── */
window.animateCount = function(el, target, suffix, duration){
  if(!el || isNaN(target)) return;
  duration = duration || 2000;
  suffix   = suffix   || '';
  const steps = 60;
  const inc   = target / steps;
  let cur = 0;
  const timer = setInterval(()=>{
    cur += inc;
    if(cur >= target){
      el.textContent = Number(target).toLocaleString('en-IN') + suffix;
      clearInterval(timer);
    } else {
      el.textContent = Math.floor(cur).toLocaleString('en-IN') + suffix;
    }
  }, duration / steps);
};

/* ── STORIES SLIDER ── */
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
    dots.forEach((d,j)=> d.classList.toggle('active', j === idx));
  }
  window.goToStory    = goTo;
  window.slideStories = dir => goTo(idx + dir);

  let auto = setInterval(()=> goTo(idx+1), 5000);
  track.addEventListener('mouseenter', ()=> clearInterval(auto));
  track.addEventListener('mouseleave', ()=>{ auto = setInterval(()=> goTo(idx+1), 5000); });

  let sx = 0;
  track.addEventListener('touchstart', e=>{ sx = e.touches[0].clientX; }, {passive:true});
  track.addEventListener('touchend',   e=>{
    const dx = e.changedTouches[0].clientX - sx;
    if(Math.abs(dx) > 50) goTo(dx < 0 ? idx+1 : idx-1);
  });
})();

/* ── CSRF INJECTION ── */
(function(){
  fetch(_apiBase + 'get_csrf.php').then(r=>r.json()).then(d=>{
    if(!d.token) return;
    document.querySelectorAll('[name="csrf_token"]').forEach(el=> el.value = d.token);
    ['foodCsrf','clothCsrf','volCsrf','contactCsrf'].forEach(id=>{
      const el = document.getElementById(id);
      if(el) el.value = d.token;
    });
  }).catch(()=>{});
})();

/* ── LOGIN-GATE ── */
(function(){
  const gate = document.getElementById('loginBanner');
  if(!gate) return;
  fetch(_apiBase + 'get_csrf.php').then(r=>r.json()).then(d=>{
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
      .then(r=> r.ok ? r.json().catch(()=>({})) : Promise.reject(r))
      .then(()=>{
        showToast(successMsg || 'Submitted successfully!', 'success');
        form.reset();
        const gate = document.getElementById('loginBanner');
        if(gate) gate.style.display = 'none';
      })
      .catch(()=> showToast('Something went wrong. Please try again.', 'error'))
      .finally(()=>{ btn.disabled = false; btn.innerHTML = orig; });
  });
};

/* ── FORM WIRING ── */
ajaxForm('volForm',   '🎉 Application submitted! We\'ll contact you soon.');
ajaxForm('contactForm','✓ Message sent! We\'ll respond within 24 hours.');
ajaxForm('foodForm',  '🍱 Food donation submitted! A volunteer will contact you for pickup.');
ajaxForm('clothForm', '👕 Clothing donation submitted! Pickup will be scheduled soon.');

/* ── FILE INPUT PREVIEW ── */
document.querySelectorAll('input[type=file]').forEach(inp=>{
  inp.addEventListener('change', function(){
    const label = document.getElementById(this.id + 'Name');
    if(label) label.textContent = this.files[0] ? this.files[0].name : '';
    const preview = document.getElementById(this.id + 'Preview');
    if(preview && this.files[0]){
      const reader = new FileReader();
      reader.onload = e=>{ preview.src = e.target.result; preview.style.display='block'; };
      reader.readAsDataURL(this.files[0]);
    }
  });
});

/* ── LIVE STATS FROM REAL API ── */
(function(){
  const statIds = ['stat-donations','stat-volunteers','stat-lives',
                   's1','s2','s3','s4','fc-meals','fc-vols'];
  if(!statIds.some(id=> document.getElementById(id))) return;

  fetch(_apiBase + 'ai_stats.php')
    .then(r=> r.json())
    .then(d=>{
      /* Real field names from api/ai_stats.php */
      const meals  = d.meals_distributed  || 0;   // food quantity delivered
      const cloth  = d.clothing_delivered || 0;   // cloth quantity delivered
      const vols   = d.active_volunteers  || 0;
      const areas  = d.areas_covered      || 0;
      const donors = d.donors             || 0;
      const total  = d.total_donations    || 0;
      const people = d.people_fed         || 0;

      /* Hero stats */
      animateCount(document.getElementById('stat-donations'),  total,  '+');
      animateCount(document.getElementById('stat-volunteers'),  vols,   '+');
      animateCount(document.getElementById('stat-lives'),       people, '+');

      /* Impact strip */
      animateCount(document.getElementById('s1'), meals,  '+');
      animateCount(document.getElementById('s2'), cloth,  '+');
      animateCount(document.getElementById('s3'), vols,   '+');

      /* Areas covered — real value */
      const s4 = document.getElementById('s4');
      if(s4) animateCount(s4, areas, '+');

      /* Floating cards */
      animateCount(document.getElementById('fc-meals'), meals, '+');
      animateCount(document.getElementById('fc-vols'),  vols,  '+');
    })
    .catch(()=>{
      /* API unreachable — show dashes, not fake numbers */
      ['stat-donations','stat-volunteers','stat-lives',
       's1','s2','s3','s4','fc-meals','fc-vols'].forEach(id=>{
        const el = document.getElementById(id);
        if(el && el.textContent === '0') el.textContent = '—';
      });
    });
})();

/* ── SHOP PREVIEW — REAL PRODUCTS FROM DB ── */
(function(){
  const grid = document.getElementById('shopPreviewGrid');
  if(!grid) return;

  const shopBase = _apiBase;           // api/ or ../api/
  const shopHref = shopBase.replace('api/', 'shop/shop.php');
  const prodHref = id => shopBase.replace('api/', 'shop/product.php?id=') + id;

  const catIcons = {
    handicraft:'🧵', textile:'🧶', food_product:'🫙',
    jewelry:'💎', art:'🎨', pottery:'🏺', organic:'🌿', other:'🛍️'
  };

  /* Show skeleton placeholders while loading */
  grid.innerHTML = [1,2,3,4].map(()=>`
    <div class="shop-card" style="pointer-events:none">
      <div class="shop-card-img" style="background:#f0f4f3">
        <div style="width:60%;height:14px;background:#e2ebe9;border-radius:6px;animation:shimmer 1.4s infinite"></div>
      </div>
      <div class="shop-card-body">
        <div style="height:13px;background:#e2ebe9;border-radius:5px;margin-bottom:8px;animation:shimmer 1.4s infinite"></div>
        <div style="height:11px;background:#e2ebe9;border-radius:5px;width:60%;animation:shimmer 1.4s infinite"></div>
      </div>
    </div>`).join('');

  fetch(shopBase + 'shop_preview.php')
    .then(r=> r.json())
    .then(data=>{
      if(!data.ok || !data.products.length){
        /* No products in DB yet — show a friendly placeholder */
        grid.innerHTML = `
          <div style="grid-column:1/-1;padding:48px 24px;text-align:center;
            background:#fff;border-radius:20px;border:1.5px dashed #e2ebe9">
            <div style="font-size:48px;margin-bottom:16px">🛍️</div>
            <h4 style="font-size:18px;font-weight:800;color:#102A43;margin-bottom:8px">
              Shop Coming Soon
            </h4>
            <p style="font-size:14px;color:#5A7184;max-width:360px;margin:0 auto 20px">
              Rural artisans are setting up their stores. Be the first to shop handmade products.
            </p>
            <a href="${shopHref}" style="display:inline-flex;align-items:center;gap:6px;
              padding:10px 24px;border-radius:20px;background:linear-gradient(135deg,#006D77,#2E8B57);
              color:#fff;font-size:13px;font-weight:700;text-decoration:none">
              Visit Shop →
            </a>
          </div>`;
        return;
      }

      grid.innerHTML = data.products.map(p=>{
        const icon = catIcons[p.category] || '🛍️';
        const img  = p.image
          ? `<img src="${p.image}" alt="${p.name}"
               style="width:100%;height:100%;object-fit:cover;display:block"
               loading="lazy" onerror="this.parentElement.innerHTML='${icon}'">`
          : icon;
        const price = '₹' + Number(p.price).toLocaleString('en-IN');
        const loc   = p.location || p.store_name;
        return `
          <a href="${prodHref(p.id)}" class="shop-card"
             style="text-decoration:none;color:inherit">
            <div class="shop-card-img">${img}</div>
            <div class="shop-card-body">
              <div class="shop-card-name">${p.name}</div>
              <div class="shop-card-price">${price}</div>
              <div class="shop-card-seller">by ${p.store_name}${loc?', '+loc:''}</div>
            </div>
          </a>`;
      }).join('');
    })
    .catch(()=>{
      /* Network error — show shop link, no dummy data */
      grid.innerHTML = `
        <div style="grid-column:1/-1;padding:40px;text-align:center;
          background:#fff;border-radius:20px;border:1px solid #e2ebe9">
          <div style="font-size:40px;margin-bottom:12px">🛍️</div>
          <p style="color:#5A7184;font-size:14px;margin-bottom:16px">
            Unable to load products right now.
          </p>
          <a href="${shopHref}" style="color:#006D77;font-weight:700;font-size:13px;
            text-decoration:none">Browse Shop →</a>
        </div>`;
    });
})();

/* ── FAQ ACCORDION ── */
window.toggleFaq = function(i){
  const item = document.getElementById('faq-' + i);
  if(!item) return;
  const isOpen = item.classList.contains('open');
  document.querySelectorAll('.faq-item.open').forEach(el=> el.classList.remove('open'));
  if(!isOpen) item.classList.add('open');
};

/* ── DONATE TAB SWITCH ── */
window.switchTab = function(name, btn){
  document.querySelectorAll('.tab-panel').forEach(p=> p.classList.remove('active'));
  document.querySelectorAll('.donate-tab').forEach(b=> b.classList.remove('active'));
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

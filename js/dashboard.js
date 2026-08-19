/* =====================================================
   ADHAAR – Unified Dashboard JS  v3.0
   Hamburger, toast, reveal, count-up, jar, steps, tabs
===================================================== */

/* ── Hamburger + Sidebar overlay ─────────────────── */
(function(){
  function initSidebar(){
    const hamburger = document.getElementById('hamburger');
    const sidebar   = document.getElementById('sidebar');
    const overlay   = document.getElementById('sidebarOverlay');
    if (!hamburger || !sidebar) return;

    function open(){
      sidebar.classList.add('open');
      if(overlay){ overlay.classList.add('show'); overlay.style.display='block'; }
      hamburger.classList.add('open');
      document.body.style.overflow='hidden';
    }
    function close(){
      sidebar.classList.remove('open');
      if(overlay){ overlay.classList.remove('show'); setTimeout(()=>overlay.style.display='',300); }
      hamburger.classList.remove('open');
      document.body.style.overflow='';
    }
    hamburger.addEventListener('click', ()=> sidebar.classList.contains('open') ? close() : open());
    if(overlay) overlay.addEventListener('click', close);

    // Close on nav-btn click (mobile)
    sidebar.querySelectorAll('.nav-btn,.nav-item,.nav-link').forEach(btn=>{
      btn.addEventListener('click', ()=>{ if(window.innerWidth<=900) close(); });
    });

    // Resize: auto-close if resized to desktop
    window.addEventListener('resize',()=>{ if(window.innerWidth>900) close(); });
  }
  if(document.readyState==='loading')
    document.addEventListener('DOMContentLoaded', initSidebar);
  else initSidebar();
})();

/* ── Toast system ────────────────────────────────── */
function showToast(msg, type='success', duration=3200){
  let wrap = document.getElementById('dashToast');
  if(!wrap){
    wrap = document.createElement('div');
    wrap.id = 'dashToast';
    document.body.appendChild(wrap);
  }
  const icons = {success:'✅', error:'❌', info:'ℹ️', warn:'⚠️'};
  const item = document.createElement('div');
  item.className = `toast-item ${type}`;
  item.innerHTML = `<span>${icons[type]||'💬'}</span><span>${msg}</span>`;
  wrap.appendChild(item);
  item.addEventListener('click', ()=> removeToast(item));
  setTimeout(()=> removeToast(item), duration);
}
function removeToast(el){
  if(!el || !el.parentNode) return;
  el.style.animation='toastOut .3s ease forwards';
  setTimeout(()=>{ if(el.parentNode) el.parentNode.removeChild(el); }, 300);
}
window.showToast = showToast;

/* ── Scroll reveal ───────────────────────────────── */
(function(){
  const els = document.querySelectorAll('.reveal');
  if(!els.length) return;
  const ro = new IntersectionObserver(entries=>{
    entries.forEach(e=>{
      if(e.isIntersecting){ e.target.classList.add('show','visible'); ro.unobserve(e.target); }
    });
  },{ threshold:0.12 });
  els.forEach(el=>ro.observe(el));
})();

/* ── Count-up ────────────────────────────────────── */
(function(){
  const els = document.querySelectorAll('[data-count]');
  if(!els.length) return;
  const co = new IntersectionObserver(entries=>{
    entries.forEach(e=>{
      if(!e.isIntersecting) return;
      const el = e.target;
      const target = parseInt(el.dataset.count, 10) || 0;
      const suffix = el.dataset.suffix ?? '+';
      if(target === 0){ el.textContent = '0' + suffix; co.unobserve(el); return; }
      let cur = 0;
      const step = Math.max(1, Math.ceil(target / 70));
      const t = setInterval(()=>{
        cur = Math.min(cur + step, target);
        el.textContent = cur.toLocaleString('en-IN') + (cur >= target ? suffix : '');
        if(cur >= target){ clearInterval(t); }
      }, 18);
      co.unobserve(el);
    });
  },{ threshold:0.5 });
  els.forEach(el=>co.observe(el));
})();

/* ── Jar fill ────────────────────────────────────── */
(function(){
  const jar = document.querySelector('.jar-liquid');
  if(!jar) return;
  const pct = parseFloat(jar.dataset.percent) || 0;
  setTimeout(()=>{ jar.style.height = Math.min(pct,100) + '%'; }, 700);
})();

/* ── Step reveal ─────────────────────────────────── */
(function(){
  const steps = document.querySelectorAll('.step');
  if(!steps.length) return;
  const so = new IntersectionObserver(entries=>{
    entries.forEach((e,i)=>{
      if(e.isIntersecting){
        setTimeout(()=>e.target.classList.add('show'), i * 80);
        so.unobserve(e.target);
      }
    });
  },{ threshold:0.2 });
  steps.forEach(s=>so.observe(s));
})();

/* ── Tab switching (generic) ─────────────────────── */
function switchTab(name, fnName){
  /* fnName = 'sw' (admin), 'goTab' (seller), 'openTab' (volunteer) */
  document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.nav-btn,.nav-item').forEach(b=>b.classList.remove('active'));
  const panel = document.getElementById('tab-' + name);
  if(panel){ panel.classList.add('active'); panel.scrollIntoView({behavior:'smooth',block:'nearest'}); }
  document.querySelectorAll('.nav-btn,.nav-item').forEach(b=>{
    const oc = b.getAttribute('onclick')||'';
    if(oc.includes("'"+name+"'") || oc.includes('"'+name+'"')) b.classList.add('active');
  });
  history.replaceState(null,'','?tab='+encodeURIComponent(name));
}

/* Expose all tab functions used by inline onclick="" */
window.sw      = (n,btn)=>{ switchTab(n); btn.classList.add('active'); };
window.goTab   = (n)=>switchTab(n);
window.openTab = (n)=>switchTab(n);

/* ── Donor dashboard hero greeting ──────────────── */
(function(){
  const el = document.getElementById('greetTime');
  if(!el) return;
  const h = new Date().getHours();
  el.textContent = h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
})();

/* ── Auto-mark active nav-btn based on URL tab ───── */
(function(){
  const tab = new URLSearchParams(window.location.search).get('tab');
  if(!tab) return;
  document.querySelectorAll('.nav-btn,.nav-item').forEach(b=>{
    const oc = b.getAttribute('onclick')||'';
    if(oc.includes("'"+tab+"'") || oc.includes('"'+tab+'"')) b.classList.add('active');
  });
})();

/* ── Confirm-before-submit on dangerous actions ──── */
document.addEventListener('click', e=>{
  const btn = e.target.closest('[data-confirm]');
  if(!btn) return;
  if(!confirm(btn.dataset.confirm)) e.preventDefault();
});

/* ── Form loading state ──────────────────────────── */
document.querySelectorAll('form.dash-form').forEach(f=>{
  f.addEventListener('submit', ()=>{
    const btn = f.querySelector('button[type="submit"]');
    if(btn && !btn.disabled){
      btn.disabled = true;
      btn.dataset.originalText = btn.textContent;
      btn.innerHTML = '<span style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite;vertical-align:middle;margin-right:6px"></span>Saving…';
    }
  });
});

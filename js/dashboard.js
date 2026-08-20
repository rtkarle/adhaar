/* =====================================================
   SoulServe – Unified Dashboard JS  v4.0
   Hamburger, toast, reveal, count-up, jar, steps, tabs
===================================================== */

/* ── Hamburger + Sidebar (FIXED – works on ALL pages) ── */
(function(){
  function initSidebar(){
    const hamburger = document.getElementById('hamburger');
    const sidebar   = document.getElementById('sidebar');
    const overlay   = document.getElementById('sidebarOverlay');
    if (!hamburger || !sidebar) return;

    function openSidebar(){
      sidebar.classList.add('open','show');
      if(overlay){ overlay.classList.add('show'); overlay.style.display='block'; }
      hamburger.setAttribute('aria-expanded','true');
      document.body.style.overflow='hidden';
      /* Animate hamburger → X */
      const spans = hamburger.querySelectorAll('span');
      if(spans.length>=3){
        spans[0].style.cssText='transform:translateY(7px) rotate(45deg)';
        spans[1].style.cssText='opacity:0;transform:scaleX(0)';
        spans[2].style.cssText='transform:translateY(-7px) rotate(-45deg)';
      }
    }
    function closeSidebar(){
      sidebar.classList.remove('open','show');
      if(overlay){ overlay.classList.remove('show'); setTimeout(()=>{ overlay.style.display=''; },300); }
      hamburger.setAttribute('aria-expanded','false');
      document.body.style.overflow='';
      const spans = hamburger.querySelectorAll('span');
      spans.forEach(s=>s.style.cssText='');
    }

    /* Use touchstart + click for best mobile response */
    ['click','touchstart'].forEach(evt=>{
      hamburger.addEventListener(evt, e=>{
        e.stopPropagation();
        e.preventDefault();
        sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
      }, {passive:false});
    });

    if(overlay){
      overlay.addEventListener('click', closeSidebar);
      overlay.addEventListener('touchstart', closeSidebar, {passive:true});
    }

    /* Close when any nav link clicked on mobile */
    sidebar.querySelectorAll('a,button').forEach(el=>{
      el.addEventListener('click', ()=>{ if(window.innerWidth<=900) closeSidebar(); });
    });

    /* Close on outside tap */
    document.addEventListener('touchstart', e=>{
      if(window.innerWidth<=900 && sidebar.classList.contains('open')
         && !sidebar.contains(e.target) && e.target!==hamburger){
        closeSidebar();
      }
    },{passive:true});

    window.addEventListener('resize',()=>{ if(window.innerWidth>900) closeSidebar(); });
  }

  if(document.readyState==='loading')
    document.addEventListener('DOMContentLoaded', initSidebar);
  else initSidebar();
})();

/* Also wire up mobile-topbar on all pages that use .header + .menu-icon */
(function(){
  function initNavMenu(){
    const toggle = document.getElementById('menuToggle');
    const nav    = document.getElementById('mobileMenu');
    if(!toggle || !nav) return;
    ['click','touchstart'].forEach(evt=>{
      toggle.addEventListener(evt, e=>{
        e.preventDefault();
        e.stopPropagation();
        const open = nav.classList.toggle('show');
        toggle.textContent = open ? '✕' : '☰';
      },{passive:false});
    });
    nav.querySelectorAll('.nav-link').forEach(l=>{
      l.addEventListener('click',()=>{ nav.classList.remove('show'); toggle.textContent='☰'; });
    });
    document.addEventListener('touchstart', e=>{
      if(nav.classList.contains('show') && !nav.contains(e.target) && e.target!==toggle)
        { nav.classList.remove('show'); toggle.textContent='☰'; }
    },{passive:true});
  }
  if(document.readyState==='loading')
    document.addEventListener('DOMContentLoaded', initNavMenu);
  else initNavMenu();
})();

/* ── Toast system ── */
function showToast(msg, type='success', duration=3500){
  let wrap = document.getElementById('dashToast');
  if(!wrap){
    wrap = document.createElement('div');
    wrap.id = 'dashToast';
    wrap.style.cssText='position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:10px;pointer-events:none';
    document.body.appendChild(wrap);
  }
  const colors={success:'#059669',error:'#dc2626',info:'#0284c7',warn:'#d97706'};
  const icons ={success:'✓',error:'✕',info:'ℹ',warn:'⚠'};
  const item = document.createElement('div');
  item.style.cssText=`display:flex;align-items:center;gap:10px;padding:13px 18px;border-radius:14px;background:${colors[type]||colors.info};color:#fff;font-size:14px;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.18);pointer-events:all;cursor:pointer;min-width:260px;max-width:360px;animation:toastIn .3s ease`;
  item.innerHTML=`<span style="font-size:16px;flex-shrink:0">${icons[type]||'ℹ'}</span><span style="flex:1">${msg}</span><span onclick="this.parentElement.remove()" style="flex-shrink:0;opacity:.7;font-size:12px">✕</span>`;
  wrap.appendChild(item);
  item.addEventListener('click',()=>item.remove());
  setTimeout(()=>{ if(item.parentElement){ item.style.animation='toastIn .3s ease reverse'; setTimeout(()=>item.remove(),280); } }, duration);
}
window.showToast = showToast;

/* ── Scroll reveal ── */
(function(){
  const els=document.querySelectorAll('.reveal');
  if(!els.length) return;
  const ro=new IntersectionObserver(entries=>{
    entries.forEach(e=>{
      if(e.isIntersecting){ e.target.classList.add('show','visible'); ro.unobserve(e.target); }
    });
  },{threshold:0.1,rootMargin:'0px 0px -40px 0px'});
  els.forEach(el=>ro.observe(el));
})();

/* ── Count-up animation ── */
(function(){
  const els=document.querySelectorAll('[data-count]');
  if(!els.length) return;
  const co=new IntersectionObserver(entries=>{
    entries.forEach(e=>{
      if(!e.isIntersecting) return;
      const el=e.target;
      const target=parseInt(el.dataset.count,10)||0;
      const suffix=el.dataset.suffix??'+';
      if(target===0){ el.textContent='0'+suffix; co.unobserve(el); return; }
      let cur=0;
      const step=Math.max(1,Math.ceil(target/70));
      const t=setInterval(()=>{
        cur=Math.min(cur+step,target);
        el.textContent=cur.toLocaleString('en-IN')+(cur>=target?suffix:'');
        if(cur>=target) clearInterval(t);
      },18);
      co.unobserve(el);
    });
  },{threshold:0.4});
  els.forEach(el=>co.observe(el));
})();

/* ── Jar fill ── */
(function(){
  const jar=document.querySelector('.jar-liquid');
  if(!jar) return;
  const pct=parseFloat(jar.dataset.percent)||0;
  setTimeout(()=>{ jar.style.height=Math.min(pct,100)+'%'; },700);
})();

/* ── Step reveal ── */
(function(){
  const steps=document.querySelectorAll('.step');
  if(!steps.length) return;
  const so=new IntersectionObserver(entries=>{
    entries.forEach((e,i)=>{
      if(e.isIntersecting){ setTimeout(()=>e.target.classList.add('show'),i*80); so.unobserve(e.target); }
    });
  },{threshold:0.15});
  steps.forEach(s=>so.observe(s));
})();

/* ── Donor greeting time ── */
(function(){
  const el=document.getElementById('greetTime');
  if(!el) return;
  const h=new Date().getHours();
  el.textContent=h<12?'Good morning':h<17?'Good afternoon':'Good evening';
})();

/* ── Generic tab switching ── */
function switchTab(name){
  document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('[data-tab]').forEach(b=>{
    b.classList.toggle('active', b.dataset.tab===name);
  });
  document.querySelectorAll('.nav-btn,.nav-item').forEach(b=>{
    const oc=b.getAttribute('onclick')||b.getAttribute('data-tab')||'';
    b.classList.toggle('active', oc.includes("'"+name+"'") || oc.includes('"'+name+'"') || oc===name);
  });
  const panel=document.getElementById('tab-'+name);
  if(panel){ panel.classList.add('active'); }
  history.replaceState(null,'','?tab='+encodeURIComponent(name));
}
window.sw      = (n,btn)=>{ switchTab(n); };
window.goTab   = n=>switchTab(n);
window.openTab = n=>switchTab(n);

/* Restore tab from URL on load */
(function(){
  const tab=new URLSearchParams(window.location.search).get('tab');
  if(tab) setTimeout(()=>switchTab(tab),0);
})();

/* ── Confirm dangerous actions ── */
document.addEventListener('click', e=>{
  const btn=e.target.closest('[data-confirm]');
  if(btn && !confirm(btn.dataset.confirm)) e.preventDefault();
});

/* ── Form loading state ── */
document.querySelectorAll('form.dash-form').forEach(f=>{
  f.addEventListener('submit',()=>{
    const btn=f.querySelector('button[type="submit"]');
    if(btn&&!btn.disabled){
      btn.disabled=true;
      btn.innerHTML='<span style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite;vertical-align:middle;margin-right:6px"></span>Saving…';
    }
  });
});

/* ── Lazy-load images ── */
(function(){
  if(!('IntersectionObserver' in window)) return;
  const imgs=document.querySelectorAll('img[data-src]');
  if(!imgs.length) return;
  const io=new IntersectionObserver(entries=>{
    entries.forEach(e=>{
      if(e.isIntersecting){
        const img=e.target;
        img.src=img.dataset.src;
        img.removeAttribute('data-src');
        io.unobserve(img);
      }
    });
  },{rootMargin:'200px'});
  imgs.forEach(img=>io.observe(img));
})();

/* ── Skeleton loader removal ── */
(function(){
  document.querySelectorAll('.skeleton-wrap').forEach(wrap=>{
    const target=wrap.dataset.target;
    if(target){
      const el=document.getElementById(target);
      if(el&&el.innerHTML.trim()!==''){
        wrap.style.display='none';
      }
    }
  });
})();

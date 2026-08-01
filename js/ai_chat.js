/**
 * Adhaar AI Chat Widget v1.0
 * Floating AI assistant that answers questions about the platform.
 * No external API needed — all rule-based with DB context from ai_stats.php.
 * Inject via: <script src="js/ai_chat.js"></script> (auto-initializes)
 */
(function () {
  'use strict';

  /* ── Determine base path (works from any subfolder depth) ── */
  const scripts = document.querySelectorAll('script[src*="ai_chat.js"]');
  let basePath = '';
  if (scripts.length) {
    const src = scripts[scripts.length - 1].getAttribute('src');
    basePath = src.replace(/js\/ai_chat\.js.*/, '');
  }

  /* ── Inject CSS ─────────────────────────────────────────── */
  const style = document.createElement('style');
  style.textContent = `
  #adhaar-chat-btn{
    position:fixed;bottom:28px;right:28px;z-index:10000;
    width:56px;height:56px;border-radius:50%;border:none;cursor:pointer;
    background:linear-gradient(135deg,#7a7d3f,#9a8f5c);
    box-shadow:0 8px 24px rgba(122,125,63,.5);
    display:flex;align-items:center;justify-content:center;
    font-size:1.4rem;transition:.3s;color:#fff;
    animation:chatPulse 3s ease-in-out infinite;
  }
  @keyframes chatPulse{0%,100%{box-shadow:0 8px 24px rgba(122,125,63,.5)}50%{box-shadow:0 12px 36px rgba(122,125,63,.75),0 0 0 8px rgba(122,125,63,.12)}}
  #adhaar-chat-btn:hover{transform:scale(1.1);}
  #adhaar-chat-window{
    position:fixed;bottom:96px;right:28px;z-index:9999;
    width:360px;max-height:520px;
    background:#fff;border-radius:20px;
    box-shadow:0 24px 64px rgba(0,0,0,.22);
    display:none;flex-direction:column;overflow:hidden;
    animation:chatSlideIn .3s cubic-bezier(.22,1,.36,1);
    font-family:'Inter',system-ui,sans-serif;
  }
  @keyframes chatSlideIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}
  #adhaar-chat-window.open{display:flex;}
  .acw-header{
    background:linear-gradient(135deg,#1e1d18,#2f2e26);
    padding:16px 18px;display:flex;align-items:center;gap:10px;flex-shrink:0;
  }
  .acw-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#7a7d3f,#9a8f5c);display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;}
  .acw-title{flex:1;}
  .acw-title h4{font-size:13px;font-weight:800;color:#fff;margin:0;}
  .acw-title span{font-size:10px;color:rgba(255,255,255,.5);display:flex;align-items:center;gap:4px}
  .acw-dot{width:7px;height:7px;border-radius:50%;background:#10b981;animation:chatPulse 1.5s infinite;}
  .acw-close{background:none;border:none;color:rgba(255,255,255,.5);font-size:18px;cursor:pointer;padding:4px;transition:.2s;border-radius:6px;}
  .acw-close:hover{background:rgba(255,255,255,.1);color:#fff}
  .acw-msgs{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px;scroll-behavior:smooth;}
  .acw-msgs::-webkit-scrollbar{width:4px;}
  .acw-msgs::-webkit-scrollbar-thumb{background:#e0ddd5;border-radius:4px;}
  .acw-msg{display:flex;gap:8px;align-items:flex-end;animation:msgIn .25s ease;}
  @keyframes msgIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
  .acw-msg.bot{flex-direction:row;}
  .acw-msg.user{flex-direction:row-reverse;}
  .acw-bubble{max-width:82%;padding:10px 13px;border-radius:16px;font-size:13px;line-height:1.6;}
  .acw-msg.bot .acw-bubble{background:#f6f5f0;color:#2f2e26;border-radius:4px 16px 16px 16px;}
  .acw-msg.user .acw-bubble{background:linear-gradient(135deg,#7a7d3f,#9a8f5c);color:#fff;border-radius:16px 4px 16px 16px;}
  .acw-msg-icon{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0;}
  .acw-msg.bot .acw-msg-icon{background:linear-gradient(135deg,#7a7d3f,#9a8f5c);color:#fff;}
  .acw-msg.user .acw-msg-icon{background:#e8e5d8;color:#5a594d;}
  .acw-quick-btns{padding:8px 16px;display:flex;gap:6px;flex-wrap:wrap;border-top:1px solid #f0ede4;}
  .acw-quick-btn{padding:6px 12px;border-radius:20px;border:1.5px solid #e0ddd5;background:#fff;font-size:11px;font-weight:600;color:#5a594d;cursor:pointer;transition:.2s;font-family:inherit;}
  .acw-quick-btn:hover{background:#7a7d3f;color:#fff;border-color:#7a7d3f;}
  .acw-input-row{display:flex;gap:0;border-top:2px solid #f0ede4;flex-shrink:0;}
  .acw-input{flex:1;padding:13px 16px;border:none;outline:none;font-size:13px;font-family:inherit;color:#2f2e26;background:#fff;}
  .acw-input::placeholder{color:#9a8f5c;}
  .acw-send{padding:0 18px;background:linear-gradient(135deg,#7a7d3f,#9a8f5c);color:#fff;border:none;cursor:pointer;font-size:1rem;transition:.2s;}
  .acw-send:hover{opacity:.88;}
  .acw-typing{display:flex;gap:4px;align-items:center;padding:8px 12px;background:#f6f5f0;border-radius:16px;width:fit-content;}
  .acw-typing span{width:7px;height:7px;border-radius:50%;background:#9a8f5c;animation:typingDot 1.2s infinite;}
  .acw-typing span:nth-child(2){animation-delay:.2s;}
  .acw-typing span:nth-child(3){animation-delay:.4s;}
  @keyframes typingDot{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-5px)}}
  @media(max-width:420px){#adhaar-chat-window{width:calc(100vw - 20px);right:10px;bottom:80px;}}
  `;
  document.head.appendChild(style);

  /* ── Build DOM ───────────────────────────────────────────── */
  const btn = document.createElement('button');
  btn.id = 'adhaar-chat-btn';
  btn.title = 'Ask Adhaar AI';
  btn.innerHTML = '🤖';
  document.body.appendChild(btn);

  const win = document.createElement('div');
  win.id = 'adhaar-chat-window';
  win.innerHTML = `
    <div class="acw-header">
      <div class="acw-avatar">🤖</div>
      <div class="acw-title">
        <h4>Adhaar AI Assistant</h4>
        <span><span class="acw-dot"></span> Online · Powered by AI</span>
      </div>
      <button class="acw-close" id="acwClose">✕</button>
    </div>
    <div class="acw-msgs" id="acwMsgs"></div>
    <div class="acw-quick-btns" id="acwQuick">
      <button class="acw-quick-btn" data-q="How do I donate?">🎁 How to donate?</button>
      <button class="acw-quick-btn" data-q="What is Adhaar?">🌿 What is Adhaar?</button>
      <button class="acw-quick-btn" data-q="How can I volunteer?">🤝 Volunteer</button>
      <button class="acw-quick-btn" data-q="What is the impact so far?">📊 Our impact</button>
      <button class="acw-quick-btn" data-q="How to sell on Adhaar Shop?">🛍️ Sell products</button>
    </div>
    <div class="acw-input-row">
      <input class="acw-input" id="acwInput" placeholder="Ask me anything about Adhaar…" maxlength="200" autocomplete="off">
      <button class="acw-send" id="acwSend">➤</button>
    </div>`;
  document.body.appendChild(win);

  /* ── Live stats cache ────────────────────────────────────── */
  let stats = {};
  fetch(basePath + 'api/ai_stats.php')
    .then(r => r.json())
    .then(d => { stats = d; })
    .catch(() => {});

  /* ── Knowledge base ──────────────────────────────────────── */
  function getAnswer(q) {
    q = q.toLowerCase().trim();

    /* Greetings */
    if (/^(hi|hello|hey|namaste|namaskar|hii)/.test(q))
      return '👋 Hello! I\'m the Adhaar AI Assistant. I can help you with donations, volunteering, selling on our shop, and understanding our impact. What would you like to know?';

    /* What is Adhaar */
    if (/what is adhaar|about adhaar|what does adhaar do|tell me about/.test(q))
      return '🌿 <strong>Adhaar – The SoulServe</strong> is an AI-powered platform that connects surplus food and unused clothing with communities in need.<br><br>We have 3 modules:<br>🎁 <strong>Donors</strong> — donate food/clothes<br>🤝 <strong>Volunteers</strong> — pick up and deliver<br>🏪 <strong>Sellers</strong> — sell handmade products<br><br>Every donation is verified, tracked, and delivered with dignity.';

    /* How to donate */
    if (/how.*donate|donate.*how|submit.*donation|make.*donation/.test(q))
      return '🎁 <strong>To donate:</strong><br>1. <a href="' + basePath + 'auth/register.php" style="color:#7a7d3f;font-weight:700">Create a free account</a> as a Donor<br>2. Login and go to your Dashboard<br>3. Click <strong>"🎁 Donate Now"</strong><br>4. Choose Food 🍱 or Clothing 👕<br>5. Fill the form with pickup address and photo<br>6. Submit — a volunteer will collect it from you!';

    /* Volunteer */
    if (/volunteer|how.*volunteer|join.*volunteer|become.*volunteer/.test(q))
      return '🤝 <strong>To volunteer:</strong><br>1. <a href="' + basePath + 'auth/register.php" style="color:#7a7d3f;font-weight:700">Register</a> and select <strong>Volunteer</strong><br>2. Fill your city/location details<br>3. Login to your Volunteer Dashboard<br>4. Accept pickup tasks assigned by admin or AI<br>5. Pick up donations and deliver them!<br><br>Our <strong>AI Auto-Assign</strong> matches you to nearby tasks automatically.';

    /* Sell on shop */
    if (/sell|shop|seller|artisan|handmade|product/.test(q))
      return '🛍️ <strong>To sell on Adhaar Shop:</strong><br>1. <a href="' + basePath + 'auth/register.php" style="color:#7a7d3f;font-weight:700">Register</a> as a <strong>Seller</strong><br>2. Set up your store (name, logo, description)<br>3. Add products with photos and prices<br>4. Buyers (donors + volunteers) can purchase directly<br>5. Admin processes your payment via UPI/Bank<br><br>Perfect for rural artisans, women entrepreneurs, and local craftspeople 🌿';

    /* Impact / stats */
    if (/impact|stats|how many|meals|clothes|volunteers|delivered/.test(q)) {
      const meals  = stats.meals_distributed  || '—';
      const cloth  = stats.clothing_delivered  || '—';
      const vols   = stats.active_volunteers   || '—';
      const rate   = stats.delivery_rate        || '—';
      const people = stats.people_fed           || '—';
      return `📊 <strong>Our Live Impact:</strong><br>
🍱 <strong>${meals}</strong> meals distributed<br>
👕 <strong>${cloth}</strong> clothing kits delivered<br>
🤝 <strong>${vols}</strong> active volunteers<br>
✅ <strong>${rate}%</strong> delivery success rate<br>
👥 AI predicts <strong>${people}</strong> people impacted<br><br>
<a href="${basePath}pages/impact.php" style="color:#7a7d3f;font-weight:700">View full AI impact report →</a>`;
    }

    /* Track donation */
    if (/track|status|where.*donation|my donation/.test(q))
      return '📍 <strong>To track your donation:</strong><br>Login → Donor Dashboard → Click <strong>"📍 Track Donations"</strong><br><br>You\'ll see a live status timeline: Submitted → Accepted → Scheduled → Picked Up → Delivered<br><br>The page auto-refreshes every 30 seconds with real-time updates.';

    /* Register / Login */
    if (/register|sign up|create account|how to join/.test(q))
      return '✍️ <strong>To join Adhaar:</strong><br>1. Visit <a href="' + basePath + 'auth/register.php" style="color:#7a7d3f;font-weight:700">Register page</a><br>2. Choose your role: Donor, Volunteer, or Seller<br>3. Verify your email with an OTP<br>4. You\'re ready to make an impact! 🌿';

    /* AI features */
    if (/ai|artificial intelligence|auto assign|smart|machine learning/.test(q))
      return '🤖 <strong>Adhaar AI Features:</strong><br>🎯 <strong>AI Auto-Assign</strong> — Scores all volunteers (0–100) and assigns the best one<br>🍱 <strong>Food Validity Check</strong> — Verifies food is safe before acceptance<br>📈 <strong>Demand Forecast</strong> — Predicts next week\'s donation volume<br>💚 <strong>Impact Prediction</strong> — Calculates CO₂ saved, people fed, economic value<br>💡 <strong>Smart Suggestions</strong> — Personalised recommendations for donors<br>📊 <strong>Live Analytics</strong> — Real-time admin insights and alerts';

    /* Contact */
    if (/contact|email|phone|reach|help/.test(q))
      return '📞 <strong>Contact Adhaar:</strong><br>📧 adhaarsoulserve@gmail.com<br>📞 +91 82379 17354<br>📍 Kopargaon, Maharashtra<br><br>Or use our <a href="' + basePath + 'pages/contact.html" style="color:#7a7d3f;font-weight:700">Contact Page</a> to send a message.';

    /* Thank you */
    if (/thank|thanks|great|awesome|good/.test(q))
      return '💚 You\'re welcome! Every small action counts. Ready to make a difference? <a href="' + basePath + 'auth/register.php" style="color:#7a7d3f;font-weight:700">Join Adhaar today →</a>';

    /* Default */
    return '🤖 I\'m not sure about that specific query, but I can help with:<br>• 🎁 How to donate food or clothing<br>• 🤝 How to volunteer<br>• 🛍️ How to sell on Adhaar Shop<br>• 📊 Our platform impact stats<br>• 📍 Tracking your donation<br><br>Try one of the quick buttons below, or ask in a different way!';
  }

  /* ── Message rendering ───────────────────────────────────── */
  const msgContainer = document.getElementById('acwMsgs');

  function addMsg(text, role) {
    const div = document.createElement('div');
    div.className = 'acw-msg ' + role;
    const icon = role === 'bot' ? '🤖' : '👤';
    div.innerHTML = `
      <div class="acw-msg-icon">${icon}</div>
      <div class="acw-bubble">${text}</div>`;
    msgContainer.appendChild(div);
    msgContainer.scrollTop = msgContainer.scrollHeight;
    return div;
  }

  function showTyping() {
    const div = document.createElement('div');
    div.className = 'acw-msg bot';
    div.id = 'acwTyping';
    div.innerHTML = `<div class="acw-msg-icon">🤖</div><div class="acw-typing"><span></span><span></span><span></span></div>`;
    msgContainer.appendChild(div);
    msgContainer.scrollTop = msgContainer.scrollHeight;
  }

  function removeTyping() {
    const t = document.getElementById('acwTyping');
    if (t) t.remove();
  }

  function ask(question) {
    if (!question.trim()) return;
    addMsg(question, 'user');
    document.getElementById('acwInput').value = '';
    // Hide quick buttons after first real interaction
    document.getElementById('acwQuick').style.display = 'none';
    showTyping();
    // Simulate thinking delay
    const delay = 500 + Math.random() * 600;
    setTimeout(() => {
      removeTyping();
      addMsg(getAnswer(question), 'bot');
    }, delay);
  }

  /* ── Events ──────────────────────────────────────────────── */
  btn.addEventListener('click', () => {
    win.classList.toggle('open');
    if (win.classList.contains('open') && msgContainer.children.length === 0) {
      // Welcome message
      addMsg('👋 Hi! I\'m the <strong>Adhaar AI Assistant</strong>.<br>I can answer questions about donations, volunteering, our shop, and our real-time impact.<br><br>What can I help you with today?', 'bot');
    }
    if (win.classList.contains('open')) {
      document.getElementById('acwInput').focus();
    }
  });

  document.getElementById('acwClose').addEventListener('click', () => {
    win.classList.remove('open');
  });

  document.getElementById('acwSend').addEventListener('click', () => {
    ask(document.getElementById('acwInput').value);
  });

  document.getElementById('acwInput').addEventListener('keydown', e => {
    if (e.key === 'Enter') ask(e.target.value);
  });

  document.querySelectorAll('.acw-quick-btn').forEach(b => {
    b.addEventListener('click', () => ask(b.getAttribute('data-q')));
  });

  /* ── Close on outside click ──────────────────────────────── */
  document.addEventListener('click', e => {
    if (!win.contains(e.target) && e.target !== btn) {
      win.classList.remove('open');
    }
  });

})();

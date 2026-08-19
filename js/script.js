/* ================= MOBILE MENU ================= */
const menuToggle = document.getElementById("menuToggle");
const nav = document.getElementById("mobileMenu");

if (menuToggle && nav) {
  menuToggle.addEventListener("click", () => {
    nav.classList.toggle("show");
  });
}

/* ================= STORY SLIDER DATA & BUILD ================= */
const storyData = [
  { img: "🍱", tag: "Food Rescue",    title: "A Meal That Restored Hope",    text: "A single donation from a local event fed a family of five that hadn't eaten properly in two days — delivered within hours, with dignity intact." },
  { img: "👕", tag: "Clothing Drive", title: "Warm Clothes Before Winter",   text: "Donated clothes reached children in a rural area just before the cold season. Timing and care made all the difference." },
  { img: "🤝", tag: "Volunteer Story",title: "Volunteers on the Ground",     text: "Our volunteers don't just deliver supplies — they build trust, empathy, and lasting human connections in every community." },
  { img: "🌱", tag: "Sustainability", title: "Zero Waste, Real Impact",      text: "What would have been thrown away became someone's lifeline. Responsible redistribution is at the heart of everything we do." },
  { img: "🏘️", tag: "Community",     title: "Reaching the Unreached",       text: "We expanded to 3 new areas in 2026, connecting with communities that had never received organised support before." },
  { img: "🛍️", tag: "Empowerment",   title: "Rural Artisans Now Selling",   text: "Adhaar Shop connected a women's self-help group in Kopargaon to buyers across Maharashtra — income, not charity." },
];

const sliderEl = document.getElementById("storySlider");
if (sliderEl) {
  sliderEl.innerHTML = storyData.map(s => `
    <div class="story-card">
      <div style="width:100%;height:170px;background:linear-gradient(135deg,#f0ede5,#e8e4d8);display:flex;align-items:center;justify-content:center;font-size:56px">${s.img}</div>
      <div class="story-desc"><strong style="display:block;font-size:11px;letter-spacing:2px;color:#7a7d3f;text-transform:uppercase;margin-bottom:8px">${s.tag}</strong><strong style="display:block;font-size:15px;color:#2f2e26;margin-bottom:8px">${s.title}</strong>${s.text}</div>
    </div>`).join("");

  const prev = document.querySelector(".story-prev");
  const next = document.querySelector(".story-next");
  if (prev) prev.addEventListener("click", () => { sliderEl.scrollBy({ left: -300, behavior: "smooth" }); });
  if (next) next.addEventListener("click", () => { sliderEl.scrollBy({ left:  300, behavior: "smooth" }); });

  // Auto-scroll
  let autoSlide = setInterval(() => { sliderEl.scrollBy({ left: 280, behavior: "smooth" }); }, 4000);
  sliderEl.addEventListener("mouseover",  () => clearInterval(autoSlide));
  sliderEl.addEventListener("mouseleave", () => { autoSlide = setInterval(() => sliderEl.scrollBy({ left: 280, behavior: "smooth" }), 4000); });
}

/* ================= SCROLL REVEAL ================= */
const revealElements = document.querySelectorAll(".reveal");

const revealObserver = new IntersectionObserver(
  entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add("show","visible","active");
        revealObserver.unobserve(entry.target);
      }
    });
  },
  { threshold: 0.15 }
);

revealElements.forEach(el => revealObserver.observe(el));

/* ================= COUNT-UP IMPACT ================= */
const counters = document.querySelectorAll(".impact-card h3");

const countObserver = new IntersectionObserver(
  entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const el = entry.target;
        const target = parseInt(el.getAttribute("data-count")); // SAFE
        let count = 0;
        const step = Math.max(1, Math.floor(target / 80));

        const timer = setInterval(() => {
          count += step;
          if (count >= target) {
            el.innerText = target + "+";
            clearInterval(timer);
          } else {
            el.innerText = count;
          }
        }, 20);

        countObserver.unobserve(el);
      }
    });
  },
  { threshold: 0.6 }
);

counters.forEach(c => countObserver.observe(c));
/* ================= DONATE SECTION (index.html only) ================= */
const donateTop    = document.querySelector(".donate-top");
const donateChoice = document.getElementById("donateChoice");
const foodForm     = document.getElementById("foodForm");
const clothForm    = document.getElementById("clothForm");

/* Only wire up if these elements actually exist on the current page */
if (donateTop && donateChoice && foodForm && clothForm) {
  function openChoice() {
    donateTop.classList.add("hide");
    donateChoice.classList.remove("hidden");
    foodForm.classList.add("hidden");
    clothForm.classList.add("hidden");
  }

  function openFood() {
    donateChoice.classList.add("hidden");
    foodForm.classList.remove("hidden");
    clothForm.classList.add("hidden");
  }

  function openCloth() {
    donateChoice.classList.add("hidden");
    foodForm.classList.add("hidden");
    clothForm.classList.remove("hidden");
  }

  /* Expose globally so inline onclick="" attributes work */
  window.openChoice = openChoice;
  window.openFood   = openFood;
  window.openCloth  = openCloth;

  /* Initial state */
  document.addEventListener("DOMContentLoaded", () => {
    donateTop.classList.remove("hide");
    donateChoice.classList.add("hidden");
    foodForm.classList.add("hidden");
    clothForm.classList.add("hidden");
  });
}

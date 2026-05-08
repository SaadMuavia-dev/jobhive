// ══════════════════════════════════════════════════════════════
//  JobHive — Main JS  (PHP API edition)
//  All UI/visual code is UNCHANGED.
//  Only auth + apply + contact now talk to the PHP backend.
// ══════════════════════════════════════════════════════════════

const API = {
  auth:    'api/auth.php',
  apply:   'api/apply.php',
  jobs:    'api/jobs.php',
  profile: 'api/profile.php',
  contact: 'api/contact.php',
};

/* ── TOAST ── */
function showToast(msg, type = 'success') {
  const container = document.getElementById('toast-container');
  const div = document.createElement('div');
  div.className = `toast-msg ${type}`;
  div.innerHTML = `<span>${msg}</span>`;
  container.appendChild(div);
  setTimeout(() => {
    div.style.animation = 'slideOut .3s ease forwards';
    setTimeout(() => div.remove(), 300);
  }, 3200);
}

/* ── API helper ── */
async function apiPost(url, data) {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
    body: JSON.stringify(data),
  });
  return res.json();
}

async function apiGet(url) {
  const res = await fetch(url, { credentials: 'include' });
  return res.json();
}

/* ── AUTH STATE (session-based, check server) ── */
let _currentUser = null;          // cached after /me

async function fetchCurrentUser() {
  try {
    const r = await apiGet(API.auth + '?action=me');
    _currentUser = r.success ? r.data.user : null;
  } catch {
    _currentUser = null;
  }
  return _currentUser;
}

function getCurrentUser() { return _currentUser; }

/* ── NAVBAR STATE ── */
function updateNavbar() {
  const user     = getCurrentUser();
  const authBtns = document.getElementById('auth-btns');
  const userMenu = document.getElementById('user-menu');
  const userNameEl = document.getElementById('nav-username');
  if (!authBtns || !userMenu) return;
  if (user) {
    authBtns.classList.add('d-none');
    authBtns.style.display = 'none';
    userMenu.classList.remove('d-none');
    userMenu.style.display = 'flex';
    if (userNameEl) userNameEl.textContent = user.name.split(' ')[0];
    injectPostJobLink(user);
  } else {
    authBtns.classList.remove('d-none');
    authBtns.style.display = 'flex';
    userMenu.classList.add('d-none');
    userMenu.style.display = 'none';
    removePostJobLink();
  }
}

/* ── LOGIN ── */
async function handleLogin(e) {
  e.preventDefault();
  const btn = document.getElementById('login-btn');
  btn.disabled = true; btn.textContent = 'Signing In…';

  const fd       = new FormData(e.target);
  const email    = fd.get('email').trim().toLowerCase();
  const password = fd.get('password');

  const r = await apiPost(API.auth + '?action=login', { email, password });

  if (r.success) {
    _currentUser = r.data.user;
    showToast('Welcome back, ' + _currentUser.name.split(' ')[0] + '! 🎉');
    const modal = bootstrap.Modal.getInstance(document.getElementById('authModal'));
    if (modal) modal.hide();
    updateNavbar();
    e.target.reset();
  } else {
    showToast(r.error || 'Invalid email or password.', 'error');
  }
  btn.disabled = false; btn.textContent = 'Login';
}

/* ── SIGNUP ── */
async function handleSignup(e) {
  e.preventDefault();
  const btn     = document.getElementById('signup-btn');
  const pass    = document.getElementById('signup-password').value;
  const confirm = document.getElementById('signup-confirm').value;

  if (pass !== confirm) { showToast('Passwords do not match.', 'error'); return; }
  if (pass.length < 6)  { showToast('Password must be at least 6 characters.', 'error'); return; }

  const fd = new FormData(e.target);
  btn.disabled = true; btn.textContent = 'Creating Account…';

  const r = await apiPost(API.auth + '?action=register', {
    name:     fd.get('name').trim(),
    email:    fd.get('email').trim().toLowerCase(),
    password: pass,
    city:     fd.get('city').trim(),
    country:  fd.get('country').trim(),
  });

  if (r.success) {
    showToast('Account created successfully! Please sign in to continue. 🎉');
    document.getElementById('login-tab').click();
    e.target.reset();
  } else {
    showToast(r.error || 'Registration failed.', 'error');
  }
  btn.disabled = false; btn.textContent = 'Create Account';
}

/* ── LOGOUT ── */
async function doLogout() {
  await apiPost(API.auth + '?action=logout', {});
  _currentUser = null;
  showToast('You have been signed out successfully.');
  updateNavbar();
}

/* ── REQUIRE LOGIN ── */
function requireLogin(msg) {
  if (!_currentUser) {
    showToast(msg || 'Please sign in to continue.', 'error');
    setTimeout(() => {
      const modal = new bootstrap.Modal(document.getElementById('authModal'));
      modal.show();
      setTimeout(() => document.getElementById('login-tab').click(), 200);
    }, 500);
    return false;
  }
  return true;
}

/* ── APPLY JOB MODAL ── */
function applyJob(title, jobId = null) {
  if (!requireLogin('Please sign in to apply for this position.')) return;
  const user  = getCurrentUser();
  const modal = document.getElementById('applyModal');
  if (!modal) return;

  document.getElementById('apply-form-wrap').style.display  = 'block';
  document.getElementById('apply-form-wrap').style.animation = '';
  document.getElementById('apply-success-wrap').style.display = 'none';
  document.getElementById('apply-form').reset();
  document.getElementById('apply-job-title').textContent  = title;
  document.getElementById('apply-hidden-title').value     = title;

  // Store job_id on a hidden field if available
  let jobIdField = document.getElementById('apply-hidden-job-id');
  if (!jobIdField) {
    jobIdField = document.createElement('input');
    jobIdField.type = 'hidden';
    jobIdField.id   = 'apply-hidden-job-id';
    jobIdField.name = 'job_id';
    document.getElementById('apply-form').appendChild(jobIdField);
  }
  jobIdField.value = jobId || '';

  const cityEl    = document.getElementById('apply-city');
  const countryEl = document.getElementById('apply-country');
  if (cityEl    && user.city)    cityEl.value    = user.city;
  if (countryEl && user.country) countryEl.value = user.country;

  new bootstrap.Modal(modal).show();
}

async function handleApply(e) {
  e.preventDefault();

  // ── VALIDATION ──
  const fd = new FormData(e.target);
  const degree     = (fd.get('degree')     || '').trim();
  const experience = (fd.get('experience') || '').trim();
  const age        = (fd.get('age')        || '').trim();
  const gender     = (fd.get('gender')     || '').trim();
  const city       = (fd.get('city')       || '').trim();
  const country    = (fd.get('country')    || '').trim();

  if (!degree) {
    showToast('Please select your highest degree.', 'error');
    document.querySelector('#apply-form select[name="degree"]')?.focus();
    return;
  }
  if (!experience) {
    showToast('Please select your years of experience.', 'error');
    document.querySelector('#apply-form select[name="experience"]')?.focus();
    return;
  }
  if (!age || isNaN(age) || parseInt(age) < 16 || parseInt(age) > 70) {
    showToast('Please enter a valid age (16–70).', 'error');
    document.querySelector('#apply-form input[name="age"]')?.focus();
    return;
  }
  if (!gender) {
    showToast('Please select your gender.', 'error');
    document.querySelector('#apply-form select[name="gender"]')?.focus();
    return;
  }
  if (!city) {
    showToast('Please enter your city.', 'error');
    document.querySelector('#apply-form input[name="city"]')?.focus();
    return;
  }
  if (!country) {
    showToast('Please select your country.', 'error');
    document.querySelector('#apply-form select[name="country"]')?.focus();
    return;
  }

  const btn = document.getElementById('apply-submit-btn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting Application…';

  const payload = {
    job_id:       fd.get('job_id')       || null,
    job_title:    fd.get('job_title')    || '',
    degree,
    experience,
    age,
    gender,
    city,
    country,
    cover_letter: fd.get('cover_letter') || '',
  };

  const r = await apiPost(API.apply, payload);

  if (r.success) {
    const formDiv    = document.getElementById('apply-form-wrap');
    const successDiv = document.getElementById('apply-success-wrap');
    formDiv.style.animation = 'fadeOut .3s ease forwards';
    setTimeout(() => {
      formDiv.style.display    = 'none';
      successDiv.style.display = 'flex';
    }, 300);
  } else {
    showToast(r.error || 'Unable to submit your application. Please try again.', 'error');
  }
  btn.disabled = false;
  btn.innerHTML = 'Submit Application';
}

/* ── CONTACT FORM ── */
function initContactForm() {
  const form = document.getElementById('contact-form');
  if (!form) return;
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!requireLogin('Please sign in to send us a message.')) return;
    const fd = new FormData(form);
    const r  = await apiPost(API.contact, {
      name:    fd.get('name'),
      email:   fd.get('email'),
      subject: fd.get('subject') || '',
      message: fd.get('message'),
    });
    if (r.success) {
      showToast("Your message has been sent successfully. We will get back to you shortly. ✉️");
      form.reset();
    } else {
      showToast(r.error || 'Unable to send your message. Please try again.', 'error');
    }
  });
}

/* ── HERO SEARCH ── */
function initHeroSearch() {
  const form = document.getElementById('hero-search-form');
  if (!form) return;
  form.addEventListener('submit', e => {
    e.preventDefault();
    const q = document.getElementById('hero-search-input').value;
    window.location.href = 'jobs.html?q=' + encodeURIComponent(q);
  });
}

/* ── JOB FILTER ── */
function initJobFilter() {
  const searchInput = document.getElementById('job-search');
  if (!searchInput) return;
  searchInput.addEventListener('input', () => {
    if (typeof filterJobs === 'function') filterJobs();
  });
}

/* ── PRELOAD SEARCH ── */
function preloadSearch() {
  const params = new URLSearchParams(window.location.search);
  const q = params.get('q');
  if (q) {
    const input = document.getElementById('job-search');
    if (input) { input.value = q; input.dispatchEvent(new Event('input')); }
  }
}

/* ── PARTICLE CANVAS ── (unchanged) */
function initParticles() {
  const canvas = document.getElementById('hero-canvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  function resize() { canvas.width = canvas.offsetWidth; canvas.height = canvas.offsetHeight; }
  resize();
  window.addEventListener('resize', resize);
  const DOTS = Array.from({ length: 55 }, () => ({
    x: Math.random() * canvas.width, y: Math.random() * canvas.height,
    vx: (Math.random() - 0.5) * 0.5, vy: (Math.random() - 0.5) * 0.5,
    r: Math.random() * 2 + 1
  }));
  function draw() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    DOTS.forEach(d => {
      d.x += d.vx; d.y += d.vy;
      if (d.x < 0 || d.x > canvas.width)  d.vx *= -1;
      if (d.y < 0 || d.y > canvas.height) d.vy *= -1;
      ctx.beginPath(); ctx.arc(d.x, d.y, d.r, 0, Math.PI * 2);
      ctx.fillStyle = 'rgba(247,183,49,0.55)'; ctx.fill();
    });
    for (let i = 0; i < DOTS.length; i++) {
      for (let j = i + 1; j < DOTS.length; j++) {
        const dx = DOTS[i].x - DOTS[j].x, dy = DOTS[i].y - DOTS[j].y;
        const dist = Math.sqrt(dx*dx + dy*dy);
        if (dist < 110) {
          ctx.beginPath(); ctx.moveTo(DOTS[i].x, DOTS[i].y); ctx.lineTo(DOTS[j].x, DOTS[j].y);
          ctx.strokeStyle = 'rgba(247,183,49,' + (0.18 * (1 - dist / 110)) + ')';
          ctx.lineWidth = 0.8; ctx.stroke();
        }
      }
    }
    requestAnimationFrame(draw);
  }
  draw();
}

/* ── COUNTER ANIMATION ── (unchanged) */
function animateCounters() {
  document.querySelectorAll('.stat strong[data-target]').forEach(el => {
    const target = parseInt(el.dataset.target);
    const suffix = el.dataset.suffix || '';
    let current  = 0;
    const step   = target / 60;
    const timer  = setInterval(() => {
      current += step;
      if (current >= target) { current = target; clearInterval(timer); }
      el.textContent = Math.floor(current).toLocaleString() + suffix;
    }, 20);
  });
}

/* ── SCROLL REVEAL ── (unchanged) */
function initScrollReveal() {
  const els = document.querySelectorAll('.reveal');
  const obs = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) { entry.target.classList.add('revealed'); obs.unobserve(entry.target); }
    });
  }, { threshold: 0.1 });
  els.forEach(el => obs.observe(el));
}

/* ── TILT EFFECT ── (unchanged) */
function initTilt() {
  document.querySelectorAll('.job-card, .cat-card').forEach(card => {
    card.addEventListener('mousemove', e => {
      const rect = card.getBoundingClientRect();
      const x = e.clientX - rect.left - rect.width / 2;
      const y = e.clientY - rect.top  - rect.height / 2;
      card.style.transform = 'perspective(600px) rotateY('+(x/25)+'deg) rotateX('+(-y/25)+'deg) translateY(-6px)';
    });
    card.addEventListener('mouseleave', () => {
      card.style.transition = 'transform .5s ease';
      card.style.transform  = '';
      setTimeout(() => card.style.transition = '', 500);
    });
  });
}

/* ── TYPING EFFECT ── (unchanged) */
function initTyping() {
  const el = document.getElementById('hero-typing');
  if (!el) return;
  const words = ['Dream Job', 'Perfect Career', 'Next Opportunity', 'Future Role'];
  let wi = 0, ci = 0, deleting = false;
  function tick() {
    const word = words[wi];
    el.textContent = deleting ? word.substring(0, ci--) : word.substring(0, ci++);
    if (!deleting && ci > word.length) { deleting = true; setTimeout(tick, 1200); return; }
    if (deleting && ci < 0)           { deleting = false; wi = (wi + 1) % words.length; ci = 0; }
    setTimeout(tick, deleting ? 60 : 110);
  }
  tick();
}

/* ── INIT ── */
document.addEventListener('DOMContentLoaded', async () => {
  // Restore session from server (replaces localStorage check)
  await fetchCurrentUser();
  updateNavbar();

  initHeroSearch();
  initContactForm();
  initJobFilter();
  preloadSearch();
  initParticles();
  initScrollReveal();
  initTyping();
  setTimeout(initTilt, 300);

  const heroStats = document.querySelector('.hero-stats');
  if (heroStats) {
    const obs = new IntersectionObserver(entries => {
      if (entries[0].isIntersecting) { animateCounters(); obs.disconnect(); }
    }, { threshold: 0.5 });
    obs.observe(heroStats);
  }

  document.getElementById('login-form')?.addEventListener('submit', handleLogin);
  document.getElementById('signup-form')?.addEventListener('submit', handleSignup);
  document.getElementById('logout-btn')?.addEventListener('click', doLogout);
  document.getElementById('apply-form')?.addEventListener('submit', handleApply);

  document.getElementById('hero-signup-btn')?.addEventListener('click', () => {
    new bootstrap.Modal(document.getElementById('authModal')).show();
    setTimeout(() => document.getElementById('signup-tab').click(), 200);
  });
  document.getElementById('apply-close-btn')?.addEventListener('click', () => {
    const modal = bootstrap.Modal.getInstance(document.getElementById('applyModal'));
    if (modal) modal.hide();
  });
});

/* ── PROFILE MODAL ── */
async function openProfileModal() {
  if (!requireLogin('Please sign in to view your profile.')) return;
  const modal = document.getElementById('profileModal');
  if (!modal) return;

  const body = document.getElementById('profile-modal-body');
  body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2 text-muted">Loading your profile…</p></div>';
  new bootstrap.Modal(modal).show();

  try {
    const r = await apiGet(API.profile);
    if (!r.success) { body.innerHTML = '<p class="text-danger text-center py-4">Unable to load profile. Please try again.</p>'; return; }
    const p = r.data.profile;
    body.innerHTML = `
      <form id="profile-edit-form">
        <div class="row g-3">
          <div class="col-12 text-center mb-2">
            <div style="width:72px;height:72px;border-radius:50%;background:var(--primary);color:#fff;font-size:2rem;display:flex;align-items:center;justify-content:center;margin:0 auto;font-family:var(--font-head);font-weight:800">${(p.name||'U')[0].toUpperCase()}</div>
            <p class="text-muted mt-2 mb-0" style="font-size:.82rem">Member since ${new Date(p.created_at).getFullYear()}</p>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Full Name</label>
            <input type="text" class="form-control" name="name" value="${p.name||''}" required />
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Email Address</label>
            <input type="email" class="form-control" value="${p.email||''}" disabled />
            <small class="text-muted">Email address cannot be changed.</small>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">City</label>
            <input type="text" class="form-control" name="city" value="${p.city||''}" />
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Country</label>
            <input type="text" class="form-control" name="country" value="${p.country||''}" />
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Phone Number</label>
            <input type="text" class="form-control" name="phone" value="${p.phone||''}" placeholder="+92 300 0000000" />
          </div>
          <div class="col-12 mt-2">
            <button type="submit" class="btn-primary-custom w-100" id="profile-save-btn">Save Changes</button>
          </div>
        </div>
      </form>`;
    document.getElementById('profile-edit-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = document.getElementById('profile-save-btn');
      btn.disabled = true; btn.textContent = 'Saving…';
      const fd = new FormData(e.target);
      const r2 = await apiPost(API.profile + '?section=basic', {
        name: fd.get('name'), city: fd.get('city'), country: fd.get('country'), phone: fd.get('phone')
      });
      if (r2.success) {
        _currentUser.name = fd.get('name');
        updateNavbar();
        showToast('Your profile has been updated successfully.');
        bootstrap.Modal.getInstance(modal).hide();
      } else {
        showToast(r2.error || 'Unable to save changes. Please try again.', 'error');
      }
      btn.disabled = false; btn.textContent = 'Save Changes';
    });
  } catch {
    body.innerHTML = '<p class="text-danger text-center py-4">An unexpected error occurred. Please try again.</p>';
  }
}

/* ── SAVED JOBS (Applied Jobs) MODAL ── */
async function openSavedJobsModal() {
  if (!requireLogin('Please sign in to view your applied jobs.')) return;
  const modal = document.getElementById('savedJobsModal');
  if (!modal) return;

  const body = document.getElementById('saved-jobs-body');
  body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2 text-muted">Fetching your applications…</p></div>';
  new bootstrap.Modal(modal).show();

  try {
    const r = await apiGet(API.apply);
    if (!r.success) { body.innerHTML = '<p class="text-danger text-center py-4">Unable to load your applications. Please try again.</p>'; return; }
    const apps = r.data.applications;
    if (!apps.length) {
      body.innerHTML = '<div class="text-center py-5"><div style="font-size:3rem">📋</div><h6 class="mt-3" style="font-family:var(--font-head);color:var(--primary)">No Applications Yet</h6><p class="text-muted">You have not applied to any jobs yet. Browse available openings and submit your first application today.</p><a href="jobs.html" class="btn btn-sm" style="background:var(--primary);color:#fff;border-radius:8px;font-weight:600">Browse Jobs</a></div>';
      return;
    }
    const statusBadge = s => ({
      pending:  '<span class="badge" style="background:rgba(247,183,49,.2);color:#b07d00;font-weight:600">Under Review</span>',
      reviewed: '<span class="badge" style="background:rgba(10,35,66,.1);color:var(--primary);font-weight:600">Reviewed</span>',
      shortlisted: '<span class="badge" style="background:rgba(46,204,113,.15);color:#1a8a4a;font-weight:600">Shortlisted</span>',
      rejected: '<span class="badge" style="background:rgba(255,71,87,.12);color:#c0392b;font-weight:600">Not Selected</span>',
    }[s] || '<span class="badge bg-secondary">Unknown</span>');
    body.innerHTML = apps.map(a => `
      <div class="d-flex gap-3 align-items-start p-3 mb-2" style="background:var(--light-bg);border-radius:10px">
        <div style="width:44px;height:44px;border-radius:10px;background:var(--primary);color:#fff;font-size:1.3rem;display:flex;align-items:center;justify-content:center;flex-shrink:0">💼</div>
        <div class="flex-grow-1">
          <div style="font-family:var(--font-head);font-weight:700;color:var(--primary)">${a.job_title||'Position'}</div>
          <div class="text-muted" style="font-size:.85rem">${a.company||'Company'}</div>
          <div class="d-flex gap-2 align-items-center mt-1 flex-wrap">
            ${statusBadge(a.status)}
            <span class="text-muted" style="font-size:.78rem"><i class="bi bi-calendar3 me-1"></i>Applied ${new Date(a.applied_at).toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'})}</span>
          </div>
        </div>
      </div>`).join('');
  } catch {
    body.innerHTML = '<p class="text-danger text-center py-4">An unexpected error occurred. Please try again.</p>';
  }
}

/* ── POST JOB LINK in navbar (visible to authenticated users only) ── */
function injectPostJobLink(user) {
  // Static nav-post-job-li (jobs.html, about.html etc.)
  const staticLi = document.getElementById('nav-post-job-li');
  if (staticLi) { staticLi.style.display = user ? '' : 'none'; }

  if (!user) return;
  // Dynamic injection for index.html (which doesn't have static li)
  const navUl = document.querySelector('#navMenu .navbar-nav');
  if (!navUl || navUl.querySelector('.post-job-link') || staticLi) return;
  const li = document.createElement('li');
  li.className = 'nav-item';
  li.innerHTML = `<a class="nav-link post-job-link" href="post-job.html" style="color:var(--accent);font-weight:700"><i class="bi bi-plus-circle me-1"></i>Post a Job</a>`;
  // Append at end (after Contact)
  navUl.appendChild(li);
}

function removePostJobLink() {
  const staticLi = document.getElementById('nav-post-job-li');
  if (staticLi) staticLi.style.display = 'none';
  document.querySelector('.post-job-link')?.closest('.nav-item')?.remove();
}



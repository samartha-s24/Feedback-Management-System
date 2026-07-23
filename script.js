const navLinks = document.querySelectorAll('.nav-link');
const sections = document.querySelectorAll('.page-section');
const root = document.documentElement;
const themeToggle = document.getElementById('themeToggle');
const notifBell = document.getElementById('notifBell');
const logoutBtn = document.getElementById('logoutBtn');
const saveSettingsBtn = document.getElementById('saveSettingsBtn');

const siteData = {
  dashboard: {
    pageTitle: 'Welcome to Zeal',
    pageSubtitle: 'Staff Dashboard',
    pageDescription: 'Keep your staff feedback on track with the latest pending forms, announcements, and priority notifications.',
    pendingForms: 1,
    formsSubmitted: 6,
    announcements: 3,
    priorityTitle: 'Test 3 draft',
    priorityDue: 'Due: 26 Jul 2026',
    priorityMessage: 'This is your highest-priority task to review and complete.',
    deadlines: [
      'Midterm Survey - due 26 Jul 2026',
      'Project Review - due 02 Aug 2026',
      'Course Evaluation - due 05 Aug 2026'
    ],
    adminNotification: 'Admin assigned a new feedback form. Please review and submit by the due date.'
  },
  profile: {
    name: 'Alex Carter',
    title: 'Staff ID: STAF001 • Operations',
    email: 'alex.carter@zeal.edu',
    phone: '+123 456 7890',
    department: 'Operations',
    pendingFeedback: 1
  },
  forms: [
    { course: 'Advanced AI', form: 'Midterm Survey', due: '26 Jul 2026' },
    { course: 'Database Systems', form: 'Project Review', due: '02 Aug 2026' },
    { course: 'Software Engineering', form: 'Course Evaluation', due: '05 Aug 2026' }
  ],
  history: [
    { course: 'Advanced AI', form: 'Pre-final Feedback', status: 'Submitted', submitted: '20 Jul 2026' },
    { course: 'Database Systems', form: 'Lab Feedback', status: 'Submitted', submitted: '18 Jul 2026' },
    { course: 'Software Engineering', form: 'Assignment Review', status: 'Submitted', submitted: '16 Jul 2026' }
  ],
  announcements: [
    { title: 'New feedback form available', body: 'A staff feedback form has been released for review and completion soon.' },
    { title: 'Policy update', body: 'Review the updated feedback submission guidelines before the next team check-in.' },
    { title: 'System maintenance', body: 'The portal will be unavailable on 28 July from 10PM to 12AM.' }
  ]
};

function showToast(message) {
  const toastContainer = document.getElementById('toastContainer');
  const toast = document.createElement('div');
  toast.className = 'rounded-2xl bg-slate-900 px-5 py-4 text-sm text-slate-100 shadow-xl border border-slate-700';
  toast.textContent = message;
  toastContainer.appendChild(toast);
  setTimeout(() => toast.remove(), 3200);
}

function setActivePage(page) {
  navLinks.forEach((link) => {
    const active = link.dataset.page === page;
    link.classList.toggle('bg-cyan-500/15', active);
    link.classList.toggle('text-cyan-300', active);
    link.classList.toggle('hover:bg-slate-950', !active);
  });

  sections.forEach((section) => {
    section.classList.toggle('hidden', section.id !== page);
  });

  document.getElementById('pageTitle').textContent = siteData.dashboard.pageTitle;
  document.getElementById('pageSubtitle').textContent = siteData.dashboard.pageSubtitle;
  document.getElementById('pageDescription').textContent = siteData.dashboard.pageDescription;

  if (page === 'dashboard') {
    document.getElementById('pendingFormsCount').textContent = siteData.dashboard.pendingForms;
    document.getElementById('formsSubmittedCount').textContent = siteData.dashboard.formsSubmitted;
    document.getElementById('announcementCount').textContent = siteData.dashboard.announcements;
    document.getElementById('priorityTitle').textContent = siteData.dashboard.priorityTitle;
    document.getElementById('priorityDue').textContent = siteData.dashboard.priorityDue;
    document.getElementById('priorityMessage').textContent = siteData.dashboard.priorityMessage;
    const deadlinesList = document.getElementById('deadlinesList');
    deadlinesList.innerHTML = siteData.dashboard.deadlines.map((deadline) => `<li>${deadline}</li>`).join('');
    const adminNotificationBar = document.getElementById('adminNotificationBar');
    const adminNotificationText = document.getElementById('adminNotificationText');
    if (siteData.dashboard.adminNotification) {
      adminNotificationText.textContent = siteData.dashboard.adminNotification;
      adminNotificationBar.classList.remove('hidden');
    } else {
      adminNotificationBar.classList.add('hidden');
    }
  }

  if (page === 'profile') {
    document.querySelector('#profile h2').textContent = siteData.profile.name;
    document.querySelector('#profile p').textContent = siteData.profile.title;
    document.getElementById('settingsEmail').value = siteData.profile.email;
    document.getElementById('settingsPassword').value = '';
    document.getElementById('settingsConfirmPassword').value = '';
  }

  if (page === 'forms') {
    const formsTable = document.getElementById('formsTable');
    formsTable.innerHTML = siteData.forms.map((item) => `
      <tr class="bg-slate-900 border-b border-slate-800">
        <td class="px-4 py-4 text-slate-100">${item.course}</td>
        <td class="px-4 py-4 text-slate-300">${item.form}</td>
        <td class="px-4 py-4 text-slate-300">${item.due}</td>
      </tr>
    `).join('');
  }

  if (page === 'history') {
    const historyTable = document.getElementById('historyTable');
    historyTable.innerHTML = siteData.history.map((item) => `
      <tr class="bg-slate-900 border-b border-slate-800">
        <td class="px-4 py-4 text-slate-100">${item.course}</td>
        <td class="px-4 py-4 text-slate-300">${item.form}</td>
        <td class="px-4 py-4 text-emerald-400">${item.status}</td>
        <td class="px-4 py-4 text-slate-300">${item.submitted}</td>
      </tr>
    `).join('');
  }

  if (page === 'announcements') {
    const announcementCards = document.getElementById('announcementCards');
    announcementCards.innerHTML = siteData.announcements.map((item) => `
      <div class="rounded-[24px] bg-slate-900 p-5 border border-slate-800">
        <h3 class="text-xl font-semibold text-white">${item.title}</h3>
        <p class="mt-2 text-slate-400">${item.body}</p>
      </div>
    `).join('');
  }

  if (page === 'dashboard') {
    const dashboardAnnouncementCards = document.getElementById('dashboardAnnouncementCards');
    if (dashboardAnnouncementCards) {
      dashboardAnnouncementCards.innerHTML = siteData.announcements.slice(0, 2).map((item) => `
        <div class="rounded-[24px] bg-slate-900 p-5 border border-slate-800">
          <h3 class="text-base font-semibold text-white">${item.title}</h3>
          <p class="mt-2 text-slate-400">${item.body}</p>
        </div>
      `).join('');
    }
  }
}

navLinks.forEach((link) => {
  link.addEventListener('click', () => setActivePage(link.dataset.page));
});

if (themeToggle) {
  themeToggle.addEventListener('click', () => {
    root.classList.toggle('light-theme');
    const isLight = root.classList.contains('light-theme');
    document.body.classList.toggle('bg-slate-950', !isLight);
    document.body.classList.toggle('bg-slate-50', isLight);
    document.body.classList.toggle('text-slate-100', !isLight);
    document.body.classList.toggle('text-slate-950', isLight);
    themeToggle.textContent = isLight ? 'Dark theme' : 'Toggle theme';
  });
}

if (notifBell) {
  notifBell.addEventListener('click', () => {
    showToast('You have 3 high priority notifications.');
  });
}

if (logoutBtn) {
  logoutBtn.addEventListener('click', () => {
    showToast('You have been logged out.');
    setTimeout(() => {
      window.location.href = '/logout';
    }, 800);
  });
}

if (saveSettingsBtn) {
  saveSettingsBtn.addEventListener('click', () => {
    const email = document.getElementById('settingsEmail').value.trim();
    const currentPassword = document.getElementById('settingsCurrentPassword').value.trim();
    const password = document.getElementById('settingsPassword').value.trim();
    const confirmPassword = document.getElementById('settingsConfirmPassword').value.trim();

    if (!email || !currentPassword || !password || !confirmPassword) {
      showToast('Email, current password, new password, and confirm password are required.');
      return;
    }

    if (password !== confirmPassword) {
      showToast('New password and confirm password must match.');
      return;
    }

    showToast('Email and password updated successfully.');
  });
}

setActivePage('dashboard');

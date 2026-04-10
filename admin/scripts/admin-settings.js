/**
 * Admin Settings JavaScript
 * Handles flatpickr initialization, tab synchronization, offline mode toggles,
 * staff autocomplete, and Bootstrap popovers
 */
document.addEventListener("DOMContentLoaded", () => {

  // ============================================
  // Flatpickr Date Pickers
  // ============================================
  ['startPicker', 'endPicker'].forEach(id => {
    if (document.getElementById(id)) {
      flatpickr('#' + id, {
        enableTime: true,
        dateFormat: 'Y-m-d H:i:S',
        time_24hr: true
      });
    }
  });

  // ============================================
  // Tab Title Map
  // ============================================
  const titles = {
    'main-settings':            'Main Settings',
    'tracker-settings':         'Tracker Settings',
    'date-time':                'Date & Time',
    'cookie-settings':          'Cookie Settings',
    'avatar-settings':          'Avatar Settings',
    'security-settings':        'Security Settings',
    'email-settings':           'Email Settings',
    'announce-settings':        'ANNOUNCE Settings',
    'kps-settings':             'KPS Settings',
    'user-management-settings': 'Cleanup Settings',
    'registration-settings':    'Registration',
    'staff-team':               'Staff Team',
    'freeleech-settings':       'Freeleech Settings',
  };

  // ============================================
  // Tab Switch — update hash + page title
  // ============================================
  document.querySelectorAll('.sidebar .nav-link[data-bs-toggle="tab"]').forEach(link => {
    link.addEventListener('shown.bs.tab', () => {
      const id = link.getAttribute('href').replace('#', '');
      history.replaceState(null, '', '#' + id);
      const el = document.getElementById('current-tab-title');
      if (el) el.textContent = titles[id] ?? 'Tracker Settings';
    });
  });

  // ============================================
  // Hash Routing on Page Load
  // ============================================
  const rawHash = location.hash.replace('#', '');
  const startId = rawHash && titles[rawHash] ? rawHash : 'main-settings';
  const startLink = document.querySelector(`.sidebar a[href="#${startId}"]`);
  if (startLink) bootstrap.Tab.getOrCreateInstance(startLink).show();

  // ============================================
  // Offline Mode Toggle
  // ============================================
  const siteSel = document.getElementById('siteonline');
  const offGrp  = document.getElementById('offlineDurationGroup');
  const limMode = document.getElementById('limitedMode');
  const unlMode = document.getElementById('unlimitedMode');
  const timeGrp = document.getElementById('timeLimitGroup');

  siteSel?.addEventListener('change', () => {
    if (offGrp) offGrp.style.display = siteSel.value === 'no' ? 'block' : 'none';
  });

  limMode?.addEventListener('change', () => {
    if (timeGrp) timeGrp.style.display = 'block';
  });

  unlMode?.addEventListener('change', () => {
    if (timeGrp) timeGrp.style.display = 'none';
  });

  // ============================================
  // Staff Autocomplete — Auto-fill ID from Username
  // ============================================
  if (typeof staffData !== 'undefined') {
    document.querySelectorAll('input[name="staffnames[]"]').forEach(inp => {
      inp.addEventListener('input', function () {
        const match = staffData.find(
          s => s.username.toLowerCase() === this.value.trim().toLowerCase()
        );
        if (match) {
          const idInp = this.closest('tr')?.querySelector('input[name="staffids[]"]');
          if (idInp && !idInp.value) {
            idInp.value = match.id;
            idInp.classList.add('bg-success', 'bg-opacity-25');
            setTimeout(() => idInp.classList.remove('bg-success', 'bg-opacity-25'), 1500);
          }
        }
      });
    });
  }

  // ============================================
  // Bootstrap Popovers
  // ============================================
  document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => {
    new bootstrap.Popover(el, { container: 'body' });
  });

});
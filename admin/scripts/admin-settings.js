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
  // Tab Title Synchronization
  // ============================================
  const titles = {
    'main-settings': 'Main Settings',
    'tracker-settings': 'Tracker Settings',
    'date-time': 'Date & Time',
    'cookie-settings': 'Cookie Settings',
    'avatar-settings': 'Avatar Settings',
    'security-settings': 'Security Settings',
    'email-settings': 'Email Settings',
    'announce-settings': 'ANNOUNCE Settings',
    'kps-settings': 'KPS Settings',
    'user-management-settings': 'Cleanup Settings',
    'registration-settings': 'Registration',
    'staff-team': 'Staff Team',
    'freeleech-settings': 'Freeleech Settings',
  };

  document.querySelectorAll('.sidebar .nav-link[data-bs-toggle="tab"]').forEach(link => {
    link.addEventListener('shown.bs.tab', () => {
      const id = link.getAttribute('href').replace('#', '');
      const el = document.getElementById('current-tab-title');
      if (el) {
        el.textContent = titles[id] ?? 'Tracker Settings';
      }
    });
  });

  // ============================================
  // Hash Routing on Load
  // ============================================
  const hash = location.hash;
  if (hash) {
    const link = document.querySelector('.sidebar a[href="' + hash + '"]');
    if (link) {
      bootstrap.Tab.getOrCreateInstance(link).show();
    }
  }

  // ============================================
  // Offline Mode Toggle
  // ============================================
  const siteSel = document.getElementById('siteonline');
  const offGrp = document.getElementById('offlineDurationGroup');
  const limMode = document.getElementById('limitedMode');
  const unlMode = document.getElementById('unlimitedMode');
  const timeGrp = document.getElementById('timeLimitGroup');

  if (siteSel && offGrp) {
    siteSel.addEventListener('change', () => {
      offGrp.style.display = siteSel.value === 'no' ? 'block' : 'none';
    });
  }

  if (limMode && timeGrp) {
    limMode.addEventListener('change', () => {
      timeGrp.style.display = 'block';
    });
  }

  if (unlMode && timeGrp) {
    unlMode.addEventListener('change', () => {
      timeGrp.style.display = 'none';
    });
  }

  // ============================================
  // Staff Autocomplete - Auto-fill ID from Username
  // ============================================
  // Note: staffData must be defined in the PHP template before including this script
  if (typeof staffData !== 'undefined') {
    document.querySelectorAll('input[name="staffnames[]"]').forEach(inp => {
      inp.addEventListener('input', function() {
        const match = staffData.find(s => 
          s.username.toLowerCase() === this.value.trim().toLowerCase()
        );
        
        if (match) {
          const idInp = this.closest('tr')?.querySelector('input[name="staffids[]"]');
          if (idInp && !idInp.value) {
            idInp.value = match.id;
            idInp.classList.add('bg-success', 'bg-opacity-25');
            setTimeout(() => {
              idInp.classList.remove('bg-success', 'bg-opacity-25');
            }, 1500);
          }
        }
      });
    });
  }

  // ============================================
  // Bootstrap Popovers Initialization
  // ============================================
  const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
  popoverTriggerList.forEach(el => {
    new bootstrap.Popover(el);
  });

});
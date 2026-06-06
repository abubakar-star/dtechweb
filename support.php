<?php
session_start();

require_once 'db.php';
require_once 'includes/logger.php';

// Redirect if user not logged in
if (!isset($_SESSION['user_id'])) {

    createLog(
        $conn,
        'security',
        'Unauthorized support access',
        'Someone tried to access support.php without logging in',
        'warning',
        null
    );

    header("Location: login.php");
    exit();
}

// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

createLog(
    $conn,
    'support',
    'Support page opened',
    'User opened support.php',
    'info',
    $_SESSION['user_id']
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Support Page</title>
  <link rel="icon" href="tt.png" type="x-icon" />
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
      
      #topLoader {

    position: fixed;

    top: 0;

    left: 0;

    width: 0%;

    height: 3px;

    background: #1a73e8; /* Change color */

    z-index: 99999;

    transition: width 0.4s ease;

}
      
    @keyframes slideIn { 0% { transform: translateX(100%); opacity: 0; } 100% { transform: translateX(0); opacity: 1; } }
    @keyframes slideOut { 0% { transform: translateX(0); opacity: 1; } 100% { transform: translateX(100%); opacity: 0; } }
    .toast {
      position: fixed; top: 1.5rem; right: 1.5rem; color: white; padding: 1rem 1.5rem; border-radius: 0.5rem;
      box-shadow: 0 10px 15px rgba(0,0,0,0.2); font-weight: 600; animation: slideIn 0.5s ease-out forwards;
      z-index: 1100; cursor: default; background-color: #16a34a;
    }
    .toast.error { background-color: #dc2626; }
    .toast.hide { animation: slideOut 0.5s ease-in forwards; }

    #spinnerModal {
      position: fixed; inset: 0; background-color: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1200;
    }
    #spinnerModal.active { display: flex; }
    .spinner {
      border: 4px solid rgba(59,130,246,0.2); border-top: 4px solid #3b82f6; border-radius: 50%; width: 3rem; height: 3rem;
      animation: spin 1s linear infinite; margin-bottom: 0.5rem;
    }
    @keyframes spin { 0% { transform: rotate(0deg);} 100% { transform: rotate(360deg);} }
    #spinnerText { color: #374151; font-weight: 600; font-size: 1rem; }

    @keyframes bounceHover {
  0%, 100% {
    transform: translateY(0);
    animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
  }
  50% {
    transform: translateY(-8px);
    animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
  }
}

.bounce-hover:hover {
  animation: bounceHover 0.5s;
}
  </style>
</head>
<body class="bg-gray-100 font-sans text-gray-800 relative">
    
    <div id="topLoader"></div>
    
<!-- MOBILE TOP BAR (Hamburger / three lines) -->
<header class="md:hidden sticky top-0 z-50 bg-white shadow-sm">
  <div class="flex items-center justify-between px-4 py-3">
    <button id="mobileMenuBtn" aria-label="Open menu" class="p-2 rounded hover:bg-gray-100 focus:outline-none focus:ring">
      <!-- three lines icon -->
      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
      </svg>
    </button>
    <div class="flex items-center gap-2">
      <span class="text-base font-bold text-blue-700">D-LINK NETWORK</span>
    </div>
    <div class="w-6"></div>
  </div>
</header>

<!-- MOBILE OVERLAY FOR SIDEBAR -->
<div id="mobileOverlay" class="fixed inset-0 bg-black/40 z-40 hidden md:hidden"></div>

<div class="flex min-h-screen">
  <!-- Sidebar (mobile slide-in + desktop static) -->
  <aside id="sidebar"
         class="fixed md:static inset-y-0 left-0 w-64 bg-white shadow-lg flex flex-col transform -translate-x-full md:translate-x-0 transition-transform duration-300 z-50 md:z-auto">
    <div class="p-6 border-b flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-blue-700">D-LINK NETWORK</h1>
        <p class="text-sm text-gray-500">Client Dashboard</p>
      </div>
      <!-- Close on mobile -->
      <button id="closeSidebarBtn" class="md:hidden p-2 rounded hover:bg-gray-100" aria-label="Close menu">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>
    <nav class="flex-1 p-4 space-y-4 overflow-y-auto no-scrollbar">
      <a href="index.php" class="block px-4 py-2 hover:bg-gray-100 rounded">Dashboard</a>
     <a href="subscription.php" class="block px-4 py-2 hover:bg-gray-100 rounded">My Subscription</a>
      <a href="router.php" class="block px-4 py-2 hover:bg-gray-100 rounded">Router</a>
      <a href="#" class="block px-4 py-2 rounded bg-blue-100 text-blue-700 font-semibold">Support</a>
      <a href="settings.php" class="block px-4 py-2 hover:bg-gray-100 rounded">Settings</a>
    </nav>
    <div class="p-4 border-t mb-4">
      <a href="logout.php" class="w-full block text-center bg-red-100 text-red-600 py-2 rounded hover:bg-red-600 hover:text-white transition-colors duration-300 bounce-hover">Logout</a>
    </div>
  </aside>

    <!-- Main Content -->
    <main class="flex-1 p-6 space-y-6 overflow-auto">
      <h2 class="text-2xl font-bold mb-4">Support</h2>

      <div class="bg-white p-6 rounded-xl shadow-md space-y-4">
        <p class="text-gray-700">Need help? Contact our support team below or use the form to report issues.</p>

        <div>
          <h4 class="text-md font-semibold">Support Contacts</h4>
          <ul class="text-sm text-gray-600 list-disc pl-6">
            <li>Phone: +254 758 788020</li>
            <li>Email: dlinkwifi254@gmail.com</li>
            <li>WhatsApp: +254 758 788020</li>
          </ul>
        </div>

        <!-- Support Form -->
        <form id="supportForm" method="POST" class="space-y-4">
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
          <div>
            <label class="block text-sm font-medium">Issue Title</label>
            <input name="issueTitle" id="issueTitle" type="text" class="w-full p-2 border rounded" placeholder="e.g. Internet is slow" required />
          </div>
          <div>
            <label class="block text-sm font-medium">Message</label>
            <textarea name="issueMessage" id="issueMessage" class="w-full p-2 border rounded h-24" placeholder="Describe your issue here..." required></textarea>
          </div>
          <button id="submitBtn" type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Submit</button>
        </form>
      </div>

      <!-- Tickets Section -->
      <div class="bg-white p-6 rounded-xl shadow-md">
        <h3 class="text-lg font-semibold mb-3">Your Support Tickets</h3>

        <div id="ticketsContainer" class="space-y-2 text-sm text-gray-700">
          <!-- Tickets will load here dynamically -->
        </div>

        <!-- Bottom-center dropdown: Show 5 / 10 / All -->
        <div class="flex justify-center mt-6">
          <label for="ticketsPerPage" class="mr-2 text-gray-700 font-medium">Show:</label>
          <select id="ticketsPerPage" class="border border-gray-300 rounded p-2">
            <option value="5" selected>5</option>
            <option value="10">10</option>
            <option value="all">All</option>
          </select>
        </div>
      </div>
    </main>
  </div>

  <!-- Toast container -->
  <div id="toastContainer" class="fixed top-6 right-6 space-y-2 z-50"></div>

  <!-- Spinner Modal -->
  <div id="spinnerModal" aria-live="polite" aria-label="Loading" role="alert">
    <div class="flex flex-col items-center p-6 bg-white rounded-xl shadow-lg">
      <div class="spinner" aria-hidden="true"></div>
      <div id="spinnerText">Submitting...</div>
    </div>
  </div>

    <script>

document.onreadystatechange = function () {

    var loader = document.getElementById("topLoader");

    if (document.readyState === "loading") {

        loader.style.width = "30%";

    }

    if (document.readyState === "interactive") {

        loader.style.width = "70%";

    }

    if (document.readyState === "complete") {

        loader.style.width = "100%";

        setTimeout(() => loader.style.display = "none", 400);

    }

};

        document.addEventListener('DOMContentLoaded', function () {

    const links = document.querySelectorAll('a[href]');

    links.forEach(link => {

        link.addEventListener('click', function () {

            const href = this.getAttribute('href');

            // Ignore anchors, javascript links and external actions
            if (
                href &&
                !href.startsWith('#') &&
                !href.startsWith('javascript:') &&
                !this.hasAttribute('target')
            ) {

                const loader = document.getElementById('topLoader');

                loader.style.display = 'block';
                loader.style.width = '30%';

                setTimeout(() => {
                    loader.style.width = '70%';
                }, 100);

            }

        });

    });

});

</script>

  <script>
    const form = document.getElementById('supportForm');
    const submitBtn = document.getElementById('submitBtn');
    const toastContainer = document.getElementById('toastContainer');
    const spinnerModal = document.getElementById('spinnerModal');
    const ticketsContainer = document.getElementById('ticketsContainer');
    const ticketsPerPageSelect = document.getElementById('ticketsPerPage');

    function showToast(message, type = 'success') {
      const toast = document.createElement('div');
      toast.className = `toast ${type === 'success' ? '' : 'error'}`;
      toast.textContent = message;
      toastContainer.appendChild(toast);

      setTimeout(() => {
        toast.classList.add('hide');
        setTimeout(() => { toast.remove(); }, 500);
      }, 2000);
    }

    function showSpinner(text = 'Submitting...') {
      document.getElementById('spinnerText').textContent = text;
      spinnerModal.classList.add('active');
    }
    function hideSpinner() { spinnerModal.classList.remove('active'); }

    // Simple encoder to avoid HTML injection when rendering tickets
    function escapeHTML(str) {
      return String(str)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    }

    // Load user's tickets with limit: '5' | '10' | 'all'
    function loadTickets(limit = '5') {
      const url = `support_api.php?action=get_tickets&limit=${encodeURIComponent(limit)}`;
      fetch(url)
        .then(res => res.json())
        .then(data => {
          ticketsContainer.innerHTML = '';
          if (data.status !== 'success') {
            ticketsContainer.innerHTML = '<p class="text-red-500">Failed to load tickets.</p>';
            return;
          }
          const tickets = data.tickets || [];
          if (tickets.length === 0) {
            ticketsContainer.innerHTML = '<p class="text-gray-500">No tickets found.</p>';
            return;
          }

          tickets.forEach(ticket => {
            const div = document.createElement('div');
            div.className = 'p-3 border rounded flex justify-between items-start bg-white hover:bg-gray-50 transition';

            // Status badge (resolved => green, else => yellow)
            const status = String(ticket.status || '').toLowerCase();
            const isResolved = (status === 'resolved' || status === 'closed');
            const statusBadge = `<span class="text-xs px-2 py-1 rounded ${isResolved ? 'bg-green-100 text-green-600' : 'bg-yellow-100 text-yellow-600'}">${escapeHTML(ticket.status || '')}</span>`;

            // Date
            let createdStr = '';
            if (ticket.created_at) {
              const dt = new Date(ticket.created_at.replace(' ', 'T'));
              if (!isNaN(dt)) {
                createdStr = dt.toLocaleString();
              } else {
                createdStr = ticket.created_at;
              }
            }

            div.innerHTML = `
              <div class="pr-3">
                <p class="font-semibold text-gray-800 mb-0.5">${escapeHTML(ticket.title || '')}</p>
                <p class="text-gray-600">${escapeHTML(ticket.message || '')}</p>
                ${createdStr ? `<p class="text-xs text-gray-400 mt-1">Created on: ${escapeHTML(createdStr)}</p>` : ''}
              </div>
              ${statusBadge}
            `;
            ticketsContainer.appendChild(div);
          });
        })
        .catch(() => {
          ticketsContainer.innerHTML = '<p class="text-red-500">Failed to load tickets.</p>';
        });
    }

    // Submit form via fetch with a minimum spinner duration (2s)
    form.addEventListener('submit', (e) => {
      e.preventDefault();

      const formData = new FormData(form);
      const minSpinnerMs = 2000;
      const start = Date.now();

      showSpinner('Submitting...');
      submitBtn.disabled = true;

      fetch('support_api.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        const elapsed = Date.now() - start;
        const remaining = Math.max(0, minSpinnerMs - elapsed);

        setTimeout(() => {
          hideSpinner();
          submitBtn.disabled = false;

          if (data.status === 'success') {
            showToast(data.message || 'Ticket submitted successfully', 'success');
            form.reset();
            // Reload tickets with current selected limit
            loadTickets(ticketsPerPageSelect.value || '5');
          } else {
            showToast(data.message || 'Failed to submit ticket', 'error');
          }
        }, remaining);
      })
      .catch(() => {
        const elapsed = Date.now() - start;
        const remaining = Math.max(0, minSpinnerMs - elapsed);

        setTimeout(() => {
          hideSpinner();
          submitBtn.disabled = false;
          showToast('An error occurred. Please try again.', 'error');
        }, remaining);
      });
    });

    // Handle dropdown change
    ticketsPerPageSelect.addEventListener('change', () => {
      loadTickets(ticketsPerPageSelect.value || '5');
    });

    // Initial load: default show 5
    loadTickets('5');

       // ====== Mobile sidebar (three-lines hamburger) ======
  const mobileMenuBtn = document.getElementById('mobileMenuBtn');
  const mobileOverlay = document.getElementById('mobileOverlay');
  const sidebar = document.getElementById('sidebar');
  const closeSidebarBtn = document.getElementById('closeSidebarBtn');

  function openSidebar() {
    sidebar.classList.remove('-translate-x-full');
    mobileOverlay.classList.remove('hidden');
    document.body.classList.add('modal-open');
  }
  function closeSidebar() {
    sidebar.classList.add('-translate-x-full');
    mobileOverlay.classList.add('hidden');
    document.body.classList.remove('modal-open');
  }

  mobileMenuBtn?.addEventListener('click', openSidebar);
  closeSidebarBtn?.addEventListener('click', closeSidebar);
  mobileOverlay?.addEventListener('click', closeSidebar);

  // Close sidebar on ESC (mobile)
  document.addEventListener('keydown', (e) => {
    const isMobile = window.getComputedStyle(document.querySelector('header')).display !== 'none';
    if (isMobile && e.key === 'Escape' && !sidebar.classList.contains('md:translate-x-0')) {
      closeSidebar();
    }
  });
  </script>
</body>
</html>

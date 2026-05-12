<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
// DB connection

$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname     = "dlink_network";

// DB connection
/*
$servername = "sql313.infinityfree.com";
$db_username = "if0_39741603";
$db_password = "mkala3771";
$dbname = "if0_39741603_dlink_network";
*/

$conn = new mysqli($servername, $db_username, $db_password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get router info for the current user
$sql = "SELECT r.router_name, r.model, r.ip_address, r.firmware_version, 
               r.status, r.router_password
        FROM users u
        LEFT JOIN routers r ON u.router_id = r.id
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$router = $result->fetch_assoc();

$model     = $router['model'] ?? 'N/A';
$ipAddress = $router['ip_address'] ?? 'N/A';
$firmware  = $router['firmware_version'] ?? 'N/A';
$status    = $router['status'] ?? 'offline'; // online/offline/maintenance
$password  = $router['router_password'] ?? '';

// Fetch recent password requests for logs
$logSql = "
 SELECT created_at, status
 FROM router_password_requests
 WHERE user_id = ?
 ORDER BY created_at DESC
 LIMIT 100
";
$logStmt = $conn->prepare($logSql);
$logStmt->bind_param("i", $_SESSION['user_id']);
$logStmt->execute();
$logResult = $logStmt->get_result();

$passwordLogs = [];
while ($row = $logResult->fetch_assoc()) {
    $passwordLogs[] = $row;
}



$sql = "SELECT first_name, router_password FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$first_name = $user['first_name'];
$password   = $user['router_password'] ?? '';

date_default_timezone_set('Africa/Nairobi'); // set your timezone

$hour = date('H'); // get current hour (0-23)
if ($hour >= 5 && $hour < 12) {
    $greeting = "Good morning";
    $icon = '<svg xmlns="http://www.w3.org/2000/svg" class="inline w-6 h-6 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v2m0 14v2m8.485-8.485l-1.414 1.414M4.929 5.515L3.515 6.929M21 12h-2M5 12H3m16.485 4.485l-1.414-1.414M4.929 18.485l-1.414-1.414M12 7a5 5 0 100 10 5 5 0 000-10z"/>
             </svg>';
} elseif ($hour >= 12 && $hour < 17) {
    $greeting = "Good afternoon";
       $icon = '<svg xmlns="http://www.w3.org/2000/svg" class="inline w-6 h-6 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v2m0 14v2m8.485-8.485l-1.414 1.414M4.929 5.515L3.515 6.929M21 12h-2M5 12H3m16.485 4.485l-1.414-1.414M4.929 18.485l-1.414-1.414M12 7a5 5 0 100 10 5 5 0 000-10z"/>
             </svg>';
} else {
    $greeting = "Good evening";
      $icon = '<svg xmlns="http://www.w3.org/2000/svg" class="inline w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12.79A9 9 0 1111.21 3a7 7 0 109.79 9.79z"/>
             </svg>';
}


$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Router Page</title>
  <link rel="icon" href="tt.png" type="x-icon" />
  <link rel="stylesheet" href="payment.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>

    @keyframes pulseYellow {
  0% { background-color: #fef3c7; }
  50% { background-color: #fde68a; }
  100% { background-color: transparent; }
}

.pulse-changed {
  animation: pulseYellow 2s ease-in-out 3;
}

      
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
      
    /* Fade in message */
    #message {
      position: fixed;
      top: 1rem;
      right: 1rem;
      z-index: 9999;
      padding: 1rem 1.5rem;
      border-radius: 0.375rem;
      font-weight: 600;
      color: white;
      background-color: #dc2626; /* red-600 */
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.4s ease;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
      max-width: 320px;
      word-break: break-word;
    }
    #message.show { opacity: 1; pointer-events: auto; }

    button:disabled { cursor: not-allowed; opacity: 0.6; }

    @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }
    #devNotice { animation: blink 2s infinite; }

    /* Prevent body scroll when mobile sidebar is open */
    body.modal-open { overflow: hidden; }

    /* Hide scrollbars on the sidebar list */
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

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
<body class="bg-gray-100 font-sans text-gray-800">
    
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
       <span class="text-base font-bold text-blue-700"><?php echo $greeting . ', ' . htmlspecialchars($first_name) . '  ' . $icon; ?></span>
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
      <a href="#" class="block px-4 py-2 rounded bg-blue-100 text-blue-700 font-semibold">Router</a>
      <a href="support.php" class="block px-4 py-2 hover:bg-gray-100 rounded">Support</a>
      <a href="settings.php" class="block px-4 py-2 hover:bg-gray-100 rounded">Settings</a>
    </nav>
    <div class="p-4 border-t mb-4">
      <a href="logout.php" class="w-full block text-center bg-red-100 text-red-600 py-2 rounded hover:bg-red-600 hover:text-white transition-colors duration-300">Logout</a>
    </div>
  </aside>

  <!-- Main Router Page -->
  <main class="flex-1 p-6 space-y-6 overflow-auto">
  <!--  <div id="devNotice" class="fixed top-4 right-4 bg-green-600 text-white font-semibold px-4 py-2 rounded shadow-lg z-50">
      ⚠️ Testing in Progress
    </div>
-->
    <h2 class="text-2xl font-bold mb-4">Router Information</h2>

    <!-- Router Status -->
    <div class="bg-white p-6 rounded-xl shadow space-y-4">
      <h3 class="text-lg font-semibold">Router Status</h3>
      <p><strong>Model:</strong> <?php echo htmlspecialchars($model); ?></p>
      <p><strong>IP Address:</strong> <?php echo htmlspecialchars($ipAddress); ?></p>
      <p><strong>Firmware Version:</strong> <?php echo htmlspecialchars($firmware); ?></p>
      <p><strong>Status:</strong>
        <span id="routerStatus" class="<?php echo ($status === 'online') ? 'text-green-600' : 'text-red-600'; ?> font-semibold">
          <?php echo ucfirst($status); ?>
        </span>
      </p>
    </div>

    <!-- Router Controls -->
    <div class="bg-white p-6 rounded-xl shadow space-y-4">
      <h3 class="text-lg font-semibold">Router Controls</h3>
      <button id="routerActionBtn" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded bounce-hover" onclick="window.location.href='rt.php'" disabled>Connect Router</button>
      <button id="diagnoseBtn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded bounce-hover" disabled>Diagnose Router</button>
      <button id="openUsersModal" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded bounce-hover" disabled>Connected Users</button>
    </div>

    <!-- Change Router Password -->
    <div class="bg-white p-6 rounded-xl shadow space-y-4">
      <h3 class="text-lg font-semibold">Change Router Password</h3>
      <form id="passwordForm" class="space-y-4">
        <div>
          <label class="block mb-1">Current Password</label>
          <div class="flex">
            <input type="text" id="currentPassword" value="<?php echo htmlspecialchars($password); ?>" class="w-full border border-gray-300 rounded p-2" placeholder="Enter current password">
            <button type="button" id="togglePassword" class="ml-2 px-3 py-2 bg-gray-200 rounded">Hide</button>
          </div>
        </div>
       <div>
  <label class="block mb-1">New Password</label>
  <div class="flex">
    <input type="password" id="newPassword" class="w-full border border-gray-300 rounded p-2" placeholder="Enter new password" required>
    <button type="button" id="toggleNewPassword" class="ml-2 px-3 py-2 bg-gray-200 rounded">Show</button>
  </div>
</div>

<div>
  <label class="block mb-1">Confirm New Password</label>
  <div class="flex">
    <input type="password" id="confirmNewPassword" class="w-full border border-gray-300 rounded p-2" placeholder="Confirm new password" required>
    <button type="button" id="toggleConfirmPassword" class="ml-2 px-3 py-2 bg-gray-200 rounded">Show</button>
  </div>
</div>

        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded bounce-hover">Update Password</button>
      </form>
    </div>

    <!-- Router Logs -->
    <div class="bg-white p-6 rounded-xl shadow">
      <h3 class="text-lg font-semibold mb-4">Recent Logs</h3>
      <table class="w-full text-sm text-left border-collapse">
        <thead>
          <tr class="bg-gray-100 text-gray-600 uppercase text-xs">
            <th class="px-4 py-2">Date</th>
            <th class="px-4 py-2">Event</th>
            <th class="px-4 py-2">Status</th>
          </tr>
        </thead>
        <tbody id="logsBody"></tbody>
      </table>

      <!-- Show entries dropdown -->
      <div id="showLimitContainer" class="mt-4 flex justify-center">
        <label for="logLimitSelect" class="mr-2 font-semibold">Show:</label>
        <select id="logLimitSelect" class="border border-gray-300 rounded p-1">
          <option value="10">10</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
      </div>
    </div>
  </main>
</div>

<!-- Connected Users Modal -->
<div id="usersModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="bg-white rounded-xl shadow-lg max-w-3xl w-full p-6">
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-lg font-semibold">Connected Users</h3>
      <button id="closeUsersModal" class="text-gray-500 hover:text-gray-700">&times;</button>
    </div>
    <p class="mb-4 font-semibold text-gray-700">Total Users Connected: <span id="totalUsers" class="text-blue-600">0</span></p>
    <table class="w-full border-collapse border border-gray-300 text-sm">
      <thead>
        <tr class="bg-gray-100">
          <th class="border border-gray-300 px-4 py-2">Device Name</th>
          <th class="border border-gray-300 px-4 py-2">IP Address</th>
          <th class="border border-gray-300 px-4 py-2">MAC Address</th>
          <th class="border border-gray-300 px-4 py-2">Status</th>
          <th class="border border-gray-300 px-4 py-2">Actions</th>
        </tr>
      </thead>
      <tbody id="usersTableBody">
        <tr>
          <td class="border border-gray-300 px-4 py-2">John's Phone</td>
          <td class="border border-gray-300 px-4 py-2">192.168.0.101</td>
          <td class="border border-gray-300 px-4 py-2">AA:BB:CC:DD:EE:01</td>
          <td class="border border-gray-300 px-4 py-2 text-green-600">Connected</td>
          <td class="border border-gray-300 px-4 py-2">
            <button class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600">Whitelist</button>
            <button class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600">Blacklist</button>
          </td>
        </tr>
        <tr>
          <td class="border border-gray-300 px-4 py-2">Laptop</td>
          <td class="border border-gray-300 px-4 py-2">192.168.0.102</td>
          <td class="border border-gray-300 px-4 py-2">AA:BB:CC:DD:EE:02</td>
          <td class="border border-gray-300 px-4 py-2 text-green-600">Connected</td>
          <td class="border border-gray-300 px-4 py-2">
            <button class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600">Whitelist</button>
            <button class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600">Blacklist</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Restart/Connect Loading Modal -->
<div id="loadingModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="bg-white rounded-xl shadow-lg p-6 flex flex-col items-center">
    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mb-4"></div>
    <p id="loadingText" class="font-semibold text-gray-700">Loading...</p>
  </div>
</div>

<!-- Diagnose Router Modal -->
<div id="diagnoseModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-lg">
    <h3 class="text-lg font-semibold mb-4">Diagnosing Router...</h3>
    <div id="diagnoseContent" class="space-y-2 text-gray-700"></div>
    <div class="mt-4 text-right">
      <button id="closeDiagnoseModal" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded hidden">Close</button>
    </div>
  </div>
</div>

<!-- Floating Message -->
<div id="message"></div>
    
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

</script>


<script>

  const topLoader = document.getElementById('topLoader');

function showRefreshLine() {
  topLoader.style.display = 'block';
  topLoader.style.width = '30%';
  setTimeout(() => topLoader.style.width = '70%', 150);
}

function hideRefreshLine() {
  topLoader.style.width = '100%';
  setTimeout(() => {
    topLoader.style.display = 'none';
    topLoader.style.width = '0%';
  }, 400);
}

  // ====== Elements ======
  const openBtn = document.getElementById('openUsersModal');
  const closeBtn = document.getElementById('closeUsersModal');
  const modal = document.getElementById('usersModal');
  const totalUsersSpan = document.getElementById('totalUsers');
  const usersTableBody = document.getElementById('usersTableBody');

  const routerStatusEl = document.getElementById('routerStatus');
  const routerActionBtn = document.getElementById('routerActionBtn');
  const loadingModal = document.getElementById('loadingModal');
  const loadingText = document.getElementById('loadingText');

  const diagnoseBtn = document.getElementById('diagnoseBtn');
  const diagnoseModal = document.getElementById('diagnoseModal');
  const diagnoseContent = document.getElementById('diagnoseContent');
  const closeDiagnoseModal = document.getElementById('closeDiagnoseModal');

  const messageEl = document.getElementById('message');

  // Logs
  const logsBody = document.getElementById('logsBody');
  const logLimitSelect = document.getElementById('logLimitSelect');

  let previousLogStatuses = {};

  let lastLogsHash = '';

 let logs = <?php echo json_encode(array_map(function($l){
  return [
    'date' => date('M d, Y H:i:s', strtotime($l['created_at'])),
    'event' => 'Router Password Change',
    'status' => ucfirst($l['status'])
  ];
}, $passwordLogs)); ?>;

  let logDisplayLimit = parseInt(logLimitSelect.value, 10);

  // ====== Message helper ======
  function showMessage(text, isError = false, duration = 4000) {
    messageEl.textContent = text;
    messageEl.style.backgroundColor = isError ? '#dc2626' : '#16a34a';
    messageEl.classList.add('show');
    clearTimeout(messageEl.hideTimeout);
    messageEl.hideTimeout = setTimeout(() => { messageEl.classList.remove('show'); }, duration);
  }

  function isRouterOffline() { return routerStatusEl.textContent.trim() === 'Offline'; }

  // ====== Render logs with limit ======
function renderLogs() {
  logsBody.innerHTML = '';
  const toShow = logs.slice(0, logDisplayLimit);

  if (toShow.length === 0) {
    // Show "No logs found" row
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td colspan="3" class="px-4 py-2 text-center text-gray-500">No logs found</td>
    `;
    logsBody.appendChild(tr);
    return;
  }

  toShow.forEach(({ date, event, status }) => {
    let statusClass = 'text-gray-600';

    if (status.toLowerCase() === 'pending') statusClass = 'text-yellow-600';
    if (status.toLowerCase() === 'changed') statusClass = 'text-green-600';
    if (status.toLowerCase() === 'failed')  statusClass = 'text-red-600';

    const tr = document.createElement('tr');
    tr.className = 'border-t';
    tr.innerHTML = `
      <td class="px-4 py-2">${date}</td>
      <td class="px-4 py-2">${event}</td>
      <td class="px-4 py-2 ${statusClass} font-semibold">${status}</td>
    `;
    logsBody.appendChild(tr);
  });
}


  function addLog(date, event, status) { logs.unshift({ date, event, status }); renderLogs(); }
  function getFormattedDateTime() {
    const today = new Date();
    return today.toLocaleString('en-US', {
      month: 'short', day: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false
    });
  }
  renderLogs();

  // Dropdown change listener
  logLimitSelect.addEventListener('change', (e) => { logDisplayLimit = parseInt(e.target.value, 10); renderLogs(); });

  // ====== Router Status button text and style update ======
  function updateButtonAndStatus() {
    if (isRouterOffline()) {
      routerStatusEl.classList.remove('text-green-600');
      routerStatusEl.classList.add('text-red-600');
      routerActionBtn.textContent = 'Connect Router';
      routerActionBtn.className = 'bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded';
    } else {
      routerStatusEl.classList.remove('text-red-600');
      routerStatusEl.classList.add('text-green-600');
      routerActionBtn.textContent = 'Restart Router';
      routerActionBtn.className = 'bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded';
    }
  }
  updateButtonAndStatus();

  // ====== Router action button handler ======
  routerActionBtn.addEventListener('click', () => {
    const formattedDate = getFormattedDateTime();
    if (isRouterOffline()) {
      loadingText.textContent = 'Connecting Router...';
      loadingModal.classList.remove('hidden');
      loadingModal.classList.add('flex');
      addLog(formattedDate, 'Connect Router', 'Success');
      setTimeout(() => {
        loadingModal.classList.add('hidden');
        loadingModal.classList.remove('flex');
        routerStatusEl.textContent = 'Online';
        updateButtonAndStatus();
        showMessage('Router connected successfully!');
      }, 2000);
    } else {
      loadingText.textContent = 'Router Restarting...';
      loadingModal.classList.remove('hidden');
      loadingModal.classList.add('flex');
      addLog(formattedDate, 'Restart Router', 'Success');
      setTimeout(() => {
        loadingModal.classList.add('hidden');
        loadingModal.classList.remove('flex');
        showMessage('Router restarted successfully!');
      }, 2000);
    }
  });

  // ====== Connected Users modal with offline check ======
  openBtn.addEventListener('click', () => {
    if (isRouterOffline()) { showMessage('Your Router is offline!', true); return; }
    loadingText.textContent = 'Fetching connected users...';
    loadingModal.classList.remove('hidden');
    loadingModal.classList.add('flex');
    addLog(getFormattedDateTime(), 'Viewed Connected Users', 'Success');
    setTimeout(() => {
      loadingModal.classList.add('hidden');
      loadingModal.classList.remove('flex');
      const totalUsers = usersTableBody.getElementsByTagName('tr').length;
      totalUsersSpan.textContent = totalUsers;
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }, 1500);
  });
  closeBtn.addEventListener('click', () => { modal.classList.add('hidden'); modal.classList.remove('flex'); });
  window.addEventListener('click', (e) => {
    if (e.target === modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
    if (e.target === diagnoseModal) { closeDiagnoseModal.click(); }
  });

  // ====== Diagnose Router Simulation with offline check ======
  diagnoseBtn.addEventListener('click', () => {
    if (isRouterOffline()) { showMessage('Your Router is offline!', true); return; }
    diagnoseContent.innerHTML = '';
    diagnoseModal.classList.remove('hidden');
    diagnoseModal.classList.add('flex');
    closeDiagnoseModal.classList.add('hidden');
    addLog(getFormattedDateTime(), 'Diagnose Router', 'Success');

    function addStep(message) {
      const step = document.createElement('div');
      step.className = 'flex justify-between';
      const msgSpan = document.createElement('span');
      msgSpan.textContent = message;
      const okSpan = document.createElement('span');
      okSpan.className = 'text-green-600 font-semibold hidden';
      okSpan.textContent = 'OK';
      step.appendChild(msgSpan); step.appendChild(okSpan); diagnoseContent.appendChild(step);
      setTimeout(() => { okSpan.classList.remove('hidden'); }, 500);
    }

    setTimeout(() => addStep('Checking internet connection...'), 0);
    setTimeout(() => addStep('Testing ping to server...'), 2000);
    setTimeout(() => addStep('Measuring network speed...'), 3000);
    setTimeout(() => {
      const finalMsg = document.createElement('p');
      finalMsg.className = 'text-green-600 font-semibold mt-2';
      finalMsg.textContent = 'Diagnosis complete: Connection is stable. Speed: 95 Mbps.';
      diagnoseContent.appendChild(finalMsg);
      closeDiagnoseModal.classList.remove('hidden');
    }, 5000);
  });
  closeDiagnoseModal.addEventListener('click', () => { diagnoseModal.classList.add('hidden'); diagnoseModal.classList.remove('flex'); });

  // ====== Change password form handling ======
  const passwordForm = document.getElementById('passwordForm');
  const newPasswordInput = document.getElementById('newPassword');
  const confirmNewPasswordInput = document.getElementById('confirmNewPassword');
  passwordForm.addEventListener('submit', async e => {
  e.preventDefault();

  if (newPasswordInput.value !== confirmNewPasswordInput.value) {
    showMessage('New Password and Confirm New Password do not match!', true);
    return;
  }

  const formData = new FormData();
  formData.append('new_password', newPasswordInput.value);

  try {
    const res = await fetch('request_password_change.php', {
      method: 'POST',
      body: formData
    });
    const data = await res.json();

    if (data.success) {
      addLog(getFormattedDateTime(), 'Password Change Requested', 'Pending');
      showMessage('Password change request submitted. ISP will process it.');
      passwordForm.reset();
    } else {
      showMessage(data.message || 'Request failed', true);
    }
  } catch (err) {
    showMessage('Network error. Try again.', true);
  }
});

  document.getElementById('togglePassword').addEventListener('click', function() {
    const passInput = document.getElementById('currentPassword');
    if (passInput.type === 'text') { passInput.type = 'password'; this.textContent = 'Show'; }
    else { passInput.type = 'text'; this.textContent = 'Hide'; }
  });

  // Toggle New Password
document.getElementById('toggleNewPassword').addEventListener('click', function() {
  const passInput = document.getElementById('newPassword');
  if (passInput.type === 'password') {
    passInput.type = 'text';
    this.textContent = 'Hide';
  } else {
    passInput.type = 'password';
    this.textContent = 'Show';
  }
});

// Toggle Confirm New Password
document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
  const passInput = document.getElementById('confirmNewPassword');
  if (passInput.type === 'password') {
    passInput.type = 'text';
    this.textContent = 'Hide';
  } else {
    passInput.type = 'password';
    this.textContent = 'Show';
  }
});


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

// ====== Smart Auto-refresh with toast + pulse ======
async function refreshPasswordLogs() {
  try {
    showRefreshLine();

    const res = await fetch('fetch_password_logs.php');
    const data = await res.json();

    if (!Array.isArray(data)) {
      hideRefreshLine();
      return;
    }

    const newHash = JSON.stringify(data);

    if (newHash !== lastLogsHash) {

      // Detect status changes
      data.forEach((log, index) => {
        const key = log.date + log.event; // simple unique key
        const oldStatus = previousLogStatuses[key];
        const newStatus = log.status.toLowerCase();

        // 🔔 Toast when Pending -> Changed
        if (oldStatus === 'pending' && newStatus === 'changed') {
          showMessage('✅ Your router password has been changed by ISP!', false, 6000);
        }

        previousLogStatuses[key] = newStatus;
      });

      logs = data;
      renderLogs();
      lastLogsHash = newHash;

      // Apply pulse animation to Changed rows
      setTimeout(() => {
        const rows = logsBody.querySelectorAll('tr');
        rows.forEach((row, i) => {
          const statusCell = row.querySelector('td:last-child');
          if (statusCell && statusCell.textContent.toLowerCase() === 'changed') {
            row.classList.add('pulse-changed');
            setTimeout(() => row.classList.remove('pulse-changed'), 6000);
          }
        });
      }, 100);

      console.log('Logs updated with animations');

    } else {
      console.log('No log changes');
    }

    hideRefreshLine();

  } catch (err) {
    console.error('Failed to refresh logs', err);
    hideRefreshLine();
  }
}

// Initial load refresh (optional)
refreshPasswordLogs();

// Initialize previous statuses after first load
setTimeout(() => {
  logs.forEach(log => {
    const key = log.date + log.event;
    previousLogStatuses[key] = log.status.toLowerCase();
  });
}, 1000);


// Refresh every 30 seconds
setInterval(refreshPasswordLogs, 5000);

</script>

</body>
</html>

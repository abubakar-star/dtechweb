<?php
// settings.php
session_start();
require_once 'includes/logger.php';
require 'includes/payment_guard.php';

// For local testing only you can uncomment the following line and set a user id.
// $_SESSION['user_id'] = 1;

if (!isset($_SESSION['user_id'])) {

    createLog(
        null,
        'authentication',
        'unauthorized_settings_access',
        'Guest attempted to access settings.php',
        'warning'
    );

    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);

// DB connection

$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$username = $_ENV['MYSQLUSER'];
$password = $_ENV['MYSQLPASSWORD'];

$conn = new mysqli($host, $username, $password, $dbname, $port);
if ($conn->connect_error) {

    createLog(
        $conn,
        'database',
        'connection_failed',
        'Database connection failed in settings.php',
        'critical',
        $user_id
    );

    die("DB Connection failed: " . $conn->connect_error);
}

// Determine which password column exists: prefer 'password' then 'user_password'
$colStmt = $conn->prepare("
  SELECT COLUMN_NAME
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users' AND COLUMN_NAME IN ('password','user_password')
");
$colStmt->bind_param("s", $dbname);
$colStmt->execute();
$colRes = $colStmt->get_result();
$pwd_col = null;
while ($r = $colRes->fetch_assoc()) {
    // choose password if present, else user_password
    if ($r['COLUMN_NAME'] === 'password') $pwd_col = 'password';
    if ($r['COLUMN_NAME'] === 'user_password' && $pwd_col === null) $pwd_col = 'user_password';
}
$colStmt->close();

if ($pwd_col === null) {

    createLog(
        $conn,
        'database',
        'missing_password_column',
        'No password column found in users table',
        'critical',
        $user_id
    );

    $conn->close();

    die("No password column ('password' or 'user_password') found in users table.");
}

// Fetch user details and the appropriate password column
$sql = "SELECT id, username, first_name, last_name, phone_number, {$pwd_col} AS pwd FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {

    createLog(
        $conn,
        'database',
        'prepare_failed',
        "Settings query prepare failed: {$conn->error}",
        'error',
        $user_id
    );

    $conn->close();

    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
$conn->close();

$first_name = $user['first_name'];

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

if (!$user) {

    createLog(
        $conn,
        'user',
        'settings_user_not_found',
        "User ID {$user_id} not found while opening settings",
        'warning',
        $user_id
    );

    die("User not found.");
}

// store pwd column name for client-side use (not necessary but informative)
$pwd_col_used = $pwd_col;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Settings Page</title>
  <link rel="icon" href="tt.png" type="x-icon" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
      
      #topLoader {

    position: fixed;

    top: 0;

    left: 0;

    width: 0%;

    height: 3px;

    background: #1a73e8; /* Change color */

    z-index: 99999;

    transition: width 0.25s ease;

}

.phone-btn-spinner{
    width:18px;
    height:18px;
    border:2px solid rgba(255,255,255,0.3);
    border-top-color:#fff;
    border-radius:50%;
    animation:spinPhone .7s linear infinite;
}

@keyframes spinPhone{
    to{
        transform:rotate(360deg);
    }
}
      
    /* small inline spinner used inside the button */
    .btn-spinner {
      display: inline-block;
      width: 16px;
      height: 16px;
      border: 2px solid rgba(255,255,255,0.3);
      border-top-color: white;
      border-radius: 50%;
      animation: btn-spin 0.8s linear infinite;
      vertical-align: middle;
      margin-left: 8px;
    }
    @keyframes btn-spin { to { transform: rotate(360deg); } }

    /* page loader modal (optional) */
    .modal-loader { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.45); z-index: 60; }
    .hidden { display: none; }

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
        <p class="text-sm text-gray-500">Client Dashboard</p></div>
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
      <a href="router.php" class="block px-4 py-2 hover:bg-gray-100">Router</a>
      <a href="support.php" class="block px-4 py-2 hover:bg-gray-100 rounded">Support</a>
      <a href="#" class="block px-4 py-2 rounded bg-blue-100 text-blue-700 font-semibold">Settings</a>
    </nav>
    <div class="p-4 border-t mb-4">
      <a href="logout.php" class="w-full block text-center bg-red-100 text-red-600 py-2 rounded hover:bg-red-600 hover:text-white transition-colors duration-300 bounce-hover">Logout</a>
    </div>
  </aside>

    <!-- Main Content -->
    <main class="flex-1 py-6 pr-6 pl-6 space-y-6 overflow-auto">
      <h2 class="text-2xl font-bold mb-4">Account Settings</h2>

      <div class="bg-white p-6 rounded-xl shadow-md space-y-6 max-w-3xl">
        <!-- Personal Info -->
        <div>
          <h4 class="text-md font-semibold mb-1">Personal Info</h4>
          <input id="fullName" type="text" placeholder="Full Name" value="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>" class="w-full p-2 border rounded mb-2" />
          <input id="phoneNumber" type="text" placeholder="Phone Number" value="<?php echo htmlspecialchars($user['phone_number']); ?>" class="w-full p-2 border rounded" />
        </div>

<!-- Change Phone Button -->
<div class="pt-2">
  <button
    id="openPhoneModal"
    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded bounce-hover">
    Change Phone Number
  </button>
</div>

        <!-- Change Password -->
        <div>
          <h4 class="text-md font-semibold mb-1">Change Account Password</h4>

          <!-- Current Password (shows DB value as readonly text by default) -->
          <div class="relative mb-2">
            <label class="block text-sm text-gray-600 mb-1">Current Password</label>
            <input
              type="text"
              id="currentPassword"
              class="w-full p-2 border rounded pr-10 bg-gray-50"
              value="<?php echo htmlspecialchars($user['pwd']); ?>"
              readonly
              autocomplete="current-password"
            />
            <button type="button" onclick="toggleVisibility('currentPassword', this)" class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-500">
              <svg xmlns="http://www.w3.org/2000/svg" id="eyeCurrent" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
            </button>
          </div>

          <!-- New Password -->
          <div class="relative mb-2">
            <label class="block text-sm text-gray-600 mb-1">New Password</label>
            <input
              type="password"
              id="newPassword"
              class="w-full p-2 border rounded pr-10"
              autocomplete="new-password"
              placeholder="New Password"
            />
            <button type="button" onclick="toggleVisibility('newPassword', this)" class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-500">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
            </button>
          </div>

          <!-- Confirm New Password -->
          <div class="relative mb-2">
            <label class="block text-sm text-gray-600 mb-1">Confirm New Password</label>
            <input
              type="password"
              id="confirmNewPassword"
              class="w-full p-2 border rounded pr-10"
              autocomplete="new-password"
              placeholder="Confirm New Password"
            />
            <button type="button" onclick="toggleVisibility('confirmNewPassword', this)" class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-500">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
            </button>
          </div>

          <!-- Inline error -->
          <p id="errorMsg" class="text-red-500 text-sm mt-1 hidden"></p>
        </div>

        <!-- Save Changes button: will show inline spinner while request is in progress -->
        <div class="pt-4">
          <button id="saveChangesBtn" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 flex items-center justify-center">
            <span id="saveBtnText">Save Changes</span>
            <!-- spinner inserted programmatically -->
          </button>
        </div>

        <!-- Success toast (hidden by default) -->
        <div id="successToast" class="hidden fixed top-6 right-6 bg-green-500 text-white px-4 py-2 rounded shadow">✅ Password updated</div>
      </div>
    </main>
  </div>


  <!-- PHONE CHANGE MODAL -->
<div id="phoneModal"
     class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">

  <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6 relative">

    <button
      id="closePhoneModal"
      class="absolute top-3 right-3 text-gray-500 hover:text-black text-xl">
      &times;
    </button>

    <h2 class="text-xl font-bold mb-4">Change Phone Number</h2>

    <div class="space-y-4">

  <div>
    <label class="block text-sm text-gray-600 mb-1">
      Current Password
    </label>

    <div class="relative">

        <!-- SHOW by default -->
        <input
          type="text"
          id="verifyPassword"
          class="w-full border rounded p-2 pr-12"
          placeholder="Enter current password">

        <!-- Eye Toggle Button -->
        <button
          type="button"
          id="toggleVerifyPassword"
          class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-500 hover:text-gray-700">

            <!-- Eye Open -->
            <svg id="eyeOpenVerify"
                 xmlns="http://www.w3.org/2000/svg"
                 fill="none"
                 viewBox="0 0 24 24"
                 stroke="currentColor"
                 class="w-5 h-5">

                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>

                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>

        </button>

    </div>
</div>

      <div>
        <label class="block text-sm text-gray-600 mb-1">
          New Phone Number
        </label>

        <input
          type="text"
          id="newPhoneNumber"
          class="w-full border rounded p-2"
          placeholder="2547XXXXXXXX">
      </div>

      <p id="phoneError"
         class="text-red-500 text-sm hidden"></p>

     <button
  id="updatePhoneBtn"
  class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded flex items-center justify-center gap-2">

  <span id="updatePhoneText">Update Phone Number</span>

</button>

    </div>

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
    // Toggle visibility of an input (works for text/password)
    function toggleVisibility(inputId, btn) {
      const input = document.getElementById(inputId);
      if (!input) return;
      input.type = input.type === 'password' ? 'text' : 'password';
      // optional: swap icon in btn if desired
    }

    // Show inline spinner inside the button
    function setButtonLoading(isLoading) {
      const btn = document.getElementById('saveChangesBtn');
      const textEl = document.getElementById('saveBtnText');
      if (isLoading) {
        // disable button
        btn.disabled = true;
        textEl.textContent = 'Saving';
        // add spinner element
        const spinner = document.createElement('span');
        spinner.className = 'btn-spinner';
        spinner.id = 'btnSpinner';
        btn.appendChild(spinner);
      } else {
        btn.disabled = false;
        // restore text
        textEl.textContent = 'Save Changes';
        // remove spinner if present
        const spinner = document.getElementById('btnSpinner');
        if (spinner) spinner.remove();
      }
    }

    // Show error text below inputs
    function showError(msg) {
      const e = document.getElementById('errorMsg');
      e.textContent = msg;
      e.classList.remove('hidden');
    }
    function hideError() {
      const e = document.getElementById('errorMsg');
      e.textContent = '';
      e.classList.add('hidden');
    }

    // Show success toast briefly
    function showSuccessToast(msg) {
      const t = document.getElementById('successToast');
      t.textContent = '✅ ' + (msg || 'Password updated');
      t.classList.remove('hidden');
      setTimeout(() => t.classList.add('hidden'), 3000);
    }

    document.getElementById('saveChangesBtn').addEventListener('click', async function (e) {
      e.preventDefault();
      hideError();

      const currentPassword = document.getElementById('currentPassword').value.trim();
      const newPassword = document.getElementById('newPassword').value.trim();
      const confirmNewPassword = document.getElementById('confirmNewPassword').value.trim();

      // Client-side validation
      const regex = /^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{6,}$/; // min 6, letters + numbers
      if (!regex.test(newPassword)) {
        showError("New password must be at least 6 characters and include letters and numbers.");
        return;
      }
      if (newPassword !== confirmNewPassword) {
        showError("New password and Confirm password do not match.");
        return;
      }
      if (currentPassword === '') {
        showError("Current password is required.");
        return;
      }

      // Start spinner on button
      setButtonLoading(true);

      try {
        const res = await fetch('update_password.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            currentPassword: currentPassword,
            newPassword: newPassword
          })
        });

        const data = await res.json();

        if (res.ok && data.status === 'success') {
          // success: update currentPassword display with new password returned by server
          if (data.new_password !== undefined) {
            document.getElementById('currentPassword').value = data.new_password;
          }
          showSuccessToast(data.message || 'Password updated');
          // clear new fields
          document.getElementById('newPassword').value = '';
          document.getElementById('confirmNewPassword').value = '';
        } else {
          // server returned an error
          const errMsg = (data && data.message) ? data.message : 'Failed to update password.';
          showError(errMsg);
        }
      } catch (err) {
        showError('Network error. Please try again.');
        console.error(err);
      } finally {
        // stop spinner
        setButtonLoading(false);
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
  </script>

  <script>

const phoneModal = document.getElementById("phoneModal");

document.getElementById("openPhoneModal")
.addEventListener("click", () => {

    const loader = document.getElementById("topLoader");

    // Reset loader
    loader.style.display = "block";
    loader.style.width = "0%";

    // Animate like website loading
    setTimeout(() => {
        loader.style.width = "30%";
    }, 100);

    setTimeout(() => {
        loader.style.width = "70%";
    }, 300);

    setTimeout(() => {
        loader.style.width = "100%";
    }, 600);

    // Open modal after loading animation
    setTimeout(() => {

        phoneModal.classList.remove("hidden");
        phoneModal.classList.add("flex");

        // Hide loader again
        setTimeout(() => {
            loader.style.display = "none";
            loader.style.width = "0%";
        }, 200);

    }, 750);

});

document.getElementById("closePhoneModal")
.addEventListener("click", () => {

    phoneModal.classList.add("hidden");
    phoneModal.classList.remove("flex");
});

document.getElementById("updatePhoneBtn")
.addEventListener("click", async () => {

    const btn =
        document.getElementById("updatePhoneBtn");

    const btnText =
        document.getElementById("updatePhoneText");

    const password =
        document.getElementById("verifyPassword").value.trim();

    const phone =
        document.getElementById("newPhoneNumber").value.trim();

    const error =
        document.getElementById("phoneError");

    error.classList.add("hidden");

    /* Kenya phone validation */
    const phoneRegex = /^2547\d{8}$/;

    if (!phoneRegex.test(phone)) {

        error.textContent =
            "Enter a valid Safaricom number starting with 254";

        error.classList.remove("hidden");

        return;
    }

    /* START BUTTON LOADING */
    btn.disabled = true;

    btnText.textContent = "Updating...";

    const spinner = document.createElement("span");

    spinner.className = "phone-btn-spinner";

    spinner.id = "phoneSpinner";

    btn.appendChild(spinner);

    try {

        const res = await fetch(
            "update_phone.php",
            {
                method: "POST",

                headers: {
                    "Content-Type":
                    "application/x-www-form-urlencoded"
                },

                body: new URLSearchParams({
                    password: password,
                    phone: phone
                })
            }
        );

        const data = await res.json();

        /* simulate website loading feel */
        setTimeout(() => {

            /* STOP BUTTON LOADING */
            btn.disabled = false;

            btnText.textContent =
                "Update Phone Number";

            document.getElementById("phoneSpinner")?.remove();

            if (data.status === "success") {

                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: 'Phone number updated successfully.',
                    confirmButtonColor: '#16a34a'
                }).then(() => {

                    location.reload();

                });

            } else {

                error.textContent = data.message;

                error.classList.remove("hidden");
            }

        }, 1200);

    } catch (err) {

        btn.disabled = false;

        btnText.textContent =
            "Update Phone Number";

        document.getElementById("phoneSpinner")?.remove();

        error.textContent =
            "Network error.";

        error.classList.remove("hidden");
    }

});

document.getElementById("toggleVerifyPassword")
.addEventListener("click", () => {

    const input =
        document.getElementById("verifyPassword");

    if (input.type === "text") {

        input.type = "password";

    } else {

        input.type = "text";
    }

});
</script>
</body>
</html>

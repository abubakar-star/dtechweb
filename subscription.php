<?php
session_start();

require_once 'includes/logger.php';

if (!isset($_SESSION['user_id'])) {

    createLog(
        null,
        'authentication',
        'unauthorized_subscription_access',
        'Guest attempted to access subscription.php',
        'warning'
    );

    header("Location: login.php");
    exit();
}

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
        'Database connection failed in subscription.php',
        'critical',
        $_SESSION['user_id']
    );

    die("Connection failed: " . $conn->connect_error);
}

// Check if user has an active subscription
$stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM payments 
    WHERE user_id = ? AND status = 'completed'
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$stmt->bind_result($paymentCount);
$stmt->fetch();
$stmt->close();

$hasPaid = $paymentCount > 0;

$extraCharges = [];
$totalExtraCharges = 0;

$sql = "
SELECT id, charge_name, amount, status
FROM extra_charges
WHERE user_id = ?
AND status = 'pending'
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $extraCharges[] = $row;
    $totalExtraCharges += (float)$row['amount'];
}

$stmt->close();

$installationFee = 0.00;




// Fetch all available packages from the database
$packages = [];

$pkg_sql = "
    SELECT id, package_name, speed, price 
    FROM packages 
    WHERE status = 'active'
";

$pkg_result = $conn->query($pkg_sql);

if (!$pkg_result) {

    createLog(
        $conn,
        'database',
        'package_query_failed',
        "Failed to fetch active packages: {$conn->error}",
        'error',
        $_SESSION['user_id']
    );

} else {

    while ($row = $pkg_result->fetch_assoc()) {
        $packages[] = $row;
    }
}

$sql = "SELECT first_name, last_name, phone_number, email FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {

    createLog(
        $conn,
        'user',
        'user_details_missing',
        "User details not found in subscription.php for user ID {$_SESSION['user_id']}",
        'warning',
        $_SESSION['user_id']
    );

    die("User not found.");
}

$fullName = trim($user['first_name'] . ' ' . $user['last_name']);
$phone = $user['phone_number'];
$email = $user['email'];

// Get speed & price from packages linked to the current user
$sql = "SELECT 
            u.first_name, 
            u.last_name, 
            r.mac_address, 
            r.ip_address, 
            u.package_id,
            p.package_name, 
            p.speed, 
            p.price,
            u.Expiry
        FROM users u
        LEFT JOIN routers r ON u.router_id = r.id
        LEFT JOIN packages p ON u.package_id = p.id
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$subscription = $result->fetch_assoc();

if (!$subscription) {

    createLog(
        $conn,
        'subscription',
        'subscription_not_found',
        "Subscription details missing for user ID {$_SESSION['user_id']}",
        'warning',
        $_SESSION['user_id']
    );
}

$speed = $subscription['speed'] ?? 'N/A';
$price = isset($subscription['price']) ? 'KES ' . number_format($subscription['price'], 0) : 'N/A';
$expiry = isset($subscription['Expiry']) ? date("M j, Y", strtotime($subscription['Expiry'])) : 'N/A';
$userPackageId = $subscription['package_id'] ?? null;

// Assign variables
$fullName   = trim($user['first_name'] . ' ' . $user['last_name']);
$ipAddress  = $user['ip_address'] ?? 'N/A';
$macAddress = $user['mac_address'] ?? 'N/A';
$planName   = $user['package_name'] ?? 'Unknown Plan';
$planSpeed  = $user['speed'] ?? 'N/A';
$expiryDate = $user['Expiry'] ?? null;
$planPrice  = isset($user['price']) ? 'KES ' . number_format($user['price'], 2) . ' / month' : 'N/A';

// ensure $user exists and extract numeric package price
$packagePriceNumber = 0.00;
if (!empty($user) && isset($user['price']) && is_numeric($user['price'])) {
    $packagePriceNumber = (float) $user['price'];
}

// formatted strings used in invoice modal
$priceFormatted      = 'KES ' . number_format($packagePriceNumber, 2);
$quantity            = 1;
$subtotal            = $packagePriceNumber * $quantity;
$totalDue = $subtotal + $totalExtraCharges;
$subtotalFormatted   = 'KES ' . number_format($subtotal, 2);
$totalDueFormatted = 'KES ' . number_format($totalDue, 2);

$sql = "SELECT first_name FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

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

$stmt->close();
$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Subscription Page</title>
  <link rel="icon" href="tt.png" type="x-icon" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
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
      
    /* Sidebar animation (ADDED) */
    .sidebar {
      transition: transform 0.3s ease-in-out;
    }
    .sidebar-hidden {
      transform: translateX(-100%);
    }

    /* Toast animations */
    @keyframes slideIn {
      0% { transform: translateX(100%); opacity: 0; }
      100% { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
      0% { transform: translateX(0); opacity: 1; }
      100% { transform: translateX(100%); opacity: 0; }
    }
    .toast {
      position: fixed;
      top: 1.5rem;
      right: 1.5rem;
      color: white;
      padding: 1rem 1.5rem;
      border-radius: 0.5rem;
      box-shadow: 0 10px 15px rgba(0, 0, 0, 0.2);
      font-weight: 600;
      animation: slideIn 0.5s ease-out forwards;
      z-index: 1100;
      cursor: default;
      background-color: #16a34a; /* green-600 */
    }
    .toast.error {
      background-color: #dc2626; /* red-600 */
    }
    .toast.hide {
      animation: slideOut 0.5s ease-in forwards;
    }

    /* Spinner Modal styles */
    #spinnerModal {
      position: fixed;
      inset: 0;
      background-color: rgba(0,0,0,0.5);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 1200;
    }
    #spinnerModal.active {
      display: flex;
    }
    .spinner {
      border: 4px solid rgba(59, 130, 246, 0.2); /* blue-500 with 20% opacity */
      border-top: 4px solid #3b82f6; /* blue-500 */
      border-radius: 50%;
      width: 3rem;
      height: 3rem;
      animation: spin 1s linear infinite;
      margin-bottom: 0.5rem;
    }
    @keyframes spin {
      0% { transform: rotate(0deg);}
      100% { transform: rotate(360deg);}
    }
    #spinnerText {
      color: #374151; /* gray-700 */
      font-weight: 600;
      font-size: 1rem;
    }

    /* Invoice Modal */
    #invoiceModal {
      position: fixed;
      inset: 0;
      background-color: rgba(0,0,0,0.5);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 1300;
      padding: 1rem;
    }
    #invoiceModal.active {
      display: flex;
    }
    #invoiceModal > div {
      background: white;
      max-width: 900px;
      width: 100%;
      border-radius: 0.5rem;
      box-shadow: 0 10px 25px rgba(0,0,0,0.15);
      position: relative;
      margin-top: 1.5rem; /* shift up from bottom */
    }
    #invoiceModal button.closeBtn {
      position: absolute;
      top: 1rem;
      right: 1rem;
      font-size: 1.5rem;
      background: none;
      border: none;
      cursor: pointer;
      color: #4b5563;
      transition: color 0.2s;
    }
    #invoiceModal button.closeBtn:hover {
      color: #000;
    }

    @media print {
      body * {
        visibility: hidden;
      }
      #invoiceModal, #invoiceModal * {
        visibility: visible;
      }
      #invoiceModal {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        max-height: none !important;
      }
    }

    body.modal-open {
      overflow: hidden;
    }

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
        <p class="text-sm text-gray-500">Client Dashboard</p>   </div>
      <!-- Close on mobile -->
      <button id="closeSidebarBtn" class="md:hidden p-2 rounded hover:bg-gray-100" aria-label="Close menu">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>
    <nav class="flex-1 p-4 space-y-4 overflow-y-auto no-scrollbar">
      <a href="index.php" class="block px-4 py-2 hover:bg-gray-100 rounded">Dashboard</a>
      <a href="#" class="block px-4 py-2 rounded bg-blue-100 text-blue-700 font-semibold">My Subscription</a>
      <a href="router.php" class="block px-4 py-2 hover:bg-gray-100">Router</a>
      <a href="support.php" class="block px-4 py-2 hover:bg-gray-100 rounded">Support</a>
      <a href="settings.php" class="block px-4 py-2 hover:bg-gray-100 rounded">Settings</a>
    </nav>
    <div class="p-4 border-t mb-4">
      <a href="logout.php" class="w-full block text-center bg-red-100 text-red-600 py-2 rounded hover:bg-red-600 hover:text-white transition-colors duration-300">Logout</a>
    </div>
  </aside>

    <!-- Main Content -->
    <main class="flex-1 p-6 space-y-6 overflow-auto">
      <!-- Hamburger button on mobile (ADDED) -->

      <h2 class="text-2xl font-bold mb-4">My Subscription</h2>

      <!-- the rest of your original content continues here (subscription, invoice, JS, etc.) -->
      <!-- I did not remove any of your lines; only added the toggle buttons + sidebar animation -->


      <?php if ($hasPaid): ?>
        <!-- ✅ PAID USER -->

      <div class="bg-white p-6 rounded-xl shadow-md">
        <h3 class="text-lg font-semibold mb-2">Current Plan</h3>
        <p class="text-gray-700">
          You are currently subscribed to <strong><?php echo htmlspecialchars($speed); ?></strong> at 
  <strong><?php echo htmlspecialchars($price); ?></strong> per month.
        </p>
        <p class="text-sm text-gray-500 mt-2">Next renewal: <strong><?php echo $expiry; ?></strong></p>


        <hr class="my-4" />

        <h4 class="text-md font-semibold mb-1">Change Plan</h4>

              <select id="planSelect" class="w-full p-2 border rounded">
  <option value="">Select a plan</option>
  <?php foreach ($packages as $pkg): ?>
      <option 
        value="<?php echo htmlspecialchars($pkg['id']); ?>"
        <?php echo ($pkg['id'] == $userPackageId) ? 'selected' : ''; ?>>
        <?php echo htmlspecialchars($pkg['speed'] . ' – KES ' . number_format($pkg['price'], 0)); ?>
      </option>
  <?php endforeach; ?>
</select>


        <button id="updatePlanBtn" class="mt-4 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 bounce-hover">
          Update Plan
        </button>

            <?php else: ?>
        <!-- ❌ UNPAID USER -->

 <div class="bg-white p-6 rounded-xl shadow-md">
        <h3 class="text-lg font-semibold mb-2">Current Plan</h3>
        <p class="text-gray-700">
          You are not subscribed to any package. Please choose a <strong>package</strong> to <strong>activate</strong>.
        </p>
        <p class="font-bold text-sm text-gray-500 mt-2">
  Installation Fee:
  <span class="font-bold text-gray-800">Ksh.
    <?php echo $installationFee; ?> /=
  </span>
</p>


        <hr class="my-4" />

        <h4 class="text-md font-semibold mb-1">Choose Package</h4>

              <select id="planSelect" class="w-full p-2 border rounded">
  <option value="">Select a plan</option>
  <?php foreach ($packages as $pkg): ?>
      <option 
        value="<?php echo htmlspecialchars($pkg['id']); ?>"
        <?php echo ($pkg['id'] == $userPackageId) ? 'selected' : ''; ?>>
        <?php echo htmlspecialchars($pkg['speed'] . ' – KES ' . number_format($pkg['price'], 0)); ?>
      </option>
  <?php endforeach; ?>
</select>


        <button id="updatePlanBtn" class="mt-4 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 bounce-hover">
          Select Package
        </button>

          <?php endif; ?>



      </div>
    </main>
  </div>

  <!-- Toast container -->
  <div id="toastContainer" class="fixed top-6 right-6 space-y-2 z-50"></div>

  <!-- Spinner Modal -->
  <div id="spinnerModal" aria-live="polite" aria-label="Loading" role="alert">
    <div class="flex flex-col items-center p-6 bg-white rounded-xl shadow-lg">
      <div class="spinner" aria-hidden="true"></div>
      <div id="spinnerText">Updating plan...</div>
    </div>
  </div>

  <!-- Invoice Modal -->
  <div id="invoiceModal" class="hidden" role="dialog" aria-modal="true" aria-labelledby="invoiceTitle">
    <div>
      <button class="closeBtn" aria-label="Close invoice modal">&times;</button>

      <div id="invoiceContent" class="p-8">
        <h2 class="text-xl font-semibold mb-4">View Invoice & Payment Details</h2>
        <div class="flex justify-between items-start border-b pb-4">
          <div>
            <h1 class="text-orange-600 text-2xl font-bold">D-Link Network Inc.</h1>
          <p class="text-sm text-gray-600 mt-1">dlinkwifi254@gmail.com<br>+254 758 788 020<br>Jua Kali,Lungalunga-Mtandikeni Road <br>Horo Horo Border, Kwale County, Kenya</p>
        
          </div>
          <div class="text-right text-sm hidden md:block">
            <h3 class="text-gray-700 font-semibold text-lg">INVOICE</h3>
             <p><strong>Invoice:</strong> <span id="invoiceNumber"></span></p>
         <p><strong>Date:</strong> <span id="invoiceDate"></span></p>
          <p><strong>Package Expiry:</strong> <span id="invoiceExpiry"></span></p>   </div>
        </div>

        <div class="flex justify-between mt-6 text-sm">
          <div>
            <p class="text-gray-500 font-semibold">BILL TO</p>
           <p class="text-black font-bold uppercase"><?php echo htmlspecialchars($fullName); ?></p>
          <p><?php echo htmlspecialchars($email); ?></p>
          <p><?php echo htmlspecialchars($phone); ?></p>
        </div>
          <div class="text-right">
            <p class="text-gray-500 font-semibold mb-1">STATUS</p>
            <span id="invoiceStatus" class="bg-yellow-200 text-yellow-800 px-3 py-1 rounded-full text-xs font-bold">PURCHASE</span>
          </div>
        </div>

        <div class="mt-6">
       <table class="w-full text-sm text-left border-collapse">
            <thead>
              <tr class="bg-gray-100 text-gray-600 uppercase text-xs">
                <th class="px-4 py-2 font-semibold">Description</th>
                <th class="px-4 py-2 font-semibold">Price</th>
              <th class="px-4 py-2 font-semibold hidden md:block">Quantity</th>
                <th class="px-4 py-2 font-semibold">Total</th>
              </tr>
            </thead>
           <tbody>
     <tr class="border-t">
                <td class="px-4 py-2" id="invoiceDescription">Package Subscription (<?php echo htmlspecialchars($planSpeed); ?>)</td>
                <td class="px-4 py-2" id="invoicePrice"><?php echo $priceFormatted; ?></td>
               <td class="hidden md:block px-4 py-2"><?php echo $quantity; ?></td>
                <td class="px-4 py-2 whitespace-nowrap" id="invoiceTotal"><?php echo $priceFormatted; ?></td>
              </tr>

<?php foreach ($extraCharges as $charge): ?>
<tr class="border-t text-orange-600 font-semibold">
    <td class="px-4 py-2">
        <?php echo htmlspecialchars($charge['charge_name']); ?>
    </td>

    <td class="px-4 py-2">
        KES <?php echo number_format($charge['amount'], 2); ?>
    </td>

    <td class="hidden md:block px-4 py-2">
        1
    </td>

    <td class="px-4 py-2 whitespace-nowrap">
        KES <?php echo number_format($charge['amount'], 2); ?>
    </td>
</tr>
<?php endforeach; ?>

</tbody>

          </table>
        </div>

        <div class="flex justify-between items-center mt-6 flex-wrap gap-4">
          <button id="payNowBtn"
onclick="payWithPaystack()"
class="w-full md:w-auto bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded shadow bounce-hover">
Pay Now
</button>
<div class="hidden md:block bg-gray-100 p-4 rounded w-64 text-sm">
            <div class="flex justify-between"><span>Subtotal:</span><span class="font-semibold" id="subtotal"><?php echo $subtotalFormatted; ?></span></div>
            <div class="flex justify-between mt-2 text-base font-bold text-orange-600">
              <span>Total Due:</span><span id="totalDue"><?php echo $totalDueFormatted; ?></span>
            </div>
          </div>
        </div>

        <div class="mt-6 text-center text-sm text-gray-600">
          <p class="text-orange-600 font-bold text-lg mb-2 animate-bounce">Thank you for your business!</p>
          <div class="flex justify-center space-x-4">
            <p class="text-blue-600 cursor-pointer hover:underline" onclick="window.print()">Print Invoice</p>
            <p class="text-blue-600 cursor-pointer hover:underline" id="downloadInvoiceBtn">Download Invoice</p>
          </div>
        </div>
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
</script>

<script src="https://js.paystack.co/v1/inline.js"></script>
<script>

function payWithPaystack() {

    if (!price) {
        showToast("No plan selected yet.", "error");
        return;
    }

    const payBtn = document.getElementById("payNowBtn");

    payBtn.disabled = true;
    payBtn.classList.add("opacity-50", "cursor-not-allowed");

    payBtn.innerHTML = `
        <svg class="animate-spin h-5 w-5 text-white inline-block mr-2"
             xmlns="http://www.w3.org/2000/svg"
             fill="none"
             viewBox="0 0 24 24">
          <circle class="opacity-25"
                  cx="12"
                  cy="12"
                  r="10"
                  stroke="currentColor"
                  stroke-width="4"></circle>
          <path class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
        </svg>
        Processing...
    `;

    const today = new Date();

    const y = today.getFullYear();
    const m = String(today.getMonth() + 1).padStart(2, '0');
    const d = String(today.getDate()).padStart(2, '0');

    const invoiceNumber =
        `INV-dlinknetwork-${y}${m}${d}`;

    // FIRST PAY EXTRA CHARGES
    if (parseFloat(totalExtraCharges) > 0) {

        showToast(
            "Paying outstanding charges first..."
        );

        initializeExtraCharges(
            invoiceNumber,
            payBtn
        );

    } else {

        initializeSubscription(
            invoiceNumber,
            payBtn
        );

    }
}

function initializeExtraCharges(
    invoiceNumber,
    payBtn
) {

    fetch("initialize_extra_charges.php", {
        method: "POST",
        headers: {
            "Content-Type":
                "application/x-www-form-urlencoded"
        },
        body:
            "invoice_number=" +
            encodeURIComponent(invoiceNumber)
    })

    .then(r => r.json())

    .then(data => {

        if (!data.success) {

            showToast(
                data.message ||
                "Failed to initialize extra charges",
                "error"
            );

            resetPayButton(payBtn);
            return;
        }

        showToast(
            "Extra charges STK sent"
        );

        pollExtraChargeStatus(
            data.reference,
            invoiceNumber,
            payBtn
        );

    })

    .catch(error => {

        console.error(error);

        showToast(
            "Extra charges payment error",
            "error"
        );

        resetPayButton(payBtn);

    });
}

function pollExtraChargeStatus(
    reference,
    invoiceNumber,
    payBtn
) {

    let attempts = 0;

    const interval = setInterval(() => {

        attempts++;

        fetch(
            "check_payment_sub_status.php?reference=" +
            encodeURIComponent(reference)
        )

        .then(r => r.json())

        .then(data => {

            if (!data.success) {
                return;
            }

            if (data.status === "completed") {

    clearInterval(interval);

    showToast(
        "Extra charges paid successfully. Waiting 60 seconds before subscription payment..."
    );

    payBtn.disabled = true;

payBtn.classList.add(
    "opacity-50",
    "cursor-not-allowed"
);

    let countdown = 60;

    const countdownInterval = setInterval(() => {

        payBtn.innerHTML =
            `Subscription payment in ${countdown}s`;

        countdown--;
if (countdown < 0) {

    clearInterval(countdownInterval);

    payBtn.disabled = false;

    payBtn.classList.remove(
        "opacity-50",
        "cursor-not-allowed"
    );

    payBtn.innerHTML =
        "Sending Subscription STK...";

    initializeSubscription(
        invoiceNumber,
        payBtn
    );
}

    }, 1000);
}

            if (data.status === "failed") {

                clearInterval(interval);

                showToast(
                    data.failure_reason ||
                    "Extra charges payment failed",
                    "error"
                );

                resetPayButton(payBtn);
            }

        });

        if (attempts >= 60) {

            clearInterval(interval);

            showToast(
                "Extra charges timeout",
                "error"
            );

            resetPayButton(payBtn);
        }

    }, 2000);
}

function initializeSubscription(
    invoiceNumber,
    payBtn
) {

    showToast(
        "Sending subscription STK..."
    );

    fetch("initialize_sub_onasis.php", {

        method: "POST",

        headers: {
            "Content-Type":
                "application/x-www-form-urlencoded"
        },

        body:
            "package_id=" +
            encodeURIComponent(planSelect.value) +
            "&invoice_number=" +
            encodeURIComponent(invoiceNumber)
    })

    .then(r => r.json())

    .then(data => {

        if (!data.success) {

            showToast(
                data.message ||
                "Subscription initialization failed",
                "error"
            );

            resetPayButton(payBtn);
            return;
        }

        pollPaymentStatus(
            data.reference
        );

    })

    .catch(error => {

        console.error(error);

        showToast(
            "Subscription payment error",
            "error"
        );

        resetPayButton(payBtn);

    });
}

function resetPayButton(payBtn) {

    payBtn.disabled = false;

    payBtn.classList.remove(
        "opacity-50",
        "cursor-not-allowed"
    );

    payBtn.innerHTML = "Retry Payment";
}

function pollPaymentStatus(reference) {

    const payBtn = document.getElementById("payNowBtn");

    let attempts = 0;

    const interval = setInterval(() => {

        attempts++;

        fetch("check_payment_sub_status.php?reference=" + encodeURIComponent(reference))

        .then(response => response.json())

        .then(data => {

            if (!data.success) {
                return;
            }

            // PAYMENT SUCCESS
            if (data.status === "completed") {

                clearInterval(interval);

                showToast("Payment successful");

                payBtn.disabled = false;
                payBtn.classList.remove("opacity-50", "cursor-not-allowed");
                payBtn.innerHTML = "Paid";

                setTimeout(() => {
                    window.location.reload();
                }, 2000);

            }

            // PAYMENT FAILED
            else if (data.status === "failed") {

                clearInterval(interval);

                showToast(
                    data.failure_reason || "Payment failed",
                    "error"
                );

                payBtn.disabled = false;
                payBtn.classList.remove("opacity-50", "cursor-not-allowed");
                payBtn.innerHTML = "Retry Payment";

            }

        })

        .catch(error => {
            console.error(error);
        });

        // Stop polling after 2 minutes
        if (attempts >= 60) {

            clearInterval(interval);

            showToast(
                "Payment confirmation timeout. Please refresh later.",
                "error"
            );

            payBtn.disabled = false;
            payBtn.classList.remove("opacity-50", "cursor-not-allowed");
            payBtn.innerHTML = "Retry Payment";
        }

    }, 2000);
}


 const totalExtraCharges =
<?php echo json_encode($totalExtraCharges); ?>;
const currentPackageId = <?php echo json_encode($userPackageId); ?>;
  const currentExpiry = <?php echo json_encode($subscription['Expiry'] ?? null); ?>;


  const updatePlanBtn = document.getElementById('updatePlanBtn');
  const planSelect = document.getElementById('planSelect');
  const toastContainer = document.getElementById('toastContainer');
  const spinnerModal = document.getElementById('spinnerModal');
  const invoiceModal = document.getElementById('invoiceModal');
  const invoiceDescription = document.getElementById('invoiceDescription');
  const invoicePrice = document.getElementById('invoicePrice');
  const invoiceTotal = document.getElementById('invoiceTotal');
  const subtotalSpan = document.getElementById('subtotal');
  const totalDueSpan = document.getElementById('totalDue');
  const invoiceStatus = document.getElementById('invoiceStatus');
  const closeInvoiceBtn = invoiceModal.querySelector('.closeBtn');
  const downloadInvoiceBtn = document.getElementById('downloadInvoiceBtn');

  function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast ${type === 'success' ? '' : 'error'}`;
    toast.textContent = message;
    toastContainer.appendChild(toast);

    setTimeout(() => {
      toast.classList.add('hide');
      setTimeout(() => {
        toast.remove();
      }, 500);
    }, 2000);
  }

  function showSpinner() {
    spinnerModal.classList.add('active');
  }
  function hideSpinner() {
    spinnerModal.classList.remove('active');
  }

  function showInvoice() {
    invoiceModal.classList.add('active');
    document.body.classList.add('modal-open');

const today = new Date();
    const y = today.getFullYear();
    const mont = today.toLocaleString('en-US', { month: 'short' });
    const m = String(today.getMonth() + 1).padStart(2, '0');
    const d = String(today.getDate()).padStart(2, '0');
    const formattedDate = `${y}${m}${d}`;
    document.getElementById('invoiceNumber').textContent = `INV-dlinknetwork-${formattedDate}`;
    const dte = `${mont} ${d}, ${y}`;
    document.getElementById('invoiceDate').textContent = dte;
    
    function addDaysFormatted(days) {
    const today = new Date();
    const futureDate = new Date(today);
    futureDate.setDate(futureDate.getDate() + days);

    // Format as "M j, Y"
    const options = { month: 'short', day: 'numeric', year: 'numeric' };
    return futureDate.toLocaleDateString('en-US', options);

}

const newExpiry = addDaysFormatted(30);
document.getElementById('invoiceExpiry').textContent = newExpiry;



  }
  function hideInvoice() {
    invoiceModal.classList.remove('active');
    document.body.classList.remove('modal-open');
  } 


let price = null; // Declare globally or in a higher scope


if (updatePlanBtn) {
updatePlanBtn.addEventListener('click', () => {


  const selectedPlan = planSelect.value.trim();

  if (!selectedPlan) {
    showToast('Please select a plan before updating.', 'error');
    return;
  }

  // Check if user selected same plan & subscription is still active
  const today = new Date();
  const expiryDate = currentExpiry ? new Date(currentExpiry) : null;

  if (parseInt(selectedPlan) === parseInt(currentPackageId) && expiryDate && expiryDate >= today) {
    showToast('You are already subscribed to this plan. Please choose a different package.', 'error');
    return;
  }

  // Extract plan details for invoice
  const selectedOption = planSelect.options[planSelect.selectedIndex].text;
  const planParts = selectedOption.split('–').map(s => s.trim());
  const description = planParts[0] || "Subscription Plan";
  price = planParts[1] || "KES 0";


// Convert selected plan price to number
const planAmount = parseFloat(price.replace(/[^0-9.]/g, '')) || 0;

// PHP → JS installation fee
const installationFee = <?php echo json_encode($installationFee); ?>;

// Calculate totals
const subtotal = planAmount;
const totalDue = subtotal + installationFee;


  invoiceDescription.textContent = "Package Subscription " +"("+ description +")";
  invoicePrice.textContent = price;
  invoiceTotal.textContent = price;
subtotalSpan.textContent = `Ksh ${subtotal.toFixed(2)}`;
totalDueSpan.textContent = `Ksh ${totalDue.toFixed(2)}`;
  invoiceStatus.textContent = "UPGRADE";
  invoiceStatus.className = "bg-yellow-200 text-yellow-800 px-3 py-1 rounded-full text-xs font-bold";

  showSpinner();

  setTimeout(() => {
    hideSpinner();
    showInvoice();
  }, 2000);
});
}

  closeInvoiceBtn.addEventListener('click', () => {
    hideInvoice();
   /* showToast('Your plan has been updated successfully!', 'success');*/
  });

  invoiceModal.addEventListener('click', (e) => {
    if (e.target === invoiceModal) {
      hideInvoice();
    /*  showToast('Your plan has been updated successfully!', 'success');*/
    }
  });

  downloadInvoiceBtn.addEventListener('click', () => {
     let jsVar = price;
  alert(jsVar);
    const element = document.getElementById('invoiceContent');
    const opt = {
      margin: 0.5,
      filename: 'invoice-dlinknetwork.pdf',
      image: { type: 'jpeg', quality: 0.98 },
      html2canvas: { scale: 2 },
      jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
    };
    html2pdf().set(opt).from(element).save();
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

</body>
</html

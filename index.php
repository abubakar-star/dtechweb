<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require 'db.php';
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

// Redirect unpaid users
if (!$hasPaid) {
    header("Location: subscription.php");
    exit();
}


$sql = "SELECT first_name, last_name, phone_number, status, created_at, email FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$fullName = trim($user['first_name'] . ' ' . $user['last_name']);
$phone = $user['phone_number'];
$first_name = $user['first_name'];
$email = $user['email'];
$userStatus = $user['status'];
$payd = $user['created_at'];

// Fetch user + router + package info
$sql = "SELECT 
            u.first_name, 
            u.last_name, 
            r.mac_address, 
            r.ip_address, 
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
$user = $result->fetch_assoc();

// Assign variables
$fullName   = trim($user['first_name'] . ' ' . $user['last_name']);
$ipAddress  = $user['ip_address'] ?? 'N/A';
$macAddress = $user['mac_address'] ?? 'N/A';
$planName   = $user['package_name'] ?? 'Unknown Plan';
$planSpeed  = $user['speed'] ?? 'N/A';
$expiryDate = $user['Expiry'] ?? null;
$planPrice  = isset($user['price']) ? 'KES ' . number_format($user['price'], 2) . ' / month' : 'N/A';
$planPriceuser  = isset($user['price']) ? 'KES ' . number_format($user['price'], 2) : 'N/A';
// ensure $user exists and extract numeric package price
$packagePriceNumber = 0.00;
if (!empty($user) && isset($user['price']) && is_numeric($user['price'])) {
    $packagePriceNumber = (float) $user['price'];
}

// formatted strings used in invoice modal
$priceFormatted      = 'KES ' . number_format($packagePriceNumber);
$quantity            = 1;
$subtotal            = $packagePriceNumber * $quantity;
$subtotalFormatted   = 'KES ' . number_format($subtotal, 2);
$totalDueFormatted   = $subtotalFormatted;

// Fetch the latest notice
$notice_sql = "SELECT message, link FROM notices ORDER BY created_at DESC LIMIT 1";
$notice_result = $conn->query($notice_sql);
$notice = $notice_result->fetch_assoc();

/*
// Fetch payment history (include invoice_number)
$sql = "SELECT invoice_number, amount, payment_date, status 
        FROM payments 
        WHERE user_id = ? 
        ORDER BY payment_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$paymentHistoryResult = $stmt->get_result();
$payments = $paymentHistoryResult->fetch_all(MYSQLI_ASSOC);

// Get latest invoice number or default to N/A
$invoice_number = 'N/A';
if (!empty($payments) && !empty($payments[0]['invoice_number'])) {
    $invoice_number = $payments[0]['invoice_number'];
    
}
*/

// Fetch total paid from payments table
$sql = "SELECT COALESCE(SUM(amount), 0) AS total_paid 
        FROM payments 
        WHERE user_id = ? AND status = 'completed'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$paymentData = $result->fetch_assoc();
if ($expiryDate && strtotime($expiryDate) <= time()) {
    // Expired — total paid is zero
    $totalPaid = 'KES 0.00';
} else {
    $totalPaid = 'KES ' . number_format($paymentData['total_paid'], 2);
}
$paidAmountNumber = floatval(str_replace(['KES ', ','], '', $totalPaid));

// Fetch payment history
$sql = "SELECT transaction_id, amount, payment_date, status 
        FROM payments 
        WHERE user_id = ?
        AND status = 'completed' 
        ORDER BY payment_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$paymentHistoryResult = $stmt->get_result();
$payments = $paymentHistoryResult->fetch_all(MYSQLI_ASSOC);



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

$ip = $_SERVER['REMOTE_ADDR'];

$user_id = $_SESSION['user_id']; 

// Get IP + device info
$ip_log = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

// 1. Increment visits counter
$conn->query("UPDATE users SET visit_count = visit_count + 1 WHERE id = $user_id");

// 2. Log the visit in user_visits table
$stmt = $conn->prepare("INSERT INTO user_visits (user_id, ip_address, user_agent) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $user_id, $ip_log, $user_agent);
$stmt->execute();

$stmt->close();


$incDte = date('M d, Y');
$invDteExp = date('M d, Y',strtotime('+30 days'));


function generateInvoiceNumber($conn, $length = 12) {

      $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    // Removed confusing characters like O, 0, I, 1

    do {

        $invoice = '';

        // Generate random invoice
        for ($i = 0; $i < $length; $i++) {
            $invoice .= $chars[random_int(0, strlen($chars) - 1)];
        }

        // Check if invoice exists within last 1 day
        $stmt = $conn->prepare("
            SELECT id 
            FROM payments 
            WHERE invoice_number = ?
            AND payment_date >= NOW() - INTERVAL 1 DAY
            LIMIT 1
        ");

        $stmt->bind_param("s", $invoice);
        $stmt->execute();
        $stmt->store_result();

        $exists = $stmt->num_rows > 0;

        $stmt->close();

    } while ($exists);

    return $invoice;
}

/* GENERATE NEW INVOICE NUMBER HERE */
$invDteInv = generateInvoiceNumber($conn);

$conn->close();

?>



<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>D-LINK NETWORK</title>
  <link rel="icon" href="tt.png" type="x-icon" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
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
      }
    }
    body.modal-open {
      overflow: hidden;
    }

    /* Prevent content shift when sidebar shows on mobile */
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


/* Blue pulsing dot for reconnecting */
.pulse-blue {
  width: 8px;
  height: 8px;
  background-color: #2563eb; /* Tailwind blue-600 */
  border-radius: 9999px;
  display: inline-block;
  position: relative;
}

.pulse-blue::after {
  content: "";
  position: absolute;
  inset: 0;
  border-radius: 9999px;
  background-color: #2563eb;
  animation: pulseBlue 1.5s infinite;
}

@keyframes pulseBlue {
  0% {
    transform: scale(1);
    opacity: 0.9;
  }
  70% {
    transform: scale(2.2);
    opacity: 0;
  }
  100% {
    opacity: 0;
  }
}

#net-toast {
  position: fixed; top: 20px; left: 50%;
  transform: translateX(-50%) translateY(-80px);
  display: flex; align-items: center; gap: 10px;
  padding: 12px 20px; border-radius: 12px;
  border: 1px solid #ddd; background: #fff;
  font-size: 14px; font-weight: 500;
  transition: transform .4s ease, opacity .3s ease;
  opacity: 0; z-index: 9999; white-space: nowrap;
}
#net-toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
#net-toast .dot { width:10px; height:10px; border-radius:50%; }
      
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
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M4 6h16M4 12h16M4 18h16" />
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
  <!-- Sidebar (responsive) -->
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
      <a href="#" class="block px-4 py-2 rounded bg-blue-100 text-blue-700 font-semibold">Dashboard</a>
      <a href="subscription.php" class="block px-4 py-2 hover:bg-gray-100 rounded">My Subscription</a>
      <a href="router.php" class="block px-4 py-2 hover:bg-gray-100 rounded">Router</a>
      <a href="support.php" class="block px-4 py-2 hover:bg-gray-100 rounded">Support</a>
      <a href="settings.php" class="block px-4 py-2 hover:bg-gray-100 rounded">Settings</a>
    </nav>
    <div class="p-4 border-t mb-4">
      <a href="logout.php" class="w-full block text-center bg-red-100 text-red-600 py-2 rounded hover:bg-red-600 hover:text-white transition-colors duration-300">
        Logout
      </a>
    </div>
  </aside>

  <!-- Main Dashboard (no gap to sidebar on desktop: uses md:ml-64) -->
  <main class="flex-1 p-6 space-y-6 overflow-auto">

<?php if ($userStatus === 'terminated'): ?>
  <div class="bg-red-100 border border-red-300 text-red-800 px-6 py-4 rounded-xl shadow flex items-start gap-3">
    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 mt-0.5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
        d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16a2 2 0 001.73 3z"/>
    </svg>

    <div>
      <p class="font-bold text-lg">Account Terminated</p>
      <p class="text-sm mt-1">
        Contact your ISP for account reactivation. {+254758788020}
      </p>
    </div>
  </div>
<?php endif; ?>


    <!-- Top Bar -->
   <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
  <!-- Left Section: Welcome + IP/MAC -->
  <div class="flex flex-col">
    <h2 class="text-2xl font-bold">Welcome, <?php echo htmlspecialchars($fullName); ?>!</h2>
    <p class="text-sm text-gray-600">
      I.P ADDRESS: <?php echo $ip; ?>
    </p>
  </div>

  <!-- Right Section: Buttons + Status -->
  <div class="flex flex-col sm:flex-row sm:items-center gap-4">
  <button id="expiryBtn"
    class="flex sm:justify-start justify-center items-center gap-2 bg-gray-200 text-gray-700 text-sm px-4 py-2 rounded transition cursor-default w-full sm:w-auto">
    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-600" fill="none" viewBox="0 0 24 24"
        stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M8 7V3m8 4V3m-9 8h10m2 9a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v11a2 2 0 002 2h14z" />
    </svg>
    <span id="expiryText">
        <?php echo $expiryDate ? 'Expiry: ' . date("d M Y", strtotime($expiryDate)) : 'Expiry: N/A'; ?>
    </span>
</button>

   <div id="invoiceBtnWrapper" class="hidden">
  <button id="invoiceBtn"
    class="bg-blue-600 text-white text-sm px-4 py-2 rounded hover:bg-blue-700 transition w-full sm:w-auto bounce-hover">
    View Invoice & Payment Details
  </button>
</div>


    <span id="statusBadge"
      class="block text-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-700">
      Status: Active
    </span>
  </div>
</div>


    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div class="bg-white p-4 rounded-xl shadow">
        <h3 class="text-sm text-gray-500">Current Plan</h3>
        <p class="text-xl font-bold"><?php echo htmlspecialchars($planSpeed); ?></p>
        <p class="text-xs text-gray-400 mt-1"><?php echo htmlspecialchars($planPrice); ?></p>
      </div>
      <div class="bg-white p-4 rounded-xl shadow">
        <h3 class="text-sm text-gray-500">Total Paid</h3>
        <p class="text-xl font-bold text-blue-700"><?php echo $planPriceuser; ?></p>
      </div>
      <div class="p-4 rounded-xl shadow <?php echo ($paidAmountNumber == 0) ? 'bg-red-600 text-white' : 'bg-white'; ?>">
        <h3 class="text-sm <?php echo ($paidAmountNumber == 0) ? 'text-white' : 'text-gray-500'; ?>">Next Payment Due</h3>
        <p class="text-xl font-bold <?php echo ($paidAmountNumber == 0) ? 'text-white' : 'text-red-600'; ?>">
          <?php echo $expiryDate ? date("M j, Y", strtotime($expiryDate)) : 'N/A'; ?>
        </p>
      </div>
    </div>

    <!-- Payment History -->
    <div class="bg-white p-6 rounded-xl shadow">
      <h3 class="text-lg font-bold mb-4">Payment History</h3>
      <div class="overflow-x-auto">
       <table class="w-full text-sm text-left">
  <thead>
    <tr class="text-gray-600 border-b">
      <!-- Month column hidden on mobile -->
      <th class="py-2 hidden sm:table-cell">Month</th>

      <!-- Date Paid column appears first on mobile -->
      <th class="py-2 order-first sm:order-none">Date Paid</th>

      <th class="py-2">Amount</th>
      <th class="py-2 hidden sm:table-cell">Status</th>
      <th class="py-2">Receipt</th> <!-- ✅ New column -->
    </tr>
  </thead>
  <tbody id="paymentTableBody">
    <?php if (!empty($payments)): ?>
        <?php foreach ($payments as $payment): ?>
            <tr class="border-b <?php echo $payment['status'] === 'failed' ? 'bg-red-50' : ($payment['status'] === 'pending' ? 'bg-yellow-50' : ''); ?>">
                
                <!-- Month Column -->
                <td class="py-2 hidden sm:table-cell">
                    <?php echo date("F Y", strtotime($payment['payment_date'])); ?>
                </td>

                <!-- Date Paid Column -->
                <td class="py-2 pr-6 sm:pr-2 order-first sm:order-none">
                    <?php echo date("M j, Y", strtotime($payment['payment_date'])); ?>
                </td>

                <!-- Amount Column -->
                <td class="py-2 pr-6 sm:pr-2">
                    KES <?php echo number_format($payment['amount'], 2); ?>
                </td>

                <!-- Status Column -->
                <td class="hidden sm:table-cell py-2 font-semibold 
                    <?php 
                        echo $payment['status'] === 'completed' ? 'text-green-600' : 
                            ($payment['status'] === 'pending' ? 'text-yellow-600' : 'text-red-600'); 
                    ?>">
                    <?php echo ucfirst($payment['status']); ?>
                </td>

        <!-- Invoice Column -->
                <td class="py-2">
                  <?php if (!empty($payment['transaction_id'])): ?>
                      <button 
                        class="text-blue-600 hover:underline"
                        onclick="viewInvoice('<?php echo $payment['transaction_id']; ?>','<?php echo $payment['payment_date']; ?>')">
                        View
                      </button>
                      |
                      <button 
                        class="text-green-600 hover:underline"
                        onclick="downloadInvoiceByNumber('<?php echo $payment['transaction_id']; ?>','<?php echo $payment['payment_date']; ?>')">
                        Download
                      </button>
                  <?php else: ?>
                      <span class="text-gray-400">N/A</span>
                  <?php endif; ?>
                </td>

            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="4" class="py-4 text-center text-gray-500">No payment history found</td>
        </tr>
    <?php endif; ?>
  </tbody>
</table>

      </div>

      <div class="mt-4 flex justify-center items-center space-x-2">
        <label for="showEntries" class="text-sm text-gray-700">Show</label>
        <select id="showEntries" class="border rounded px-2 py-1 text-sm">
          <option value="5">5</option>
          <option value="10">10</option>
          <option value="20">20</option>
          <option value="all">All</option>
        </select>
        <span class="text-sm text-gray-700">entries</span>
      </div>
    </div>

    <!-- Notification -->
    <?php if (!empty($notice)): ?>
      <div class="bg-yellow-100 p-4 rounded-xl shadow text-yellow-800 text-sm">
        <strong>Notice:</strong> <?php echo htmlspecialchars($notice['message']); ?>
        <?php if (!empty($notice['link'])): ?>
          <a href="<?php echo htmlspecialchars($notice['link']); ?>" class="text-blue-600 underline" target="_blank">Read more</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </main>
</div>

<!-- INVOICE MODAL -->
<div id="invoiceModal" class="fixed inset-0 z-50 bg-black bg-opacity-50 flex items-center justify-center hidden p-4">
  <div class="bg-white max-w-4xl w-full rounded shadow-lg relative mt-6 overflow-hidden">
    <button onclick="closeModal()" class="absolute top-4 right-4 text-gray-600 hover:text-black text-xl" aria-label="Close invoice">&times;</button>

<div id="paidStamp" class="hidden absolute inset-0 flex items-center justify-center pointer-events-none">
    <div class="rotate-[-20deg] border-4 border-green-600 text-green-600 text-5xl font-bold px-8 py-4 rounded-xl opacity-20">
        PAID
    </div>
</div>

    <div id="invoiceContent" class="p-8">
      <h2 id="head2" class="text-xl font-semibold mb-4">View Invoice & Payment Details</h2>
      <div class="flex justify-between items-start border-b pb-4">
        <div>
          <h1 class="text-orange-600 text-2xl font-bold">D-Link Network Inc.</h1>
          <p class="text-sm text-gray-600 mt-1">dlinkwifi254@gmail.com<br>+254 758 788 020<br>Jua Kali,Lungalunga-Mtandikeni Road <br>Horo Horo Border, Kwale County, Kenya</p>
        </div>
        <div class="text-right text-sm hidden md:block">
          <h3 class="text-gray-700 font-semibold text-lg"><span id="chrct">INVOICE</span></h3>
          <p><strong><span id="changeRec">Invoice :</span></strong><span id="changeRecWord"> INV</span><span id="changeDLWord">-dlink-</span> <span id="invoiceNumber" class="text-green-600"><?php echo $invDteInv ?></span></p>
          <p><strong>Date: </strong><span id="invoiceReceipt"><?php echo $incDte ?></span></p>
          <p id="invoiceDue"><strong>Package Expiry:</strong> <?php echo $invDteExp ?></p>
          <p id="paymentSuccessMsg"
   class="hidden text-green-600 font-semibold mt-1">
  Payment received successfully.
</p>          
        </div>
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
          <span id="invoiceStatus" class="bg-yellow-200 text-yellow-800 px-3 py-1 rounded-full text-xs font-bold">PENDING</span>
        </div>
      </div>

      <div class="mt-6">
        <table class="w-full text-sm text-left border-collaps">
          <thead>
            <tr class="bg-gray-100 text-gray-600 uppercase text-xs">
              <th class="px-4 py-2 font-semibold">Description</th>
              <th class=" px-4 py-2 font-semibold">Price</th>
              <th class="hidden md:block px-4 py-2 font-semibold">Quantity</th>
              <th class="px-4 py-2 font-semibold">Total</th>
            </tr>
          </thead>
          <tbody>
            <tr class="border-t">
              <td class="px-4 py-2">Package Subscription (<?php echo htmlspecialchars($planSpeed); ?>)</td>
              <td class="px-4 py-2"><?php echo $priceFormatted; ?></td> <!-- Price -->
              <td class="hidden md:block px-4 py-2"><?php echo $quantity; ?></td>       <!-- Quantity -->
              <td class="px-4 py-2 whitespace-nowrap"><?php echo $priceFormatted; ?></td> <!-- Total -->
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex justify-between items-center mt-6 flex-wrap gap-4">

      <button id="payNowBtn" class="w-full md:w-auto bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded shadow bounce-hover">Pay Now</button>
         
        <div class="hidden md:block bg-gray-100 p-4 rounded w-64 text-sm">
          <div class="flex justify-between"><span>Subtotal:</span><span class="font-semibold"><?php echo $subtotalFormatted; ?></span></div>
          <div class="flex justify-between mt-2 text-base font-bold text-orange-600">
            <span id="totalLBL">Total Due:</span><span id="totalLBLVl"><?php echo $totalDueFormatted; ?></span>
          </div>
        </div>
      </div>

      <div class="mt-6 text-center text-sm text-gray-600">
        <p class="text-orange-600 font-bold text-lg mb-2 animate-bounce">Thank you for your business!</p>
        <div class="flex justify-center space-x-4">
          <p class="text-blue-600 cursor-pointer hover:underline" onclick="window.print()">Print Invoice</p>
          <p class="text-blue-600 cursor-pointer hover:underline" onclick="downloadInvoice()">Download Invoice</p>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="net-toast"></div>

<!-- LOADING SPINNER OVERLAY -->
<div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
  <div class="flex flex-col items-center">
    <svg class="animate-spin h-10 w-10 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path class="opacity-75" fill="currentColor"
        d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z">
      </path>
    </svg>
    <p class="text-white mt-3 text-sm">Loading invoice...</p>
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
document.getElementById("payNowBtn").addEventListener("click", function () {
    const payBtn = this;

   // Show spinner + disable button + grey it out
    payBtn.disabled = true;
    payBtn.classList.remove("bg-blue-600", "hover:bg-blue-700");
    payBtn.classList.add("opacity-50", "cursor-not-allowed");
    payBtn.innerHTML = `
        <svg class="animate-spin h-5 w-5 text-white inline-block mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
        </svg>
        Processing...
    `;
      fetch("initialize_onasis.php", {
    method: "POST",

    headers: {
        "Content-Type": "application/x-www-form-urlencoded"
    },

    body: new URLSearchParams({
        invoice_number:
            document.getElementById("invoiceNumber").innerText
    })
})
    .then(res => res.json())
    .then(data => {

        console.log(data);

        if (data.success) {
const phoneNumber = "<?php echo htmlspecialchars($phone); ?>";
     Swal.fire({
    title: 'Waiting for Payment',
    html: `
        <div style="font-size:16px;">
            STK Push sent to <b>${phoneNumber}</b><br>
            <span style="color:#6b7280;">Complete the payment on your phone...</span>
        </div>
    `,
    allowOutsideClick: false,
    didOpen: () => {
        Swal.showLoading();
    }
});

const reference = data.reference;

const interval = setInterval(() => {

    fetch("check_payment_status.php?reference=" + reference)
        .then(res => res.json())
        .then(payment => {

            console.log(payment);

            if (payment.status === 'completed') {
              

                clearInterval(interval);

                Swal.fire({
                    icon: 'success',
                    title: 'Payment Successful',
                    text: 'Your account has been activated.',
                    confirmButtonColor: '#22c55e'
                });

payBtn.classList.remove(
    "opacity-50",
    "cursor-not-allowed"
);

const statusBadge1 = document.getElementById("invoiceStatus");
if (statusBadge1) {
  statusBadge1.textContent = "PAID";
  statusBadge1.classList.remove("bg-yellow-200", "text-yellow-800");
  statusBadge1.classList.add("bg-green-200", "text-green-800");
}

payBtn.innerHTML = `
    Invoice Paid
    <svg class="animate-spin h-4 w-4 text-white inline-block ml-2"
         xmlns="http://www.w3.org/2000/svg"
         fill="none"
         viewBox="0 0 24 24">
        <circle class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4">
        </circle>
        <path class="opacity-75"
              fill="currentColor"
              d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z">
        </path>
    </svg>
`;


 document.getElementById("paidStamp").classList.remove("hidden");

 
setTimeout(() => {
    window.location.href = "index.php";
}, 7500);

            } // CANCELLED
else if (payment.status === 'cancelled') {

    clearInterval(interval);

    Swal.fire({
        icon: 'warning',
        title: 'Transaction Cancelled',
        text: 'You cancelled the payment request.',
        confirmButtonColor: '#f59e0b'
    });
payBtn.disabled = false;
    payBtn.innerHTML = "Retry Payment";

    payBtn.classList.remove(
    "opacity-50",
);
}
// FAILED
else if (payment.status === 'failed') {

    clearInterval(interval);

    let reason = payment.failure_reason || "Transaction was not completed.";

/* Friendly error messages */
if (
    reason.includes("The initiator information is invalid.")
) {
    reason = "Wrong M-PESA PIN entered.";
}

else if (
    reason.includes("DS timeout user cannot be reached.")
) {
    reason = "Phone cannot be reached.";
}

else if (
    reason.includes("Request Cancelled by user.")
) {
    reason = "You cancelled the payment request.";
}

else if (
    reason.includes("The balance is insufficient for the transaction.")
) {
    reason = "Insufficient M-PESA balance.";
}

    Swal.fire({
        icon: 'error',
        title: 'Payment Failed',
        text: reason,
        confirmButtonColor: '#ef4444'
    });

    payBtn.disabled = false;

    payBtn.innerHTML = "Retry Payment";

    payBtn.classList.remove(
        "opacity-50",
        "cursor-not-allowed"
    );
}

// TIMEOUT
else if (payment.status === 'timeout') {

    clearInterval(interval);

    Swal.fire({
        icon: 'info',
        title: 'Payment Timeout',
        text: 'You did not respond to the STK prompt.',
        confirmButtonColor: '#3b82f6'
    });

   payBtn.disabled = false;
    payBtn.innerHTML = "Retry Payment";

    payBtn.classList.remove(
    "opacity-50",
);
}

        });

}, 3000);

            // OPTIONAL:
            // start polling payment status here
            payBtn.innerHTML = "Waiting for Payment...";

        } else {

            Swal.fire({
    title: 'Payment Failed',
    text: data.message,
    icon: 'error',
    confirmButtonText: 'Retry',
    background: '#1e293b',
    color: '#fff',
    confirmButtonColor: '#ef4444'
});

            payBtn.disabled = false;
            payBtn.classList.remove("opacity-50", "cursor-not-allowed");
            payBtn.innerHTML = "Retry Payment";
        }

    })
    .catch(err => {

        console.error("Error:", err);

        alert("Something went wrong while initializing payment.");

        payBtn.disabled = false;
        payBtn.classList.remove("opacity-50", "cursor-not-allowed");
        payBtn.innerHTML = "Retry Payment";
    });


    function restoreButton() {
            // Restore button (enabled + blue)
            payBtn.disabled = false;
            payBtn.classList.remove("bg-gray-400", "cursor-not-allowed");
            payBtn.classList.add("bg-blue-600", "hover:bg-blue-700");
        payBtn.innerHTML = "Retry Payment";

       
    }
});


  // ====== Expiry / Status logic ======
  const dueDate = new Date("<?php echo $expiryDate; ?>");
  const expiryBtn = document.getElementById('expiryBtn');
  const expiryText = document.getElementById('expiryText');
  const invoiceBtnWrapper = document.getElementById('invoiceBtnWrapper');
  const invoiceBtn = document.getElementById('invoiceBtn');
  const invoiceModal = document.getElementById('invoiceModal');
  const statusBadge = document.getElementById('statusBadge');

  function updateDashboard() {
  const userStatus = "<?= $userStatus ?>";
  const suspendedThreshold = 30; // 30 days after expiry = auto-suspend
    const now = new Date();
    const daysDiff = Math.ceil((dueDate - now) / (1000 * 60 * 60 * 24));

      // 🚫 TERMINATED USERS — NO INVOICE, NO PAYMENT
  if (userStatus === 'terminated') {
    invoiceBtnWrapper.classList.add('hidden');
    expiryBtn.classList.add('hidden');

    statusBadge.innerText = "Status: Terminated";
    statusBadge.className =
      "block text-center px-3 py-1 rounded-full text-sm font-medium bg-black text-white";

    return; // ⛔ stop all further logic
  }

     if (userStatus === 'queued' && daysDiff > 0) {
    statusBadge.innerHTML = `
      Status: Reconnecting
      <span class="ml-2 pulse-blue"></span>
    `;
    statusBadge.className =
      "block text-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-700";
    expiryBtn.classList.remove('hidden'); // show expiry
    invoiceBtnWrapper.classList.add('hidden');
    return; // stop further checks
  }

// ---------- Suspended (manual or auto) ----------
  if (userStatus === 'suspended' || daysDiff <= -suspendedThreshold) {
    statusBadge.innerText = "Status: Suspended";
    statusBadge.className =
      "block text-center px-3 py-1 rounded-full text-sm font-medium bg-gray-300 text-gray-800";
    expiryBtn.classList.add('hidden');

    // Show invoice if expiry passed
    if (daysDiff <= 0) {
      invoiceBtnWrapper.classList.remove('hidden');
    } else {
      invoiceBtnWrapper.classList.add('hidden');
    }
    return;
  }

    if (isNaN(dueDate.getTime())) {
      // handle null expiry dates safely
      expiryBtn.classList.add('hidden');
      statusBadge.innerText = "Status: Unknown";
      statusBadge.classList.remove("bg-green-100","text-green-700","bg-red-100","text-red-700");
      statusBadge.classList.add("bg-yellow-100","text-yellow-700");
      invoiceBtnWrapper.classList.add('hidden');
      return;
    }

    if (daysDiff <= 0) {
      expiryBtn.classList.add('hidden');
      invoiceBtnWrapper.classList.remove('hidden');
      statusBadge.innerText = "Status: Inactive";
      statusBadge.classList.remove("bg-green-100", "text-green-700");
      statusBadge.classList.add("bg-red-100", "text-red-700");
    } else {
      expiryBtn.classList.remove('hidden');
      statusBadge.innerText = "Status: Active";
      statusBadge.classList.remove("bg-red-100", "text-red-700");
      statusBadge.classList.add("bg-green-100", "text-green-700");

      if (daysDiff <= 5) {
        expiryBtn.classList.remove("bg-gray-200", "text-gray-700");
        expiryBtn.classList.add("bg-red-500", "text-white", "animate-pulse");
        expiryText.innerText = `Expiry: ${daysDiff} Day${daysDiff > 1 ? 's' : ''} remaining`;
        invoiceBtnWrapper.classList.remove('hidden');
      } else {
        expiryBtn.classList.remove("bg-red-500", "text-white", "animate-pulse");
        expiryBtn.classList.add("bg-gray-200", "text-gray-700");
        // Keep a readable date here rather than hard-coded text
        const opts = { day: '2-digit', month: 'short', year: 'numeric' };
        expiryText.innerText = `Expiry: ${dueDate.toLocaleDateString('en-US', opts)}`;
        invoiceBtnWrapper.classList.add('hidden');
      }
    }
  }

  updateDashboard();
  setInterval(updateDashboard, 1000);

  // ====== Invoice modal open/close ======
  function openModal() {
    invoiceModal.classList.remove('hidden');
    document.body.classList.add('modal-open');
  }

  function closeModal() {
    invoiceModal.classList.add('hidden');
    document.body.classList.remove('modal-open');
    location.reload();
  }

  function downloadInvoice() {
    const element = document.getElementById('invoiceContent');
    const opt = {
      margin: 0.5,
      filename: 'invoice-dlink.pdf',
      image: { type: 'jpeg', quality: 0.98 },
      html2canvas: { scale: 2 },
      jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
    };
    html2pdf().set(opt).from(element).save();
  }

  invoiceBtn?.addEventListener('click', function () {
    document.getElementById('loadingOverlay').classList.remove('hidden'); // Show loader
    updateInvoiceNumber(); // Set invoice number while loading
    
    setTimeout(() => {
        document.getElementById('loadingOverlay').classList.add('hidden'); // Hide loader
        openModal(); // Open invoice modal
    }, 1800); // Delay 1.8s for spinner
  });

  // ====== Payment table show entries ======
  document.addEventListener("DOMContentLoaded", function () {
    const rows = document.querySelectorAll("#paymentTableBody tr");
    const select = document.getElementById("showEntries");

    function updateVisibleRows() {
        let limit = select.value === "all" ? rows.length : parseInt(select.value);
        rows.forEach((row, index) => {
            row.style.display = index < limit ? "" : "none";
        });
    }

    select.addEventListener("change", updateVisibleRows);
    updateVisibleRows(); // Initial load
  });

  // ====== Invoice number/date ======
  function updateInvoiceNumber() {
      const today = new Date();
      const y = today.getFullYear();
      const mont = today.toLocaleString('en-US', { month: 'short' });
      const m = String(today.getMonth() + 1).padStart(2, '0');
      const d = String(today.getDate()).padStart(2, '0');
      const dte = `${mont} ${d}, ${y}`;
      
       // ✅ Guard against missing element
  const invoiceDateEl = document.getElementById('invoiceDate');
  if (invoiceDateEl) {
    invoiceDateEl.textContent = dte;
  }
  }

  // Guarded listener (avoid null errors if element isn't in DOM)
  document.getElementById('invoiceBtn')?.addEventListener('click', updateInvoiceNumber);

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
    if (e.key === 'Escape' && !sidebar.classList.contains('md:translate-x-0')) {
      closeSidebar();
    }
  });

function viewInvoice(invoiceNumber, paymentDate) {
  // Set invoice number
  document.getElementById("invoiceNumber").innerText = invoiceNumber; 
document.getElementById("changeRec").innerText = "Transaction ID:";
document.getElementById("changeRecWord").innerText = "";
document.getElementById("paidStamp").classList.remove("hidden");
document.getElementById("changeDLWord").innerText = "";
document.getElementById("chrct").innerText = "VERIFIED";
const paidDate = new Date(paymentDate);
  document.getElementById("invoiceReceipt").innerText =
    paidDate.toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: '2-digit'
    });
// ✅ Hide Due Date
  const due = document.getElementById("invoiceDue");
  if (due) due.classList.add("hidden");

  // ✅ Show success message
  const successMsg = document.getElementById("paymentSuccessMsg");
  if (successMsg) successMsg.classList.remove("hidden");


  // Hide "Pay Now" but keep space
  const payNowBtn = document.getElementById("payNowBtn");
  if (payNowBtn) {
    payNowBtn.style.visibility = "hidden"; // hidden keeps layout spacing
  }

  // Change status badge from PENDING → PAID
  const statusBadge = document.getElementById("invoiceStatus");
  if (statusBadge) {
    statusBadge.textContent = "PAID";
    statusBadge.classList.remove("bg-yellow-200", "text-yellow-800");
    statusBadge.classList.add("bg-green-200", "text-green-800");
  }

 const head2 = document.getElementById("head2");
if (head2) {
  head2.textContent = "RECEIPT";
  head2.classList.remove("text-xl"); // remove smaller size
  head2.classList.add("text-2xl", "text-green-600", "font-bold"); // bigger & green
}

  // ✅ Replace "Total Due" → "Total Paid"
  const totalNumLabel = document.getElementById("totalLBLVl");
  const totalLabel = document.getElementById("totalLBL");
  if (totalLabel) {
    totalLabel.textContent = "Total Paid: ";
    totalLabel.classList.remove("text-orange-600");
    totalLabel.classList.add("text-green-800");
    totalNumLabel.classList.add("text-green-800");
  }
  

  // Remove Print & Download Invoice buttons
  document.querySelectorAll("#invoiceModal .text-blue-600").forEach(btn => btn.remove());

  // Open modal
  document.getElementById("invoiceModal").classList.remove("hidden");
  document.body.classList.add("modal-open");
}


function downloadInvoiceByNumber(invoiceNumber, paymentDate) {
    // Set invoice number
  document.getElementById("invoiceNumber").innerText = invoiceNumber; 
document.getElementById("changeRec").innerText = "Receipt:";
document.getElementById("changeRecWord").innerText = " RCT";
document.getElementById("chrct").innerText = "VERIFIED";
const paidDate = new Date(paymentDate);
  document.getElementById("invoiceReceipt").innerText =
    paidDate.toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: '2-digit'
    });
// ✅ Hide Due Date
  const due = document.getElementById("invoiceDue");
  if (due) due.classList.add("hidden");

  // ✅ Show success message
  const successMsg = document.getElementById("paymentSuccessMsg");
  if (successMsg) successMsg.classList.remove("hidden");


  // Hide "Pay Now" but keep space
  const payNowBtn = document.getElementById("payNowBtn");
  if (payNowBtn) {
    payNowBtn.style.visibility = "hidden"; // hidden keeps layout spacing
  }

  // Change status badge from PENDING → PAID
  const statusBadge = document.getElementById("invoiceStatus");
  if (statusBadge) {
    statusBadge.textContent = "PAID";
    statusBadge.classList.remove("bg-yellow-200", "text-yellow-800");
    statusBadge.classList.add("bg-green-200", "text-green-800");
  }

  const head2 = document.getElementById("head2");
if (head2) {
  head2.textContent = "RECEIPT";
  head2.classList.remove("text-xl"); // remove smaller size
  head2.classList.add("text-2xl", "text-green-600", "font-bold", "text-right", "ml-auto"); // bigger, green & right-aligned
}

  // ✅ Replace "Total Due" → "Total Paid"
  const totalNumLabel = document.getElementById("totalLBLVl");
  const totalLabel = document.getElementById("totalLBL");
  if (totalLabel) {
    totalLabel.textContent = "Total Paid: ";
    totalLabel.classList.remove("text-orange-600");
    totalLabel.classList.add("text-green-800", "font-bold");
  }
  if (totalNumLabel) {
    totalNumLabel.classList.remove("text-orange-600");
    totalNumLabel.classList.add("text-green-800", "font-bold");
  }

  // Remove Print & Download Invoice buttons
  document.querySelectorAll("#invoiceModal .text-blue-600").forEach(btn => btn.remove());

  // Export invoice modal content as PDF
  const element = document.getElementById('invoiceContent');
  const opt = {
    margin: 0.5,
    filename: 'invoice-' + invoiceNumber + '.pdf',
    image: { type: 'jpeg', quality: 0.98 },
    html2canvas: { scale: 2 },
    jsPDF: { unit: 'in', format: 'letter', orientation: 'landscape' }
  };
  html2pdf().set(opt).from(element).save();
}
</script>
<script>
let hideTimer;
function showNetToast(status) {
  const toast = document.getElementById('net-toast');
  const isOnline = status === 'online';
  toast.innerHTML = `
    <span class="dot" style="background:${isOnline?'#639922':'#E24B4A'}"></span>
    ${isOnline ? 'Back online' : 'You are offline'}`;
  toast.style.borderColor = isOnline ? '#c0dd97' : '#f7c1c1';
  clearTimeout(hideTimer);
  toast.classList.add('show');
  if (isOnline) hideTimer = setTimeout(() => toast.classList.remove('show'), 3000);
}
window.addEventListener('online',  () => showNetToast('online'));
window.addEventListener('offline', () => showNetToast('offline'));
</script>
</body>
</html>

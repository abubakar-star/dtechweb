<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// DB connection
$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$username = $_ENV['MYSQLUSER'];
$password = $_ENV['MYSQLPASSWORD'];

$conn = new mysqli($host, $username, $password, $dbname, $port);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT first_name, last_name, phone_number, email FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$fullName = trim($user['first_name'] . ' ' . $user['last_name']);
$phone = $user['phone_number'];
$email = $user['email'];


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
// ensure $user exists and extract numeric package price
$packagePriceNumber = 0.00;
if (!empty($user) && isset($user['price']) && is_numeric($user['price'])) {
    $packagePriceNumber = (float) $user['price'];
}

// formatted strings used in invoice modal
$priceFormatted      = 'KES ' . number_format($packagePriceNumber, 2);
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
$sql = "SELECT amount, payment_date, status 
        FROM payments 
        WHERE user_id = ? 
        ORDER BY payment_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$paymentHistoryResult = $stmt->get_result();
$payments = $paymentHistoryResult->fetch_all(MYSQLI_ASSOC);

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payment</title>
  <link rel="icon" href="tt.png" type="x-icon" />
  <link rel="stylesheet" href="styles.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>
  <div class="payment-container">
    <div class="sidebar">
      <h4>PAY WITH</h4>
      <ul id="sidebar-list">
        <li class="active bordered-box2"><img src="phone.png"/> M-PESA</li>
        <li class="bordered-box2"><img src="phone.png"/> M-PESA Till</li>
        <li class="bordered-box3"><img src="airtel.png"/> Airtel Money</li>
      </ul>
    </div>

    <!-- MPESA -->
     
    <div class="form-area" id="payment-form-mpesa">
      <div class="floating-header bordered-box">
      <img src="D.png" alt="D-Link Logo" class="logo" />
      <div class="email-header">
  <div class="user-email"><?php echo htmlspecialchars($email); ?></div>
  <div class="amount">Pay <span class="green-text">KES <?php echo $subtotal; ?></span></div>
</div>
</div>
      <div class="mpesa-logo">
        <img src="mpesa_logo-C8Va13Qa.svg" alt="M-PESA Logo" class="logo-img zoom-ring-return">
      </div>
      <p class="instruction">Please enter your mobile money number<br>to begin this payment</p>

      <div class="input-group">
        <input type="text" id="mpesa-phone" placeholder="070 000 0000">
        <span class="flag"><img src="https://flagcdn.com/w40/ke.png" alt="KE Flag"></span>
      </div>

      <button class="pay-button" id="mpesa-pay" disabled>Pay KES <?php echo $subtotal; ?></button>

      <div class="alt-method">
        Prefer a different method?<br>
        <a href="#" id="switch-to-till">Switch to M-PESA Till</a>
      </div>
    </div>


    <!-- MPESA TILL-->
     
    <div class="form-area" id="payment-form-till">
      <div class="floating-header bordered-box">
      <img src="D.png" alt="D-Link Logo" class="logo" />
      <div class="email-header">
  <div class="user-email"><?php echo htmlspecialchars($email); ?></div>
  <div class="amount">Pay <span class="green-text">KES <?php echo $subtotal; ?></span></div>
</div>
</div>
      <div class="mpesa-logo">
        <img src="mpesa_logo-C8Va13Qa.svg" alt="M-PESA Logo" class="logo-img zoom-ring-return">
      </div>

      <p class="instruction2">Pay from your M-PESA Till</p>
      <p class="instruction">Enter your business’s M-PESA Till<br> number to make a payment directly<br> from your Till account.</p>

      <div class="input-group">
        <input type="text" id="till-phone" placeholder="Enter Till Number">
        <span class="flag"><img src="https://flagcdn.com/w40/ke.png" alt="KE Flag"></span>
      </div>

      <button class="pay-button" id="till-pay" disabled>Pay KES <?php echo $subtotal; ?></button>

      
    </div>
  

   <!-- AIRTEL -->
     
    <div class="form-area" id="payment-form-airtel">
      <div class="floating-header bordered-box">
      <img src="D.png" alt="D-Link Logo" class="logo" />
      <div class="email-header">
  <div class="user-email"><?php echo htmlspecialchars($email); ?></div>
  <div class="amount">Pay <span class="green-text">KES <?php echo $subtotal; ?></span></div>
</div>
</div>
      <div class="mpesa-logo">
        <img src="logo.png" alt="M-PESA Logo" class="logo-img zoom-ring-return">
      </div>
      <p class="instruction">Please enter your mobile money number<br>to begin this payment</p>

      <div class="input-group">
        <input type="text" id="airtel-phone" placeholder="070 000 0000">
        <span class="flag"><img src="https://flagcdn.com/w40/ke.png" alt="KE Flag"></span>
      </div>

      <button class="pay-button" id="airtel-pay" disabled>Pay KES <?php echo $subtotal; ?></button> 
  </div>


  <!-- PAYMENT VERIFICATION -->

  <div id="loading-screen" style="display: none;" class="form-area">
  <div class="floating-header bordered-box">
    <img src="D.png" alt="D-Link Logo" class="logo" />
    <div class="email-header">
      <div class="user-email"><?php echo htmlspecialchars($email); ?></div>
      <div class="amount">Pay <span class="green-text">KES <?php echo $subtotal; ?></span></div>
    </div>
  </div>

  <div class="mpesa-logo">
    <img src="ph.svg" alt="Processing" class="logo-img2 zoom-ring-return">
  </div>

  <p class="instruction2" id="payer-number">0769112320</p>
  <p class="instruction">Please enter your PIN on your phone to complete this payment</p>

  <div class="payment-status">
   <div class="progress-ring">
  <svg class="countdown-ring" width="60" height="60">
    <circle
      class="progress-ring__circle"
      stroke="#00c853"
      stroke-width="5"
      fill="transparent"
      r="26"
      cx="30"
      cy="30"
    />
  </svg>
</div>

    <div class="countdown-text">
      Payment is valid for <span id="countdown">01:00</span>
    </div>
  </div>

  <button class="pay-button" id="action-button" onclick="location.reload()">Cancel</button>
</div>



<script>
  const sidebarItems = document.querySelectorAll('#sidebar-list li');
  const forms = {
    'M-PESA': 'payment-form-mpesa',
    'M-PESA Till': 'payment-form-till',
    'Airtel Money': 'payment-form-airtel'
  };

  const formElements = Object.values(forms).map(id => document.getElementById(id));

  function showForm(label) {
    // Set active class
    sidebarItems.forEach(item => {
      item.classList.toggle('active', item.innerText.trim() === label);
    });

    // Show relevant form
    formElements.forEach(form => {
      form.style.display = 'none';
    });
    const formId = forms[label];
    const formToShow = document.getElementById(formId);
    if (formToShow) formToShow.style.display = 'block';
  }

  sidebarItems.forEach(item => {
    item.addEventListener('click', () => {
      const label = item.innerText.trim();
      showForm(label);
    });
  });

  document.getElementById("switch-to-till")?.addEventListener("click", function (e) {
    e.preventDefault();
    showForm("M-PESA Till");
  });

  // Initially show only the first one
  document.addEventListener("DOMContentLoaded", () => {
    formElements.forEach((form, index) => {
      form.style.display = index === 0 ? 'block' : 'none';
    });
  });

    function isValidKenyanPhoneNumber(phone) {
    const cleaned = phone.replace(/\s+/g, '');
    return /^0(7|1)\d{8}$/.test(cleaned);
  }

 function startCountdown(duration, display, onComplete) {
  let timer = duration;

  const interval = setInterval(() => {
    const minutes = String(Math.floor(timer / 60)).padStart(2, '0');
    const seconds = String(timer % 60).padStart(2, '0');
    display.textContent = `${minutes}:${seconds}`;

    // Turn red when 10 seconds left
    if (timer === 10) {
      const circle = document.querySelector('.progress-ring__circle');
      if (circle) {
        circle.style.stroke = '#e53935'; // red
      }
    }

    if (--timer < 0) {
      clearInterval(interval);
      display.textContent = "Expired";

        // Change Cancel button to Retry
  const actionButton = document.getElementById('action-button');
  if (actionButton) {
    actionButton.textContent = "Retry";
    actionButton.onclick = () => location.reload(); // retry reloads
  }

      if (typeof onComplete === 'function') {
        onComplete();
      }
    }
  }, 1000);
}

function startProgressRing(duration, circle) {
  const radius = circle.r.baseVal.value;
  const circumference = 2 * Math.PI * radius;

  circle.style.strokeDasharray = `${circumference} ${circumference}`;
  circle.style.strokeDashoffset = 0;

let remaining = duration;
const interval = setInterval(() => {
  const offset = circumference * (1 - remaining / duration);
  circle.style.strokeDashoffset = offset;

    if (--remaining < 0) {
      clearInterval(interval);
    }
  }, 1000);
}

  function setupFormLogic(inputId, buttonId) {
    const input = document.getElementById(inputId);
    const button = document.getElementById(buttonId);

    if (!input || !button) return;

    input.addEventListener('input', () => {
      const phone = input.value.trim();
      button.disabled = !isValidKenyanPhoneNumber(phone);
    });

   button.addEventListener('click', (e) => {
  e.preventDefault();
  const phone = input.value.trim();
  if (isValidKenyanPhoneNumber(phone)) {

     // Store the active form
    const activeForm = input.closest('.form-area');
    window.lastForm = activeForm; // store globally

    // Hide all forms
    document.querySelectorAll('.form-area').forEach(f => f.style.display = 'none');

    // Set number
    document.getElementById('payer-number').textContent = phone;

    // Show loading screen
    document.getElementById('loading-screen').style.display = 'block';

      // Start countdown and progress
    const countdownDisplay = document.getElementById('countdown');
    startCountdown(60, countdownDisplay, () => {
      // After expired, change Cancel to Retry
      const actionButton = document.getElementById('action-button');
      if (actionButton) {
        actionButton.textContent = "Retry";
  actionButton.onclick = () => {
  // Hide loading screen
  document.getElementById('loading-screen').style.display = 'none';

  // Show previous form
  if (window.lastForm) {
    window.lastForm.style.display = 'block';

    const phoneInput = window.lastForm.querySelector('input[type="text"]');
    const payBtn = window.lastForm.querySelector('button.pay-button');

    // Preserve previous phone number
    if (phoneInput) phoneInput.value = document.getElementById('payer-number').textContent;

    // Re-enable the pay button (since the number is valid)
    if (payBtn) payBtn.disabled = false;
  }

  // Reset spinner progress and color
  const circle = document.querySelector('.progress-ring__circle');
  if (circle) {
    circle.style.strokeDashoffset = 2 * Math.PI * circle.r.baseVal.value;
    circle.style.stroke = "#00c853"; // back to green
  }

  // Reset countdown text
  document.getElementById('countdown').textContent = '01:00';

  // Reset Retry button back to Cancel
  actionButton.textContent = "Cancel";
  actionButton.onclick = () => location.reload();
};

      }
    });

    // Start spinner
    const circle = document.querySelector('.progress-ring__circle');
    if (circle) startProgressRing(60, circle);
  }
});
  }
  document.addEventListener('DOMContentLoaded', () => {
    setupFormLogic('mpesa-phone', 'mpesa-pay');
    setupFormLogic('till-phone', 'till-pay');
    setupFormLogic('airtel-phone', 'airtel-pay');
  });
</script>


</body>
</html>

<?php
session_start();

include 'includes/logger.php';

// Detect Android devices
$isAndroid = preg_match('/Android/i', $_SERVER['HTTP_USER_AGENT']);

// ✅ If user is already logged in, redirect
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// ✅ If cookies exist, auto-login
if (isset($_COOKIE['remember_user']) && isset($_COOKIE['remember_token'])) {
// DB connection


$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$username = $_ENV['MYSQLUSER'];
$password = $_ENV['MYSQLPASSWORD'];

    $conn = new mysqli($host, $username, $password, $dbname, $port);
    if (!$conn->connect_error) {
        $username = $_COOKIE['remember_user'];
        $token = $_COOKIE['remember_token'];

        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND remember_token = ?");
        $stmt->bind_param("ss", $username, $token);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            header("Location: index.php");
            exit();
        }
        $stmt->close();
        $conn->close();
    }
}

$pendingApproval = false;

$errorMessage = "";
$attempt = 0;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
// DB connection

$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$username = $_ENV['MYSQLUSER'];
$password = $_ENV['MYSQLPASSWORD'];

    $conn = new mysqli($host, $username, $password, $dbname, $port);
    if ($conn->connect_error) {
        $errorMessage = "Database connection failed";
        $attempt = 1;
    } else {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $remember = isset($_POST['remember']);

        $sql = "SELECT * FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();

            // ✅ Replace with password_verify if using hashes
         if ($password === $row['password']) {

    if ($row['verification_status'] !== 'approved') {

        createLog(
            $conn,
            'authentication',
            'Pending Verification Login',
            'User attempted login before verification approval',
            'warning',
            $row['id']
        );

        $pendingApproval = true;

    } else {

if (empty($row['biometric_token'])) {

    $biometricToken = bin2hex(random_bytes(32));

    $updateBio = $conn->prepare("
        UPDATE users
        SET biometric_token = ?
        WHERE id = ?
    ");

    $updateBio->bind_param(
        "si",
        $biometricToken,
        $row['id']
    );

    $updateBio->execute();
}

        $_SESSION['user_id'] = $row['id'];
        $_SESSION['username'] = $row['username'];

        createLog(
            $conn,
            'authentication',
            'User Login',
            'User logged in successfully',
            'success',
            $row['id']
        );

        if ($remember) {

            $token = bin2hex(random_bytes(16));

            setcookie("remember_user", $username, time() + (86400 * 30), "/");
            setcookie("remember_token", $token, time() + (86400 * 30), "/");

            $update = $conn->prepare(
                "UPDATE users SET remember_token = ? WHERE id = ?"
            );

            $update->bind_param("si", $token, $row['id']);
            $update->execute();
            $update->close();
        }

        header("Location: index.php");
        exit();
    }
} else {
              createLog(
    $conn,
    'security',
    'Failed Login',
    'Incorrect password entered',
    'warning'
);

                $errorMessage = "Invalid password";
                $attempt = 1;
            }
        } else {
          createLog(
    $conn,
    'security',
    'Failed Login',
    'Username does not exist',
    'warning'
);

            $errorMessage = "Wrong credentials";
            $attempt = 1;
        }
        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login</title>
  <link rel="icon" href="tt.png" type="x-icon" />
   <link rel="manifest" href="/manifest.json">
   <link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<meta name="theme-color" content="#2563eb">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
      html, body {
      margin: 0;
      padding: 0;
      overflow: hidden; /* hides scrollbars */
    }

    @media (max-width: 768px) {
  html, body {
    overflow: hidden;      /* no scrollbars on mobile */
    height: 100%;
    width: 100%;
  }
}

    .error-text { color: #fecaca; font-size: 0.875rem; margin-top: 0.25rem; font-weight: bold; }
    .spinner { border: 3px solid transparent; border-top: 3px solid white; border-radius: 50%; width: 18px; height: 18px; animation: spin 1s linear infinite; display: inline-block; vertical-align: middle; }
    @keyframes spin { 0% { transform: rotate(0deg);} 100% { transform: rotate(360deg);} }
    @keyframes shake { 0% { transform: translateX(0);} 20% { transform: translateX(-5px);} 40% { transform: translateX(5px);} 60% { transform: translateX(-5px);} 80% { transform: translateX(5px);} 100% { transform: translateX(0);} }
    .shake { animation: shake 0.4s ease-in-out; }

    .otp-box{
  width:45px;
  height:55px;
  text-align:center;
  font-size:24px;
  border:2px solid #ccc;
  border-radius:12px;
  outline:none;
  transition:0.2s;
}

.otp-box:focus{
  border-color:#2563eb;
}

.otp-success{
  border-color:green !important;
  background:#dcfce7;
}

.otp-error{
  border-color:red !important;
  background:#fee2e2;
}

.fingerprint-fab{
    position: fixed;
    bottom: 20px;
    right: 20px;

    width: 52px;
    height: 52px;

    border-radius: 50%;

     background: linear-gradient(
    135deg,
    #111111,
    #2b2b2b
);

    display: flex;
    align-items: center;
    justify-content: center;

     color: #ffffff;
    font-size: 22px;
    border: 1px solid rgba(255,255,255,0.15);

    cursor: pointer;

     box-shadow:
        0 4px 12px rgba(0,0,0,.35);
        backdrop-filter: blur(8px);

    z-index: 9999;

     transition: all .25s ease;

}


.fingerprint-fab:hover{
     transform: scale(1.05);
    background: rgba(20,20,20,0.95);
}

.fingerprint-fab i{
    line-height: 1;
}

.loader {
  width: 45px;
  height: 45px;
  border: 4px solid rgba(255,255,255,0.2);
  border-top: 4px solid white;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

      #installBanner{
    position:fixed;
    top:20px;
    right:20px;

    width:340px;

    background:#ffffff;

    border-radius:16px;

    box-shadow:0 10px 25px rgba(0,0,0,.15);

    padding:16px;

    display:none;

    align-items:center;

    gap:12px;

    z-index:99999;

    animation:slideIn .3s ease;
}

#installBanner img{
    width:48px;
    height:48px;
    border-radius:12px;
}

.banner-text{
    flex:1;
}

.banner-text h4{
    margin:0;
    font-size:15px;
}

.banner-text p{
    margin:3px 0 0;
    color:#64748b;
    font-size:13px;
}

#installBtn{
    border:none;
    background:#2563eb;
    color:white;
    padding:10px 14px;
    border-radius:8px;
    cursor:pointer;
    font-weight:600;
}

@keyframes slideIn{
    from{
        opacity:0;
        transform:translateY(-10px);
    }
    to{
        opacity:1;
        transform:translateY(0);
    }
}

#loadingOverlay {
  backdrop-filter: blur(5px);
}

body.swal2-shown,
html.swal2-shown {
  padding-right: 0 !important;
  overflow: hidden !important;
}

.swal2-height-auto {
  height: 100% !important;
}
  </style>
</head>
<body class="bg-cover bg-center bg-no-repeat h-screen flex items-center justify-center font-sans overflow-hidden"
      style="background-image: url('y.webp');">

  <!-- Gradient background with glow -->
  <div class="pointer-events-none fixed -top-24 -left-20 h-80 w-80 rounded-full bg-white/10 blur-3xl"></div>
  <div class="pointer-events-none fixed -bottom-24 -right-16 h-80 w-80 rounded-full bg-cyan-300/20 blur-3xl"></div>


 <!-- Glass card -->
  <div class="relative z-10 w-full max-w-md p-8 space-y-6
              bg-white/10 backdrop-blur-xl rounded-2xl border border-white/20 shadow-2xl
              transition duration-300 hover:shadow-[0_0_30px_rgba(255,255,255,0.3)] hover:border-white/40 hover:bg-white/15">
    <div class="text-center">
      <h1 class="text-3xl font-extrabold text-white drop-shadow mb-2">D-LINK NETWORK</h1>
      <p class="text-white/80">Sign in to your account</p>
    </div>

    <form class="space-y-6 <?php if($attempt) echo 'shake'; ?>" id="loginForm" action="" method="POST" novalidate>
      <div>
        <label for="username" class="block text-sm font-medium text-white/90">Username</label>
        <input type="text" id="username" name="username" placeholder="Enter your username"
          value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>"
          class="mt-1 block w-full px-4 py-3 rounded-md shadow-sm
                 bg-white/15 text-white placeholder-white/60
                 border border-white/20 focus:outline-none
                 focus:ring-2 focus:ring-white/60 focus:border-white/60"/>
        <p id="usernameError" class="error-text hidden">Please enter your username.</p>
      </div>

    <div>
  <label for="password" class="block text-sm font-medium text-white/90">Password</label>
  <div class="relative">
    <input
      type="password"
      id="password"
      name="password"
      placeholder="••••••••"
      class="mt-1 block w-full px-4 py-3 rounded-md shadow-sm
             bg-white/15 text-white placeholder-white/60
             border border-white/20 focus:outline-none
             focus:ring-2 focus:ring-white/60 focus:border-white/60 pr-10"
    />

    <!-- Eye toggle button -->
    <button
      type="button"
      id="togglePassword"
      class="absolute inset-y-0 right-0 flex items-center pr-3 text-white/70 hover:text-white focus:outline-none"
    >
      <!-- Open eye icon -->
      <svg id="eyeOpen" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
           viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
      </svg>

      <!-- Closed eye icon -->
      <svg id="eyeClosed" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" fill="none"
           viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.956 9.956 0 013.989-5.272m3.091-1.209
              A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a9.956 9.956 0 01-4.167 5.163M3 3l18 18" />
      </svg>
    </button>
  </div>
  <p id="passwordError" class="error-text hidden">Please enter your password.</p>
</div>


      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <input id="remember" name="remember" type="checkbox"
            class="h-4 w-4 text-indigo-300 focus:ring-indigo-300 border-white/30 rounded bg-white/20"/>
          <label for="remember" class="ml-2 block text-sm text-white/90">Remember me</label>
        </div>
        <div class="text-sm">
          <a href="#"
   id="forgotPasswordBtn"
   class="font-medium text-white/90 hover:text-white">
   Forgot your password?
</a>
        </div>
      </div>

      <button type="submit" id="signInBtn"
        class="w-full py-3 rounded-md font-semibold
               bg-white/20 hover:bg-white/30 text-white
               ring-1 ring-white/40 shadow-md flex justify-center items-center transition">
        Sign In
      </button>

       <div class="text-center mt-4">

<div id="biometricBtn" class="fingerprint-fab">
    <i class="fas fa-fingerprint"></i>
</div>

      <?php if ($errorMessage): ?>
        <p id="loginError" class="error-text text-center"><?php echo $errorMessage; ?></p>
      <?php else: ?>
        <p id="loginError" class="error-text text-center hidden"></p>
      <?php endif; ?>
    </form>

    <p class="text-center text-sm text-white/80">

    
      Can't access your account? <br>Contact 
      <a href="#" class="font-medium text-white hover:text-white/90">0758788020</a>
      <br>
<?php if ($isAndroid): ?>
<a href="D-LINK.apk"
   download
   class="inline-flex items-center gap-1 mt-2 text-cyan-300 hover:text-cyan-200 text-sm font-medium">

  APK

  <svg xmlns="http://www.w3.org/2000/svg"
       class="h-4 w-4"
       fill="none"
       viewBox="0 0 24 24"
       stroke="currentColor">

    <path stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M7 10l5 5m0 0l5-5m-5 5V4"/>

  </svg>

</a>
<?php endif; ?>
    </p>
  </div>

  <!-- OTP MODAL -->
<div id="otpModal"
     class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50">

  <div class="bg-white rounded-2xl p-6 w-[90%] max-w-sm shadow-2xl relative">
       <!-- CLOSE BUTTON -->
<button id="closeOtpModal"
        class="absolute top-3 right-3 text-gray-400 hover:text-red-500 text-2xl font-bold leading-none">
  &times;
</button>

    <h2 class="text-2xl font-bold text-center mb-2">
      Verify OTP
    </h2>

<p id="otpPhoneText"
   class="text-gray-500 text-center mb-5">
   Enter the 6-digit code sent to your phone
</p>

    <!-- OTP BOXES -->
    <div class="flex justify-center gap-2 mb-5">
      <input id="otp1"
      maxlength="6"
       class="otp-box"
       autocomplete="one-time-code"
       inputmode="numeric" />
     
  <input id="otp2"
         maxlength="1"
         class="otp-box"
         inputmode="numeric" />

  <input id="otp3"
         maxlength="1"
         class="otp-box"
         inputmode="numeric" />

  <input id="otp4"
         maxlength="1"
         class="otp-box"
         inputmode="numeric" />

  <input id="otp5"
         maxlength="1"
         class="otp-box"
         inputmode="numeric" />

  <input id="otp6"
         maxlength="1"
         class="otp-box"
         inputmode="numeric" />

    </div>

    <p id="otpMessage"
       class="text-center text-sm mt-3 hidden"></p>

  </div>
</div>


<!-- NEW PASSWORD MODAL -->
<div id="passwordModal"
     class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50">

  <div class="bg-white rounded-2xl p-6 w-[90%] max-w-sm shadow-2xl">
 

    <h2 class="text-2xl font-bold text-center mb-5">
      Create New Password
    </h2>

   <!-- NEW PASSWORD -->
<div class="relative mb-4">

  <input type="text"
         id="newPassword"
         placeholder="New password"
         class="w-full border rounded-lg px-4 py-3 pr-12"/>

  <button type="button"
          id="toggleNewPassword"
          class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500">

    <!-- EYE OPEN -->
    <svg id="newEyeOpen"
         xmlns="http://www.w3.org/2000/svg"
         class="h-5 w-5"
         fill="none"
         viewBox="0 0 24 24"
         stroke="currentColor">

      <path stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>

      <path stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M2.458 12C3.732 7.943 7.523 5 12 5
               c4.478 0 8.268 2.943 9.542 7
               -1.274 4.057-5.064 7-9.542 7
               -4.477 0-8.268-2.943-9.542-7z"/>
    </svg>

    <!-- EYE CLOSED -->
    <svg id="newEyeClosed"
         xmlns="http://www.w3.org/2000/svg"
         class="h-5 w-5 hidden"
         fill="none"
         viewBox="0 0 24 24"
         stroke="currentColor">

      <path stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M3 3l18 18"/>
    </svg>

  </button>

</div>


<!-- CONFIRM PASSWORD -->
<div class="relative mb-5">

  <input type="text"
         id="confirmPassword"
         placeholder="Confirm password"
         class="w-full border rounded-lg px-4 py-3 pr-12"/>

  <button type="button"
          id="toggleConfirmPassword"
          class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500">

    <!-- EYE OPEN -->
    <svg id="confirmEyeOpen"
         xmlns="http://www.w3.org/2000/svg"
         class="h-5 w-5"
         fill="none"
         viewBox="0 0 24 24"
         stroke="currentColor">

      <path stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>

      <path stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M2.458 12C3.732 7.943 7.523 5 12 5
               c4.478 0 8.268 2.943 9.542 7
               -1.274 4.057-5.064 7-9.542 7
               -4.477 0-8.268-2.943-9.542-7z"/>
    </svg>

    <!-- EYE CLOSED -->
    <svg id="confirmEyeClosed"
         xmlns="http://www.w3.org/2000/svg"
         class="h-5 w-5 hidden"
         fill="none"
         viewBox="0 0 24 24"
         stroke="currentColor">

      <path stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M3 3l18 18"/>
    </svg>

  </button>

</div>

 <button id="changePasswordBtn"
  class="w-full bg-green-600 text-white py-3 rounded-lg font-semibold flex items-center justify-center gap-2">

  <span id="changePasswordText">
    Change Password
  </span>

  <span id="changePasswordLoader"
        class="hidden w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin">
  </span>

</button>

    <p id="passwordMessage"
       class="text-center text-sm mt-3 hidden"></p>

  </div>
</div>

<!-- LOADING OVERLAY -->
<div id="loadingOverlay"
     class="fixed inset-0 bg-black/40 hidden items-center justify-center z-[9999]">

  <div class="loader"></div>

</div>

<div id="installBanner">

    <img src="/images/dlink-logo.png" alt="D-LINK">

    <div class="banner-text">
        <h4>Install D-LINK</h4>
        <p>Get faster access from your desktop.</p>
    </div>

    <button id="installBtn">
        Install
    </button>

</div>
    
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
       const togglePassword = document.getElementById('togglePassword');
  const passwordField = document.getElementById('password');
  const eyeOpen = document.getElementById('eyeOpen');
  const eyeClosed = document.getElementById('eyeClosed');

  togglePassword.addEventListener('click', () => {
    const isHidden = passwordField.type === 'password';
    passwordField.type = isHidden ? 'text' : 'password';

    // Toggle visibility of icons
    eyeOpen.classList.toggle('hidden', !isHidden);
    eyeClosed.classList.toggle('hidden', isHidden);
  });
      
    const loginForm = document.getElementById('loginForm');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const usernameError = document.getElementById('usernameError');
    const passwordError = document.getElementById('passwordError');
    const signInBtn = document.getElementById('signInBtn');

    loginForm.addEventListener('submit', (e) => {
      usernameError.classList.add('hidden');
      passwordError.classList.add('hidden');

      let valid = true;
      if (!usernameInput.value.trim()) { usernameError.classList.remove('hidden'); valid = false; }
      if (!passwordInput.value.trim()) { passwordError.classList.remove('hidden'); valid = false; }

      if (!valid) {
        e.preventDefault();
      } else {
        signInBtn.disabled = true;
        signInBtn.innerHTML = '<span class="spinner"></span>';
      }
    });
  </script>
  <script>

    const changePasswordBtn =
  document.getElementById("changePasswordBtn");

const changePasswordText =
  document.getElementById("changePasswordText");

const changePasswordLoader =
  document.getElementById("changePasswordLoader");

const closeOtpModal =
  document.getElementById("closeOtpModal");

    const loadingOverlay =
  document.getElementById("loadingOverlay");

let resetUsername = "";

const forgotBtn = document.getElementById("forgotPasswordBtn");
const otpModal = document.getElementById("otpModal");
const passwordModal = document.getElementById("passwordModal");

const otpBoxes = document.querySelectorAll(".otp-box");
// AUTO PASTE OTP FROM PHONE SMS
otpBoxes[0].addEventListener("input", (e) => {

  const value = e.target.value;

  // Detect full OTP pasted from SMS
  if(value.length > 1){

    const otpArray =
      value.replace(/\D/g, '').split('');

    otpBoxes.forEach((box, index) => {

      box.value = otpArray[index] || '';

    });

    // Trigger verification automatically
    otpBoxes[5].dispatchEvent(
      new Event("input")
    );

  }

});

// NEW PASSWORD TOGGLE
const toggleNewPassword =
  document.getElementById("toggleNewPassword");

const newPassword =
  document.getElementById("newPassword");

const newEyeOpen =
  document.getElementById("newEyeOpen");

const newEyeClosed =
  document.getElementById("newEyeClosed");

toggleNewPassword.addEventListener("click", () => {

  if(newPassword.type === "text"){

    newPassword.type = "password";

    newEyeOpen.classList.add("hidden");
    newEyeClosed.classList.remove("hidden");

  }else{

    newPassword.type = "text";

    newEyeOpen.classList.remove("hidden");
    newEyeClosed.classList.add("hidden");

  }

});


// CONFIRM PASSWORD TOGGLE
const toggleConfirmPassword =
  document.getElementById("toggleConfirmPassword");

const confirmPassword =
  document.getElementById("confirmPassword");

const confirmEyeOpen =
  document.getElementById("confirmEyeOpen");

const confirmEyeClosed =
  document.getElementById("confirmEyeClosed");

toggleConfirmPassword.addEventListener("click", () => {

  if(confirmPassword.type === "text"){

    confirmPassword.type = "password";

    confirmEyeOpen.classList.add("hidden");
    confirmEyeClosed.classList.remove("hidden");

  }else{

    confirmPassword.type = "text";

    confirmEyeOpen.classList.remove("hidden");
    confirmEyeClosed.classList.add("hidden");

  }

});


// AUTO MOVE OTP INPUTS
otpBoxes.forEach((box, index) => {

  box.addEventListener("input", () => {

    if(box.value.length === 1 && index < otpBoxes.length - 1){
      otpBoxes[index + 1].focus();
    }

  });

});


// OPEN FORGOT PASSWORD
forgotBtn.addEventListener("click", async (e) => {

  e.preventDefault();

  const username = document.getElementById("username").value.trim();

  if(!username){
    Swal.fire({
  icon: "warning",
  title: "Username Required",
  text: "Please enter your username first",
  confirmButtonColor: "#2563eb",
  background: "#ffffff",
  color: "#111827"
});
    return;
  }

  resetUsername = username;

  // CLOSE OTP MODAL
closeOtpModal.addEventListener("click", () => {

  otpModal.classList.remove("flex");
  otpModal.classList.add("hidden");

  // CLEAR OTP INPUTS
  otpBoxes.forEach(box => {

    box.value = "";

    box.classList.remove(
      "otp-success",
      "otp-error"
    );

    box.disabled = false;

  });

});

  // SHOW LOADER
loadingOverlay.classList.remove("hidden");
loadingOverlay.classList.add("flex");

const startTime = Date.now();

const response = await fetch("send_otp.php", {

  method: "POST",

  headers:{
    "Content-Type":"application/x-www-form-urlencoded"
  },

  body: "username=" + encodeURIComponent(username)

});

const result = await response.text();


// RELATIVE LOADING TIME
const elapsed = Date.now() - startTime;

const minimumTime = 1200;

if(elapsed < minimumTime){

  await new Promise(resolve =>
    setTimeout(resolve, minimumTime - elapsed)
  );

}


// HIDE LOADER
loadingOverlay.classList.remove("flex");
loadingOverlay.classList.add("hidden");

if(result.startsWith("success|")){

  const phone = result.split("|")[1];

  document.getElementById("otpPhoneText")
  .innerHTML =
    `Enter the 6-digit code sent to <br><strong>${phone}</strong>`;

  otpModal.classList.remove("hidden");
  otpModal.classList.add("flex");

}else{

    alert(result);

  }

});



otpBoxes.forEach((box, index) => {

  // INPUT
  box.addEventListener("input", async () => {

    // NUMBERS ONLY
    box.value = box.value.replace(/[^0-9]/g, '');

    // AUTO MOVE
    if(box.value.length === 1 && index < otpBoxes.length - 1){
      otpBoxes[index + 1].focus();
    }

    // BUILD OTP
    let otp = "";

    otpBoxes.forEach(b => {
      otp += b.value;
    });

    // AUTO VERIFY
    if(otp.length === 6){

      const response = await fetch("verify_otp.php", {

        method:"POST",

        headers:{
          "Content-Type":"application/x-www-form-urlencoded"
        },

        body:
          "username=" + encodeURIComponent(resetUsername)
          + "&otp=" + encodeURIComponent(otp)

      });

      const result = await response.text();

      const otpMessage =
        document.getElementById("otpMessage");

      if(result.trim() === "success"){

        otpBoxes.forEach(box => {

          box.classList.add("otp-success");
          box.classList.remove("otp-error");

          box.disabled = true;

        });

        otpMessage.innerHTML = "OTP verified";
        otpMessage.className =
          "text-green-600 text-center mt-3";

        setTimeout(() => {

          otpModal.classList.add("hidden");

          passwordModal.classList.remove("hidden");
          passwordModal.classList.add("flex");

        }, 1000);

      }else{

        otpBoxes.forEach(box => {

          box.classList.add("otp-error");
          box.classList.remove("otp-success");

        });

        otpMessage.innerHTML = "Invalid OTP";
        otpMessage.className =
          "text-red-600 text-center mt-3";

        // CLEAR WRONG OTP
        setTimeout(() => {

          otpBoxes.forEach(box => {

            box.value = "";
            box.disabled = false;

            box.classList.remove(
              "otp-error",
              "otp-success"
            );

          });

          otpBoxes[0].focus();

        }, 1200);

      }

    }

  });

  // BACKSPACE
  box.addEventListener("keydown", (e) => {

    if(
      e.key === "Backspace"
      &&
      box.value === ""
      &&
      index > 0
    ){
      otpBoxes[index - 1].focus();
    }

  });

});




// CHANGE PASSWORD
document.getElementById("changePasswordBtn")
.addEventListener("click", async () => {

  const newPassword =
    document.getElementById("newPassword").value;

  const confirmPassword =
    document.getElementById("confirmPassword").value;

  const msg =
    document.getElementById("passwordMessage");

  if(newPassword !== confirmPassword){

    msg.innerHTML = "Passwords do not match";
    msg.className = "text-red-600 text-center mt-3";
    return;

  }

  // SHOW LOADER
changePasswordBtn.disabled = true;

changePasswordText.innerHTML =
  "Updating...";

changePasswordLoader.classList.remove("hidden");

  const response = await fetch("change_password.php", {

    method:"POST",

    headers:{
      "Content-Type":"application/x-www-form-urlencoded"
    },

    body:
      "username=" + encodeURIComponent(resetUsername)
      + "&password=" + encodeURIComponent(newPassword)

  });

  const result = await response.text();

if(result === "success"){

  changePasswordText.innerHTML =
    "Success";

  window.location.href = "index.php";

}else{

  // HIDE LOADER
  changePasswordBtn.disabled = false;

  changePasswordText.innerHTML =
    "Change Password";

  changePasswordLoader.classList.add("hidden");

    msg.innerHTML = result;
    msg.className = "text-red-600 text-center mt-3";

  }

});

</script>

    <script>
if ('serviceWorker' in navigator) {

    window.addEventListener('load', () => {

        navigator.serviceWorker.register('/sw.js')

        .then(() => {
            console.log('Service Worker Registered');
        })

        .catch(error => {
            console.error(error);
        });

    });

}
</script>

<script>

const isAndroidApp =
    typeof window.DTECH_APP !== "undefined";

if (!isAndroidApp) {

    document.getElementById(
        "biometricBtn"
    ).style.display = "none";

}

</script>

<script>

document.getElementById('biometricBtn').addEventListener('click', function () {

    DTECH_APP.startBiometricLogin();

});

</script>

    <script>

let deferredPrompt;

function isDesktop() {
    return !/Android|iPhone|iPad|iPod/i.test(
        navigator.userAgent
    );
}

window.addEventListener(
    'beforeinstallprompt',
    (e) => {

        if (!isDesktop()) {
            return;
        }

        e.preventDefault();

        deferredPrompt = e;

        const hiddenTime =
    localStorage.getItem(
        'dlink_install_hidden'
    );

if (hiddenTime) {

    const daysPassed =
        (Date.now() - hiddenTime) /
        (1000 * 60 * 60 * 24);

    if (daysPassed < 30) {
        return;
    }
}

        const banner =
            document.getElementById('installBanner');

        banner.style.display = 'flex';

      setTimeout(() => {

    banner.style.display = 'none';

    localStorage.setItem(
        'dlink_install_hidden',
        Date.now()
    );

}, 10000);
    }
);

document
.getElementById('installBtn')
.addEventListener(
    'click',
    async () => {

        if (!deferredPrompt) return;

        localStorage.setItem(
            'dlink_install_hidden',
            Date.now()
        );

        deferredPrompt.prompt();

        await deferredPrompt.userChoice;

        document
            .getElementById('installBanner')
            .style.display = 'none';

        deferredPrompt = null;
    }
);

</script>

<script>
  function resetLoginButton(){

    const btn =
        document.getElementById('signInBtn');

    btn.disabled = false;

    btn.innerHTML = 'Sign In';
}
</script>

<?php if ($pendingApproval): ?>
<script>

Swal.fire({
    icon: 'info',
    title: 'Verification Ongoing',
    text: 'Your account is awaiting administrator approval.',
    confirmButtonColor: '#2563eb',
    allowOutsideClick: false
});

</script>
<?php endif; ?>
</body>
</html>

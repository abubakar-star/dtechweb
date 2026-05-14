<?php
session_start();

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
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];

                // ✅ Remember Me feature
                if ($remember) {
                    $token = bin2hex(random_bytes(16)); // Secure random token
                    setcookie("remember_user", $username, time() + (86400 * 30), "/"); // 30 days
                    setcookie("remember_token", $token, time() + (86400 * 30), "/");

                    // Save token to DB for verification later
                    $update = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                    $update->bind_param("si", $token, $row['id']);
                    $update->execute();
                    if ($update->error) {
    error_log("Remember token update failed: " . $update->error);
}
                    $update->close();
                }

                header("Location: index.php");
                exit();
            } else {
                $errorMessage = "Invalid password";
                $attempt = 1;
            }
        } else {
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
          <a href="#" class="font-medium text-white/90 hover:text-white">Forgot your password?</a>
        </div>
      </div>

      <button type="submit" id="signInBtn"
        class="w-full py-3 rounded-md font-semibold
               bg-white/20 hover:bg-white/30 text-white
               ring-1 ring-white/40 shadow-md flex justify-center items-center transition">
        Sign In
      </button>

      <?php if ($errorMessage): ?>
        <p id="loginError" class="error-text text-center"><?php echo $errorMessage; ?></p>
      <?php else: ?>
        <p id="loginError" class="error-text text-center hidden"></p>
      <?php endif; ?>
    </form>

    <p class="text-center text-sm text-white/80">

    <!-- Download APK Button -->
<div class="text-center mt-4">
  <a href="D-LINK.apk" download
     class="inline-flex items-center gap-2 px-5 py-3 rounded-xl
            bg-cyan-500/20 hover:bg-cyan-500/30
            border border-cyan-300/30
            text-white font-medium shadow-lg
            transition duration-300">

    <!-- Download Icon -->
    <svg xmlns="http://www.w3.org/2000/svg"
         class="h-5 w-5"
         fill="none"
         viewBox="0 0 24 24"
         stroke="currentColor">
      <path stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M7 10l5 5m0 0l5-5m-5 5V4"/>
    </svg>

    Download App
  </a>
</div>

      Can't access your account? <br>Contact 
      <a href="#" class="font-medium text-white hover:text-white/90">0758788020</a>
    </p>
  </div>

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
</body>
</html>

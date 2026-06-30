<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: sms_bulk.php");
    exit();
}

$campaignTitle = trim($_POST['campaign_title'] ?? '');
$recipientGroup = trim($_POST['recipient_group'] ?? '');
$packageId = trim($_POST['package_id'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($campaignTitle === '' || $recipientGroup === '' || $message === '') {
    header("Location: sms_bulk.php");
    exit();
}

/* ===============================
   FRIENDLY RECIPIENT GROUP NAME
================================ */

$groupNames = [
    'active'    => 'All Active Customers',
    'expired'   => 'Expired Customers',
    'expiring3' => 'Expiring Within 3 Days',
    'package'   => 'Specific Package'
];

$groupDisplay = $groupNames[$recipientGroup] ?? $recipientGroup;



/* ===============================
   DATABASE CONNECTION
================================ */

$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$username = $_ENV['MYSQLUSER'];
$password = $_ENV['MYSQLPASSWORD'];

$conn = new mysqli($host, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die("Database connection failed");
}

/* ===============================
   PACKAGE NAME
================================ */

$packageName = '';

if ($recipientGroup === 'package' && !empty($packageId)) {

    $stmt = $conn->prepare("
        SELECT package_name
        FROM packages
        WHERE id = ?
    ");

    $stmt->bind_param("i", $packageId);

    $stmt->execute();

    $stmt->bind_result($packageName);

    $stmt->fetch();

    $stmt->close();

}

/* ===============================
   RECIPIENT COUNT
================================ */

$totalRecipients = 0;

switch ($recipientGroup) {

    case 'active':

        $sql = "
            SELECT COUNT(*) AS total
            FROM users
            WHERE status='active'
        ";

        break;

    case 'expired':

        $sql = "
            SELECT COUNT(*) AS total
            FROM users
            WHERE status='active'
            AND Expiry < NOW()
        ";

        break;

    case 'expiring3':

        $sql = "
            SELECT COUNT(*) AS total
            FROM users
            WHERE status='active'
            AND Expiry BETWEEN NOW()
                            AND DATE_ADD(NOW(), INTERVAL 3 DAY)
        ";

        break;

    case 'package':

        $packageId = (int)$packageId;

        $sql = "
            SELECT COUNT(*) AS total
            FROM users
            WHERE status='active'
            AND package_id=$packageId
        ";

        break;

    default:

        $sql = "";

}

if ($sql != "") {

    $result = $conn->query($sql);

    if ($row = $result->fetch_assoc()) {

        $totalRecipients = $row['total'];

    }

}

/* ===============================
   SMS STATISTICS
================================ */

$characterCount = strlen($message);

$smsSegments = max(1, ceil($characterCount / 160));

$totalSmsUnits = $smsSegments * $totalRecipients;
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>Confirm Bulk SMS</title>

<script src="https://cdn.tailwindcss.com"></script>

</head>

<body class="bg-gray-900 min-h-screen">

<div class="max-w-4xl mx-auto p-8">

    <div class="bg-gray-800 rounded-xl p-8">

        <h1 class="text-3xl font-bold text-white mb-6">
            Confirm Bulk SMS
        </h1>

        <div class="space-y-4 text-white">

            <div>
                <strong>Campaign Title:</strong>
                <?= htmlspecialchars($campaignTitle) ?>
            </div>

            <div>
                <strong>Recipient Group:</strong>
               <?= htmlspecialchars($groupDisplay) ?>
            </div>

            <?php if ($recipientGroup === 'package'): ?>

<div>

    <strong>Package:</strong>

    <?= htmlspecialchars($packageName) ?>

</div>

<?php endif; ?>

            <div>

    <strong>Recipients:</strong>

    <?= number_format($totalRecipients) ?>

</div>

<div>

    <strong>Characters:</strong>

    <?= number_format($characterCount) ?>

</div>

<div>

    <strong>SMS Segments:</strong>

    <?= number_format($smsSegments) ?>

</div>

<div>

    <strong>Total SMS Units:</strong>

    <span class="font-bold text-yellow-400">

        <?= number_format($totalSmsUnits) ?>

    </span>

</div>

            <div>
                <strong>Message:</strong>

                <div class="mt-2 bg-gray-700 p-4 rounded whitespace-pre-wrap">
                    <?= htmlspecialchars($message) ?>
                </div>

            </div>

        </div>

        <div class="mt-8 flex justify-between">

            <a
                href="sms_bulk.php"
                class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded">

                ← Back

            </a>

            <button
                class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded">

                Send SMS

            </button>

        </div>

    </div>

</div>

</body>

</html>

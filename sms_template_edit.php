<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set("Africa/Nairobi");

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

$success = '';
$error = '';

/* ===============================
   VALIDATE ID
================================ */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid template ID");
}

$id = (int) $_GET['id'];

/* ===============================
   LOAD TEMPLATE
================================ */
$stmt = $conn->prepare("
    SELECT *
    FROM sms_templates
    WHERE id = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Template not found");
}

$template = $result->fetch_assoc();

/* ===============================
   UPDATE TEMPLATE
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $template_name = trim($_POST['template_name']);
    $message = trim($_POST['message']);

    if (empty($template_name) || empty($message)) {

        $error = "All fields are required.";

    } else {

        $stmt = $conn->prepare("
            UPDATE sms_templates
            SET
                template_name = ?,
                message = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "ssi",
            $template_name,
            $message,
            $id
        );

        if ($stmt->execute()) {

            $success = "Template updated successfully.";

            // Reload updated data
            $stmt = $conn->prepare("
                SELECT *
                FROM sms_templates
                WHERE id = ?
            ");

            $stmt->bind_param("i", $id);
            $stmt->execute();

            $template = $stmt->get_result()->fetch_assoc();

        } else {

            $error = "Failed to update template.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit SMS Template</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-900 min-h-screen">

<div class="max-w-4xl mx-auto p-8">

    <!-- Header -->
    <div class="flex items-center justify-between mb-8">

        <h1 class="text-3xl font-bold text-white">
            ✏️ Edit Template
        </h1>

        <a href="sms_templates.php"
           class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
            ← Back
        </a>

    </div>

    <?php if ($success): ?>
        <div class="bg-green-600 text-white p-4 rounded-lg mb-6">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-600 text-white p-4 rounded-lg mb-6">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="bg-gray-800 rounded-xl p-8 shadow">

        <form method="POST" class="space-y-6">

            <div>
                <label class="block text-gray-300 mb-2">
                    Template Name
                </label>

                <input type="text"
                       name="template_name"
                       value="<?= htmlspecialchars($template['template_name']) ?>"
                       required
                       class="w-full bg-gray-700 text-white rounded-lg p-3 border border-gray-600">
            </div>

            <div>
                <label class="block text-gray-300 mb-2">
                    Message
                </label>

                <textarea
                    name="message"
                    rows="6"
                    required
                    class="w-full bg-gray-700 text-white rounded-lg p-3 border border-gray-600"><?= htmlspecialchars($template['message']) ?></textarea>
            </div>

            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg">
                Update Template
            </button>

        </form>

    </div>

</div>

</body>
</html>
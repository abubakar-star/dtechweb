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
   SAVE TEMPLATE
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $template_name = trim($_POST['template_name']);
    $message = trim($_POST['message']);

    if (empty($template_name) || empty($message)) {

        $error = "All fields are required.";

    } else {

        $stmt = $conn->prepare("
            INSERT INTO sms_templates
            (template_name, message)
            VALUES (?, ?)
        ");

        $stmt->bind_param(
            "ss",
            $template_name,
            $message
        );

        if ($stmt->execute()) {
            $success = "Template saved successfully.";
        } else {
            $error = "Failed to save template.";
        }
    }
}

/* ===============================
   LOAD TEMPLATES
================================ */
$templates = [];

$result = $conn->query("
    SELECT *
    FROM sms_templates
    ORDER BY created_at DESC
");

while ($row = $result->fetch_assoc()) {
    $templates[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SMS Templates</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-900 min-h-screen">

<div class="max-w-6xl mx-auto p-8">

    <!-- Header -->
    <div class="flex items-center justify-between mb-8">

        <h1 class="text-3xl font-bold text-white">
            📝 SMS Templates
        </h1>

        <a href="sms.php"
           class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
            ← Back
        </a>

    </div>

    <!-- Alerts -->
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

    <!-- Add Template Card -->
    <div class="bg-gray-800 rounded-xl p-8 shadow mb-8">

        <h2 class="text-2xl font-bold text-white mb-6">
            Add Template
        </h2>

        <form method="POST" class="space-y-6">

            <div>
                <label class="block text-gray-300 mb-2">
                    Template Name
                </label>

                <input type="text"
                       name="template_name"
                       required
                       placeholder="Example: Maintenance Notice"
                       class="w-full bg-gray-700 text-white rounded-lg p-3 border border-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-gray-300 mb-2">
                    Message
                </label>

                <textarea
                    name="message"
                    rows="5"
                    required
                    placeholder="Enter SMS template..."
                    class="w-full bg-gray-700 text-white rounded-lg p-3 border border-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
            </div>

            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg">
                Save Template
            </button>

        </form>

    </div>

    <!-- Existing Templates -->
    <div class="bg-gray-800 rounded-xl p-8 shadow">

        <h2 class="text-2xl font-bold text-white mb-6">
            Existing Templates
        </h2>

        <?php if (empty($templates)): ?>

            <p class="text-gray-400">
                No templates found.
            </p>

        <?php else: ?>

            <div class="overflow-x-auto">

                <table class="w-full text-left">

                    <thead>
                        <tr class="border-b border-gray-700">

                            <th class="py-3 text-gray-300">
                                Template Name
                            </th>

                            <th class="py-3 text-gray-300">
                                Message Preview
                            </th>

                            <th class="py-3 text-gray-300">
                                Created
                            </th>

                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($templates as $template): ?>

                            <tr class="border-b border-gray-700">

                                <td class="py-4 text-white">
                                    <?= htmlspecialchars($template['template_name']) ?>
                                </td>

                                <td class="py-4 text-gray-300">
                                    <?= htmlspecialchars(substr($template['message'], 0, 80)) ?>...
                                </td>

                                <td class="py-4 text-gray-400">
                                    <?= htmlspecialchars($template['created_at']) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>
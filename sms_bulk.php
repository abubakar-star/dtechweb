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

/* ===============================
   LOAD TEMPLATES
================================ */
$templates = [];

$result = $conn->query("
    SELECT id, template_name, message
    FROM sms_templates
    ORDER BY template_name ASC
");

while ($row = $result->fetch_assoc()) {
    $templates[] = $row;
}

/* ===============================
   LOAD ACTIVE PACKAGES
================================ */
$packages = [];

$result = $conn->query("
    SELECT id, package_name
    FROM packages
    WHERE status='active'
    ORDER BY package_name ASC
");

while ($row = $result->fetch_assoc()) {
    $packages[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">

<title>Bulk SMS</title>

<script src="https://cdn.tailwindcss.com"></script>

</head>

<body class="bg-gray-900 min-h-screen">

<div class="max-w-5xl mx-auto p-8">

    <!-- Header -->
    <div class="flex items-center justify-between mb-8">

        <h1 class="text-3xl font-bold text-white">
            📢 Bulk SMS
        </h1>

        <a href="sms.php"
           class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
            ← Back
        </a>

    </div>

    <div class="bg-gray-800 rounded-xl p-8 shadow">

        <form id="bulkForm" method="POST">

            <div class="space-y-6">

                <!-- Campaign -->
                <div>

                    <label class="block text-gray-300 mb-2">
                        Campaign Title
                    </label>

                    <input
                        type="text"
                        name="campaign_title"
                        class="w-full bg-gray-700 text-white rounded-lg p-3 border border-gray-600"
                        placeholder="Example: July Maintenance Notice"
                        required>

                </div>

                <!-- Recipient Group -->
                <div>

                    <label class="block text-gray-300 mb-2">
                        Recipient Group
                    </label>

                    <select
                        id="recipient_group"
                        name="recipient_group"
                        class="w-full bg-gray-700 text-white rounded-lg p-3 border border-gray-600"
                        required>

                        <option value="">Select Group</option>
                        <option value="active">All Active Customers</option>
                        <option value="expired">Expired Customers</option>
                        <option value="expiring3">Expiring Within 3 Days</option>
                        <option value="package">Specific Package</option>

                    </select>

                </div>

                <!-- Package -->
                <div id="packageBox" class="hidden">

                    <label class="block text-gray-300 mb-2">
                        Package
                    </label>

                    <select
                        name="package_id"
                        class="w-full bg-gray-700 text-white rounded-lg p-3 border border-gray-600">

                        <option value="">Select Package</option>

                        <?php foreach($packages as $package): ?>

                            <option value="<?= $package['id'] ?>">
                                <?= htmlspecialchars($package['package_name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <!-- Template -->
                <div>

                    <label class="block text-gray-300 mb-2">
                        SMS Template
                    </label>

                    <select
                        id="template"
                        class="w-full bg-gray-700 text-white rounded-lg p-3 border border-gray-600">

                        <option value="">Custom Message</option>

                        <?php foreach($templates as $template): ?>

                            <option
                                value="<?= htmlspecialchars($template['message']) ?>">

                                <?= htmlspecialchars($template['template_name']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <!-- Message -->
                <div>

                    <label class="block text-gray-300 mb-2">
                        Message
                    </label>

                    <textarea
                        id="message"
                        name="message"
                        rows="6"
                        class="w-full bg-gray-700 text-white rounded-lg p-3 border border-gray-600"
                        required></textarea>

                    <div class="flex justify-between mt-2 text-sm text-gray-400">

                        <span id="chars">
                            0 Characters
                        </span>

                        <span id="pages">
                            1 SMS
                        </span>

                    </div>

                </div>

                <!-- Preview -->
                <div class="bg-gray-700 rounded-lg p-4">

                    <p class="text-gray-300">

                        Estimated Recipients:

                        <span
                            id="recipientCount"
                            class="font-bold text-green-400">

                            0

                        </span>

                    </p>

                </div>

                <button
                    id="continueBtn"
                    type="submit"
                    disabled
                    class="w-full bg-purple-700 hover:bg-purple-800 disabled:bg-gray-600 text-white font-bold py-3 rounded-lg">

                    Continue

                </button>

            </div>

        </form>

    </div>

</div>

<script>
const packageSelect=document.querySelector('[name="package_id"]');

group.addEventListener('change',()=>{
    packageBox.classList.toggle('hidden',group.value!=='package');
    loadRecipients();
});

packageSelect.addEventListener('change',loadRecipients);

function loadRecipients(){

    const form=new FormData();

    form.append('group',group.value);
    form.append('package_id',packageSelect.value);

    fetch('sms_recipient_count.php',{

        method:'POST',
        body:form

    })
    .then(r=>r.json())
    .then(data=>{

        recipients.textContent=data.count;

        button.disabled=data.count==0;

    });

}
</script>

</body>
</html>
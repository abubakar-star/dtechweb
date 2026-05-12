<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin – Device Bindings</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-900 text-white p-6 min-h-screen">

<div class="max-w-7xl mx-auto">

<h1 class="text-2xl font-bold mb-4">
🔐 Admin – Device Bindings
</h1>

<!-- SEARCH + FILTER -->
<div class="flex flex-col md:flex-row gap-4 mb-4">

<input
    type="text"
    id="search"
    placeholder="Search by name..."
    class="w-full md:w-1/3 bg-gray-800 border border-gray-600 rounded px-4 py-2 text-sm focus:outline-none focus:ring focus:ring-blue-500"
/>

<select
    id="status"
    class="w-full md:w-1/4 bg-gray-800 border border-gray-600 rounded px-4 py-2 text-sm">
    <option value="">All statuses</option>
    <option value="pending">Pending</option>
    <option value="approved">Approved</option>
    <option value="rejected">Rejected</option>
</select>

</div>

<!-- RESULTS -->
<div id="results" class="bg-gray-800 rounded-lg overflow-x-auto">
    <!-- AJAX CONTENT LOADS HERE -->
</div>

</div>

<script>
const searchInput = document.getElementById('search');
const statusSelect = document.getElementById('status');
const resultsDiv = document.getElementById('results');

function loadBindings() {
    const search = searchInput.value;
    const status = statusSelect.value;

    fetch(`fetch_bindings.php?search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}`)
        .then(res => res.text())
        .then(html => {
            resultsDiv.innerHTML = html;
        });
}

searchInput.addEventListener('keyup', loadBindings);
statusSelect.addEventListener('change', loadBindings);

// initial load
loadBindings();
</script>

</body>
</html>

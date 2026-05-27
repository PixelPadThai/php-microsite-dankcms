<?php
http_response_code(503);
header('Retry-After: 3600');
$pageTitle = 'Maintenance';
require __DIR__ . '/../templates/head.php';
?>
<body class="maintenance-body">
  <main class="maintenance-main">
    <h1>We'll be right back</h1>
    <p>This site is undergoing scheduled maintenance. Please check back shortly.</p>
  </main>
</body>
</html>

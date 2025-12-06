<?php
// Simple index to avoid 403 Forbidden when directory listing is disabled.
// Redirects users to the login page of the application.

$target = 'view/page/login/index.php';

if (php_sapi_name() === 'cli') {
    // If run from CLI, show the target for debugging.
    echo "Redirect target: $target\n";
    exit;
}

// Use a relative redirect so it works when the project is served as /wms/
header('Location: ' . $target);
exit;

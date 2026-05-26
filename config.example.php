<?php
/**
 * Copy this file to config.php and fill in your values.
 * config.php is gitignored — secrets stay local.
 */

// === Environment ===
define('APP_ENV', 'dev'); // 'dev' | 'prod'

// === Auth ===
// Generate hash: php -r "echo password_hash('your-password', PASSWORD_DEFAULT) . PHP_EOL;"
define('ADMIN_PASSWORD_HASH', '$2y$12$REPLACE_WITH_YOUR_HASH');
define('ADMIN_IP_ALLOWLIST', []); // empty = any IP

// === Data source ===
define('DATA_SOURCE', 'json'); // 'json' | 'directus'

// === Directus (only used when DATA_SOURCE = 'directus') ===
define('DIRECTUS_URL',   '');
define('DIRECTUS_TOKEN', '');
define('CACHE_TTL', 300);

// === Site ===
define('SITE_URL', 'http://localhost:8000');

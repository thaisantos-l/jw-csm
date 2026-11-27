#!/usr/bin/env bash
# Run once on Hostinger from a private copy of this script and ../config/.
# Does not create/import a database or overwrite an existing wp-config.php.
set -euo pipefail
cms=/home/u343461047/domains/jwusinvest.com.br/public_html/cms
private=/home/u343461047/jw-private
source_root=$(cd "$(dirname "$0")/.." && pwd)
[[ "$(id -un)" == u343461047 && "$(readlink -f "$cms")" == "$cms" ]] || exit 1
mkdir -p "$private"
chmod 700 "$private"
wp_version=$(/usr/bin/php -r '$m=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); echo $m["wordpress"];' "$source_root/config/dependencies.json")
if [[ ! -f "$cms/wp-includes/version.php" ]]; then
  /usr/bin/php -d disable_functions= /usr/local/bin/wp core download --path="$cms" --version="$wp_version" --locale=pt_BR --skip-content
fi
/usr/bin/php -d disable_functions= /usr/local/bin/wp core verify-checksums --path="$cms" --version="$wp_version" --locale=pt_BR
mkdir -p "$cms/wp-content/plugins/jw-core" "$cms/wp-content/themes/jw-headless" "$cms/wp-content/uploads"
temp=$(mktemp -d "$private/dependencies.XXXXXX")
trap 'rm -rf "$temp"' EXIT
/usr/bin/php -r '$m=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);foreach($m["plugins"] as $name=>$version){echo "$name $version\n";}' "$source_root/config/dependencies.json" > "$temp/plugins.list"
while read -r plugin version; do
  [[ "$plugin" =~ ^[a-z0-9-]+$ && "$version" =~ ^[0-9.]+$ ]] || exit 1
  if [[ ! -d "$cms/wp-content/plugins/$plugin" ]]; then
    curl --fail --silent --show-error --location "https://downloads.wordpress.org/plugin/$plugin.$version.zip" -o "$temp/$plugin.zip"
    unzip -tq "$temp/$plugin.zip"
    unzip -q "$temp/$plugin.zip" -d "$temp"
    test -d "$temp/$plugin"
    mv "$temp/$plugin" "$cms/wp-content/plugins/$plugin"
  fi
done < "$temp/plugins.list"
install -m 600 "$source_root/config/production-constants.php" "$private/production-constants.php"
if [[ ! -f "$cms/.htaccess" ]]; then
  install -m 644 "$source_root/config/hosting.htaccess" "$cms/.htaccess"
fi
if [[ ! -f "$cms/wp-config.php" ]]; then
  JW_BOOTSTRAP_CMS="$cms" JW_BOOTSTRAP_PRIVATE="$private" /usr/bin/php <<'PHP'
<?php
$cms = getenv('JW_BOOTSTRAP_CMS');
$private = getenv('JW_BOOTSTRAP_PRIVATE');
$config = <<<'CONFIG'
<?php
// Hosting configuration. Keep credentials outside public_html and outside Git.
$database_file = '/home/u343461047/jw-private/database.php';
if (!is_readable($database_file)) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('Retry-After: 3600');
    exit('{"code":"JW_DATABASE_PENDING"}');
}
$database = require $database_file;
foreach (array('name', 'user', 'password', 'host') as $key) {
    if (!isset($database[$key]) || !is_string($database[$key]) || $database[$key] === '') {
        http_response_code(503);
        exit('JW_DATABASE_PENDING');
    }
}
define('DB_NAME', $database['name']);
define('DB_USER', $database['user']);
define('DB_PASSWORD', $database['password']);
define('DB_HOST', $database['host']);
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
$table_prefix = $database['table_prefix'] ?? 'jw_';
unset($database);
require '/home/u343461047/jw-private/production-constants.php';
CONFIG;
foreach (array('AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT') as $key) {
    $config .= "\ndefine('$key', '" . bin2hex(random_bytes(48)) . "');";
}
$config .= "\nif (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/'); }\nrequire_once ABSPATH . 'wp-settings.php';\n";
file_put_contents($cms . '/wp-config.php', $config);
chmod($cms . '/wp-config.php', 0600);
$example = <<<'EXAMPLE'
<?php
// Copy to database.php, fill the Hostinger credentials and keep permissions 0600.
return array(
    'name' => '',
    'user' => '',
    'password' => '',
    'host' => 'localhost',
    'table_prefix' => 'jw_',
);
EXAMPLE;
if (!file_exists($private . '/database.example.php')) {
    file_put_contents($private . '/database.example.php', $example . "\n");
    chmod($private . '/database.example.php', 0600);
}
PHP
fi
echo 'CMS infrastructure prepared. Database import, credentials and media migration remain separate.'

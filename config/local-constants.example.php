<?php
/** Add to your private wp-config.php before loading wp-settings.php. */
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'DISALLOW_FILE_EDIT', true );
define( 'JW_ALLOWED_ORIGINS', array( 'http://127.0.0.1:3001', 'http://localhost:3001', 'http://127.0.0.1:4174' ) );
// Keep database credentials and unique WordPress salts in wp-config.php, outside Git.
// SMTP is configured through WP Mail SMTP. Local capture: 127.0.0.1:1025,
// no TLS/authentication, with Mailpit bound to loopback and no forwarding.

<?php
/** Production hosting settings; database credentials and salts stay private. */
define( 'WP_ENVIRONMENT_TYPE', 'production' );
define( 'WP_HOME', 'https://cms.jwusinvest.com.br' );
define( 'WP_SITEURL', 'https://cms.jwusinvest.com.br' );
define( 'DISALLOW_FILE_EDIT', true );
define( 'WP_AUTO_UPDATE_CORE', false );
define( 'JW_ALLOWED_ORIGINS', array( 'https://jwusinvest.com.br', 'https://www.jwusinvest.com.br' ) );

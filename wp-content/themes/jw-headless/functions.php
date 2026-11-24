<?php
/** Headless CMS theme. @package JWHeadless */
add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'menus' );
		add_theme_support( 'title-tag' );
	}
);

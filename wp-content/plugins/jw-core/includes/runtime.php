<?php
/** Immediate invalidation and a bounded, versioned public snapshot. @package JWCore */
defined( 'ABSPATH' ) || exit;

/** Coalesce a save's metadata writes until the request completes. */
function jw_core_dirty() {
	static $registered = false;
	if ( ! $registered ) {
		$registered = true;
		add_action(
			'shutdown',
			function () {
				update_option( 'jw_content_revision', wp_generate_uuid4(), false );
			},
			PHP_INT_MAX
		);
	}
}

/** Invalidate all visible post lifecycle changes, including media and menus. */
function jw_core_post_dirty( $post_id ) {
	if ( in_array( get_post_type( $post_id ), array( 'page', 'jw_property', 'attachment', 'nav_menu_item' ), true ) ) {
		jw_core_dirty();
	}
}
add_action( 'save_post', 'jw_core_post_dirty', 100 );
add_action( 'before_delete_post', 'jw_core_post_dirty' );
add_action( 'wp_update_nav_menu', 'jw_core_dirty' );
add_action( 'delete_attachment', 'jw_core_post_dirty' );

/** Only changes that can affect the public projection invalidate it. */
function jw_core_meta_dirty( $meta_id, $post_id, $key ) {
	if ( str_starts_with( $key, 'jw_' ) || str_starts_with( $key, '_wp_attachment_' ) || str_starts_with( $key, '_menu_item_' ) ) {
		jw_core_post_dirty( $post_id );
	}
}
foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $jw_hook ) {
	add_action( $jw_hook, 'jw_core_meta_dirty', 10, 3 );
}
function jw_core_option_dirty( $option ) {
	if ( str_starts_with( $option, 'jw_site_' ) || str_starts_with( $option, 'theme_mods_' ) || in_array( $option, array( 'page_on_front', 'show_on_front', 'siteurl', 'home' ), true ) ) {
		jw_core_dirty();
	}
}
foreach ( array( 'added_option', 'updated_option', 'deleted_option' ) as $jw_hook ) {
	add_action( $jw_hook, 'jw_core_option_dirty' );
}

/** Build from the frozen JWHome operation, with a before/after revision guard. */
function jw_core_snapshot() {
	if ( ! function_exists( 'graphql' ) ) {
		return new WP_Error( 'CONTENT_UNAVAILABLE', 'Conteúdo temporariamente indisponível.', array( 'status' => 503 ) );
	}
	for ( $attempt = 0; $attempt < 3; ++$attempt ) {
		$revision = get_option( 'jw_content_revision', '' );
		$cached   = get_transient( 'jw_public_content' );
		if ( is_array( $cached ) && ( $cached['revision'] ?? null ) === $revision ) {
			return $cached;
		}
		$result = graphql(
			array(
				'query'         => file_get_contents( JW_CORE_PATH . 'contracts/jw-home.graphql' ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Fixed local product contract; no network or user path.
				'operationName' => 'JWHome',
			)
		);
		if ( ! empty( $result['errors'] ) || ! isset( $result['data']['jwContent'] ) ) {
			return new WP_Error( 'CONTENT_UNAVAILABLE', 'Conteúdo temporariamente indisponível.', array( 'status' => 503 ) );
		}
		wp_cache_delete( 'jw_content_revision', 'options' );
		if ( get_option( 'jw_content_revision', '' ) !== $revision ) {
			continue;
		}
		$data     = $result['data']['jwContent'];
		$digest   = hash( 'sha256', wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		$snapshot = array(
			'schemaVersion' => 1,
			'revision'      => $revision,
			'digest'        => $digest,
			'generatedAt'   => gmdate( 'c' ),
			'data'          => $data,
		);
		set_transient( 'jw_public_content', $snapshot, 5 * MINUTE_IN_SECONDS );
		return $snapshot;
	}
	return new WP_Error( 'CONTENT_UPDATING', 'Atualização em andamento. Tente novamente.', array( 'status' => 503 ) );
}

/** Conditional runtime read; never fetches credentials or sends a message. */
function jw_core_content_rest( $request ) {
	$snapshot = jw_core_snapshot();
	if ( is_wp_error( $snapshot ) ) {
		return $snapshot;
	}
	$etag     = '"' . $snapshot['digest'] . '"';
	$response = new WP_REST_Response( $request->get_header( 'if-none-match' ) === $etag ? null : $snapshot, $request->get_header( 'if-none-match' ) === $etag ? 304 : 200 );
	$response->header( 'ETag', $etag );
	$response->header( 'Cache-Control', 'no-cache, max-age=0, must-revalidate' );
	return $response;
}

/** Route-local CORS for conditional public reads. */
function jw_core_runtime_headers( $served, $result, $request ) {
	if ( '/jw/v1/site-content' !== rtrim( $request->get_route(), '/' ) ) {
		return $served;
	}
	foreach ( array( 'Access-Control-Allow-Origin', 'Access-Control-Allow-Credentials', 'Access-Control-Allow-Methods', 'Access-Control-Allow-Headers', 'Access-Control-Expose-Headers' ) as $header ) {
		header_remove( $header );
	}
	header( 'Vary: Origin', false );
	if ( jw_core_contact_origin_allowed( $request->get_header( 'origin' ) ) ) {
		header( 'Access-Control-Allow-Origin: ' . $request->get_header( 'origin' ) );
		header( 'Access-Control-Allow-Methods: GET, OPTIONS' );
		header( 'Access-Control-Allow-Headers: If-None-Match' );
		header( 'Access-Control-Expose-Headers: ETag' );
	}
	header( 'X-Robots-Tag: noindex' );
	return $served;
}
add_filter( 'rest_pre_serve_request', 'jw_core_runtime_headers', PHP_INT_MAX, 3 );
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'jw/v1',
			'/site-content',
			array(
				'methods'             => 'GET',
				'callback'            => 'jw_core_content_rest',
				'permission_callback' => '__return_true',
			)
		);
	}
);

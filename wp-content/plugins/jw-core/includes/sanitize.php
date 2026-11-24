<?php
/** Strict editorial normalization. @package JWCore */
defined( 'ABSPATH' ) || exit;

/** Accept HTTPS, local HTTP during development, root paths and anchors. */
function jw_core_url( $value ) {
	if ( ! is_string( $value ) || strlen( $value ) > 2048 || preg_match( '/[\x00-\x20\\\\]/', $value ) ) {
		return '';
	}
	if ( preg_match( '~^(#[a-zA-Z][\w-]*|/(?!/)[^<>]*)$~D', $value ) ) {
		return $value;
	}
	$parts = wp_parse_url( $value );
	if ( ! is_array( $parts ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
		return '';
	}
	$local = 'local' === wp_get_environment_type() && in_array( $parts['host'], array( 'localhost', '127.0.0.1' ), true );
	return ( 'https' === ( $parts['scheme'] ?? '' ) || ( $local && 'http' === ( $parts['scheme'] ?? '' ) ) ) ? esc_url_raw( $value, array( 'https', 'http' ) ) : '';
}

/** Bound strings by Unicode code points. */
function jw_core_text( $value, $limit = 300, $multiline = false ) {
	if ( ! is_string( $value ) ) {
		return '';
	}
	$value = $multiline ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
	return mb_substr( trim( $value ), 0, $limit );
}

/** Normalize the complete known schema; unknown keys never persist. */
function jw_core_sanitize_fields( $input, $schema ) {
	$input  = is_array( $input ) ? $input : array();
	$output = array();
	foreach ( $schema as $key => $field ) {
		$value = $input[ $key ] ?? null;
		switch ( $field['type'] ) {
			case 'boolean':
				$output[ $key ] = in_array( $value, array( true, 1, '1' ), true );
				break;
			case 'image':
			case 'property':
			case 'page':
				$output[ $key ] = is_scalar( $value ) ? absint( $value ) : 0;
				break;
			case 'repeater':
				$output[ $key ] = array();
				foreach ( array_slice( is_array( $value ) ? $value : array(), 0, 20 ) as $row ) {
					$output[ $key ][] = jw_core_sanitize_fields( $row, $field['children'] );
				}
				break;
			case 'link':
				$value          = is_array( $value ) ? $value : array();
				$output[ $key ] = array(
					'label'  => jw_core_text( $value['label'] ?? '' ),
					'url'    => jw_core_url( $value['url'] ?? '' ),
					'newTab' => ! empty( $value['newTab'] ),
				);
				break;
			case 'url':
				$output[ $key ] = jw_core_url( $value );
				break;
			case 'email':
				$output[ $key ] = is_string( $value ) && is_email( $value ) ? sanitize_email( $value ) : '';
				break;
			case 'rich':
				$output[ $key ] = is_string( $value ) ? wp_kses_post( mb_substr( $value, 0, 15000 ) ) : '';
				break;
			default:
				$output[ $key ] = jw_core_text( $value, 'textarea' === $field['type'] ? 4000 : 300, 'textarea' === $field['type'] );
		}
	}
	return $output;
}

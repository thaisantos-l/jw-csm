<?php
/** Published-only projection from native WordPress metadata. @package JWCore */
defined( 'ABSPATH' ) || exit;

/** Authenticated callers receive the same public projection as anonymous ones. */
function jw_core_public_post( $id, $type ) {
	$post = get_post( absint( $id ) );
	return $post && $type === $post->post_type && 'publish' === $post->post_status && '' === $post->post_password ? $post : null;
}

/** Resolve media from attachment metadata, preserving editorial alternatives. */
function jw_core_image( $id ) {
	$post = get_post( absint( $id ) );
	if ( ! $post || 'attachment' !== $post->post_type || ! in_array( $post->post_status, array( 'inherit', 'publish' ), true ) || ! str_starts_with( (string) $post->post_mime_type, 'image/' ) ) {
		return null;
	}
	if ( $post->post_parent && ! jw_core_public_post( $post->post_parent, get_post_type( $post->post_parent ) ) ) {
		return null;
	}
	$src    = wp_get_attachment_url( $post->ID );
	$meta   = wp_get_attachment_metadata( $post->ID );
	$width  = absint( $meta['width'] ?? 0 );
	$height = absint( $meta['height'] ?? 0 );
	if ( ! $src || ! $width || ! $height || ! jw_core_url( $src ) ) {
		return null;
	}
	$srcset = wp_get_attachment_image_srcset( $post->ID, 'full' );
	return array(
		'id'     => $post->ID,
		'src'    => $src,
		'alt'    => jw_core_text( get_post_meta( $post->ID, '_wp_attachment_image_alt', true ) ),
		'width'  => $width,
		'height' => $height,
		'srcSet' => $srcset ? $srcset : '',
	);
}

/** Convert sanitized field definitions into their public values. */
function jw_core_project( $data, $schema ) {
	$data = jw_core_sanitize_fields( $data, $schema );
	foreach ( $schema as $key => $field ) {
		if ( 'image' === $field['type'] ) {
			$data[ $key ] = jw_core_image( $data[ $key ] );
		} elseif ( 'rich' === $field['type'] ) {
			$data[ $key ] = trim( wp_strip_all_tags( $data[ $key ] ) ) ? wp_kses_post( wpautop( $data[ $key ] ) ) : '';
		} elseif ( 'repeater' === $field['type'] ) {
			$data[ $key ] = array_map(
				function ( $row ) use ( $field ) {
					return jw_core_project( $row, $field['children'] );
				},
				$data[ $key ]
			);
		}
	}
	return $data;
}

/** Resolve an explicitly selected, published property. */
function jw_core_property( $id ) {
	$post = jw_core_public_post( $id, 'jw_property' );
	if ( ! $post ) {
		return null;
	}
	return array(
		'id'    => $post->ID,
		'title' => jw_core_text( $post->post_title ),
	) + jw_core_project( get_post_meta( $post->ID, 'jw_property_data', true ), jw_core_property_schema() );
}

/** Native menus, bounded and with safe destinations. */
function jw_core_menu( $location, $portfolio_visible ) {
	$locations = get_nav_menu_locations();
	$items     = wp_get_nav_menu_items( $locations[ $location ] ?? 0 );
	$output    = array();
	foreach ( array_slice( is_array( $items ) ? $items : array(), 0, 20 ) as $item ) {
		$url = jw_core_url( $item->url );
		if ( ! $url || ( ! $portfolio_visible && '#portfolio' === $url ) ) {
			continue;
		}
		if ( 'post_type' === $item->type && ! jw_core_public_post( $item->object_id, $item->object ) ) {
			continue;
		}
		$output[] = array(
			'label'  => jw_core_text( $item->title ),
			'url'    => $url,
			'newTab' => '_blank' === $item->target,
		);
	}
	return $output;
}

/** Only complete, published legal pages appear publicly. */
function jw_core_legal( $id, $slug ) {
	$post = jw_core_public_post( $id, 'page' );
	if ( ! $post || ! trim( wp_strip_all_tags( $post->post_content ) ) ) {
		return null;
	}
	return array(
		'title' => jw_core_text( $post->post_title ),
		'slug'  => $slug,
		'body'  => wp_kses_post( wpautop( $post->post_content ) ),
	);
}

/** Single typed source for JWHome GraphQL and the runtime envelope. */
function jw_core_content() {
	$post = jw_core_public_post( get_option( 'page_on_front' ), 'page' );
	$home = null;
	if ( $post ) {
		$home = array( 'id' => $post->ID );
		foreach ( jw_core_home_schema() as $key => $group ) {
			$home[ $key ] = jw_core_project( get_post_meta( $post->ID, 'jw_home_' . $key, true ), $group['fields'] );
		}
		$selection                       = $home['portfolio']['items'];
		$home['portfolio']['properties'] = array();
		if ( $home['portfolio']['enabled'] ) {
			foreach ( $selection as $row ) {
				$property = jw_core_property( $row['property'] );
				if ( $property ) {
					$home['portfolio']['properties'][] = $property;
				}
			}
		}
		unset( $home['portfolio']['items'] );
	}
	$site = array();
	foreach ( jw_core_site_schema() as $key => $group ) {
		$site[ $key ] = jw_core_project( get_option( 'jw_site_' . $key, array() ), $group['fields'] );
	}
	$site['contacts']['formEnabled'] = (bool) $home && jw_core_contact_email( $site['contacts']['recipient'] );
	unset( $site['contacts']['recipient'] );
	$visible            = $home && $home['portfolio']['enabled'];
	$site['headerMenu'] = jw_core_menu( 'jw_header', $visible );
	$site['footerMenu'] = jw_core_menu( 'jw_footer', $visible );
	$site['privacy']    = jw_core_legal( $site['footer']['privacyPage'], 'privacidade' );
	$site['terms']      = jw_core_legal( $site['footer']['termsPage'], 'termos' );
	unset( $site['footer']['privacyPage'], $site['footer']['termsPage'] );
	return array(
		'home' => $home,
		'site' => $site,
	);
}

<?php
/** Typed public GraphQL contract. @package JWCore */
defined( 'ABSPATH' ) || exit;

/** Register a group and recursively typed repeater rows. */
function jw_core_graphql_group( $name, $schema ) {
	$fields = array();
	foreach ( $schema as $key => $definition ) {
		$type = $definition['type'];
		if ( 'recipient' === $key || in_array( $type, array( 'page', 'property' ), true ) ) {
			continue;
		}
		if ( 'repeater' === $type ) {
			$row_name = $name . ucfirst( $key ) . 'Item';
			jw_core_graphql_group( $row_name, $definition['children'] );
			$gql_type = array( 'list_of' => $row_name );
		} else {
			$gql_type = array(
				'boolean' => 'Boolean',
				'image'   => 'JWImage',
				'link'    => 'JWLink',
			)[ $type ] ?? 'String';
		}
		$fields[ $key ] = array(
			'type'        => $gql_type,
			'description' => $definition['label'],
		);
	}
	if ( 'JWHomePortfolio' === $name ) {
		unset( $fields['items'] );
		$fields['properties'] = array( 'type' => array( 'list_of' => 'JWPropertyCard' ) );
	}
	if ( 'JWSiteContacts' === $name ) {
		$fields['formEnabled'] = array( 'type' => 'Boolean' );
	}
	if ( 'JWPropertyCard' === $name ) {
		$fields['id']    = array( 'type' => 'Int' );
		$fields['title'] = array( 'type' => 'String' );
	}
	register_graphql_object_type( $name, array( 'fields' => $fields ) );
}

/** Register explicit public roots, without credentials or contact storage. */
function jw_core_graphql_register() {
	$image_fields = array();
	foreach ( array( 'id', 'width', 'height', 'src', 'alt', 'srcSet' ) as $key ) {
		$image_fields[ $key ] = array( 'type' => in_array( $key, array( 'id', 'width', 'height' ), true ) ? 'Int' : 'String' );
	}
	register_graphql_object_type( 'JWImage', array( 'fields' => $image_fields ) );
	register_graphql_object_type(
		'JWLink',
		array(
			'fields' => array(
				'label'  => array( 'type' => 'String' ),
				'url'    => array( 'type' => 'String' ),
				'newTab' => array( 'type' => 'Boolean' ),
			),
		)
	);
	jw_core_graphql_group( 'JWPropertyCard', jw_core_property_schema() );
	$home_fields = array( 'id' => array( 'type' => 'Int' ) );
	foreach ( jw_core_home_schema() as $key => $group ) {
		$schema = $group['fields'];
		if ( 'portfolio' === $key ) {
			unset( $schema['items'] );
		}
		jw_core_graphql_group( 'JWHome' . ucfirst( $key ), $schema );
		$home_fields[ $key ] = array( 'type' => 'JWHome' . ucfirst( $key ) );
	}
	register_graphql_object_type( 'JWHomeContent', array( 'fields' => $home_fields ) );
	$site_fields = array();
	foreach ( jw_core_site_schema() as $key => $group ) {
		jw_core_graphql_group( 'JWSite' . ucfirst( $key ), $group['fields'] );
		$site_fields[ $key ] = array( 'type' => 'JWSite' . ucfirst( $key ) );
	}
	register_graphql_object_type(
		'JWLegal',
		array(
			'fields' => array(
				'title' => array( 'type' => 'String' ),
				'slug'  => array( 'type' => 'String' ),
				'body'  => array( 'type' => 'String' ),
			),
		)
	);
	foreach ( array( 'headerMenu', 'footerMenu' ) as $key ) {
		$site_fields[ $key ] = array( 'type' => array( 'list_of' => 'JWLink' ) );
	}
	$site_fields['privacy'] = array( 'type' => 'JWLegal' );
	$site_fields['terms']   = array( 'type' => 'JWLegal' );
	register_graphql_object_type( 'JWSite', array( 'fields' => $site_fields ) );
	register_graphql_object_type(
		'JWContent',
		array(
			'fields' => array(
				'home' => array( 'type' => 'JWHomeContent' ),
				'site' => array( 'type' => 'JWSite' ),
			),
		)
	);
	register_graphql_field(
		'RootQuery',
		'jwContent',
		array(
			'type'        => 'JWContent',
			'resolve'     => 'jw_core_content',
			'description' => 'Published JW content. Identical for authenticated and anonymous callers.',
		)
	);
}
add_action( 'graphql_register_types', 'jw_core_graphql_register' );

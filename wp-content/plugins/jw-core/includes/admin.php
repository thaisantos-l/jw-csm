<?php
/** Native metaboxes, media controls and accessible repeaters. @package JWCore */
defined( 'ABSPATH' ) || exit;

/** Render a known field definition. All values are escaped at the boundary. */
function jw_core_render_field( $name, $field, $value, $template = false ) {
	$id   = 'jw_' . substr( md5( $name ), 0, 12 );
	$type = $field['type'];
	echo '<div class="jw-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label>';
	if ( 'repeater' === $type ) {
		$rows = is_array( $value ) ? $value : array();
		echo '<div class="jw-repeater" data-name="' . esc_attr( $name ) . '"><div class="jw-rows">';
		foreach ( $rows as $index => $row ) {
			jw_core_render_row( $name, $index, $field['children'], $row );
		}
		echo '</div><template>';
		jw_core_render_row( $name, '__ROW__', $field['children'], array(), true );
		echo '</template><button type="button" class="button jw-add">Adicionar item</button><p class="description">Até 20 itens. Use Subir/Descer para definir a ordem.</p></div>';
	} elseif ( 'image' === $type ) {
		$src = $value ? wp_get_attachment_url( absint( $value ) ) : '';
		echo '<div class="jw-media"><input id="' . esc_attr( $id ) . '" type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( absint( $value ) ) . '">';
		echo '<img alt="Prévia da mídia" src="' . esc_url( $src ? $src : '' ) . '" ' . ( $src ? '' : 'hidden' ) . '><p><button class="button jw-pick" type="button">Selecionar imagem</button> <button class="button jw-clear" type="button">Remover</button></p><p class="description">Texto alternativo editável na Biblioteca de mídia.</p></div>';
	} elseif ( 'link' === $type ) {
		$value = is_array( $value ) ? $value : array();
		foreach ( array(
			'label' => 'Texto do link',
			'url'   => 'Destino HTTPS, /caminho ou #âncora',
		) as $sub => $label ) {
			echo '<label class="jw-sublabel">' . esc_html( $label ) . '<input type="text" maxlength="2048" name="' . esc_attr( $name . '[' . $sub . ']' ) . '" value="' . esc_attr( $value[ $sub ] ?? '' ) . '"></label>';
		}
		echo '<label class="jw-sublabel"><input type="checkbox" name="' . esc_attr( $name . '[newTab]' ) . '" value="1" ' . checked( ! empty( $value['newTab'] ), true, false ) . '> Abrir em nova aba</label>';
	} elseif ( 'boolean' === $type ) {
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0"><input id="' . esc_attr( $id ) . '" type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( (bool) $value, true, false ) . '>';
	} elseif ( in_array( $type, array( 'property', 'page' ), true ) ) {
		$posts = get_posts(
			array(
				'post_type'      => 'property' === $type ? 'jw_property' : 'page',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Bounded admin-only selector, never a public connection.
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"><option value="0">Selecione</option>';
		foreach ( $posts as $post ) {
			echo '<option value="' . esc_attr( $post->ID ) . '" ' . selected( (int) $value, $post->ID, false ) . '>' . esc_html( $post->post_title . ( 'publish' !== $post->post_status ? ' (' . $post->post_status . ')' : '' ) ) . '</option>';
		}
		echo '</select><p class="description">Somente conteúdo publicado aparece no site.</p>';
	} elseif ( 'rich' === $type && ! $template ) {
		wp_editor(
			is_string( $value ) ? $value : '',
			$id,
			array(
				'textarea_name' => $name,
				'textarea_rows' => 12,
				'media_buttons' => false,
				'teeny'         => true,
			)
		);
	} elseif ( in_array( $type, array( 'textarea', 'rich' ), true ) ) {
		echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="4" maxlength="4000">' . esc_textarea( is_string( $value ) ? $value : '' ) . '</textarea>';
	} else {
		echo '<input id="' . esc_attr( $id ) . '" type="' . esc_attr( 'email' === $type ? 'email' : 'text' ) . '" maxlength="' . ( 'url' === $type ? '2048' : '300' ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $value ) ? $value : '' ) . '">';
	}
	echo '</div>';
}

/** Render one reorderable row; templates are inert until added. */
function jw_core_render_row( $name, $index, $schema, $row, $template = false ) {
	echo '<fieldset class="jw-row"><legend>Item <span class="jw-number">' . esc_html( is_numeric( $index ) ? $index + 1 : '' ) . '</span></legend>';
	foreach ( $schema as $key => $field ) {
		jw_core_render_field( $name . '[' . $index . '][' . $key . ']', $field, $row[ $key ] ?? null, $template );
	}
	echo '<div class="jw-row-actions"><button type="button" class="button jw-up" aria-label="Mover item para cima">Subir</button> <button type="button" class="button jw-down" aria-label="Mover item para baixo">Descer</button> <button type="button" class="button jw-remove">Remover item</button></div></fieldset>';
}

/** Register Home and property metaboxes. */
function jw_core_metaboxes( $type, $post ) {
	if ( 'page' === $type && (int) get_option( 'page_on_front' ) === $post->ID ) {
		foreach ( jw_core_home_schema() as $key => $group ) {
			add_meta_box(
				'jw_home_' . $key,
				$group['label'],
				'jw_core_home_box',
				'page',
				'normal',
				'default',
				array(
					'key'    => $key,
					'fields' => $group['fields'],
				)
			);
		}
	} elseif ( 'jw_property' === $type ) {
		add_meta_box( 'jw_property_data', 'Dados do imóvel', 'jw_core_property_box', 'jw_property', 'normal', 'high' );
	}
}
add_action( 'add_meta_boxes', 'jw_core_metaboxes', 10, 2 );

/** Render a Home group with its own nonce (supports hidden metaboxes). */
function jw_core_home_box( $post, $box ) {
	$key = $box['args']['key'];
	wp_nonce_field( 'jw_home_' . $key, 'jw_nonce_' . $key );
	$data = get_post_meta( $post->ID, 'jw_home_' . $key, true );
	foreach ( $box['args']['fields'] as $field => $definition ) {
		jw_core_render_field( 'jw_home[' . $key . '][' . $field . ']', $definition, $data[ $field ] ?? null );
	}
}

/** Render property fields. */
function jw_core_property_box( $post ) {
	wp_nonce_field( 'jw_property', 'jw_property_nonce' );
	$data = get_post_meta( $post->ID, 'jw_property_data', true );
	foreach ( jw_core_property_schema() as $key => $field ) {
		jw_core_render_field( 'jw_property[' . $key . ']', $field, $data[ $key ] ?? null );
	}
}

/** Persist only authorized full groups; revisions/autosaves never clear fields. */
function jw_core_save_post( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( 'jw_property' === get_post_type( $post_id ) && isset( $_POST['jw_property_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jw_property_nonce'] ) ), 'jw_property' ) ) {
		update_post_meta( $post_id, 'jw_property_data', jw_core_sanitize_fields( isset( $_POST['jw_property'] ) ? wp_unslash( $_POST['jw_property'] ) : array(), jw_core_property_schema() ) );
	}
	if ( (int) get_option( 'page_on_front' ) !== $post_id ) {
		return;
	}
	foreach ( jw_core_home_schema() as $key => $group ) {
		$nonce = 'jw_nonce_' . $key;
		if ( isset( $_POST[ $nonce ], $_POST['jw_home'][ $key ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce ] ) ), 'jw_home_' . $key ) ) {
			update_post_meta( $post_id, 'jw_home_' . $key, jw_core_sanitize_fields( wp_unslash( $_POST['jw_home'][ $key ] ), $group['fields'] ) );
		}
	}
}
add_action( 'save_post', 'jw_core_save_post' );

/** Register a conventional settings page and native menus access. */
function jw_core_admin_menu() {
	add_menu_page( 'JW US Invest', 'JW US Invest', 'manage_options', 'jw-settings', 'jw_core_settings_page', 'dashicons-admin-home', 3 );
	add_submenu_page( 'jw-settings', 'Configurações', 'Configurações', 'manage_options', 'jw-settings', 'jw_core_settings_page' );
	add_submenu_page( 'jw-settings', 'Editar Home', 'Editar Home', 'edit_pages', 'post.php?post=' . absint( get_option( 'page_on_front' ) ) . '&action=edit' );
	add_submenu_page( 'jw-settings', 'Menus', 'Menus', 'edit_theme_options', 'nav-menus.php' );
}
add_action( 'admin_menu', 'jw_core_admin_menu' );

/** Render/save global settings using a nonce and capability gate. */
function jw_core_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( isset( $_POST['jw_settings_nonce'] ) ) {
		check_admin_referer( 'jw_settings', 'jw_settings_nonce' );
		foreach ( jw_core_site_schema() as $key => $group ) {
			update_option( 'jw_site_' . $key, jw_core_sanitize_fields( isset( $_POST['jw_site'][ $key ] ) ? wp_unslash( $_POST['jw_site'][ $key ] ) : array(), $group['fields'] ), false );
		}
		echo '<div class="notice notice-success"><p>Configurações salvas. O site recebe as alterações automaticamente.</p></div>';
	}
	echo '<div class="wrap jw-settings"><h1>JW US Invest — Configurações</h1><p>Conteúdo compartilhado do site. O SMTP é configurado separadamente no plugin WP Mail SMTP.</p><form method="post">';
	wp_nonce_field( 'jw_settings', 'jw_settings_nonce' );
	foreach ( jw_core_site_schema() as $key => $group ) {
		echo '<section class="jw-settings-group"><h2>' . esc_html( $group['label'] ) . '</h2>';
		$data = get_option( 'jw_site_' . $key, array() );
		foreach ( $group['fields'] as $field => $definition ) {
			jw_core_render_field( 'jw_site[' . $key . '][' . $field . ']', $definition, $data[ $field ] ?? null );
		}
		echo '</section>';
	}
	submit_button( 'Salvar configurações' );
	echo '</form></div>';
}

/** Load editor assets only on JW editing screens. */
function jw_core_admin_assets( $hook ) {
	$screen = get_current_screen();
	if ( 'toplevel_page_jw-settings' !== $hook && ! in_array( $screen->post_type ?? '', array( 'page', 'jw_property' ), true ) ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_style( 'jw-admin', JW_CORE_URL . 'assets/admin.css', array(), '1.0.0' );
	wp_enqueue_script( 'jw-admin', JW_CORE_URL . 'assets/admin.js', array( 'jquery', 'media-editor' ), '1.0.0', true );
}
add_action( 'admin_enqueue_scripts', 'jw_core_admin_assets' );
add_filter(
	'use_block_editor_for_post',
	function ( $enabled, $post ) {
		return (int) get_option( 'page_on_front' ) === $post->ID ? false : $enabled;
	},
	10,
	2
);
add_filter(
	'admin_body_class',
	function ( $classes ) {
		global $post;
		return $post && (int) get_option( 'page_on_front' ) === $post->ID ? $classes . ' jw-home-editor' : $classes;
	}
);

<?php
/** Editorial definitions shared by validation, UI and GraphQL. @package JWCore */
defined( 'ABSPATH' ) || exit;

/** Compact field definition. */
function jw_core_field( $label, $type = 'text', $children = array() ) {
	return array(
		'label'    => $label,
		'type'     => $type,
		'children' => $children,
	);
}

/** Common editable section heading. */
function jw_core_heading() {
	return array(
		'eyebrow'   => jw_core_field( 'Chamada' ),
		'title'     => jw_core_field( 'Título' ),
		'highlight' => jw_core_field( 'Trecho do título em destaque (copie um trecho exato)' ),
	);
}

/** The Home is a single page with independent, bounded groups. */
function jw_core_home_schema() {
	$heading = jw_core_heading();
	$item    = array(
		'title'       => jw_core_field( 'Título' ),
		'description' => jw_core_field( 'Descrição', 'textarea' ),
	);
	return array(
		'hero'          => array(
			'label'  => '01 — Hero',
			'fields' => $heading + array(
				'description' => jw_core_field( 'Apresentação', 'textarea' ),
				'image'       => jw_core_field( 'Imagem de fundo', 'image' ),
				'primary'     => jw_core_field( 'CTA principal', 'link' ),
				'secondary'   => jw_core_field( 'CTA secundário', 'link' ),
			),
		),
		'about'         => array(
			'label'  => '02 — Sobre',
			'fields' => $heading + array( 'body' => jw_core_field( 'Texto de apresentação', 'rich' ) ),
		),
		'institutional' => array(
			'label'  => '03 — Imagem institucional',
			'fields' => array(
				'image'      => jw_core_field( 'Imagem', 'image' ),
				'decorative' => jw_core_field( 'Imagem decorativa (não exige texto alternativo)', 'boolean' ),
			),
		),
		'activities'    => array(
			'label'  => '04 — Frentes de atuação',
			'fields' => $heading + array(
				'items' => jw_core_field(
					'Frentes',
					'repeater',
					array(
						'title'       => jw_core_field( 'Título' ),
						'subtitle'    => jw_core_field( 'Subtítulo' ),
						'description' => jw_core_field( 'Descrição', 'textarea' ),
					)
				),
			),
		),
		'thesis'        => array(
			'label'  => '05 — Tese de investimentos',
			'fields' => $heading + array( 'items' => jw_core_field( 'Argumentos', 'repeater', $item ) ),
		),
		'criteria'      => array(
			'label'  => '06 — Critérios',
			'fields' => $heading + array( 'items' => jw_core_field( 'Critérios', 'repeater', $item + array( 'icon' => jw_core_field( 'Ícone decorativo', 'image' ) ) ) ),
		),
		'portfolio'     => array(
			'label'  => '07 — Imóveis na Home',
			'fields' => array( 'enabled' => jw_core_field( 'Exibir imóveis na Home', 'boolean' ) ) + $heading + array(
				'items' => jw_core_field( 'Imóveis selecionados — use as setas para ordenar', 'repeater', array( 'property' => jw_core_field( 'Imóvel', 'property' ) ) ),
			),
		),
		'steps'         => array(
			'label'  => '08 — Como investir',
			'fields' => $heading + array( 'items' => jw_core_field( 'Etapas', 'repeater', $item ) ),
		),
		'faq'           => array(
			'label'  => '09 — Perguntas frequentes',
			'fields' => $heading + array(
				'items' => jw_core_field(
					'Perguntas — somente itens com resposta aparecem no site',
					'repeater',
					array(
						'question' => jw_core_field( 'Pergunta' ),
						'answer'   => jw_core_field( 'Resposta', 'textarea' ),
					)
				),
			),
		),
		'contact'       => array(
			'label'  => '10 — Contato',
			'fields' => $heading + array( 'submitLabel' => jw_core_field( 'Texto do botão do formulário' ) ),
		),
	);
}

/** Fields owned by each property. */
function jw_core_property_schema() {
	return array(
		'image'    => jw_core_field( 'Foto do imóvel', 'image' ),
		'location' => jw_core_field( 'Localização' ),
		'kind'     => jw_core_field( 'Tipo' ),
		'ticket'   => jw_core_field( 'Ticket — texto exibido sem reformatação' ),
		'status'   => jw_core_field( 'Situação' ),
		'link'     => jw_core_field( 'Link opcional do card', 'link' ),
	);
}

/** Global settings contain editorial values, never SMTP credentials. */
function jw_core_site_schema() {
	return array(
		'identity' => array(
			'label'  => 'Identidade e SEO',
			'fields' => array(
				'name'           => jw_core_field( 'Nome do site' ),
				'logo'           => jw_core_field( 'Logo do cabeçalho', 'image' ),
				'footerMark'     => jw_core_field( 'Símbolo do rodapé', 'image' ),
				'footerWordmark' => jw_core_field( 'Nome no rodapé', 'image' ),
				'seoTitle'       => jw_core_field( 'Título SEO' ),
				'seoDescription' => jw_core_field( 'Descrição SEO', 'textarea' ),
				'socialImage'    => jw_core_field( 'Imagem de compartilhamento', 'image' ),
			),
		),
		'contacts' => array(
			'label'  => 'Contato compartilhado',
			'fields' => array(
				'email'           => jw_core_field( 'E-mail exibido no site', 'email' ),
				'recipient'       => jw_core_field( 'Destinatário do formulário (somente servidor)', 'email' ),
				'phone'           => jw_core_field( 'Telefone' ),
				'instagramHandle' => jw_core_field( '@ do Instagram' ),
				'instagramUrl'    => jw_core_field( 'URL do Instagram', 'url' ),
				'channelsLabel'   => jw_core_field( 'Título dos canais' ),
				'addresses'       => jw_core_field(
					'Endereços',
					'repeater',
					array(
						'title'   => jw_core_field( 'País / identificação' ),
						'address' => jw_core_field( 'Endereço', 'textarea' ),
					)
				),
			),
		),
		'footer'   => array(
			'label'  => 'Rodapé',
			'fields' => array(
				'description'     => jw_core_field( 'Descrição', 'textarea' ),
				'copyright'       => jw_core_field( 'Copyright' ),
				'navigationTitle' => jw_core_field( 'Título da coluna de navegação' ),
				'contactTitle'    => jw_core_field( 'Título da coluna de contato' ),
				'contactLink'     => jw_core_field( 'Link de contato', 'link' ),
				'privacyPage'     => jw_core_field( 'Página de privacidade', 'page' ),
				'termsPage'       => jw_core_field( 'Página de termos', 'page' ),
			),
		),
	);
}

/** Register public API entities and native menu locations. */
function jw_core_register_types() {
	register_post_type(
		'jw_property',
		array(
			'labels'              => array(
				'name'          => 'Imóveis',
				'singular_name' => 'Imóvel',
				'add_new_item'  => 'Adicionar imóvel',
				'edit_item'     => 'Editar imóvel',
			),
			'public'              => false,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_rest'        => false,
			'show_in_graphql'     => true,
			'graphql_single_name' => 'JWProperty',
			'graphql_plural_name' => 'JWProperties',
			'has_archive'         => false,
			'rewrite'             => false,
			'supports'            => array( 'title', 'revisions' ),
			'menu_icon'           => 'dashicons-building',
		)
	);
	register_nav_menus(
		array(
			'jw_header' => 'JW — Cabeçalho',
			'jw_footer' => 'JW — Rodapé',
		)
	);
	foreach ( jw_core_home_schema() as $key => $group ) {
		register_post_meta(
			'page',
			'jw_home_' . $key,
			array(
				'single'       => true,
				'type'         => 'object',
				'show_in_rest' => false,
			)
		);
	}
	register_post_meta(
		'jw_property',
		'jw_property_data',
		array(
			'single'       => true,
			'type'         => 'object',
			'show_in_rest' => false,
		)
	);
}
add_action( 'init', 'jw_core_register_types' );

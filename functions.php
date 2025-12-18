<?php

/* use theme CSS file for the site */
add_action('enqueue_block_assets', function () {
	$theme = wp_get_theme();

	wp_enqueue_style(
		'dws-main-styles',
		get_theme_file_uri('assets/css/main.css'),
		['wp-block-library', 'global-styles'],
		$theme->get('Version')
	);

	wp_enqueue_script(
		'dsw-main-js', 
		get_theme_file_uri('assets/js/dsw.js'), 
		null, 
		$theme->get('Version'), 
		true
	);
}, 20);

/* use theme CSS file in the editor UI */
add_action('after_setup_theme', function () {
	add_theme_support('editor-styles');
	add_editor_style('assets/css/main.css');
});

function dws_relative_url($url) {
	if (is_admin() || is_feed()) return $url;
	return wp_make_link_relative($url);
}

add_filter('the_permalink', 'dws_relative_url');
add_filter('wp_get_attachment_url', 'dws_relative_url');
add_filter('script_loader_src', 'dws_relative_url');
add_filter('style_loader_src', 'dws_relative_url');

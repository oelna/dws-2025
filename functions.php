<?php

add_action('enqueue_block_assets', function () {
	$theme = wp_get_theme();

	wp_enqueue_style(
		'dws-main-styles',
		get_theme_file_uri('assets/css/main.css'),
		['wp-block-library', 'global-styles'],
		$theme->get('Version')
	);
}, 20);

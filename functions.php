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
		'dws-main-js', 
		get_theme_file_uri('assets/js/dws.js'), 
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

function dws_favicon() {
	echo('<link rel="icon" href="'.get_template_directory_uri().'/assets/img/favicon.svg" sizes="any" type="image/svg+xml">' . "\n");
	echo('<link rel="icon" href="'.get_template_directory_uri().'/assets/img/favicon.ico" sizes="32x32" type="image/x-icon">' . "\n");
}

add_action('wp_head', 'dws_favicon');
add_action('admin_head', 'dws_favicon');
add_action('login_head', 'dws_favicon');

// INACTIVE FOR NOW
// Automatic theme updates from the GitHub repository: https://gist.github.com/slfrsn/a75b2b9ef7074e22ce3b
// add_filter('pre_set_site_transient_update_themes', 'automatic_GitHub_updates', 100, 1);
function automatic_GitHub_updates($data) {
	// Theme information
	$theme   = get_stylesheet(); // Folder name of the current theme
	$current = wp_get_theme()->get('Version'); // Get the version of the current theme
	// GitHub information
	$user = 'oelna'; // The GitHub username hosting the repository
	$repo = 'dws-2025'; // Repository name as it appears in the URL
	// Get the latest release tag from the repository. The User-Agent header must be sent, as per
	// GitHub's API documentation: https://developer.github.com/v3/#user-agent-required
	$file = @json_decode(@file_get_contents('https://api.github.com/repos/'.$user.'/'.$repo.'/releases/latest', false,
			stream_context_create(['http' => ['header' => "User-Agent: ".$user."\r\n"]])
	));
	if($file) {
		// $update = filter_var($file->tag_name, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
		$update = preg_replace('/^(v|ver|version)?\s*/', '', $file->tag_name);
		// Only return a response if the new version number is higher than the current version
		// if($update > $current) {
		if(version_compare($update, $current, '>')) {
			$data->response[$theme] = array(
				'theme'       => $theme,
				// Strip the version number of any non-alpha characters (excluding the period)
				// This way you can still use tags like v1.1 or ver1.1 if desired
				'new_version' => $update,
				'url'         => 'https://github.com/'.$user.'/'.$repo,
				'package'     => $file->assets[0]->browser_download_url,
			);
			// echo('a new version is available!');
		} else {
			// echo('no new version.');
		}
	}
	return $data;
}

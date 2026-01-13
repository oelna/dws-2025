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

function dws_log($log) {
	if (true === WP_DEBUG) {
		if (is_array($log) || is_object($log)) {
			error_log(print_r($log, true));
		} else {
			error_log($log);
		}
	}
}

// Automatic theme updates from the GitHub repository: https://gist.github.com/slfrsn/a75b2b9ef7074e22ce3b
add_filter('pre_set_site_transient_update_themes', 'automatic_GitHub_updates', 100, 1);
function automatic_GitHub_updates($data) {
	// Theme information
	$theme   = get_stylesheet(); // dir name of the current theme
	$current = wp_get_theme()->get('Version'); // version of the current theme
	
	// GitHub info
	$user = 'oelna'; // GitHub username
	$repo = 'dws-2025'; // Repository name

	$file = @json_decode(@file_get_contents('https://api.github.com/repos/'.$user.'/'.$repo.'/releases/latest', false,
			stream_context_create(['http' => ['header' => "User-Agent: ".$user."\r\n"]])
	));

	if($file) {
		// $update = filter_var($file->tag_name, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
		$update = preg_replace('/^(v|ver|version)?\s*/', '', $file->tag_name);

		if(version_compare($update, $current, '>')) {
			$response = array(
				'theme'       => $theme,
				'new_version' => $update,
				'url'         => 'https://github.com/'.$user.'/'.$repo,
				'package'     => $file->assets[0]->browser_download_url,
			);
			// dws_log([$current, $response]);
			$data->response[$theme] = $response;
		}
	}
	// dws_log($data);
	return $data;
}

// SQLite backups via WP-Cron: keep 1 backup for latest day/week/month/year
define( 'DWS_SQLITE_BACKUP_CRON_HOOK', 'dws_sqlite_backup_cron' );

add_action( 'after_switch_theme', 'dws_sqlite_backup_schedule' );
add_action( 'switch_theme', 'dws_sqlite_backup_unschedule' );
add_action( 'init', 'dws_sqlite_backup_schedule' );

add_action( DWS_SQLITE_BACKUP_CRON_HOOK, 'dws_sqlite_backup_run' );

function dws_sqlite_backup_schedule() {
	if ( wp_next_scheduled( DWS_SQLITE_BACKUP_CRON_HOOK ) ) {
		// dws_log('backup is already scheduled.');
		return;
	}

	if ( wp_doing_cron() ) {
		// dws_log('backup is happening right now!');
		return;
	}

	// Hourly is a good compromise; the function only writes new files when day/week/month/year changes.
	wp_schedule_event( time() + 120, 'hourly', DWS_SQLITE_BACKUP_CRON_HOOK );
	dws_log('backup has been scheduled.');
}

function dws_sqlite_backup_unschedule() {
	$ts = wp_next_scheduled( DWS_SQLITE_BACKUP_CRON_HOOK );
	if ( $ts ) {
		wp_unschedule_event( $ts, DWS_SQLITE_BACKUP_CRON_HOOK );
	}
}

function dws_sqlite_backup_run() {
	if ( ! function_exists( 'wp_date' ) ) {
		return; // very old WP; ignore
	}

	$db_path = dws_sqlite_get_db_path();

	if ( ! $db_path || ! file_exists( $db_path ) || ! is_readable( $db_path ) ) {
		return;
	}

	$backup_dir = dws_sqlite_backup_dir();
	if ( ! $backup_dir ) {
		return;
	}

	// Simple lock to avoid overlapping runs (or a long backup overlapping a new cron tick).
	$lock_path = trailingslashit( $backup_dir ) . '.dws-sqlite-backup.lock';
	$lock_fp = @fopen( $lock_path, 'c' );
	if ( ! $lock_fp ) {
		return;
	}
	if ( ! @flock( $lock_fp, LOCK_EX | LOCK_NB ) ) {
		@fclose( $lock_fp );
		return;
	}

	$now = time();
	dws_log(['Backup triggered', $now]);

	// Use site timezone.
	$daily_key   = wp_date( 'Y-m-d', $now );
	$weekly_key  = wp_date( 'o-\\WW', $now ); // ISO week key, e.g. 2026-W02
	$monthly_key = wp_date( 'Y-m', $now );
	$yearly_key  = wp_date( 'Y', $now );

	// Prefix with ".ht" to reduce accidental web exposure (common pattern for SQLite-in-WP setups).
	$daily_dest   = trailingslashit( $backup_dir ) . ".ht.sqlite.daily.{$daily_key}";
	$weekly_dest  = trailingslashit( $backup_dir ) . ".ht.sqlite.weekly.{$weekly_key}";
	$monthly_dest = trailingslashit( $backup_dir ) . ".ht.sqlite.monthly.{$monthly_key}";
	$yearly_dest  = trailingslashit( $backup_dir ) . ".ht.sqlite.yearly.{$yearly_key}";

	dws_sqlite_snapshot_if_missing( $db_path, $daily_dest );
	dws_sqlite_snapshot_if_missing( $db_path, $weekly_dest );
	dws_sqlite_snapshot_if_missing( $db_path, $monthly_dest );
	dws_sqlite_snapshot_if_missing( $db_path, $yearly_dest );

	// Keep only the most recent file for each bucket.
	dws_sqlite_prune_bucket( $backup_dir, '.ht.sqlite.daily.' );
	dws_sqlite_prune_bucket( $backup_dir, '.ht.sqlite.weekly.' );
	dws_sqlite_prune_bucket( $backup_dir, '.ht.sqlite.monthly.' );

	// keep yearly forever for now
	// dws_sqlite_prune_bucket( $backup_dir, '.ht.sqlite.yearly.' );

	@flock( $lock_fp, LOCK_UN );
	@fclose( $lock_fp );
	@unlink( $lock_path );
}

function dws_sqlite_get_db_path() {
	// Preferred: SQLite integration commonly uses these constants (dir + filename).
	if ( defined( 'FQDBDIR' ) && defined( 'FQDB' ) ) {
		// $dir = trailingslashit( FQDBDIR );
		return FQDB;
	}

	// Common default used by WordPress Studio and many SQLite-in-WP setups.
	$candidate = WP_CONTENT_DIR . '/database/.ht.sqlite';
	if ( file_exists( $candidate ) ) {
		return $candidate;
	}

	// Fallback: try any *.sqlite in wp-content/database/
	$glob = glob( WP_CONTENT_DIR . '/database/*.sqlite' );
	if ( is_array( $glob ) && ! empty( $glob ) ) {
		return $glob[0];
	}

	return '';
}

function dws_sqlite_backup_dir() {
	// Prefer a non-public location if you have one. This uses wp-content/database/backups by default.
	$dir = WP_CONTENT_DIR . '/database/backups';

	if ( ! wp_mkdir_p( $dir ) ) {
		return '';
	}

	// Basic hardening for Apache setups; harmless elsewhere.
	$htaccess = trailingslashit( $dir ) . '.htaccess';
	if ( ! file_exists( $htaccess ) ) {
		@file_put_contents( $htaccess, "Deny from all\n" );
	}
	$index = trailingslashit( $dir ) . 'index.html';
	if ( ! file_exists( $index ) ) {
		@file_put_contents( $index, '' );
	}

	return $dir;
}

function dws_sqlite_snapshot_if_missing( $source_db_path, $dest_path ) {
	if ( file_exists( $dest_path ) ) {
		return;
	}

	$tmp = $dest_path . '.tmp';

	// Best-effort consistent snapshot using SQLite API if available.
	if ( class_exists( 'SQLite3' ) && method_exists( 'SQLite3', 'backup' ) ) {
		try {
			$src = new SQLite3( $source_db_path, SQLITE3_OPEN_READONLY );
			$dst = new SQLite3( $tmp );
			$src->backup( $dst );
			$dst->close();
			$src->close();

			@rename( $tmp, $dest_path );
			return;
		} catch ( Exception $e ) {
			// Fall through to file-copy fallback.
		}
	}

	// Fallback: copy main DB file (+ WAL/SHM if present).
	@copy( $source_db_path, $tmp );

	$wal = $source_db_path . '-wal';
	$shm = $source_db_path . '-shm';
	if ( file_exists( $wal ) ) {
		@copy( $wal, $tmp . '-wal' );
	}
	if ( file_exists( $shm ) ) {
		@copy( $shm, $tmp . '-shm' );
	}

	@rename( $tmp, $dest_path );
}

function dws_sqlite_prune_bucket( $dir, $prefix ) {
	$pattern = trailingslashit( $dir ) . $prefix . '*';
	$files = glob( $pattern );
	if ( ! is_array( $files ) || count( $files ) <= 1 ) {
		return;
	}

	// Sort lexicographically; with the chosen date keys, this puts newest at the end.
	sort( $files, SORT_STRING );
	$keep = array_pop( $files );

	foreach ( $files as $f ) {
		if ( $f === $keep ) {
			continue;
		}
		@unlink( $f );
		@unlink( $f . '-wal' );
		@unlink( $f . '-shm' );
	}
}

// 1) Add a Tools menu page with a "Run backup now" button.
add_action( 'admin_menu', 'dws_sqlite_backup_admin_menu' );

function dws_sqlite_backup_admin_menu() {
	add_management_page(
		'SQLite Backup',
		'SQLite Backup',
		'manage_options',
		'dws-sqlite-backup',
		'dws_sqlite_backup_admin_page'
	);
}

function dws_sqlite_backup_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Insufficient permissions.' );
	}

	$ran = false;
	$error = '';

	if ( isset( $_POST['dws_run_sqlite_backup'] ) ) {
		dws_sqlite_backup_run();
		$ran = true;
	}

	echo '<div class="wrap">';
	echo '<h1>Diesterwegschule SQLite Backup</h1>';

	echo '<h2>Run backup manually</h2>';
	if ( $ran ) {
		echo '<div class="notice notice-success"><p>Backup ran.</p></div>';
	} elseif ( $error !== '' ) {
		echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
	}

	echo '<form method="post">';
	echo '<p><button type="submit" class="button button-primary" name="dws_run_sqlite_backup" value="1">Backup now</button></p>';
	echo '</form>';

	$backup_dir = dws_sqlite_backup_dir();
	if ( $backup_dir ) {
		echo '<h2>Existing backup files</h2>';
		echo '<ul style="max-width: 80ch;">';

		$files = scandir($backup_dir);
		$files = array_diff(scandir($backup_dir), array('.', '..', '.htaccess', 'index.html'));
		foreach ($files as $f) {
			echo '<li>'.$f.'</li>';
		}

		echo '</ul>';
	}

	echo '</div>';
}

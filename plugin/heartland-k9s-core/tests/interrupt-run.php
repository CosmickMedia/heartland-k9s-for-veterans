<?php
/**
 * Local-dev harness: starts an import and ticks ONE item at a time with a short
 * pause so `timeout -s KILL` can interrupt it mid-step (simulates a fatal /
 * killed process). Never loaded by the plugin.
 *
 * docker exec -u www-data hk9-wordpress-1 timeout -s KILL 3 php \
 *   /var/www/html/wp-content/plugins/heartland-k9s-core/tests/interrupt-run.php <payload-dir>
 */

declare(strict_types=1);

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}
$_SERVER['HTTP_HOST'] = 'localhost:8093';
require dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! defined( 'HK9_LOCAL_DEV' ) || ! HK9_LOCAL_DEV ) {
	fwrite( STDERR, "HK9_LOCAL_DEV only.\n" );
	exit( 1 );
}
wp_set_current_user( 1 );

$dir = $argv[1] ?? '';
if ( '' === $dir || ! is_file( $dir . '/manifest.json' ) ) {
	fwrite( STDERR, "Usage: interrupt-run.php <payload-dir>\n" );
	exit( 1 );
}

$state = \HK9\Core\Import\Runner::start( $dir, [ 'batch' => 1, 'budget' => 60 ], 1, 'path' );
if ( is_wp_error( $state ) ) {
	fwrite( STDERR, $state->get_error_message() . "\n" );
	exit( 1 );
}
echo "run {$state['run_id']}\n";
while ( 'running' === $state['status'] ) {
	$state = \HK9\Core\Import\Runner::step( 60, 1 );
	if ( is_wp_error( $state ) ) {
		fwrite( STDERR, $state->get_error_message() . "\n" );
		exit( 1 );
	}
	echo "  {$state['step']} {$state['cursor']}/{$state['step_total']}\n";
	usleep( 400000 );
}
echo "finished: {$state['status']}\n";

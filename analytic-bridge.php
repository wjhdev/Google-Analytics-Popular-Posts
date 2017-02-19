<?php
/*
Plugin Name: Google Analytics Popular Posts
Description: Pull analytic data into your wordpress install.
Author: inn_nerds
Author URI: https://nerds.inn.org/
Version: 0.1.2
License: Copyright © 2015-2017 INN
*/

// Prevent direct file access
if ( ! defined ( 'ABSPATH' ) ) {
	exit;
}

/** Table defines */
global $wpdb;
define('METRICS_TABLE', $wpdb->prefix . "analyticbridge_metrics");
define('PAGES_TABLE', $wpdb->prefix . "analyticbridge_pages");

/** Include Google PHP client library. */
require_once( plugin_dir_path( __FILE__ ) . 'api/src/Google/Client.php');
require_once( plugin_dir_path( __FILE__ ) . 'api/src/Google/Service/Analytics.php');
require_once( plugin_dir_path( __FILE__ ) . 'AnalyticBridgeGoogleClient.php');
require_once( plugin_dir_path( __FILE__ ) . 'Analytic_Bridge_Service.php');

/**
 * Registers admin option page and populates with
 * plugin settings.
 */
require_once( plugin_dir_path( __FILE__ ) . 'inc/analytic-bridge-network-options.php');
require_once( plugin_dir_path( __FILE__ ) . 'inc/analytic-bridge-blog-options.php');

include_once(plugin_dir_path( __FILE__ ) .'classes/AnalyticsDashWidget.php');
include_once(plugin_dir_path( __FILE__ ) .'classes/AnalyticsPopularWidget.php');
include_once(plugin_dir_path( __FILE__ ) .'classes/AnalyticBridgeGoogleAnalytics.php');

/**
 * Functions for activating/deactivating the plugin.
 */
include_once(plugin_dir_path( __FILE__ ) .'inc/analytic-bridge-installing.php');

/**
 * Add new intervals for cron jobs.
 *
 * @since 0.1
 */
function new_interval($interval) {

	$interval['10m'] = array('interval' => 10*60, 'display' => 'Once every 10 minutes');
	$interval['15m'] = array('interval' => 15*60, 'display' => 'Once every 15 minutes');
	$interval['20m'] = array('interval' => 20*60, 'display' => 'Once every 20 minutes');
	$interval['30m'] = array('interval' => 30*60, 'display' => 'Once every 30 minutes');
	$interval['45m'] = array('interval' => 45*60, 'display' => 'Once every 45 minutes');

	return $interval;

}
add_filter('cron_schedules', 'new_interval');

function _ak_verbose_echo($verbose,$log) {
	if($verbose) {
		echo $log;
	}
}

/**
 * A cron job that loads data from google analytics into out analytic tables.
 *
 * @since v0.2
 * @param boolean $verbose set to true to print
 */
function largo_anaylticbridge_cron($verbose = false) {

	global $wpdb;

	$rustart = getrusage(); // track usage.

	_ak_verbose_echo($verbose,"\nBeginning analyticbridge_cron...\n\n");

	$client = ak_google_client( true, $e );

	if( $client == false ) {
		_ak_verbose_echo($verbose,"[Error] creating api client, message: " . $e["message"] . "\n\n");
		_ak_verbose_echo($verbose,"End analyticbridge_cron\n");
		return;
	}

	$queries = array( 
		array( 
			'startdate' => 'today',
			'metrics' => 'ga:pageviews,ga:avgTimeOnPage',
			'count' => 75,
		), 
		array( 
			'startdate' => 'yesterday',
			'metrics' => 'ga:pageviews,ga:avgTimeOnPage',
			'count' => 150,
		), 
	);
	$queries = apply_filters( "analytickit_queries", $queries );

	ak_clear_metric_cache();

	$analytics = new Analytic_Bridge_Service($client);
	foreach ($queries as $query) {
		query_and_save_analytics( $analytics, $query, $verbose );
	}

	_ak_verbose_echo($verbose,"Google Analytics Popular Posts cron executed successfully\n\n");
	_ak_verbose_echo($verbose,"End analyticbridge_cron\n");

	// Script end
	function rutime($ru, $rus, $index) {
		return ($ru["ru_$index.tv_sec"]*1000 + intval($ru["ru_$index.tv_usec"]/1000)) - ($rus["ru_$index.tv_sec"]*1000 + intval($rus["ru_$index.tv_usec"]/1000));
	}

	$ru = getrusage();
	_ak_verbose_echo($verbose,"\nThis process used " . rutime($ru, $rustart, "utime") . " ms for its computations\n");
	_ak_verbose_echo($verbose,"It spent " . rutime($ru, $rustart, "stime") . " ms in system calls\n");

	return;
}
add_action( 'analyticbridge_hourly_cron', 'largo_anaylticbridge_cron' );

/**
 * Queries analytics and saves them to the table for the given start date.
 *
 * If the start and end dates already exist in the table, it first clears
 * them out and refreshes the values.
 *
 * @param boolean $verbose whether we should print while we do it.
 */
function query_and_save_analytics( $analytics, $args, $verbose = false ) {
	global $wpdb;

	if ( !$args ) {
		_ak_verbose_echo( $verbose, "Error: No args specified. \n\n" ); 
		return false;
	} else if ( !array_key_exists( 'startdate', $args ) ) {
		_ak_verbose_echo( $verbose, "Error: No startdate specified. \n\n" ); 
		return false;
	} else if ( !array_key_exists( 'metrics', $args ) ) {
		_ak_verbose_echo( $verbose, "Error: No metrics specified. \n\n" ); 
		return;
	}

	$start = $args["startdate"];
	$end = array_key_exists('enddate',$args) ? $args["enddate"] : $start;
	$metrics = $args["metrics"];
	$metricsArray = explode(",", $metrics);
	$sort = array_key_exists('sort',$args) ? $args["sort"] : "-" . $metricsArray[0];
	$count = array_key_exists('count',$args) ? $args["count"] : 250;

	if($verbose) echo "Making a call to ga:get...\n";

	$report = $analytics->data_ga->get(
					get_option('analyticbridge_setting_account_profile_id'),
					$start,
					$end,
					$metrics,
					array(
					  'dimensions' => 'ga:pagePath',
					  'max-results' => $count,
					  'sort' => $sort
					)
	);

	if($verbose) {
		echo "returned report:\n\n";
		echo " - itemsPerPage: " . $report->itemsPerPage . "\n";
		echo " - row count: " . sizeof($report->rows) . "\n\n";
	}

	$gaTimezone = $analytics->timezone($report);
	update_option('analyticbridge_analytics_timezone',$gaTimezone);

	// TODO: break here if API errors.
	// TODO: paginate.

	// Start a mysql transaction, in a try catch.
	try {

		$wpdb->query('START TRANSACTION');
		
		$pagesql = "";
		$metricsql = "";

		$pagesqlInit = "INSERT INTO `" . PAGES_TABLE . "` (pagepath, post_id) VALUES \n";
		$metricsqlInit = "INSERT INTO `" . METRICS_TABLE .  "` (page_id,startdate,enddate,querytime,metric,value) VALUES \n";

		foreach ($report->rows as $k => $r) {
			
			$GAPagePath = $r[0]; // $r[0] - pagePath
			$wpurl = get_home_url() . preg_replace( '/index.php$/', '', $GAPagePath );
			$postid = url_to_postid( $wpurl );

			if ( $postid == 0 ) {
				continue;
			}

			if ( $postid && ( get_permalink( $postid ) != $wpurl ) ) {
				continue; 
			} 

			$pagesql .= $pagesql == "" ? $pagesqlInit : ", ";
			$metricsql .= $metricsql == "" ? $metricsqlInit : ", ";

			$pagesql .= $wpdb->prepare( "\t(%s, %s) ", $GAPagePath, $postid );

			// Adjust things to save based on the google analytics timezone.
			$tstart = new DateTime($start,new DateTimeZone($gaTimezone));
			$tend = new DateTime($end,new DateTimeZone($gaTimezone));

			// The time that the query happened (± a few seconds).
			$qTime = new DateTime('now',new DateTimeZone($gaTimezone));

			// $r[1] - ga:pageviews
			// $r[2] - ga:avgTimeOnPage

			$first = true;
			foreach($metricsArray as $index => $metric) {
				// Insert ga:pageviews
				$metricsql .= $wpdb->prepare(
						( $first ? "" : ",") .
						"(	(SELECT `id` from " . PAGES_TABLE . " WHERE `pagepath`=%s),
							%s,
							%s,
							%s,
							%s,
							%s) 
						", 	$r[0], 
					   		date_format($tstart, 'Y-m-d'), 
					   		date_format($tend, 'Y-m-d'), 
					   		date_format($qTime, 'Y-m-d H:i:s'), 
					   		$metric, 
					   		$r[$index+1]
					);
				$first = false;

				//$metricsql .= ", \n";
			}

		}

		// on duplicate key, don't do much.
		$pagesql .= " ON DUPLICATE KEY UPDATE `id`=LAST_INSERT_ID(id)";

		// on duplicate key update the value and querytime.
		$metricsql .= " ON DUPLICATE KEY UPDATE `id`=LAST_INSERT_ID(id),`querytime`=values(querytime),`value`=values(value)";

		$wpdb->query( $pagesql );
		$wpdb->query( $metricsql );

	// Catch mysql exception. TODO: catch only mysql exceptions.
	} catch(Exception $e) {

		$wpdb->query('ROLLBACK');
		if($verbose) echo("[Error] commiting sql to database.\n");
		if($verbose) echo("\nEnd analyticbridge_cron\n");
		return;

	}

}

/**
 * Get rid of any records in the metrics table having a startdate older than 2 days ago
 */
function ak_clear_metric_cache() {
	global $wpdb;
	$SQL = "TRUNCATE TABLE " . METRICS_TABLE . ";";
	$wpdb->query($SQL);
}

/**
 * Static logging class for cron jobs.
 */
Class AnalyticBridgeLog {

	static $date = null;
	static $errorLog = false;
	static $printLog = false;

	static function log($log) {

		if( !WP_DEBUG )
			return;

		if( $date === null ) {
			$date = new DateTime('now', new DateTimeZone('America/Chicago'));
		}

		$time = $date->format('D M d h:i:s');
		$log = "[$time] $log\n";

	}

}

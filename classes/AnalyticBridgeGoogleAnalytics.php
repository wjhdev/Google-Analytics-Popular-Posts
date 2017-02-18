<?php

/**
 * Get a metric for a post.
 * 
 * Queries the analytic bridge database to find metrics for a post.
 * 
 * @param Mixed $post (optional) the post to query for. Defaults to global. 
 * @param String $metric (optional) a metric to query for. Default is 'ga:session'
 * @param Mixed $dateString (optional) the date to query for. Default is today. Value is passed into a new DateTime() object.
 * 
 * @return mixed. Returns 'false' if data not found. Otherwise, returns an integer.
 */
function ak_metric($post = null,$metric = null,$dateString = null) {

	global $wpdb;
	
	$post = get_post( $post );
	$metric = $metric ?: 'ga:sessions';
	$dateString = $dateString ?: 'now';

	$gaTimezone = get_option( 'analyticbridge_analytics_timezone', '' );
	$date = new DateTime( $dateString, timezone_open( $gaTimezone ) );

	$result = $wpdb->get_results(" 

		SELECT
			* 
		FROM " .
			PAGES_TABLE . " as `p` 
		JOIN " .
			METRICS_TABLE . " as `m` 
			ON m.metric = '$metric' AND p.id = m.page_id
		WHERE 
			m.startdate = '" . date_format($date,'Y-m-d') . "'
			AND
			m.enddate = '" . date_format($date,'Y-m-d') . "'
			AND 
			p.post_id = {$post->ID}

		");

	return count($result) > 0 ? $result[0]->value : null;

}
<?php

/**
 * Initialize the database tables
 */
function bt_analyticsbridge_plugin_init() {
  /* do not generate any output here. */

  global $wpdb;

  /* our globals aren't going to work because we switched blogs */
  $metrics_table = $wpdb->prefix . 'bt_analyticsbridge_metrics';
  $pages_table = $wpdb->prefix . 'bt_analyticsbridge_pages';

  /* Run sql to create the proper tables we need. */
  $result = $wpdb->query(
    "

		--							---
		--  Create metrics table 	---
		--							---

		CREATE TABLE IF NOT EXISTS `" .
      $metrics_table .
      "` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`page_id` int(11) NOT NULL,
			`startdate` datetime NOT NULL,
			`enddate` datetime NOT NULL,
			`querytime` datetime NOT NULL,
			`metric` varchar(64) NOT NULL,
			`value` varchar(64) NOT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `page_id` (`page_id`,`startdate`,`enddate`,`metric`)
		) ENGINE=InnoDB  DEFAULT CHARSET=latin1

	"
  );

  /* Run sql to create the proper tables we need. */

  $result = $wpdb->query(
    "

		--							---
		--  Create metrics table 	---
		--							---

		CREATE TABLE IF NOT EXISTS `" .
      $pages_table .
      "` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`pagepath` varchar(450) NOT NULL,
			`post_id` int(11) NOT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `pagepath` (`pagepath`)
		) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

	"
  );

  // 2: Register a cron job.
  wp_schedule_event(time(), '30m', 'bt_analyticsbridge_hourly_cron');

  update_option('bt_analyticsbridge_setting_popular_posts_halflife', 14);
}
add_action('init', 'bt_analyticsbridge_plugin_init');

/**
 * Add new intervals for cron jobs.
 *
 * @since 0.1
 */
function bt_analytics_bridge_register_cron_intervals($interval) {
  $interval['10m'] = ['interval' => 10 * 60, 'display' => 'Once every 10 minutes'];
  $interval['15m'] = ['interval' => 15 * 60, 'display' => 'Once every 15 minutes'];
  $interval['20m'] = ['interval' => 20 * 60, 'display' => 'Once every 20 minutes'];
  $interval['30m'] = ['interval' => 30 * 60, 'display' => 'Once every 30 minutes'];
  $interval['45m'] = ['interval' => 45 * 60, 'display' => 'Once every 45 minutes'];

  return $interval;
}
add_filter('cron_schedules', 'bt_analytics_bridge_register_cron_intervals');
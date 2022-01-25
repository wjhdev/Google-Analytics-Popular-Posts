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

<?php


Class AnayticBridgePopularPosts implements Iterator {

	// results.
	private $result;

	// used by iterator.
	private $position = 0;

	// returns an array of post ids that may be passed to a WP_Query object.
	public $ids;

	// interval to query over.
	private $interval;

	/* -------- Constructing ----------- */

	public function __construct($size) {
		$this->result = get_option("ak_popular_posts",array());
		$this->setIds($size);
	}

	private function setIds($size) {
		$ids = array();
		foreach( $this as $popPost ) {
			$ids[] = $popPost->post_id;
		}
		$this->ids = array_slice($ids,0,$size);
	}

	/* -------- Properties ----------- */

	/**
	 * Returns a score specified by the given $pid.
	 *
	 * If the $pid is not in this list, returns false.
	 *
	 * @since v0.1
	 */
	public function score( $pid ) {
		foreach( $this as $popularPost )
			if ( $popularPost->post_id == $pid )
				return $popularPost->weighted_pageviews;

		return false;
	}

	/* -------- Saving (static) ----------- */

	public static function save() {
		$result = self::query(20);
		$result = self::sort($result);
		self::store($result);
	}
	
	private static function query($size) {
		global $wpdb;

		$size = 20;
		$halflife = get_option('analyticbridge_setting_popular_posts_halflife');
		$todaysWeight = self::percentOfDayOver();

		/* sql statement that pulls today's sessions, yesterday's
		 * sessions and a weighted average of them from the database.
		 *
		 * A note on the calculation of weighted pageviews, using a simplified equation:
		 *
		 * ( ( today's sessions * $todaysWeight ) + ( yesterday's sessions * ( 1 - $todaysWeight ) ) returns the post's sessions count, averaged between the last 24 hours.
		 * ( sessions count ) * 1/2 ^ ( ( post date - now ) / ( $halflife * 24 ) ) multiplies this post's sessions count by the half-life equation.
		 * The half-life equation raises 1/2 to the power n, where n is the number of half-lifes elapsed.
		 * $half-life is set in the plugin options in Settings > Analytic Bridge > Post halflife. It is the half-life of post popularity, in days.
		 * A post will count half as much every $halflife days.
		 * ( post date - now ) returns hours.
		 * ( $halflife * 24 ) returns hours.
		 * Dividing the post's age by the halflife-hours gives the number of half-lives that have elapsed, and thus the power that 1/2 should be raised to.
		 */
		$SQL = "
			--							---
			--  SELECT POPULAR POSTS 	---
			--							---
			SELECT
				pg.pagepath AS pagepath,
				pg.id AS page_id,
				pst.id AS post_id,
				-- coalesce returns the first result that is not NULL:
				-- either the sessions count or zero
				coalesce(t.sessions, 0) AS today_pageviews,
				coalesce(y.sessions, 0) AS yesterday_pageviews,

				-- calculate the weighted session averages.

				( -- Calculate avg_pageviews in the last 24 hours
					(coalesce(t.sessions, 0) * $todaysWeight) +
					(coalesce(y.sessions, 0) * (1 - $todaysWeight))
				) AS `avg_pageviews`,

				( -- Calulate how many days_old the post is
					TIMESTAMPDIFF( hour, pst.post_date, NOW() ) - 1
				) AS `days_old`,

				( -- Calculate weighted_pageviews, using halflife to put less emphasis
				  -- on older posts
					(
						(coalesce(t.sessions, 0) * $todaysWeight) +
						(coalesce(y.sessions, 0) * (1 - $todaysWeight))
					) * POWER(
						1/2,
						( TIMESTAMPDIFF( hour, pst.post_date, NOW() ) - 1 ) / ($halflife * 24)
					)
				) AS `weighted_pageviews`
			FROM
				`" . PAGES_TABLE . "` as `pg`
			LEFT JOIN (
				--
				-- Nested select returns today's sessions.
				--
				SELECT
					CAST(value as unsigned) as `sessions`,
					page_id
				FROM
					`" . METRICS_TABLE . "` as m
				WHERE
					m.metric = 'ga:pageviews'
				AND
					m.startdate >= CURDATE()
			) as `t` ON pg.id = t.page_id

			LEFT JOIN (
				--
				-- Nested select returns yesterday's sessions.
				--
				SELECT
					CAST(value as unsigned) as `sessions`,
					`page_id`
				FROM
					`" . METRICS_TABLE . "` as m
				WHERE
					m.metric = 'ga:pageviews'
				AND
					m.startdate >= CURDATE() - 1
				AND
					m.enddate < CURDATE()
			) as `y` ON `pg`.`id` = `y`.`page_id`

			LEFT JOIN `" . $wpdb->prefix . "posts` as `pst`
				ON `pst`.`id` = `pg`.`post_id`

			-- For now, they must be posts.
			WHERE `pst`.`post_type` = 'post'
				ORDER BY `weighted_pageviews` DESC
				LIMIT " . $size . ";";

		return $wpdb->get_results( $SQL );
		
	}

	private static function percentOfDayOver() {
		
		// 1: Calculate a ratio coeffient
		$tday = new DateTime('today',new DateTimeZone('America/Chicago'));
		$now = new DateTime('',new DateTimeZone('America/Chicago'));
		$interval = $tday->diff($now);

		// $minutes is hours*60 + $interval->i minutes
		$minutes = $interval->h * 60 + $interval->i;

		// $ratio is minutes passed today : minutes in today,
		// A measure of how long today has been
		$ratio = $minutes / (24*60);

		return $ratio;
	}

	private static function sort($results) {
		usort( $results, array( 'self', 'compare_popular_posts' ) );
		return $results;
	}

	private static function compare_popular_posts( $a, $b ) {
		$ascore = $a->weighted_pageviews;
		$bscore = $b->weighted_pageviews;
		return ( $ascore > $bscore ) ? -1 : 1;
	}

	private static function store($result) {
		update_option("ak_popular_posts",$result);
	}


	/* -------- Iterating ----------- */

	public function rewind() {
		$this->position = 0;
	}

	public function current() {
		return $this->result[$this->position];
	}

	public function key() {
		return $this->position;
	}

	public function next() {
		$this->position += 1;
		return $this->position;
	}

	public function valid() {
		return isset( $this->result[$this->position] );
	}

}


/**
 * After analytic cron, save off results
 */
function ak_save_pop_posts() {
	AnayticBridgePopularPosts::save();
}
add_action( "analytickit_cron_finished", "ak_save_pop_posts" );


<?php

/**
 * Class to fake Google Analytics requests.
 *
 * This class extends Google_Service_Analytics and fakes requests to the
 * Google API for debugging by generating data based on the global Wordpress
 * object.
 *
 * Needs work.
 *
 * @since v0.1
 */
class Google_Service_Analytics_Generator extends Google_Service_Analytics {
  public $data_ga;

  /**
   * Construct a new Analytics Generator.
   *
   * data_ga is set to ourselves. We handle the get function
   * internally.
   *
   * @since v0.1
   */
  public function __construct(Google_Client $client) {
    $this->data_ga = $this;
  }

  /**
   * Overrided Google_Service_Analytics_DataGa_Resource get function.
   *
   * @return Google_Service_Analytics_GaData Object with proper row values.
   */
  public function get($ids, $startDate, $endDate, $metrics, $optParams = []) {
    $rows = [];
    $metrics = explode(',', $metrics);

    $the_query = new WP_Query(['post_type' => 'post']);

    if ($the_query->have_posts()):
      while ($the_query->have_posts()):
        $the_query->the_post();

        $r = [];
        $url = parse_url(get_permalink());
        $r[0] = $url['path'] . $url['query'];

        foreach ($metrics as $m) {
          if ($m == 'ga:sessions') {
            $r[] = 100 + rand(-25, 25);
          } elseif ($m == 'ga:pageviews') {
            $r[] = 130 + rand(-25, 25);
          } elseif ($m == 'ga:exits') {
            $r[] = 30 + rand(-10, 10);
          } elseif ($m == 'ga:bounceRate') {
            $r[] = 60 + rand(-30, 40);
          } elseif ($m == 'ga:avgSessionDuration') {
            $r[] = 231 + rand(-100, 100);
          } elseif ($m = 'ga:avgTimeOnPage') {
            $r[] = 140 + rand(-40, 130);
          } else {
            $r[] = 0;
          }
        }

        $rows[] = $r;
      endwhile;

      wp_reset_postdata();
    endif;

    $toRet = new Google_Service_Analytics_GaData();
    $toRet->rows = $rows;

    return $toRet;
  }
}

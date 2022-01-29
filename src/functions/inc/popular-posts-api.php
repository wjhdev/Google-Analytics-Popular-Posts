<?php

/**
 * Sets a JS variable in wp_head for the popular posts widget to
 * use.
 *
 * Eventually this should turn into a REST route to allow refresh
 * without refreshing index.php
 */
function bt_analyticsbridge_print_popular_posts_to_wp_head() {
  $halflife = bt_analyticsbridge_option_popular_posts_halflife();
  $popPosts = new AnalyticsBridgePopularPosts(20, $halflife);
  $postJson = [];

  foreach ($popPosts as $i => $post) {
    $postJson[$i] = ['id' => (int) $post->post_id, 'weight' => (float) $post->weighted_pageviews];
  }

  $json = [
    'posts' => $postJson,
  ];
  echo "<script type='text/javascript'>var BT_AB_PP =" . json_encode($json) . "</script>\n";
}
add_action('wp_head', 'bt_analyticsbridge_print_popular_posts_to_wp_head');
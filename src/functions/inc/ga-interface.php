<?php

/**
 * A cron job that loads data from google analytics into out analytic tables.
 *
 * @param boolean $verbose set to true to print verbose output
 */
function _bt_anaylticsbridge_cron($verbose = false) {
  global $wpdb;

  $rustart = getrusage(); // track usage.

  if ($verbose) {
    echo "\nBeginning analyticbridge_cron...\n\n";
  }

  if (
    !(
      analyticbridge_client_id() &&
      analyticbridge_client_secret() &&
      get_option('analyticbridge_setting_account_profile_id')
    )
  ) {
    exit();
  }

  // 1: Create an API client.

  $client = analytic_bridge_google_client(true, $e);

  if ($client == false) {
    if ($verbose) {
      echo '[Error] creating api client, message: ' . $e['message'] . "\n";
    }
    if ($verbose) {
      echo "\nEnd analyticbridge_cron\n";
    }
    return;
  }

  $analytics = new Analytic_Bridge_Service($client);

  bt_analyticsbridge_query_analytics($analytics, 'today', $verbose);
  bt_analyticsbridge_query_analytics($analytics, 'yesterday', $verbose);
  _bt_analyticsbridge_purge_old_analytics();

  if ($verbose) {
    echo "Google Analytics Popular Posts cron executed successfully\n";
  }
  if ($verbose) {
    echo "\nEnd analyticbridge_cron\n";
  }

  $ru = getrusage();
  if ($verbose) {
    echo "\nThis process used " . rutime($ru, $rustart, 'utime') . " ms for its computations\n";
  }
  if ($verbose) {
    echo 'It spent ' . rutime($ru, $rustart, 'stime') . " ms in system calls\n";
  }

  return;
}
add_action('bt_analyticsbridge_hourly_cron', '_bt_anaylticsbridge_cron');

// Script end
function rutime($ru, $rus, $index) {
  return $ru["ru_$index.tv_sec"] * 1000 +
    intval($ru["ru_$index.tv_usec"] / 1000) -
    ($rus["ru_$index.tv_sec"] * 1000 + intval($rus["ru_$index.tv_usec"] / 1000));
}

/**
 * Queries analytics and saves them to the table for the given start date.
 *
 * If the start and end dates already exist in the table, it first clears
 * them out and refreshes the values.
 *
 * @param boolean $verbose whether we should print while we do it.
 */
function bt_analyticsbridge_query_analytics($analytics, $startdate, $verbose = false) {
  global $wpdb;

  $start = $startdate;

  if ($verbose) {
    echo "Making a call to ga:get...\n";
  }

  // Make API call.
  // We use $start-$start because we're interested in one day.
  $report = $analytics->data_ga->get(
    get_option('analyticbridge_setting_account_profile_id'),
    $start,
    $start,
    'ga:pageviews,ga:avgTimeOnPage',
    [
      'dimensions' => 'ga:pagePath',
      'max-results' => '1000',
      'sort' => '-ga:pageviews',
    ]
  );
  if ($verbose) {
    echo "returned report:\n\n";
    echo ' - itemsPerPage: ' . $report->itemsPerPage . "\n";
    echo ' - row count: ' . sizeof($report->rows) . "\n\n";
  }

  $gaTimezone = $analytics->timezone($report);
  update_option('analyticbridge_analytics_timezone', $gaTimezone);

  // TODO: break here if API errors.
  // TODO: paginate.

  // Start a mysql transaction, in a try catch.
  try {
    // $wpdb->query('START TRANSACTION');

    $pagesql = '';
    $metricsql = '';
    foreach ($report->rows as $k => $r) {
      $GAPagePath = $r[0]; // $r[0] - pagePath
      $wpurl = get_home_url() . preg_replace('/index.php$/', '', $GAPagePath);
      $postid = url_to_postid($wpurl);

      if ($postid == 0) {
        continue;
      }

      if ($postid && get_permalink($postid) != $wpurl) {
        // In some cases, two pagepaths might belong to the same post.
        // We do not count pagepaths that do not match the permalink
        // of the post.
        //
        // This happened to anything in this if statement clause.
        // examples include:
        //
        //   - /?p=123212
        //   - /sports/2015/04/23/heres-the-slug/index.php
        //   - /index.php?p=118468&preview=true
        //   - &c.
        //
        // So that something like a preview page doesn't overwrite its low metrics
        // with the actual count we catch it here and do nothing with it.
        // everything else follows the else.
      } else {
        $pagesql .=
          $pagesql == '' ? 'INSERT INTO `' . PAGES_TABLE . "` (pagepath, post_id) VALUES \n" : ', ';
        $metricsql .=
          $metricsql == ''
            ? 'INSERT INTO `' .
              METRICS_TABLE .
              "` (page_id,startdate,enddate,querytime,metric,value) VALUES \n"
            : ', ';

        $pagesql .= $wpdb->prepare("\t(%s, %s) ", $GAPagePath, $postid);

        // Adjust things to save based on the google analytics timezone.
        $tstart = new DateTime($startdate, new DateTimeZone($gaTimezone));
        $tend = new DateTime($startdate, new DateTimeZone($gaTimezone));

        // The time that the query happened (± a few seconds).
        $qTime = new DateTime('now', new DateTimeZone($gaTimezone));

        // $r[1] - ga:pageviews
        // $r[2] - ga:avgTimeOnPage

        // Insert ga:pageviews
        $metricsql .= $wpdb->prepare(
          '(	(SELECT `id` from ' .
            PAGES_TABLE .
            " WHERE `pagepath`=%s),
							%s,
							%s,
							%s,
							%s,
							%s) 
						",
          $r[0],
          date_format($tstart, 'Y-m-d'),
          date_format($tend, 'Y-m-d'),
          date_format($qTime, 'Y-m-d H:i:s'),
          'ga:pageviews',
          $r[1]
        );

        $metricsql .= ", \n";

        // Insert ga:pageviews
        $metricsql .= $wpdb->prepare(
          '(	(SELECT `id` from ' .
            PAGES_TABLE .
            " WHERE `pagepath`=%s),
							%s,
							%s,
							%s,
							%s,
							%s) 
						",
          $r[0],
          date_format($tstart, 'Y-m-d'),
          date_format($tend, 'Y-m-d'),
          date_format($qTime, 'Y-m-d H:i:s'),
          'ga:avgTimeOnPage',
          $r[2]
        );
      }
    }

    // on duplicate key, don't do much.
    $pagesql .= ' ON DUPLICATE KEY UPDATE `id`=LAST_INSERT_ID(id)';

    // on duplicate key update the value and querytime.
    $metricsql .=
      ' ON DUPLICATE KEY UPDATE `id`=LAST_INSERT_ID(id),`querytime`=values(querytime),`value`=values(value)';

    $wpdb->query($pagesql);
    $wpdb->query($metricsql);

    // Catch mysql exception. TODO: catch only mysql exceptions.
  } catch (Exception $e) {
    $wpdb->query('ROLLBACK');
    if ($verbose) {
      echo "[Error] commiting sql to database.\n";
    }
    if ($verbose) {
      echo "\nEnd analyticbridge_cron\n";
    }
    return;
  }
}

/**
 * Get rid of any records in the metrics table having a startdate older than 2 days ago
 */
function _bt_analyticsbridge_purge_old_analytics() {
  global $wpdb;
  $SQL =
    'delete ' .
    METRICS_TABLE .
    ' from ' .
    METRICS_TABLE .
    ' where startdate < (curdate()  - interval 2 day);';
  $wpdb->query($SQL);
}
<?php

function analyticsbridge_client_id() {
  return get_option('analyticsbridge_setting_api_client_id');
}

function analyticsbridge_client_secret() {
  return get_option('analyticsbridge_setting_api_client_secret');
}

// authenticated user:

function bt_analyticsbridge_option_authenticated_user() {
  return get_option('bt_analyticsbridge_authenticated_user');
}

function bt_analyticsbridge_set_option_authenticated_user($user) {
  update_option('bt_analyticsbridge_authenticated_user', $user);
}

// authenticated date:

function bt_analyticsbridge_option_authenticated_date_gmt() {
  return get_option('bt_analyticsbridge_authenticated_date_gmt');
}

function bt_analyticsbridge_set_option_authenticated_date_gmt($date) {
  update_option('bt_analyticsbridge_authenticated_date_gmt', $date);
}

// timezone:

function bt_analyticsbridge_option_ga_timezone() {
  return get_option('bt_analyticsbridge_ga_timezone');
}

function bt_analyticsbridge_set_option_ga_timezone($timezone) {
  update_option('bt_analyticsbridge_ga_timezone', $timezone);
}

// halflife:

function bt_analyticsbridge_option_popular_posts_halflife() {
  return get_option('bt_analyticsbridge_setting_popular_posts_halflife');
}

function bt_analyticsbridge_set_option_popular_posts_halflife($days) {
  update_option('bt_analyticsbridge_setting_popular_posts_halflife', $days);
}

// profile_id:

function bt_analyticsbridge_option_account_profile_id() {
  return get_option('bt_analyticsbridge_setting_account_profile_id');
}

function bt_analyticsbridge_set_option_account_profile_id($profile_id) {
  update_option('bt_analyticsbridge_setting_account_profile_id', $profile_id);
}

// access_token:

function bt_analyticsbridge_option_access_token() {
  return get_option('bt_analyticsbridge_access_token');
}

function bt_analyticsbridge_set_option_access_token($access_token) {
  update_option('bt_analyticsbridge_access_token', $access_token);
}

// refresh_token:

function bt_analyticsbridge_option_refresh_token() {
  return get_option('bt_analyticsbridge_refresh_token');
}

function bt_analyticsbridge_set_option_refresh_token($refresh_token) {
  update_option('bt_analyticsbridge_refresh_token', $refresh_token);
}
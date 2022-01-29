<?php

class GoogleClientAuthProvider {
  private $clientID;
  private $clientSecret;

  private $accessToken;
  private $refreshToken;

  function __construct($clientID, $clientSecret) {
  }

  /**
   *
   */
  function authenticate($accessToken, $refreshToken) {
    return true;
  }

  function client() {
  }
}

$googleClientAuthProvider = new GoogleClientAuthProvider('id', 'secret');

if ($googleClientAuthProvider->authenticate('access', 'refresh')) {
  $googleClient = $googleClientAuthProvider->client();
} else {
  // error authenticating
}

class analyticsbridge {
  /**
   * Refers to a single instance of this class.
   */
  private static $instance = null;

  /**
   * Refers to a single instance of this class.
   */
  private $client;

  private $clientAuthenticated;

  /**
   * Creates or returns an instance of this class.
   *
   * @return analyticsbridge Object A single instance of this class.
   */
  public static function get_instance() {
    if (null == self::$instance) {
      self::$instance = new self();
    }

    return self::$instance;
  } // end get_instance;

  /**
   * Initializes the plugin by setting localization, filters, and administration functions.
   */
  private function __construct() {
    $this->client = null;
    $this->clientAuthenticated = false;
  } // end constructor

  /**
   * Attempts to authenticate with google's servers.
   *
   * Pulls the access_token and refresh_token provided by google from the
   * database and authenticates a google client.
   *
   * On success, returns a google_client object that's pre authenticated and loaded
   * with the right scopes to access analytic data and email/name of the authenticated.
   *
   * On failure, returns false.
   *
   * @since v0.1
   *
   * @param boolean $auth whether we should try to authenticate the client or just set it up
   *		with the right scopes.
   * @param array $e passed by reference. If provided, $e will contain error information
   *		if authentication fails.
   *
   * @return Google_Client object on success, 'false' on failure.
   */
  public function getClient($auth = true, &$e = null) {
    if ($auth && $this->client && $this->clientAuthenticated) {
      error_log('HERERERER 122');
      return $this->client;
    }

    // We want to authenticate and there is no ( auth ticket and refresh token )
    // Both are needed, see https://developers.google.com/identity/protocols/OAuth2
    if (
      $auth &&
      !(bt_analyticsbridge_option_access_token() && bt_analyticsbridge_option_refresh_token())
    ):
      // @todo we need better user-facing errors here if there is not a access token or a refresh token
      // including instructions on how to revoke permissions in their google account to get a new refresh token when they sign in again, because google only doles those out on the first sign-in
      if ($e) {
        $e = [];
        $e['message'] =
          'No access token. Get a system administrator to authenticate the Google Analytics Popular Posts plugin.';
      }

      return false;

      // We want to authenticate and there is no client id or client secret.
      // Client id and secret are needed to create the redirect button, to send us to Google oAuth page
      // See https://developers.google.com/identity/protocols/OAuth2

      // We have everything we need.

      // Create a Google Client.

      /*
       * If there's an access token set, try to authenticate with it.
       * Otherwise we just return without any authenticating.
       */

      // return (by reference) error information.

      // Return our client.
    elseif ($auth && !(analyticsbridge_client_id() && analyticsbridge_client_secret())):
      if ($e) {
        $e = [];
        $e['message'] =
          'No client id or client secret. Get a system administrator to authenticate the Google Analytics Popular Posts plugin.';
      }

      return false;
    else:
      $config = new Google_Config();
      $config->setCacheClass('Google_Cache_Null');

      $client = new Google_Client($config);
      $client->setApplicationName('Analytic_Bridge');
      $client->setClientId(analyticsbridge_client_id());
      $client->setClientSecret(analyticsbridge_client_secret());
      $client->setRedirectUri(
        'https://localhost/wp-admin/options-general.php?page=analytic-bridge'
      );
      $client->setAccessType('offline');
      $client->setScopes([
        'https://www.googleapis.com/auth/analytics.readonly',
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/userinfo.profile',
      ]);

      if ($auth):
        error_log('HERERERER 1');
        try {
          $client->setAccessToken(bt_analyticsbridge_option_access_token());

          if ($client->isAccessTokenExpired() && bt_analyticsbridge_option_refresh_token()) {
            $token = bt_analyticsbridge_option_refresh_token();
            $accesstoken = $client->refreshToken($token);
            bt_analyticsbridge_set_option_access_token($client->getAccessToken());
          }

          $this->clientAuthenticated = true;
        } catch (Google_Auth_Exception $error) {
          error_log(print_r($error, true));
          if ($e) {
            $e = $error;
          }

          $this->clientAuthenticated = false;
          error_log('HERERERER');
          return false;
        } catch (Exception $error) {
        }
      endif;

      $this->client = $client;
      error_log('HERERERER 12');
      return $client;
    endif;
  }
}

/**
 * Attempts to authenticate with google's servers.
 *
 * @see analyticsbridge->getClient() for full documentation.
 *
 * @since v0.1
 *
 * @param boolean $auth whether we should try to authenticate the client or just set it up
 *		with the right scopes.
 * @param array $e passed by reference. If provided, $e will contain error information
 *		if authentication fails.
 * @return Google_Client object on success, 'false' on failure.
 */
function analytic_bridge_google_client($auth = true, &$e = null) {
  $analyticsbridge = analyticsbridge::get_instance();
  $client = $analyticsbridge->getClient($auth, $e);
  if ($e != null) {
    error_log($e);
  }
  error_log('here...');
  return $client;
}

/**
 * Used the first time a user is authenticating.
 *
 * Attempts to authenticate a new google client for the first time and
 * saves an access and refresh token to the database before returning the client.
 *
 * @since 0.1
 *
 * @param String $code
 */
function analytic_bridge_authenticate_google_client($code, &$e = null) {
  // get a new unauthenticated google client.
  $client = analytic_bridge_google_client(false, $e);

  // If we didn't get a client (for whatever reason) return false.
  if (!$client) {
    return false;
  }

  $client->authenticate($code);

  bt_analyticsbridge_set_option_access_token($client->getAccessToken());
  bt_analyticsbridge_set_option_refresh_token($client->getRefreshToken());

  bt_analyticsbridge_set_option_authenticated_user(get_current_user_id());
  bt_analyticsbridge_set_option_authenticated_date_gmt(current_time('mysql', true));
}
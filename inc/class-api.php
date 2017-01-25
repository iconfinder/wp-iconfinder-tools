<?php
/**
 * Iconfinder API access functions.
 *
 * @link       http://github.com/iconfinder/wp-iconfinder-tools
 * @since      2.0.0
 *
 * @package    Iconfinder_Tools
 */
class ICF_API {


    /**
     * @var int
     */
    private static $maxcount = 100;

    /**
     * @var string
     */
    private static $domain = 'iconfinder.com';

    /**
     * @var string
     */
    private static $api_url = 'https://api.iconfinder.com/v2/';

    /**
     * @var string
     */
    private static $site_url = 'https://iconfinder.com/';

    /**
     * @var string
     */
    private static $cdn_url = 'https://cdn4.iconfinder.com/';

    /**
     * @var string
     */
    private static $icons_url = 'https://www.iconfinder.com/icons/';

    /**
     * @var string
     */
    private static $iconsets_url = 'https://www.iconfinder.com/iconsets/';

    /**
     * @var bool
     */
    private static $ssl_verify = false;


    /**
     * @return int
     */
    public static function maxcount() {
        return self::$maxcount;
    }

    /**
     * @return string
     */
    public static function domain() {
        return self::$domain;
    }

    /**
     * @return string
     */
    public static function api_url() {
        return self::$api_url;
    }

    /**
     * @return string
     */
    public static function site_url() {
        return self::$site_url;
    }

    /**
     * @return string
     */
    public static function cdn_url() {
        return self::$cdn_url;
    }

    /**
     * @return string
     */
    public static function icons_url() {
        return self::$icons_url;
    }

    /**
     * @return string
     */
    public static function iconsets_url() {
        return self::$iconsets_url;
    }

    /**
     * @return boolean
     */
    public static function ssl_verify() {
        return self::$ssl_verify;
    }

    /**
     * Verifies that we have valid api credentials. We use a static var so we
     * don't need to hit the DB every time we need them during a process.
     * @param null $credentials
     * @return bool
     */
    public static function verify_credentials($credentials=null) {

        if (empty($credentials)) {
            $credentials = self::credentials();
        }
        $ap_client_id = Utils::get($credentials, 'api_client_id');
        if (empty($ap_client_id)) {
            return false;
        }
        $api_client_secret = Utils::get($credentials, 'api_client_secret');
        if (empty($api_client_secret)) {
            return false;
        }
        $username = Utils::get($credentials, 'username');
        if (empty($username)) {
            return false;
        }
        return true;
    }

    /**
     * Gets the stored api credentials. We use a static var so we
     * don't need to hit the DB every time we need them during a process.
     * @return array
     */
    public static function credentials() {
        static $auth;
        if (null === $auth) {
            $options = get_option( BIOS_PLUGIN_NAME );
            $auth = array(
                'api_client_id'     => Utils::get($options, 'api_client_id'),
                'api_client_secret' => Utils::get($options, 'api_client_secret'),
                'username'          => Utils::get($options, 'username')
            );
        }
        return $auth;
    }

    /**
     * Makes the api call.
     * @param $api_url The url to which to make the call
     * @param string $cache_key A unique key matching the call path for caching the results
     * @param bool $from_cache Whether or not to pull requests from the cache first
     * @return array|mixed|null|object
     * @throws Exception
     */
    public static function call( $api_url, $cache_key = '', $from_cache = false ) {

        // Always try the local cache first. If we get a hit, just return the stored data.

        $response = null;

        if ( $from_cache ) {
            $response = self::cache_key( $cache_key );
        }

        // If there is no cached data, make the API cale.

        if ( empty($response) || ! $from_cache ) {
            try {
                $response = json_decode(
                    wp_remote_retrieve_body(
                        wp_remote_get(
                            $api_url,
                            array('sslverify' => self::ssl_verify() )
                        )
                    ),
                    true
                );

                if (isset($response['code'])) {
                    throw new Exception("[{$response['code']}] - {$response['message']}");
                }
                else if (isset($response['detail'])) {
                    throw new Exception("[Exception] - {$response['detail']}");
                }

                /**
                 * a bit kludgy, but I want to normalize the response fields here
                 * instead of having a bunch of conditional checks elsewhere.
                 */
                if (isset($response['iconsets']) && ! isset($response['items'])) {
                    $response['items'] = $response['iconsets'];
                    $response['data_type'] = 'iconsets';
                    unset($response['iconsets']);
                }
                else if (isset($response['icons']) && ! isset($response['items'])) {
                    $response['items'] = $response['icons'];
                    $response['data_type'] = 'icons';
                    unset($response['icons']);
                }

                $response['from_cache'] = 0;

                Utils::update_cache($cache_key, $response);

                if (trim($cache_key) != '') {
                    if ( update_option( $cache_key, $response ) ) {
                        $stored_keys = get_option( 'icf_cache_keys', array() );
                        if ( ! in_array( $cache_key, $stored_keys ) )  {
                            array_push( $stored_keys, $cache_key );
                            update_option('icf_cache_keys', $stored_keys, 'no');
                        }
                    }
                }
            }
            catch(Exception $e) {
                # throw new Exception($e);
                Utils::debug(array(
                    'api_url' => $api_url,
                    'exceptionn' => $e
                ));
            }
        }

        if ($response == null && trim($cache_key) != '') {
            $response = get_option( $cache_key );
        }

        return $response;
    }

    /**
     * @param $cache_key
     * @return mixed
     */
    public static function get_cache($cache_key) {
        $response = get_option($cache_key);
        if ( self::has_data($response) ) {
            $response['from_cache'] = 1;
            return $response;
        }
        return $response;
    }

    /**
     * Checks to see if an API response has any icons or iconsets
     * @param $response
     * @return bool
     */
    public static function has_data($response) {
        if (empty($response)) { return false;  }
        if (! isset($response['iconsets']) && ! isset($response['items'])) {
            return false;
        }
        if (empty($response['iconsets']) && empty($response['items'])) {
            return false;
        }
        return true;
    }

    /**
     * @param $cache_key
     * @param $data
     */
    public static function update_cache($cache_key, $data) {
        if (trim($cache_key) != '') {
            if (update_option($cache_key, $data)) {
                $stored_keys = get_option('icf_cache_keys', array());
                if (! in_array($cache_key, $stored_keys)) {
                    array_push($stored_keys, $cache_key);
                    update_option('icf_cache_keys', $stored_keys, 'no');
                }
            }
        }
    }

    /**
     * We don't want to have to build the path every time we
     * need to make an api call so let's just create a helper.
     *
     * @example
     *
     *      $path = get_api_path('icons', array('identifier' => 'dog-activities'));
     *      result: https://api.iconfinder.com/v2/iconsets/dog-activities/icons
     *
     * @param $which
     * @param array $args
     * @return string
     */
    public static function path($which, $args=array()) {

        if (! is_array($args)) $args = array();

        $username   = Utils::get( $args, 'username' );
        $identifier = Utils::get($args, 'identifier');

        $path = array( $which );

        if ($which === 'iconsets') {
            /**
             * https://api.iconfinder.com/v2/users/iconify/iconsets
             */
            $path = array('users', $username, 'iconsets');
        }
        else if ($which === 'collections') {
            /**
             * https://api.iconfinder.com/v2/users/iconify/collections
             */
            $path = array('users', $username, 'collections');
        }
        else if ($which === 'collection') {
            /**
             * https://api.iconfinder.com/v2/collections/$identifier/iconsets
             */
            $path = array('collections', $identifier, 'iconsets');
        }
        else if ($which === 'icons') {
            /**
             * https://api.iconfinder.com/v2/iconsets/dog-activities-extended-license/icons
             */
            $path = array( 'iconsets', $identifier, 'icons' );
        }
        return implode('/', $path);
    }

    /**
     * Get the full api url for an api call. You must  pass the path for the REST request,
     * as well as any additional arguments such as 'count' and 'after' to the request.
     *
     * @example
     *
     *     $path = get_api_path('icons', array('identifier' => 'dog-activities'));
     *     $url  = get_api_url($path, array('after' => '2352', 'count' => 20));
     *
     * @see get_api_path
     * @see https://developer.iconfinder.com/
     *
     * @param array $path
     * @param array $query_args
     * @return null|string|WP_Error
     */
    public static function url($path, $query_args=array()) {

        $result = null;

        if (! is_array($query_args)) $query_args = array('count' => 100);
        if (! isset( $query_args['count'])) $query_args['count'] = 100;

        # $result = new WP_Error('error', 'No valid API credentials');

        $auth = self::credentials();

        if ( self::verify_credentials($auth) ) {

            $query_args = array_merge(array(
                'client_id'     => Utils::get($auth, 'api_client_id'),
                'client_secret' => Utils::get($auth, 'api_client_secret')
            ), $query_args);
        }

        return ICONFINDER_API_URL . $path . "?" . http_build_query($query_args); ;
    }
}
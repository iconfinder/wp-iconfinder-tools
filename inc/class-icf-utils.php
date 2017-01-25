<?php
/**
 * Class of globally-used utility functions.
 *
 * @link       http://github.com/iconfinder/wp-iconfinder-tools
 * @since      2.0.0
 *
 * @package    Iconfinder_Tools
 */
class ICF_Utils {

    /**
     * Returns the requested value or default if empty
     * @param mixed $subject
     * @param string $key
     * @param mixed $default
     * @return mixed
     *
     * @since 1.1.0
     */
    public static function get($subject, $key, $default=null) {
        $value = $default;
        if (is_array($subject)) {
            if (isset($subject[$key])) {
                $value = $subject[$key];
            }
        }
        else if (is_object($subject)) {
            if (isset($subject->$key)) {
                $value = $subject->$key;
            }
        }
        return $value;
    }

    /**
     * Tests a mixed variable for true-ness.
     * @param int|null|bool|string $value
     * @param null|string|bool|int $default
     * @return bool|null
     */
    public static function is_true($value, $default=null) {
        $result = $default;
        $trues  = array(1, '1', 'true', true, 'yes', 'da', 'si', 'oui', 'absolutment', 'yep', 'yeppers', 'fuckyeah');
        $falses = array(0, '0', 'false', false, 'no', 'non', 'nein', 'nyet', 'nope', 'nowayjose');
        if (in_array(strtolower($value), $trues, true)) {
            $result = true;
        }
        else if (in_array(strtolower($value), $falses, true)) {
            $result = false;
        }
        return $result;
    }

    /**
     * This is a debug function and ideally should be removed from the production code.
     * @param array|object  $what   The object|array to be printed
     * @param bool          $die    Whether or not to die after printing the object
     * @return string
     */
    public static function dump($what, $die=true) {

        if (is_string( $what )) $what = array( 'debug' => $what );
        $output = sprintf( '<pre>%s</pre>', print_r($what, true) );
        if ( $die ) die( $output );
        return $output;
    }

    /**
     * This is an alias for ICF_Utils::dump()
     * @param array|object  $what   The object|array to be printed
     * @param bool          $die    Whether or not to die after printing the object
     * @return string
     */
    public static function debug($what, $die=true) {

        return self::dump( $what, $die );
    }

    /**
     * Buffers the output from a file and returns the contents as a string.
     * You can pass named variables to the file using a keyed array.
     * For instance, if the file you are loading accepts a variable named
     * $foo, you can pass it to the file  with the following:
     *
     * @example
     *
     *      do_buffer('path/to/file.php', array('foo' => 'bar'));
     *
     * @param string $path
     * @param array $vars
     * @return string
     */
    public static function buffer( $path, $vars=null ) {
        $output = null;
        if (! empty($vars)) {
            extract($vars);
        }
        if (file_exists( $path )) {
            ob_start();
            include_once( $path );
            $output = ob_get_contents();
            ob_end_clean();
        }
        return $output;
    }

    /**
     * Filter the entire dataset of iconsets searching for
     * specific iconset_ids.
     * @param array $iconsets The whole dataset
     * @param array $sets An array of iconset_ids to find
     * @return array
     */
    public static function filter_iconsets( $iconsets, $sets ) {
        $filtered = array();
        if (is_array($iconsets) && count($iconsets)) {
            foreach ($iconsets as $iconset) {
                if (in_array($iconset['iconset_id'], $sets)) {
                    array_push($filtered, $iconset);
                }
            }
        }
        return $filtered;
    }

    /**
     * List N number of iconsets by a specific user.
     * @param string    $username   The username of the user whose iconsets we want.
     * @param int       $count      The number of iconsets to list
     * @return array
     */
    public static function user_iconsets( $username, $count=-1 ) {

        $result = self::all_iconsets( $username );

        if (isset($result['items'])) {
            $result = $result['items'];
            if ($count != -1) {
                $result = array_slice( $result, 0, $count );
            }
        }

        return $result;
    }

    /**
     * @param array     $iconsets  The array of iconsets
     * @return null|int         The iconset_id of the last item in the list
     */
    private static function last_id( $iconsets ) {
        $last_id = null;
        if (isset($iconsets['items']) && count($iconsets['items'])) {
            $n = count($iconsets['items'])-1;
            if (isset($iconsets['items'][$n]['iconset_id'])) {
                $last_id = $iconsets['items'][$n]['iconset_id'];
            }
        }
        return $last_id;
    }

    /**
     * Get all iconsets.
     * @param string    $username   Optional username for who to get all iconsets.
     * @return array|mixed|null|object
     */
    public static function all_iconsets( $username=null ) {

        $iconsets = array();
        $batches  = array();
        $x = 0;

        $items = array();

        if (empty($iconsets) || ! empty($username)) {

            $path = API::path('iconsets', array( 'username' => $username ));

            /**
             * We grab the first batch outside of the loop so we
             * can determine how many total iconsets there are.
             */
            $batch = API::call(
                API::url($path, array( 'count' => API::maxcount() ))
            );

            $_x = $x;
            $batches['batch-' . $_x]['url'] = API::url($path, array( 'count' => API::maxcount() ) );
            $batches['batch-' . $_x]['result'] = $batch;

            /**
             * Get the iconset_id of the last item in the list for
             * the `after` query arg.
             */
            $last_id = self::last_id( $batch );

            /**
             * How many total iconsets are there?
             */
            $total_count = self::get($batch, 'total_count');

            /**
             * This is how many API calls will be required since there
             * is a 100-count limit to API calls.
             */
            $batch_count = ceil($total_count  / API::maxcount() ) ;

            try {
                /**
                 * Add the batch to the return data.
                 */
                $iconsets = $batch;

                /**
                 * We start with offset 1 since we already grabbed
                 * the first batch (page) of results.
                 */
                for ($i=1; $i<$batch_count ; $i++) {
                    /**
                     * Default to the maximum API results count.
                     */
                    $count = API::maxcount();

                    /**
                     * If we are on the last batch, we only want to
                     * call the remaining number of results because
                     * the API will just loop around to fill the full
                     * number of results requests
                     * (IMHO, this is a logical error in the API).
                     */
                    if ( $i == $batch_count-1 ) {
                        $count = $total_count - ( $i * API::maxcount() ) - 1;
                    }

                    /**
                     * Build the query args. We onlly add the `after` argument
                     * if there is one needed. Otherwise, the API will return
                     * an empty set.
                     */
                    $query_args = array('count' => $count);
                    if ( ! empty($last_id) ) {
                        $query_args['after'] = $last_id;
                    }

                    /**
                     * Get the next batch of iconsets.
                     */
                    $batch = API::call(
                        API::url($path, $query_args )
                    );

                    /**
                     * If we have some items in the batch, get the iconset_id
                     * of the last item so we know where to start the results
                     * in the API request.
                     */
                    if (isset($batch['items']) && count($batch['items'])) {
                        if (is_array($iconsets['items']) && is_array($batch['items'])) {
                            $iconsets['items'] = array_merge($iconsets['items'], $batch['items']);
                        }
                        $n = count($batch['items'])-1;
                        if (isset($batch['items'][$n]['iconset_id'])) {
                            $last_id = self::last_id( $batch );
                            $iconsets['run_count'][] = $last_id;
                            $iconsets['run_count'][] = API::url($path, $query_args );
                        }
                    }
                }
            }
            catch(Exceptoin $e) {
                self::debug( $e );
            }
            if (isset($iconsets['items'])) {
                $ids = array();
                foreach ($iconsets['items'] as $item) {
                    if (! in_array($item['iconset_id'], $ids)) {
                        $items[] = $item;
                    }
                }
                $iconsets['items'] = $items;
                $iconsets['item_count'] = count($iconsets['items']);
            }
        }
        $result = $iconsets;
        if (! empty($username)) $iconsets = array();

        return $result;
    }

    /**
     * @param $cache_key
     * @param $data
     */
    public static function update_cache($cache_key, $data) {
        if (trim($cache_key) != '') {
            if (update_option($cache_key, $data)) {
                $stored_keys = get_option('icf_cache_keys', array());
                if (!in_array($cache_key, $stored_keys)) {
                    array_push($stored_keys, $cache_key);
                    update_option('icf_cache_keys', $stored_keys, 'no');
                }
            }
        }
    }

    /**
     * Get the current WP context.
     * @return string
     */
    public static function wp_context() {

        $context = 'index';

        if ( is_home() ) {
            // Blog Posts Index
            $context = 'home';
            if ( is_front_page() ) {
                // Front Page
                $context = 'front-page';
            }
        }
        else if ( is_date() ) {
            // Date Archive Index
            $context = 'date';
        }
        else if ( is_author() ) {
            // Author Archive Index
            $context = 'author';
        }
        else if ( is_category() ) {
            // Category Archive Index
            $context = 'category';
        }
        else if ( is_tag() ) {
            // Tag Archive Index
            $context = 'tag';
        }
        else if ( is_tax() ) {
            // Taxonomy Archive Index
            $context = 'taxonomy';
        }
        else if ( is_archive() ) {
            // Archive Index
            $context = 'archive';
        }
        else if ( is_search() ) {
            // Search Results Page
            $context = 'search';
        }
        else if ( is_404() ) {
            // Error 404 Page
            $context = '404';
        }
        else if ( is_attachment() ) {
            // Attachment Page
            $context = 'attachment';
        }
        else if ( is_single() ) {
            // Single Blog Post
            $context = 'single';
        }
        else if ( is_page() ) {
            // Static Page
            $context = 'page';
        }
        return $context;
    }
}
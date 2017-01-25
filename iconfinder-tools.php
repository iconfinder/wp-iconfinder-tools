<?php

/**
 * A collection of utility functions for use across multiple plugins. The purpose is to allow
 * access to functions like API, Utils, etc. to avoid naming conflicts and duplicating code.
 *
 * @link              http://github.com/iconfinder
 * @since             1.0.0
 * @package           Iconfinder_Tools
 *
 * @wordpress-plugin
 * Plugin Name:       Iconfinder Tools
 * Plugin URI:        http://github.com/iconfinder/wp-iconfinder-tools
 * Description:       A collection of utility functions for use across multiple plugins.
 * Version:           1.0.0
 * Author:            Scott Lewis
 * Author URI:        http://iconfinder.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       iconfinder
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

require_once( './settings.php' );
require_once( './inc/class-icf-utils.php' );
require_once( './inc/class-icf-api.php' );

if ( ! defined( 'ICF_TOOLS' )) {
    define( 'ICF_TOOLS', 1 );
}
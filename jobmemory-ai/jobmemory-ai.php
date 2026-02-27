<?php
/**
 * Plugin Name: JobMemory AI for GAIS
 * Plugin URI: https://gais.or.jp
 * Description: GAIS会員企業向けAI求人文生成プラグイン。職種名と自社情報を入力すると、GAISの知見をMemoryとして活用し、3パターンの求人文を生成します。
 * Version: 1.0.0
 * Author: GAIS
 * Author URI: https://gais.or.jp
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: jobmemory-ai
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'JMAI_VERSION', '1.0.0' );
define( 'JMAI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JMAI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once JMAI_PLUGIN_DIR . 'includes/class-memory.php';
require_once JMAI_PLUGIN_DIR . 'includes/class-ai-client.php';
require_once JMAI_PLUGIN_DIR . 'includes/class-admin.php';

register_activation_hook( __FILE__, function () {
    $memory = new JMAI_Memory();
    $memory->init_default_memory();
} );

add_action( 'plugins_loaded', function () {
    new JMAI_Admin();
} );

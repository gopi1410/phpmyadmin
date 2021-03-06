<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * Events management.
 *
 * @package PhpMyAdmin
 */

/**
 * Include required files
 */
require_once 'libraries/common.inc.php';
require_once 'libraries/common.lib.php';

/**
 * Include JavaScript libraries
 */
$GLOBALS['js_include'][] = 'jquery/timepicker.js';
$GLOBALS['js_include'][] = 'rte/common.js';
$GLOBALS['js_include'][] = 'rte/events.js';

/**
 * Include all other files
 */
require_once 'libraries/rte/rte_events.lib.php';

/**
 * Do the magic
 */
$_PMA_RTE = 'EVN';
require_once 'libraries/rte/rte_main.inc.php';

?>

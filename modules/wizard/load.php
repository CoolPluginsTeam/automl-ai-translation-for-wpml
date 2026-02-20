<?php
/**
 * Loads the setup wizard.
 *
 * @package WPML_Auto_Translate
 */

namespace AUTOML_WPML\Modules\Wizard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/wizard.php';

$wpml_at_wizard = new WPML_AT_Wizard();
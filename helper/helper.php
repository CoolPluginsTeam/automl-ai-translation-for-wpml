<?php

namespace AUTOML_WPML\Helper;

if ( ! defined( 'ABSPATH' ) ) exit;

if(class_exists(Helper::class)) return;

/**
 * Helper
 *
 * @package AUTOML_WPML\Helper
 */
class Helper {
    /**
     * Bulk translation supported
     *
     * @param object $current_screen The current screen object.
     * @return bool True if bulk translation should be rendered, false otherwise.
     */
    public static function tranlastable_post_type($current_screen){

        if((isset($current_screen->action) && $current_screen->action === 'add') || (isset($_GET['post']) && isset($_GET['action']) && $_GET['action'] === 'edit')){
            return false;
        }

        if(!isset($current_screen->post_type) || empty($current_screen->post_type)){
            return false;
        }

        if(isset($current_screen->post_type) && $current_screen->post_type === 'attachment' ){
            return false;
        }

        return true;
    }

}
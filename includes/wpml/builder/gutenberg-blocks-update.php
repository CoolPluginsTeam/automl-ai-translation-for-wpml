<?php

namespace AUTOML_WPML\Includes\Wpml\Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPML_Gutenberg_Integration;
use WPML_Gutenberg_Config_Option;
use WPML_ST_String_Factory;
use WPML_Gutenberg_Strings_Registration;
use WPML_PB_Reuse_Translations;
use WPML_PB_String_Translation;
use WPML\PB\TranslateLinks as WPML_TranslateLinks;
use WPML\PB\Gutenberg\StringsInBlock\Collection as StringsInBlockCollection;
use WPML\PB\Gutenberg\StringsInBlock\HTML as StringsInBlockHTML;
use WPML\PB\Gutenberg\StringsInBlock\Attributes as StringsInBlockAttributes;

use function WPML\Container\make;

/**
 * Gutenberg_Blocks_Update
 *
 * @package AUTOML_WPML\Includes\Wpml
 */
class Gutenberg_Blocks_Update extends Content_Update_Base {
    /**
     * @var WPML_Gutenberg_Integration
     */
    private $gutenberg_builder_factory;

    /**
     * Editor Type
     */
    protected $editor_type = 'Gutenberg';

    public function __construct(int $post_id, int $translated_post_id, array $translate_strings, string $target_language, string $nonce) {
        parent::__construct($post_id, $translated_post_id, $translate_strings, $target_language, $nonce);
    }

    protected function is_content_update() {
        return ( defined( 'DOING_AUTOML_WPML_GUTENBERG_CONTENT_UPDATE' ) && true === constant( 'DOING_AUTOML_WPML_GUTENBERG_CONTENT_UPDATE' ) );
    }

    protected function cretae_builder_integration(): void {

        if(!$this->content_update_allowed){
            wp_send_json_error( 'You are not authorized to perform this action three.' );
            exit;
        }

		global $sitepress, $wpdb;

        $config_option    = new WPML_Gutenberg_Config_Option();
		$strings_in_block = $this->create_strings_in_block( $config_option );
		$string_factory   = new WPML_ST_String_Factory( $wpdb );

		$strings_registration = new WPML_Gutenberg_Strings_Registration(
			$strings_in_block,
			$string_factory,
			new WPML_PB_Reuse_Translations( $string_factory ),
			new WPML_PB_String_Translation( $wpdb ),
			make( 'WPML_Translate_Link_Targets' ),
			WPML_TranslateLinks::getTranslatorForString( $string_factory, $sitepress->get_active_languages() )
		);

        $this->gutenberg_builder_factory=new WPML_Gutenberg_Integration($strings_in_block,$config_option,$strings_registration);
    }

    private function create_strings_in_block( WPML_Gutenberg_Config_Option $config_option ) {
		$string_parsers = [
			new StringsInBlockHTML( $config_option ),
			new StringsInBlockAttributes( $config_option ),
		];

		return new StringsInBlockCollection( $string_parsers );
	}

    protected function update_builder_translation(): void {

        if(!$this->gutenberg_builder_factory instanceof WPML_Gutenberg_Integration){
            wp_send_json_error( 'Gutenberg builder factory not found.' );
            exit;
        }
        
        $source_post=get_post($this->post_id);
        $source_content=$source_post->post_content;

        $updated_content=$this->gutenberg_builder_factory->replace_strings_in_blocks($source_content, $this->translate_strings, $this->target_language);
        
        wpml_update_escaped_post( [ 'ID' => $this->translated_post_id, 'post_content' => $updated_content ], $this->target_language );
    }

}
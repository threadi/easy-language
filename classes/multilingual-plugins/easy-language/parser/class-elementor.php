<?php
/**
 * File for handling Elementor pagebuilder for translations.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language\Parser;

use Elementor\Plugin;
use easyLanguage\Helper;
use easyLanguage\Multilingual_plugins\Easy_Language\Parser;
use easyLanguage\Multilingual_plugins\Easy_Language\Parser_Base;
use easyLanguage\Multilingual_plugins\Easy_Language\Post_Object;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handler for parsing Elementor-blocks.
 */
class Elementor extends Parser_Base implements Parser {
    /**
     * Internal name of the parser.
     *
     * @var string
     */
    protected string $name = 'Elementor';

    /**
     * Instance of this object.
     *
     * @var ?Elementor
     */
    private static ?Elementor $instance = null;

    /**
     * Constructor for this object.
     */
    private function __construct() {}

    /**
     * Prevent cloning of this object.
     *
     * @return void
     */
    private function __clone() {}

    /**
     * Return the instance of this Singleton object.
     */
    public static function get_instance(): Elementor {
        if ( ! static::$instance instanceof static ) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Return parsed texts.
     *
     * Get the elementor-content and parse its widgets to get the content of flow-text-widgets.
     *
     * @return array
     */
    public function get_parsed_texts(): array {
        // do nothing if elementor is not active.
        if( false === $this->is_elementor_active() ) {
            return array();
        }

        // define returning list.
        $resulting_texts = array();

        // get the actual elementor_data to get the texts of supported widgets.
        $data = Plugin::$instance->documents->get( $this->get_object_id() )->get_elements_data();
        if( !empty($data) && !empty($data[0]) && !empty($data[0]['elements']) ) {
            foreach( $data[0]['elements'] as $widget ) {
                $resulting_texts = $this->get_widgets( (array)$widget, $resulting_texts );
            }
        }

        // return resulting texts.
        return $resulting_texts;
    }

    /**
     * Define flow-text-widgets.
     *
     * @return array
     */
    private function get_flow_text_widgets(): array {
        return array(
            'text-editor' => array(
                'editor'
            ),
            'heading' => array(
                'title'
            )
        );
    }

    /**
     * Replace original text with translation.
     *
     * We replace the text complete 1:1.
     *
     * @param string $original_complete
     * @param string $translated_part
     * @return string
     */
    public function get_text_with_translations( string $original_complete, string $translated_part ): string {
        // do nothing if elementor is not active.
        if( false === $this->is_elementor_active() ) {
            return $original_complete;
        }

        // replace content in postmeta _elementor_data.
        $data = Plugin::$instance->documents->get( $this->get_object_id() )->get_elements_data();
        if( !empty($data) && !empty($data[0]) && !empty($data[0]['elements']) ) {
            foreach( $data[0]['elements'] as $index => $widget ) {
                $data[0]['elements'][$index] = $this->replace_content_in_widgets( $widget, $translated_part );
            }
        }
        update_post_meta( $this->get_object_id(), '_elementor_data', wp_slash( wp_json_encode( $data ) ) );

        // replacement for post_content.
        return str_replace( $this->get_text(), $translated_part, $original_complete );
    }

    /**
     * Loop through the elementor-widget to get the contents of the defined
     * flow-text-widgets.
     *
     * @param array $widget
     * @param array $resulting_texts
     * @return array
     */
    private function get_widgets( array $widget, array $resulting_texts ): array {
        // get content if it is a valid flow-text-widget.
        $flow_text_widgets = $this->get_flow_text_widgets();
        if( !empty($widget['widgetType']) && !empty($flow_text_widgets[$widget['widgetType']]) ) {
            foreach( $flow_text_widgets[$widget['widgetType']] as $text ) {
				if( !empty($widget['settings'][ $text ]) ) {
					$resulting_texts[] = $widget['settings'][ $text ];
				}
			}
        }

        // loop through inner-widgets.
        foreach( $widget['elements'] as $sub_widget ) {
            $resulting_texts = $this->get_widgets( $sub_widget, $resulting_texts );
        }

        // return resulting list.
        return $resulting_texts;
    }

    /**
     * Replace the original widget texts with the translated texts.
     * Only in the supported flow-text-elements.
     *
     * @param array $widget
     * @param string $part_translation
     * @return array
     */
    private function replace_content_in_widgets( array $widget, string $part_translation ): array {
        // get content if it is a valid flow-text-widget.
        $flow_text_widgets = $this->get_flow_text_widgets();
        if( !empty($widget['widgetType']) && !empty($flow_text_widgets[$widget['widgetType']]) ) {
            foreach( $flow_text_widgets[$widget['widgetType']] as $text ) {
                if( $this->get_text() === $widget['settings'][$text] ) {
                    $widget['settings'][$text] = $part_translation;
                }
            }
        }

        // loop through inner-widgets.
        foreach( $widget['elements'] as $index => $sub_widget ) {
            $widget['elements'][$index] = $this->replace_content_in_widgets( $sub_widget, $part_translation );
        }

        // return resulting widget.
        return $widget;
    }

    /**
     * Return whether Elementor is active.
     *
     * @return bool
     */
    private function is_elementor_active(): bool {
        return helper::is_plugin_active( 'elementor/elementor.php' );
    }

	/**
	 * Return whether the given object is using this page builder.
	 *
	 * @param Post_Object $object
	 *
	 * @return bool
	 */
	public function is_object_using_pagebuilder( Post_Object $object ): bool {
		return get_post_meta( $object->get_id(), '_elementor_data', true );
	}

	/**
	 * Return edit link for elementor-object.
	 *
	 * @return string
	 */
	public function get_edit_link(): string {
		$document = Plugin::$instance->documents->get( $this->get_object_id() );
		return $document->get_edit_url();
	}

	/**
	 * Return whether this pagebuilder plugin is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return $this->is_elementor_active();
	}
}

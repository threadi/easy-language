<?php
/**
 * File to handle the language switcher widget.
 *
 * @package easy-language
 */

namespace easyLanguage\PageBuilder\Elementor;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use easyLanguage\EasyLanguage\Switcher;

/**
 * Object to handle the language switcher-widget.
 *
 * @noinspection PhpUnused
 */
class Switcher_Widget extends Widget_Base {
	/**
	 * Retrieve the widget name.
	 *
	 * @return string Widget name.
	 */
	public function get_name(): string {
		return 'easy-language-switcher';
	}

	/**
	 * Retrieve the widget title.
	 *
	 * @return string Widget title.
	 */
	public function get_title(): string {
		return __( 'Language Switcher', 'easy-language' );
	}

	/**
	 * Retrieve the widget icon.
	 *
	 * @return string Widget icon.
	 */
	public function get_icon(): string {
		return 'easy-language-builder-icon';
	}

	/**
	 * Set keywords for elementor-internal search for widgets.
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( __( 'Easy Language', 'easy-language' ), __( 'Menu', 'easy-language' ) );
	}

	/**
	 * Retrieve the list of categories the widget belongs to.
	 *
	 * Used to determine where to display the widget in the editor.
	 *
	 * Note that currently Elementor supports only one category.
	 * When multiple categories passed, Elementor uses the first one.
	 *
	 * @return array Widget categories.
	 */
	public function get_categories(): array {
		return array( 'general' );
	}

	/**
	 * Enqueue styles.
	 */
	public function get_style_depends(): array {
		return array(
			'easy-language-switcher',
		);
	}

	/**
	 * Register the widget controls.
	 *
	 * @access protected
	 */
	protected function register_controls(): void {
		$switcher = Switcher::get_instance();

		$this->start_controls_section(
			'filter_section',
			array(
				'label' => __( 'Settings', 'easy-language' ),
			)
		);

		$this->add_control(
			'display',
			array(
				'label'   => esc_html__( 'Display', 'easy-language' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array_merge( array( '' => __( 'Text or icons (setting below)', 'easy-language' ) ), $switcher->get_display_options() ),
				'default' => '',
			)
		);

		$this->add_control(
			'show_icons',
			array(
				'label'        => esc_html__( 'Show icons', 'easy-language' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Show', 'easy-language' ),
				'label_off'    => esc_html__( 'Hide', 'easy-language' ),
				'return_value' => 'yes',
				'default'      => 'no',
				'condition'    => array(
					'display' => '',
				),
			)
		);

		$this->add_control(
			'hide_actual_language',
			array(
				'label'        => esc_html__( 'Hide actual language', 'easy-language' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Show', 'easy-language' ),
				'label_off'    => esc_html__( 'Hide', 'easy-language' ),
				'return_value' => 'yes',
				'default'      => 'no',
				'condition'    => array(
					'display!' => Switcher::DISPLAY_TOGGLE,
				),
			)
		);

		$this->add_control(
			'toggle_language',
			array(
				'label'     => esc_html__( 'Language of the toggle', 'easy-language' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => $switcher->get_toggle_language_options(),
				'default'   => '',
				'condition' => array(
					'display' => Switcher::DISPLAY_TOGGLE,
				),
			)
		);

		$this->add_control(
			'toggle_disabled',
			array(
				'label'        => esc_html__( 'Grey out if no simplification is available', 'easy-language' ),
				'description'  => esc_html__( 'If disabled, the toggle will be hidden on pages without simplification.', 'easy-language' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'no',
				'condition'    => array(
					'display' => Switcher::DISPLAY_TOGGLE,
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'visibility_section',
			array(
				'label' => __( 'Visibility', 'easy-language' ),
			)
		);

		$this->add_control(
			'visibility',
			array(
				'label'   => esc_html__( 'Visibility', 'easy-language' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $switcher->get_visibility_options(),
				'default' => 'all',
			)
		);

		$this->add_control(
			'visibility_objects',
			array(
				'label'       => esc_html__( 'Pages and terms', 'easy-language' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $this->get_object_options(),
				'default'     => array(),
				'condition'   => array(
					'visibility!' => 'all',
				),
			)
		);

		$this->add_control(
			'visibility_children',
			array(
				'label'        => esc_html__( 'Include child pages and child terms', 'easy-language' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'no',
				'condition'    => array(
					'visibility!' => 'all',
				),
			)
		);

		$this->add_control(
			'visibility_assigned',
			array(
				'label'        => esc_html__( 'Include contents assigned to the chosen terms', 'easy-language' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'no',
				'condition'    => array(
					'visibility!' => 'all',
				),
			)
		);

		$this->add_control(
			'fallback',
			array(
				'label'       => esc_html__( 'On pages without own language versions', 'easy-language' ),
				'description' => esc_html__( 'For example archives, search results or the blog homepage.', 'easy-language' ),
				'type'        => Controls_Manager::SELECT,
				'options'     => $switcher->get_fallback_options(),
				'default'     => 'auto',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'spacing_section',
			array(
				'label' => esc_html__( 'Spacing', 'easy-language' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'margin',
			array(
				'label'     => esc_html__( 'Margin', 'easy-language' ),
				'type'      => Controls_Manager::DIMENSIONS,
				'default'   => array( 0, 0, 0, 0, 'px', true ),
				'selectors' => array(
					'{{WRAPPER}} > div > a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'icons_section',
			array(
				'label'      => esc_html__( 'Icons', 'easy-language' ),
				'tab'        => Controls_Manager::TAB_STYLE,
				'conditions' => array(
					'relation' => 'or',
					'terms'    => array(
						array(
							'name'     => 'display',
							'operator' => '===',
							'value'    => Switcher::DISPLAY_ICONS,
						),
						array(
							'name'     => 'show_icons',
							'operator' => '===',
							'value'    => 'yes',
						),
					),
				),
			)
		);

		$this->add_control(
			'icon_size',
			array(
				'label'       => esc_html__( 'Icon size', 'easy-language' ),
				'description' => esc_html__( 'Leave empty to use the default size from the plugin settings.', 'easy-language' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => array( 'px', 'em', 'rem' ),
				'range'       => array(
					'px'  => array(
						'min' => 8,
						'max' => 128,
					),
					'em'  => array(
						'min'  => 0.5,
						'max'  => 8,
						'step' => 0.1,
					),
					'rem' => array(
						'min'  => 0.5,
						'max'  => 8,
						'step' => 0.1,
					),
				),
				'default'     => array(
					'unit' => 'px',
					'size' => '',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'toggle_section',
			array(
				'label'     => esc_html__( 'Toggle', 'easy-language' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'display' => Switcher::DISPLAY_TOGGLE,
				),
			)
		);

		foreach ( Switcher::get_instance()->get_toggle_design_fields() as $field_name => $field ) {
			if ( $field['color'] ) {
				$this->add_control(
					$field_name,
					array(
						'label'   => $field['label'],
						'type'    => Controls_Manager::COLOR,
						'default' => '',
					)
				);
				continue;
			}
			$this->add_control(
				$field_name,
				array(
					'label'      => $field['label'],
					'type'       => Controls_Manager::SLIDER,
					'size_units' => 'toggle_radius' === $field_name ? array( 'px', 'em', '%' ) : array( 'px', 'em', 'rem' ),
					'range'      => array(
						'px' => array(
							'min' => 0,
							'max' => 'toggle_radius' === $field_name ? 50 : 80,
						),
						'em' => array(
							'min'  => 0,
							'max'  => 5,
							'step' => 0.1,
						),
					),
					'default'    => array(
						'unit' => 'px',
						'size' => '',
					),
				)
			);
		}
		$this->end_controls_section();
	}

	/**
	 * Return the options for the visibility objects.
	 *
	 * @return array<string,string>
	 */
	private function get_object_options(): array {
		// the options are only needed in the editor (which runs in wp-admin or via AJAX), not for the output in frontend.
		if ( ! is_admin() && ! wp_doing_ajax() ) {
			return array();
		}

		// return the options from cache.
		static $options = null;
		if ( is_array( $options ) ) {
			return $options;
		}

		// get the options.
		$options = array();
		foreach ( Switcher::get_instance()->search_objects( '', 500 ) as $object ) {
			$options[ $object['value'] ] = $object['label'];
		}
		return $options;
	}

	/**
	 * Render the widget output in Elementor and frontend.
	 */
	protected function render(): void {
		$attributes = $this->get_settings_for_display();

		// get the display: if not set, the setting before 4.0.0 decides between text and icons.
		$display = $attributes['display'] ?? '';

		// check if this is the Elementor editor.
		$is_editor = \Elementor\Plugin::$instance->editor->is_edit_mode() || \Elementor\Plugin::$instance->preview->is_preview_mode();

		echo wp_kses_post(
			Switcher::get_instance()->get(
				array(
					'display'              => $display,
					'show_icons'           => 'yes' === ( $attributes['show_icons'] ?? '' ),
					'hide_actual_language' => 'yes' === ( $attributes['hide_actual_language'] ?? '' ),
					'icon_size'            => $attributes['icon_size'] ?? '',
					'toggle_language'      => $attributes['toggle_language'] ?? '',
					'toggle_disabled'      => 'yes' === ( $attributes['toggle_disabled'] ?? '' ),
					'toggle_color_on'      => $attributes['toggle_color_on'] ?? '',
					'toggle_color_off'     => $attributes['toggle_color_off'] ?? '',
					'toggle_color_thumb'   => $attributes['toggle_color_thumb'] ?? '',
					'toggle_size'          => $attributes['toggle_size'] ?? '',
					'toggle_radius'        => $attributes['toggle_radius'] ?? '',
					'visibility'           => $attributes['visibility'] ?? 'all',
					'visibility_objects'   => $attributes['visibility_objects'] ?? array(),
					'visibility_children'  => 'yes' === ( $attributes['visibility_children'] ?? '' ),
					'visibility_assigned'  => 'yes' === ( $attributes['visibility_assigned'] ?? '' ),
					'fallback'             => $attributes['fallback'] ?? 'auto',
					'preview'              => $is_editor,
					'source'               => 'elementor',
				)
			)
		);
	}
}

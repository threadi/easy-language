<?php
/**
 * File to handle the language switcher widget.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language\Parser\Elementor;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Exception;

/**
 * Object to handle the language switcher-widget.
 *
 * @noinspection PhpUnused
 */
class Switcher_Widget extends Widget_Base {
	/**
	 * Class constructor.
	 *
	 * @param array $data Widget data.
	 * @param array $args Widget arguments.
	 * @throws Exception possible throw exception
	 */
	public function __construct( $data = array(), $args = null ) {
		parent::__construct( $data, $args );
	}

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
		return 'easy-language-icon';
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
		return array( 'languages', 'menu' );
	}

	/**
	 * Enqueue styles.
	 */
	public function get_style_depends(): array {
		return array(
			'easy-language-elementor-widgets',
		);
	}

	/**
	 * Register the widget controls.
	 *
	 * @access protected
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'filter_section',
			array(
				'label' => __( 'Settings', 'easy-language' ),
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
	}

	/**
	 * Render the widget output in Elementor and frontend.
	 */
	protected function render(): void {
		$attributes = $this->get_settings_for_display();

		echo wp_kses_post(
			\EasyLanguage\Multilingual_plugins\Easy_Language\Switcher::get_instance()->get(
				array(
					'hide_actual_language' => 'yes' === $attributes['hide_actual_language'],
					'show_icons'           => 'yes' === $attributes['show_icons'],
				)
			)
		);
	}
}

<?php
/**
 * Minimal fakes of the classes of Elementor which are used by the parser for Elementor.
 *
 * Elementor saves its elements as JSON in the post meta "_elementor_data".
 *
 * @package easy-language
 */

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound, Universal.Namespaces.OneDeclarationPerFile.MultipleFound

namespace Elementor\Core\Base {
	if ( ! class_exists( 'Elementor\Core\Base\Document' ) ) {
		/**
		 * Fake of an Elementor document.
		 */
		class Document {
			/**
			 * The ID of the post.
			 *
			 * @var int
			 */
			private int $post_id;

			/**
			 * Constructor.
			 *
			 * @param int $post_id The ID of the post.
			 */
			public function __construct( int $post_id ) {
				$this->post_id = $post_id;
			}

			/**
			 * Return the elements of the document.
			 *
			 * @return array<int,mixed>
			 */
			public function get_elements_data(): array {
				$data = json_decode( (string) get_post_meta( $this->post_id, '_elementor_data', true ), true );
				return is_array( $data ) ? $data : array();
			}

			/**
			 * Return the URL of the Elementor editor.
			 *
			 * @return string
			 */
			public function get_edit_url(): string {
				return admin_url( 'post.php?post=' . $this->post_id . '&action=elementor' );
			}
		}
	}
}

namespace Elementor\Core\Files\CSS {
	if ( ! class_exists( 'Elementor\Core\Files\CSS\Post' ) ) {
		/**
		 * Fake of the CSS file of a post.
		 */
		class Post {
			/**
			 * The IDs of the posts whose CSS has been updated.
			 *
			 * @var array<int,int>
			 */
			public static array $updated = array();

			/**
			 * The ID of the post.
			 *
			 * @var int
			 */
			private int $post_id;

			/**
			 * Create the CSS object for a post.
			 *
			 * @param int $post_id The ID of the post.
			 *
			 * @return Post
			 */
			public static function create( int $post_id ): Post {
				$obj          = new self();
				$obj->post_id = $post_id;
				return $obj;
			}

			/**
			 * Update the CSS.
			 *
			 * @return void
			 */
			public function update(): void {
				self::$updated[] = $this->post_id;
			}
		}
	}
}

namespace Elementor {
	if ( ! class_exists( 'Elementor\Plugin' ) ) {
		/**
		 * Fake of the documents manager of Elementor.
		 */
		class Fake_Documents_Manager {
			/**
			 * Return the document of a post.
			 *
			 * @param int $post_id The ID of the post.
			 *
			 * @return Core\Base\Document|false
			 */
			public function get( $post_id ) {
				return get_post( (int) $post_id ) ? new Core\Base\Document( (int) $post_id ) : false;
			}
		}

		/**
		 * Fake of the main object of Elementor.
		 */
		class Plugin {
			/**
			 * The instance.
			 *
			 * @var Plugin
			 */
			public static $instance;

			/**
			 * The documents manager.
			 *
			 * @var Fake_Documents_Manager
			 */
			public $documents;

			/**
			 * Constructor.
			 */
			public function __construct() {
				$this->documents = new Fake_Documents_Manager();
			}
		}

		Plugin::$instance = new Plugin();
	}
}

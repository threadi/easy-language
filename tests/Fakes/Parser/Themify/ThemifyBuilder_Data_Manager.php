<?php
/**
 * Minimal fake of the data manager of Themify Builder for the tests of the Themify parser.
 *
 * Themify Builder stores the builder data as JSON in the post meta "_themify_builder_settings_json".
 *
 * @package easy-language
 */

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound, WordPress.Files.FileName

if ( ! class_exists( 'ThemifyBuilder_Data_Manager' ) ) {
	/**
	 * Fake of the Themify Builder data manager.
	 */
	class ThemifyBuilder_Data_Manager { // phpcs:ignore Generic.Classes.OpeningBraceSameLine.ContentAfterBrace
		/**
		 * The meta key used by Themify Builder.
		 */
		public const META_KEY = '_themify_builder_settings_json';

		/**
		 * Return the builder data of a post.
		 *
		 * @param int $post_id The post ID.
		 *
		 * @return array<int|string,mixed>
		 */
		public static function get_data( int $post_id ): array {
			$data = json_decode( (string) get_post_meta( $post_id, self::META_KEY, true ), true );
			return is_array( $data ) ? $data : array();
		}

		/**
		 * Save the builder data of a post.
		 *
		 * @param array<int|string,mixed> $data    The builder data.
		 * @param int                     $post_id The post ID.
		 *
		 * @return void
		 */
		public static function save_data( array $data, int $post_id ): void {
			update_post_meta( $post_id, self::META_KEY, wp_slash( (string) wp_json_encode( $data ) ) );
		}
	}
}

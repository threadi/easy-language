<?php
/**
 * Fake AI provider which is connected but could only generate images.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Fakes;

use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;

/**
 * Fake AI provider without text generation.
 */
class Ai_Image_Provider extends Ai_Provider {
	/**
	 * ID of this provider.
	 */
	public const ID = 'easy-language-test-image-ai';

	/**
	 * Name of this provider.
	 */
	public const NAME = 'Easy Language Test Image AI';

	/**
	 * Whether this provider is configured (connected).
	 *
	 * @var bool
	 */
	public static bool $configured = false;

	/**
	 * Create the model metadata directory with an image model only.
	 *
	 * @return ModelMetadataDirectoryInterface
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return new Ai_Image_Model_Directory();
	}
}

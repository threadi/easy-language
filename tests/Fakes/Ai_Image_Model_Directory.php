<?php
/**
 * Model directory of the fake AI provider for images.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Fakes;

use InvalidArgumentException;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

/**
 * The fake AI provider for images has one model, which could only generate images.
 */
class Ai_Image_Model_Directory implements ModelMetadataDirectoryInterface {
	/**
	 * ID of the model.
	 */
	public const MODEL_ID = 'easy-language-test-image-model';

	/**
	 * Return the metadata of all models.
	 *
	 * @return list<ModelMetadata>
	 */
	public function listModelMetadata(): array { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return array( $this->get_metadata() );
	}

	/**
	 * Return whether the model exists.
	 *
	 * @param string $model_id The model ID.
	 *
	 * @return bool
	 */
	public function hasModelMetadata( string $model_id ): bool { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return self::MODEL_ID === $model_id;
	}

	/**
	 * Return the metadata of a model.
	 *
	 * @param string $model_id The model ID.
	 *
	 * @return ModelMetadata
	 * @throws InvalidArgumentException If the model does not exist.
	 */
	public function getModelMetadata( string $model_id ): ModelMetadata { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		if ( ! $this->hasModelMetadata( $model_id ) ) {
			throw new InvalidArgumentException( 'Unknown model.' );
		}
		return $this->get_metadata();
	}

	/**
	 * Return the metadata of the model.
	 *
	 * @return ModelMetadata
	 */
	private function get_metadata(): ModelMetadata {
		return new ModelMetadata(
			self::MODEL_ID,
			'Easy Language Test Image Model',
			array( CapabilityEnum::imageGeneration() ),
			array(
				new SupportedOption( OptionEnum::inputModalities(), array( array( ModalityEnum::text() ) ) ),
				new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::image() ) ) ),
			)
		);
	}
}

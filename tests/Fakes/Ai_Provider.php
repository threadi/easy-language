<?php
/**
 * Fake AI provider for the WordPress AI client, used in tests instead of a real connector.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Fakes;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\AbstractProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

/**
 * Fake AI provider with one text generation model, which does not send any HTTP requests.
 */
class Ai_Provider extends AbstractProvider {
	/**
	 * ID of this provider.
	 */
	public const ID = 'easy-language-test-ai';

	/**
	 * Name of this provider.
	 */
	public const NAME = 'Easy Language Test AI';

	/**
	 * Whether this provider is configured (connected).
	 *
	 * @var bool
	 */
	public static bool $configured = true;

	/**
	 * Register this provider in the AI client of WordPress (once).
	 *
	 * @return void
	 */
	public static function register(): void {
		$registry = AiClient::defaultRegistry();
		if ( ! $registry->hasProvider( static::ID ) ) {
			$registry->registerProvider( static::class );
		}
	}

	/**
	 * Create the model.
	 *
	 * @param ModelMetadata    $model_metadata The model metadata.
	 * @param ProviderMetadata $provider_metadata The provider metadata.
	 *
	 * @return ModelInterface
	 */
	protected static function createModel( ModelMetadata $model_metadata, ProviderMetadata $provider_metadata ): ModelInterface { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return new Ai_Model( $model_metadata, $provider_metadata );
	}

	/**
	 * Create the provider metadata.
	 *
	 * @return ProviderMetadata
	 */
	protected static function createProviderMetadata(): ProviderMetadata { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return new ProviderMetadata( static::ID, static::NAME, ProviderTypeEnum::cloud() );
	}

	/**
	 * Create the availability.
	 *
	 * @return ProviderAvailabilityInterface
	 */
	protected static function createProviderAvailability(): ProviderAvailabilityInterface { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return new Ai_Provider_Availability( static::class );
	}

	/**
	 * Create the model metadata directory.
	 *
	 * @return ModelMetadataDirectoryInterface
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return new Ai_Model_Directory();
	}
}

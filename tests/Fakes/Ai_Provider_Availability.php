<?php
/**
 * Availability of the fake AI provider.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Fakes;

use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;

/**
 * A fake AI provider is configured depending on its static property $configured.
 */
class Ai_Provider_Availability implements ProviderAvailabilityInterface {
	/**
	 * The class name of the fake provider.
	 *
	 * @var class-string<Ai_Provider>
	 */
	private string $provider_class;

	/**
	 * Constructor.
	 *
	 * @param class-string<Ai_Provider> $provider_class The class name of the fake provider.
	 */
	public function __construct( string $provider_class ) {
		$this->provider_class = $provider_class;
	}

	/**
	 * Return whether the provider is configured.
	 *
	 * @return bool
	 */
	public function isConfigured(): bool { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return $this->provider_class::$configured;
	}
}

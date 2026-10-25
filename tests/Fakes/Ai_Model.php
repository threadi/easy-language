<?php
/**
 * Model of the fake AI provider.
 *
 * @package easy-language
 */

namespace easyLanguage\Tests\Fakes;

use RuntimeException;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\ModelMessage;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\TextGeneration\Contracts\TextGenerationModelInterface;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;

/**
 * Text generation model which records the requests and returns a configurable answer.
 */
class Ai_Model implements ModelInterface, TextGenerationModelInterface {
	/**
	 * The answer to return. If null, the text is returned with the prefix "Vereinfacht: ".
	 *
	 * @var string|null
	 */
	public static ?string $response = null;

	/**
	 * If set, the model throws an exception with this message.
	 *
	 * @var string|null
	 */
	public static ?string $error = null;

	/**
	 * The recorded requests with their system instruction and text.
	 *
	 * @var array<int,array<string,string>>
	 */
	public static array $requests = array();

	/**
	 * The model metadata.
	 *
	 * @var ModelMetadata
	 */
	private ModelMetadata $model_metadata;

	/**
	 * The provider metadata.
	 *
	 * @var ProviderMetadata
	 */
	private ProviderMetadata $provider_metadata;

	/**
	 * The model configuration.
	 *
	 * @var ModelConfig
	 */
	private ModelConfig $config;

	/**
	 * Constructor.
	 *
	 * @param ModelMetadata    $model_metadata The model metadata.
	 * @param ProviderMetadata $provider_metadata The provider metadata.
	 */
	public function __construct( ModelMetadata $model_metadata, ProviderMetadata $provider_metadata ) {
		$this->model_metadata    = $model_metadata;
		$this->provider_metadata = $provider_metadata;
		$this->config            = new ModelConfig();
	}

	/**
	 * Reset the recorded requests and the configured answer.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$response = null;
		self::$error    = null;
		self::$requests = array();
	}

	/**
	 * Return the model metadata.
	 *
	 * @return ModelMetadata
	 */
	public function metadata(): ModelMetadata {
		return $this->model_metadata;
	}

	/**
	 * Return the provider metadata.
	 *
	 * @return ProviderMetadata
	 */
	public function providerMetadata(): ProviderMetadata { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return $this->provider_metadata;
	}

	/**
	 * Set the configuration.
	 *
	 * @param ModelConfig $config The configuration.
	 *
	 * @return void
	 */
	public function setConfig( ModelConfig $config ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->config = $config;
	}

	/**
	 * Return the configuration.
	 *
	 * @return ModelConfig
	 */
	public function getConfig(): ModelConfig { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return $this->config;
	}

	/**
	 * Generate the text: record the request and return the configured answer.
	 *
	 * @param list<Message> $prompt The messages.
	 *
	 * @return GenerativeAiResult
	 * @throws RuntimeException If an error is configured.
	 */
	public function generateTextResult( array $prompt ): GenerativeAiResult { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		// collect the text of the prompt.
		$text = '';
		foreach ( $prompt as $message ) {
			foreach ( $message->getParts() as $part ) {
				$text .= (string) $part->getText();
			}
		}

		// record the request.
		self::$requests[] = array(
			'system' => (string) $this->config->getSystemInstruction(),
			'text'   => $text,
		);

		// simulate an error.
		if ( null !== self::$error ) {
			throw new RuntimeException( self::$error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		// return the answer.
		$answer = self::$response ?? 'Vereinfacht: ' . $text;
		return new GenerativeAiResult(
			'easy-language-test',
			array( new Candidate( new ModelMessage( array( new MessagePart( $answer ) ) ), FinishReasonEnum::stop() ) ),
			new TokenUsage( 1, 1, 2 ),
			$this->provider_metadata,
			$this->model_metadata
		);
	}
}

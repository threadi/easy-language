<?php
/**
 * Plugin Name: Easy Language - E2E API mock
 * Description: Answers all requests to the ChatGPT API with a predictable simplification. Only for the E2E tests, never use it on a real website.
 *
 * @package easy-language
 */

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * The prompt which is set for the tests in tests/e2e/blueprint.json.
 *
 * The plugin sends the prompt followed by the text to simplify. The mock removes the prompt and returns the text
 * with this marker in front of it, so the tests can recognize simplified texts.
 */
const EASY_LANGUAGE_E2E_PROMPT = 'E2E-PROMPT:';

/**
 * The marker for simplified texts.
 */
const EASY_LANGUAGE_E2E_MARKER = 'Leicht gesagt: ';

/**
 * Return a response in the format of the WordPress HTTP API.
 *
 * @param int    $code The HTTP status code.
 * @param string $body The body.
 *
 * @return array<string,mixed>
 */
function easy_language_e2e_response( int $code, string $body ): array {
	// the plugin reads the HTTP status from the response object, like WordPress returns it for real requests.
	$requests_response              = new \WpOrg\Requests\Response();
	$requests_response->status_code = $code;
	$requests_response->body        = $body;

	return array(
		'headers'       => array( 'content-type' => 'application/json' ),
		'body'          => $body,
		'response'      => array(
			'code'    => $code,
			'message' => 200 === $code ? 'OK' : 'Unauthorized',
		),
		'cookies'       => array(),
		'filename'      => null,
		'http_response' => new WP_HTTP_Requests_Response( $requests_response ),
	);
}

/**
 * Simplify the given text: add the marker in front of the text (inside the first HTML tag, if it starts with one).
 *
 * @param string $text The text.
 *
 * @return string
 */
function easy_language_e2e_simplify( string $text ): string {
	// remove the prompt and the whitespace after it (saving the settings trims the prompt, so it may end without space).
	if ( str_starts_with( $text, EASY_LANGUAGE_E2E_PROMPT ) ) {
		$text = ltrim( substr( $text, strlen( EASY_LANGUAGE_E2E_PROMPT ) ) );
	}

	// add the marker in the first HTML tag, so the HTML stays valid.
	if ( preg_match( '/^(\s*<[^>]+>)(.*)$/s', $text, $matches ) ) {
		return $matches[1] . EASY_LANGUAGE_E2E_MARKER . $matches[2];
	}

	return EASY_LANGUAGE_E2E_MARKER . $text;
}

/**
 * Answer requests to the ChatGPT API like the real API, but without any external request.
 *
 * Every other external request is blocked, so the tests never depend on the network.
 *
 * @param false|array<string,mixed>|WP_Error $response The response (false to run the request).
 * @param array<string,mixed>                $args     The request arguments.
 * @param string                             $url      The requested URL.
 *
 * @return false|array<string,mixed>|WP_Error
 */
function easy_language_e2e_api_mock( false|array|WP_Error $response, array $args, string $url ): false|array|WP_Error {
	// bail if another filter already answered.
	if ( false !== $response ) {
		return $response;
	}

	// answer the requests to the ChatGPT API.
	if ( str_starts_with( $url, 'https://api.openai.com/v1/chat/completions' ) ) {
		// the API key is required like in the real API.
		if ( empty( $args['headers']['Authorization'] ) || 'Bearer test_api_key' !== $args['headers']['Authorization'] ) {
			return easy_language_e2e_response( 401, (string) wp_json_encode( array( 'error' => array( 'message' => 'Incorrect API key provided.' ) ) ) );
		}

		// get the text from the request.
		$request = json_decode( (string) ( $args['body'] ?? '' ), true );
		$text    = is_array( $request ) ? (string) ( $request['messages'][0]['content'] ?? '' ) : '';

		// return the simplified text.
		return easy_language_e2e_response(
			200,
			(string) wp_json_encode(
				array(
					'choices' => array(
						array(
							'index'   => 0,
							'message' => array(
								'role'    => 'assistant',
								'content' => easy_language_e2e_simplify( $text ),
							),
						),
					),
				)
			)
		);
	}

	// block every other request to external hosts.
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	if ( '' !== $host && ! in_array( $host, array( 'localhost', '127.0.0.1', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ), true ) ) {
		return new WP_Error( 'easy_language_e2e_blocked', 'External requests are blocked during the E2E tests: ' . $url );
	}

	// let all other requests pass.
	return false;
}
add_filter( 'pre_http_request', 'easy_language_e2e_api_mock', 10, 3 );

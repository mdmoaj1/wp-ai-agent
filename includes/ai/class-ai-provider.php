<?php
/**
 * Abstract AI provider — base class that Groq and OpenAI adapters extend.
 *
 * @package AITF\AI
 */

namespace AITF\AI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class AI_Provider {

    /** @var string API key. */
    protected string $api_key;

    /** @var string Model identifier. */
    protected string $model;

    /** @var int Tokens used in the last request. */
    protected int $last_token_usage = 0;

    /**
     * @param string $api_key
     * @param string $model
     */
    public function __construct( string $api_key, string $model = '' ) {
        $this->api_key = $api_key;
        $this->model   = $model;
    }

    /**
     * Get the API endpoint URL.
     *
     * @return string
     */
    abstract protected function get_endpoint(): string;

    /**
     * Get the default model if none specified.
     *
     * @return string
     */
    abstract protected function get_default_model(): string;

    /**
     * Get available models for the dropdown.
     *
     * @return array [ 'model-id' => 'Display Name' ]
     */
    abstract public function get_available_models(): array;

    /**
     * Get the provider identifier.
     *
     * @return string
     */
    abstract public function get_provider_name(): string;

    /**
     * Generate content from a prompt.
     *
     * @param string $prompt       System + user prompt combined.
     * @param string $system_prompt System-level instruction.
     * @return array|\WP_Error Parsed response or WP_Error on failure.
     */
    public function generate( string $prompt, string $system_prompt = '' ): mixed {

        $model = ! empty( $this->model ) ? $this->model : $this->get_default_model();

        $messages = [];

        if ( ! empty( $system_prompt ) ) {
            $messages[] = [
                'role'    => 'system',
                'content' => $system_prompt,
            ];
        }

        $messages[] = [
            'role'    => 'user',
            'content' => $prompt,
        ];

        $body = wp_json_encode( [
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => 0.7,
            'max_tokens'  => 4096,
        ] );

        $response = wp_remote_post( $this->get_endpoint(), [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
            ],
            'body'    => $body,
            'timeout' => 120,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $raw  = wp_remote_retrieve_body( $response );
        $data = json_decode( $raw, true );

        if ( $code < 200 || $code >= 300 ) {
            $err_msg = $data['error']['message'] ?? "HTTP {$code}: Unknown API error";
            return new \WP_Error( 'api_error', $err_msg );
        }

        if ( empty( $data['choices'][0]['message']['content'] ) ) {
            return new \WP_Error( 'empty_response', 'AI returned an empty response.' );
        }

        // Track token usage.
        $this->last_token_usage = (int) ( $data['usage']['total_tokens'] ?? 0 );

        return [
            'content'     => $data['choices'][0]['message']['content'],
            'token_usage' => $this->last_token_usage,
            'model'       => $model,
            'provider'    => $this->get_provider_name(),
        ];
    }

    /**
     * Get tokens used in the last request.
     *
     * @return int
     */
    public function get_last_token_usage(): int {
        return $this->last_token_usage;
    }

    /**
     * Factory: create the correct provider from settings.
     *
     * @return static|\WP_Error
     */
    public static function factory(): mixed {
        $settings = get_option( 'aitf_settings', [] );
        $provider = $settings['api_provider'] ?? 'groq';
        $api_key  = $settings['api_key'] ?? '';
        $model    = $settings['model'] ?? '';

        if ( empty( $api_key ) ) {
            return new \WP_Error( 'no_api_key', 'API key is not configured.' );
        }

        return match ( $provider ) {
            'openai' => new \AITF\AI\Openai_Provider( $api_key, $model ),
            default  => new \AITF\AI\Groq_Provider( $api_key, $model ),
        };
    }
}

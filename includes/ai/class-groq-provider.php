<?php
/**
 * Groq API provider.
 *
 * @package AITF\AI
 */

namespace AITF\AI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Groq_Provider extends AI_Provider {

    protected function get_endpoint(): string {
        return 'https://api.groq.com/openai/v1/chat/completions';
    }

    protected function get_default_model(): string {
        return 'openai/gpt-oss-120b';
    }

    public function get_provider_name(): string {
        return 'groq';
    }

    public function get_available_models(): array {
        return [
            'openai/gpt-oss-120b' => 'GPT OSS 120B',
        ];
    }
}

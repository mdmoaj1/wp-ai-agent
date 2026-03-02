<?php
/**
 * OpenAI API provider.
 *
 * @package AITF\AI
 */

namespace AITF\AI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Openai_Provider extends AI_Provider {

    protected function get_endpoint(): string {
        return 'https://api.openai.com/v1/chat/completions';
    }

    protected function get_default_model(): string {
        return 'gpt-4o-mini';
    }

    public function get_provider_name(): string {
        return 'openai';
    }

    public function get_available_models(): array {
        return [
            'gpt-4o-mini' => 'GPT-4o Mini',
            'gpt-4o'      => 'GPT-4o',
            'gpt-4-turbo' => 'GPT-4 Turbo',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
        ];
    }
}

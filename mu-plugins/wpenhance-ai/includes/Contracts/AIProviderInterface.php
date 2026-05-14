<?php

namespace WPEnhance\AI\Contracts;

defined('ABSPATH') || exit;

interface AIProviderInterface {

    public function chat(array $messages): ?string;
}
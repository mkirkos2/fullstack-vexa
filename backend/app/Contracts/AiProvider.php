<?php

namespace App\Contracts;

use App\Data\AiResponse;

interface AiProvider
{
    /**
     * Generate a reply based on the conversation history.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function generateReply(array $messages): AiResponse;
}

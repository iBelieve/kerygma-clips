<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

#[Model('gpt-5-mini')]
class SermonClipTitleGenerator implements Agent
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
        You are a title generator for short sermon video clips. Given a transcript excerpt from a sermon clip, generate a single concise, engaging title.

        Rules:
        - The title must be under 100 characters
        - Do not use quotation marks around the title
        - Capture the core theme or message of the excerpt
        - Use clear, accessible language
        - Do not include any explanation, just output the title
        INSTRUCTIONS;
    }
}

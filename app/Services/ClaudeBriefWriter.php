<?php

namespace App\Services;

use Anthropic\Client;
use Anthropic\Messages\Model;
use Anthropic\Messages\StopReason;
use App\Contracts\BriefWriter;
use App\Data\BriefOptions;
use App\Data\ClientBrief;
use Illuminate\Support\Collection;

final readonly class ClaudeBriefWriter implements BriefWriter
{
    public function __construct(
        private Client $client,
        private BriefPrompt $prompt,
    ) {}

    public function write(string $digest, BriefOptions $options): ClientBrief
    {
        $message = $this->client->messages->create(
            maxTokens: 16000,
            messages: [['role' => 'user', 'content' => $digest]],
            model: Model::CLAUDE_FABLE_5_1,
            outputConfig: [
                'effort' => 'medium',
                'format' => [
                    'type' => 'json_schema',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'headline' => [
                                'type' => 'string',
                                'description' => 'One short line, at most ten words, naming the single most important change. Not a summary of the items.',
                            ],
                            'items' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'title' => ['type' => 'string'],
                                        'detail' => ['type' => 'string'],
                                        'area' => ['type' => 'string'],
                                        'certain' => [
                                            'type' => 'boolean',
                                            'description' => 'False if you inferred this from the file areas rather than the commit message, if the message claims a capability you cannot confirm was finished, or if you added detail the message did not state.',
                                        ],
                                    ],
                                    'required' => ['title', 'detail', 'area', 'certain'],
                                    'additionalProperties' => false,
                                ],
                            ],
                            'unclear' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                        'required' => ['headline', 'items', 'unclear'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            system: [
                [
                    'type' => 'text',
                    'text' => $this->prompt->build($options),
                    'cacheControl' => ['type' => 'ephemeral'],
                ],
            ],
        );

        if ($message->stopReason !== StopReason::END_TURN->value) {
            $this->resolveStopReason(
                $message->stopReason === null ? null : StopReason::tryFrom($message->stopReason)
            );
        }

        if (! is_array($message?->content)) {
            throw new \RuntimeException('Anthropic API response does not match the expected results.');
        }

        $text = Collection::make($message->content)
            ->filter(function ($item) {
                return $item->type === 'text';
            })->first();

        if (! $text->text) {
            throw new \RuntimeException('Anthropic API response does not match the expected results.');
        }

        return $this->format($text->text);
    }

    private function format(string $text): ClientBrief
    {
        $content = json_decode($text, true);

        return ClientBrief::fromArray($content);
    }

    private function resolveStopReason(?StopReason $reason): void
    {
        $error = match ($reason) {
            StopReason::MAX_TOKENS => 'Reached the max tokens',
            StopReason::REFUSAL => 'The model refused to answer',
            StopReason::MODEL_CONTEXT_WINDOW_EXCEEDED => 'The model context window was exceeded',
            StopReason::STOP_SEQUENCE => 'The model stopped due to a stop sequence',
            StopReason::TOOL_USE => 'The model stopped due to a tool use',
            StopReason::PAUSE_TURN => 'The model paused the turn',
            default => 'Unknown stop reason',
        };

        throw new \RuntimeException("Anthropic API response stopped unexpectedly: {$error}");
    }
}

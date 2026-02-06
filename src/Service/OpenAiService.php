<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service for communicating with the OpenAI API
 *
 * Handles all interactions with OpenAI's ChatGPT API for generating
 * game master responses, NPC dialogues, and narrative content.
 */
class OpenAiService
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';

    // Default AI model to use
    private const DEFAULT_MODEL = 'gpt-3.5-turbo';

    // AI creativity level (0.0 = deterministic, 2.0 = very creative)
    private const TEMPERATURE = 0.8;

    // Penalty for repeating tokens (helps avoid repetitive text)
    private const FREQUENCY_PENALTY = 0.3;

    // Penalty for using the same topics (encourages topic diversity)
    private const PRESENCE_PENALTY = 0.3;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $openaiApiKey
    ) {}

    /**
     * Send a chat completion request to OpenAI
     *
     * @param array $messages Array of message objects with 'role' and 'content'
     * @param int $maxTokens Maximum number of tokens in the response
     * @return string The AI-generated response content
     * @throws \Exception If the API returns an error or invalid response
     */
    public function chat(array $messages, int $maxTokens = 500): string
    {
        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openaiApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => self::DEFAULT_MODEL,
                    'messages' => $messages,
                    'max_tokens' => $maxTokens,
                    'temperature' => self::TEMPERATURE,
                    'top_p' => 1,
                    'frequency_penalty' => self::FREQUENCY_PENALTY,
                    'presence_penalty' => self::PRESENCE_PENALTY,
                ],
            ]);

            $data = $response->toArray();

            // Validate response structure
            if (!isset($data['choices'][0]['message']['content'])) {
                throw new \Exception('Invalid response structure from OpenAI API');
            }

            return trim($data['choices'][0]['message']['content']);

        } catch (\Exception $e) {
            throw new \Exception('OpenAI API communication error: ' . $e->getMessage());
        }
    }

    /**
     * Create a message object for the OpenAI API
     *
     * @param string $role The role of the message sender ('system', 'user', or 'assistant')
     * @param string $content The content of the message
     * @return array Message object formatted for OpenAI API
     */
    public function createMessage(string $role, string $content): array
    {
        return [
            'role' => $role,
            'content' => $content
        ];
    }

    /**
     * Build message history array from conversation history
     *
     * Useful for maintaining conversation context across multiple API calls
     *
     * @param array $systemMessage The system message (instructions for AI)
     * @param array $history Previous conversation messages
     * @param array $newMessage New user message to add
     * @return array Complete message array for API request
     */
    public function buildMessageHistory(array $systemMessage, array $history, array $newMessage): array
    {
        $messages = [$systemMessage];

        // Add conversation history
        foreach ($history as $msg) {
            $messages[] = $msg;
        }

        // Add new message
        $messages[] = $newMessage;

        return $messages;
    }
}

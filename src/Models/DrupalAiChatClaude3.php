<?php

namespace Drupal\drupalai\Models;

use Drupal\drupalai\DrupalAiChatInterface;
use Drupal\drupalai\DrupalAiHelper;
use GuzzleHttp\Client;

/**
 * Claude3 implementation of DrupalAiChat.
 */
class DrupalAiChatClaude3 implements DrupalAiChatInterface {

  /**
   * Get Chat.
   *
   * @param string $systemPrompt
   *   The system prompt.
   * @param array $messages
   *   The AI messages.
   *
   * @return object|bool
   *   The JSON response object from the API.
   */
  public function chat(string $systemPrompt, array $messages): object|bool {
    $config = \Drupal::config('drupalai.settings');
    $api_key = $config->get('claude3_api_key');

    if (!$api_key) {
      \Drupal::logger('drupalai')->error('Claude3 API key not set.');
      return FALSE;
    }

    $url = 'https://api.anthropic.com/v1/messages';

    $client = new Client();

    try {
      $response = $client->request('POST', $url, [
        'headers' => [
          'content-type' => 'application/json',
          'anthropic-version' => '2023-06-01',
          'x-api-key' => $api_key,
        ],
        'json' => [
          "model" => "claude-3-haiku-20240307",
          "max_tokens" => 4096,
          'system' => $systemPrompt,
          "messages" => $messages,
          'tools' => DrupalAiHelper::getChatTools(),
          'tool_choice' => [
            'type' => "auto",
          ],
        ],
      ]);
    }
    catch (\Exception $e) {
      \Drupal::logger('drupalai')->error('Error calling Claude API: ' . $e->getMessage());
      return FALSE;
    }

    if ($response->getStatusCode() != 200) {
      \Drupal::logger('drupalai')->error('Error calling Claude API: ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase());
      return FALSE;
    }
    else {
      $data = $response->getBody()->getContents();
      return json_decode($data);
    }
  }

  /**
   * Create an image message for Claude.
   *
   * @param string $imageBase64
   *   The base64 encoded image data.
   * @param string $userInput
   *   The user input.
   *
   * @return array
   *   The message array.
   */
  public function createImageMessage(string $imageBase64, string $userInput): array {
    return [
      "role" => "user",
      "content" => [
        [
          "type" => "image",
          "source" => [
            "type" => "base64",
            "media_type" => "image/jpeg",
            "data" => $imageBase64,
          ]
        ],
        [
          "type" => "text",
          "text" => "User input for image: $userInput",
        ],
      ],
    ];
  }

  /**
   * Create a user input message for Claude.
   *
   * @param string $userInput
   *   The user input.
   *
   * @return array
   *   The message array.
   */
  public function createUserInputMessage(string $userInput): array {
    return [
      "role" => "user",
      "content" => $userInput,
    ];
  }

  /**
   * Create an assistant message for Claude.
   *
   * @param string $assistantResponse
   *   The assistant response.
   *
   * @return array
   *   The message array.
   */
  public function createAssistantMessage(string $assistantResponse): array {
    return [
      "role" => "assistant",
      "content" => $assistantResponse,
    ];
  }

  /**
   * Create a tool result message for Claude.
   *
   * @param string $toolUseId
   *   The tool use ID.
   * @param string $result
   *   The tool result.
   *
   * @return array
   *   The message array.
   */
  public function createToolResultMessage(string $toolUseId, string $result): array {
    return [
      "role" => "user",
      "content" => [
        [
          "type" => "tool_result",
          "tool_use_id" => $toolUseId,
          "content" => $result,
        ],
      ],
    ];
  }

  /**
   * Create a tool use message for Claude.
   *
   * @param string $toolUseId
   *   The tool use ID.
   * @param string $toolName
   *   The tool name.
   * @param object $toolInput
   *   The tool input.
   *
   * @return array
   *   The message array.
   */
  public function createToolUseMessage(string $toolUseId, string $toolName, object $toolInput): array {
    return [
      "role" => "assistant",
      "content" => [
        [
          "type" => "tool_use",
          "id" => $toolUseId,
          "name" => $toolName,
          "input" => $toolInput,
        ],
      ],
    ];
  }

}

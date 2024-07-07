<?php

namespace Drupal\drupalai\Models;

use Drupal\drupalai\DrupalAiChatInterface;
use Drupal\drupalai\DrupalAiHelper;
use GuzzleHttp\Client;

/**
 * OpenAI GPT-4 implementation of DrupalAiChat.
 */
class DrupalAiChatOpenAi implements DrupalAiChatInterface {

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
    $api_key = $config->get('openai_api_key');

    if (!$api_key) {
      \Drupal::logger('drupalai')->error('OpenAI API key not set.');
      return FALSE;
    }

    $url = 'https://api.openai.com/v1/chat/completions';

    $client = new Client();

    try {
      $response = $client->request('POST', $url, [
        'headers' => [
          'Content-Type' => 'application/json',
          'Authorization' => 'Bearer ' . $api_key,
        ],
        'json' => [
          'model' => 'gpt-3.5-turbo-0125',
          'messages' => array_merge(
            [
              ['role' => 'system', 'content' => $systemPrompt],
            ],
            $messages
          ),
          'tools' => DrupalAiHelper::getChatTools('openai'),
          'tool_choice' => 'auto',
          'max_tokens' => 4096,
        ],
      ]);
    }
    catch (\Exception $e) {
      \Drupal::logger('drupalai')->error('Error calling OpenAI API: ' . $e->getMessage());
      return FALSE;
    }

    if ($response->getStatusCode() != 200) {
      \Drupal::logger('drupalai')->error('Error calling OpenAI API: ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase());
      return FALSE;
    }
    else {
      $data = $response->getBody()->getContents();
      return json_decode($data);
    }
  }

  /**
   * Create an image message for OpenAI.
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
    // Note: OpenAI's API does not support image inputs directly in chat messages.
    return [
      "role" => "user",
      "content" => "User input for image: $userInput (image data not supported directly in OpenAI API)",
    ];
  }

  /**
   * Create a user input message for OpenAI.
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
   * Create an assistant message for OpenAI.
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
   * Create a tool result message for OpenAI.
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
      "content" => $result,
    ];
  }

  /**
   * Create a tool use message for OpenAI.
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
      "content" => '',
      "tools" => [
        [
          "name" => $toolName,
          "parameters" => $toolInput,
        ],
      ],
    ];
  }
}

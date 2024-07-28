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
   * The model to use.
   *
   * @var string
   */
  private string $model;

  /**
   * The provider to use.
   */
  private string $provider;

  /**
   * Constructor.
   */
  public function __construct($model, $provider = 'openai') {
    $this->model = $model;
    $this->provider = $provider;
  }

  /**
   * Get Chat.
   *
   * @param string $systemPrompt
   *   The system prompt.
   * @param array $messages
   *   The AI messages.
   * @param string $toolChoice
   *   The tool choice.
   *
   * @return array|bool
   *   The array of message objects from the API.
   */
  public function chat(string $systemPrompt, array $messages, string $toolChoice = 'auto'): array|bool {
    $config = \Drupal::config('drupalai.settings');

    $api_key = '';

    if ($this->provider == 'openai') {
      $api_key = $config->get('openai_api_key');
      $url = 'https://api.openai.com/v1/chat/completions';
    }
    elseif ($this->provider == 'fireworks') {
      $api_key = $config->get('fireworks_api_key');
      $url = 'https://api.fireworks.ai/inference/v1/chat/completions';
    }
    else {
      $api_key = $config->get('groq_api_key');
      $url = 'https://api.groq.com/openai/v1/chat/completions';
    }

    if (!$api_key) {
      \Drupal::logger('drupalai')->error('OpenAI API key not set.');
      return FALSE;
    }

    $client = new Client();

    try {
      $headers = [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $api_key,
      ];

      $json = [
        'model' => $this->model,
        'messages' => array_merge(
          [
            ['role' => 'system', 'content' => $systemPrompt],
          ],
          $messages
        ),
        'tools' => DrupalAiHelper::getChatTools($this->provider),
        'tool_choice' => $toolChoice,
        'max_tokens' => 4096,
      ];

      $response = $client->request('POST', $url, [
        'headers' => $headers,
        'json' => $json,
      ]);
    }
    catch (\Exception $e) {
      \Drupal::logger('drupalai')->error('Error calling ' . $this->provider . ' API: ' . $e->getMessage());
      return FALSE;
    }

    if ($response->getStatusCode() != 200) {
      \Drupal::logger('drupalai')->error('Error calling ' . $this->provider . ' API: ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase());
      return FALSE;
    }
    else {
      $data = $response->getBody()->getContents();
      return [json_decode($data)->choices[0]->message];
    }
  }

  /**
   * Create an image message for OpenAI.
   *
   * @param string $image
   *   The base64 encoded image data or image URL.
   * @param string $userInput
   *   The user input.
   *
   * @return array
   *   The message array.
   */
  public function createImageMessage(string $image, string $userInput): array {
    return [
      "role" => "user",
      "content" => [
        [
          "type" => "text",
          "text" => "User input for image: $userInput",
        ],
        [
          "type" => "image_url",
          "image_url" => [
            "url" => $image,
          ],
        ],
      ],
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
    if ($this->provider == 'openai') {
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
    else {
      return [
        "role" => "assistant",
        "tool_calls" => [
          [
            "id" => $toolUseId,
            "type" => 'function',
            "function" => [
              "name" => $toolName,
              "arguments" => json_encode($toolInput),
            ],
          ],
        ],
      ];
    }
  }

  /**
   * Check if the message is a text message.
   *
   * @param object $message
   *   The message object.
   *
   * @return bool
   *   TRUE if the message is a text message, FALSE otherwise.
   */
  public function isTextMessage(object $message): bool {
    return !empty($message->content);
  }

  /**
   * Check if the message is a tool message.
   *
   * @param object $message
   *   The message object.
   *
   * @return bool
   *   TRUE if the message is a tool message, FALSE otherwise.
   */
  public function isToolMessage(object $message): bool {
    return !empty($message->tool_calls);
  }

  /**
   * Get tool calls from a message.
   *
   * @param object $message
   *   The message object.
   *
   * @return array
   *   The tool calls array.
   */
  public function toolCalls(object $message): array {
    $tools = [];

    foreach ($message->tool_calls as $toolCall) {
      $tool = new \stdClass();
      $tool->name = $toolCall->function->name;
      $tool->input = json_decode($toolCall->function->arguments);
      $tool->id = $toolCall->id;
      $tools[] = $tool;
    }

    return $tools;
  }

  /**
   * Get the text message from a message.
   *
   * @param object $message
   *   The message object.
   *
   * @return string
   *   The text message.
   */
  public function getTextMessage(object $message): string {
    return $message->content . " ";
  }

}

<?php

namespace Drupal\drupalai\Models;

use Drupal\drupalai\DrupalAiChatInterface;
use Drupal\drupalai\DrupalAiHelper;
use GuzzleHttp\Client;

/**
 * Gemini implementation of DrupalAiChat.
 */
class DrupalAiChatGemini implements DrupalAiChatInterface {

  /**
   * The model name.
   *
   * @var string
   */
  private $model;

  /**
   * Constructor.
   *
   * @param string $model
   *   The model name.
   */
  public function __construct(string $model) {
    $this->model = $model;
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
    $api_key = $config->get('gemini_api_key');

    if (!$api_key) {
      \Drupal::logger('drupalai')->error('Gemini API key not set.');
      return FALSE;
    }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->model . '-latest:generateContent?key=' . $api_key;

    $client = new Client();

    $generation_config = [
      'temperature' => 1,
      'topK' => 0,
      'topP' => 0.95,
      'maxOutputTokens' => 8192,
    ];

    $safety_settings = [
      [
        'category' => 'HARM_CATEGORY_HARASSMENT',
        'threshold' => 'BLOCK_MEDIUM_AND_ABOVE',
      ],
      [
        'category' => 'HARM_CATEGORY_HATE_SPEECH',
        'threshold' => 'BLOCK_MEDIUM_AND_ABOVE',
      ],
      [
        'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
        'threshold' => 'BLOCK_MEDIUM_AND_ABOVE',
      ],
      [
        'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
        'threshold' => 'BLOCK_MEDIUM_AND_ABOVE',
      ],
    ];

    $toolConfig = [
      "function_calling_config" => [
        "mode" => "ANY",
      ],
    ];

    // If the tool choice is not auto, set the allowed function names.
    if ($toolChoice != 'auto') {
      $toolConfig['function_calling_config']['allowed_function_names'] = [$toolChoice];
    }

    try {
      $response = $client->request('POST', $url, [
        'headers' => [
          'Content-Type' => 'application/json',
        ],
        'json' => [
          'contents' => array_merge(
            [
              ['role' => 'model', 'parts' => [['text' => $systemPrompt]]],
            ],
            $messages
          ),
          'tools' => [
            "functionDeclarations" => DrupalAiHelper::getChatTools('gemini'),
          ],
          "tool_config" => $toolConfig,
          'generationConfig' => $generation_config,
          'safetySettings' => $safety_settings,
        ],
      ]);
    }
    catch (\Exception $e) {
      \Drupal::logger('drupalai')->error('Error calling Gemini API: ' . $e->getMessage());
      return FALSE;
    }

    if ($response->getStatusCode() != 200) {
      \Drupal::logger('drupalai')->error('Error calling Gemini API: ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase());
      return FALSE;
    }
    else {
      $data = json_decode($response->getBody()->getContents());

      if (isset($data->candidates[0]->content->parts)) {
        return $data->candidates[0]->content->parts;
      }
      else {
        return FALSE;
      }
    }
  }

  /**
   * Create an image message for Gemini.
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
    // This application does not yet have integration with File API.
    return [];
  }

  /**
   * Create a user input message for Gemini.
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
      "parts" => [["text" => $userInput]],
    ];
  }

  /**
   * Create an assistant message for Gemini.
   *
   * @param string $assistantResponse
   *   The assistant response.
   *
   * @return array
   *   The message array.
   */
  public function createAssistantMessage(string $assistantResponse): array {
    return [
      "role" => "model",
      "parts" => [["text" => $assistantResponse]],
    ];
  }

  /**
   * Create a tool result message for Gemini.
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
      "role" => "model",
      "parts" => [
        [
          "text" => "Tool result for tool use ID $toolUseId: $result",
        ],
      ],
    ];
  }

  /**
   * Create a tool use message for Gemini.
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
      "role" => "model",
      "parts" => [
        [
          "text" => "Tool use for tool ID $toolUseId: $toolName",
        ],
      ],
    ];
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
    return !empty($message->text);
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
    return !empty($message->functionCall);
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

    $tool = new \stdClass();
    $tool->name = $message->functionCall->name;
    $tool->input = $message->functionCall->args;
    $tool->id = 'N/A';
    $tools[] = $tool;

    return $tools;
  }

  /**
   * Get the text from the message.
   *
   * @param object $message
   *   The message object.
   *
   * @return string
   *   The text from the essage.
   */
  public function getTextMessage(object $message): string {
    return $message->text;
  }

}

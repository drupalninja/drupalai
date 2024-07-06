<?php

namespace Drupal\drupalai\Models;

use Drupal\drupalai\DrupalAiChatInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * OpenAI implementation of DrupalAiChat.
 */
class DrupalAiChatOpenAi implements DrupalAiChatInterface {

  /**
   * The model to use.
   *
   * @var string
   */
  private $model;

  /**
   * Constructor.
   */
  public function __construct($model = 'gpt-4o') {
    $this->model = $model;
  }

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

    $contents = [
      "role" => "user",
      "content" => [
        [
          "type" => "text",
          "text" => $prompt,
        ],
      ],
    ];

    try {
      $response = $client->request('POST', $url, [
        'headers' => [
          'Content-Type' => 'application/json',
          'Authorization' => 'Bearer ' . $api_key,
        ],
        'json' => [
          "model" => $this->model,
          "messages" => $contents,
          "temperature" => 1,
          "max_tokens" => 4096,
          "top_p" => 1,
          "frequency_penalty" => 0,
          "presence_penalty" => 0,
        ],
      ]);
    } catch (RequestException $e) {
      \Drupal::logger('drupalai')->error($e->getMessage());
      return FALSE;
    }

    $text = '';
    if ($response->getStatusCode() != 200) {
      \Drupal::logger('drupalai')->error($response->getBody()->getContents());
      return FALSE;
    }
    else {
      $data = $response->getBody()->getContents();
      $json = json_decode($data);

      if (isset($json->choices[0]->message->content)) {
        $text = $json->choices[0]->message->content;
      }
    }

  }

}

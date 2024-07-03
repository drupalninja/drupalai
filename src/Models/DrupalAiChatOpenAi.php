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
   * The contents of the request.
   *
   * @var array
   */
  private $contents = [];

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
   * Prepare the request contents.
   *
   * @param string $prompt
   *   The prompt to send to the API.
   * @param string $image_url
   *   The image URL to send to the API.
   *
   * @return array
   *   The prepared request contents.
   */
  private function prepareRequestContents(string $prompt, string $image_url = ''): array {
    $contents = [
      "role" => "user",
      "content" => [
        [
          "type" => "text",
          "text" => $prompt,
        ],
      ],
    ];

    // Add image URL if provided.
    if ($image_url) {
      $contents['content'][] = [
        "type" => "image_url",
        "image_url" => [
          "url" => $image_url,
        ],
      ];
    }

    return $contents;
  }

  /**
   * Send request to OpenAI API.
   *
   * @return string|bool
   *   The response from the API or FALSE on failure.
   */
  private function sendRequest(): string|bool {
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
          "model" => $this->model,
          "messages" => $this->contents,
          "temperature" => 1,
          "max_tokens" => 4096,
          "top_p" => 1,
          "frequency_penalty" => 0,
          "presence_penalty" => 0,
        ],
      ]);
    }
    catch (RequestException $e) {
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

    return $text;
  }

  /**
   * Get Chat.
   *
   * @param string $prompt
   *   The prompt to send to the API.
   * @param string $image_url
   *   The image URL to send to the API.
   *
   * @return string
   *   The response from the API.
   */
  public function getChat(string $prompt, string $image_url = ''): string {
    $contents = $this->prepareRequestContents($prompt, $image_url);
    $this->contents[] = $contents;

    $text = $this->sendRequest();

    if ($text) {
      // Regex to match everything between <files> and </files>.
      preg_match('/(<files>.*?<\/files>)/s', $text, $matches);
      $text = $matches[1] ?? '';

      if ($text) {
        $this->contents[] = [
          "role" => "assistant",
          "content" => $text,
        ];
      }
    }

    return $text;
  }

  /**
   * Get Image Description.
   *
   * @param string $prompt
   *   The prompt to send to the API.
   * @param string $image_url
   *   The image URL to send to the API.
   *
   * @return string
   *   The response from the API.
   */
  public function getImageDescription(string $prompt, string $image_url = ''): string {
    $contents = $this->prepareRequestContents($prompt, $image_url);
    $this->contents[] = $contents;

    return $this->sendRequest();
  }

}

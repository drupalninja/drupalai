<?php

namespace Drupal\drupalai\Models;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Drupal\drupalai\DrupalAiChatInterface;

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

    // Add image URL if provided.
    if ($image_url) {
      $contents['content'][] = [
        "type" => "image_url",
        "image_url" => [
          "url" => $image_url,
        ],
      ];
    }

    $this->contents[] = $contents;

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
      print $data . "\n";

      $json = json_decode($data);

      if (isset($json->choices[0]->message->content)) {
        $text = $json->choices[0]->message->content;

        // Regex to match everything between <filse> and </files>.
        preg_match('/(<files>.*?<\/files>)/s', $text, $matches);
        $text = $matches[1] ?? '';

        if ($text) {
          $this->contents[] = [
            "role" => "assistant",
            "content" => $text,
          ];
        }
      }
    }

    return $text;
  }

}

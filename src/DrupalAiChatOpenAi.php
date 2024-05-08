<?php

namespace Drupal\drupalai;

use Drupal\Core\Site\Settings;
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
   * Get Chat.
   *
   * @param string $prompt
   *   The prompt to send to the API.
   *
   * @return string
   *   The response from the API.
   */
  public function getChat(string $prompt): string {
    $api_key = Settings::get('openai_api_key');
    $url = 'https://api.openai.com/v1/chat/completions';

    $client = new Client();

    $this->contents[] = [
      "role" => "user",
      "content" => $prompt,
    ];

    try {
      $response = $client->request('POST', $url, [
        'headers' => [
          'Content-Type' => 'application/json',
          'Authorization' => 'Bearer ' . $api_key,
        ],
        'json' => [
          "model" => "gpt-3.5-turbo",
          "messages" => $this->contents,
          "temperature" => 1,
          "max_tokens" => 500,
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

    if ($response->getStatusCode() != 200) {
      \Drupal::logger('drupalai')->error($response->getBody()->getContents());
      return FALSE;
    }
    else {
      $data = $response->getBody()->getContents();
      $content = json_decode($data)->choices[0]->message->content;

      $this->contents[] = [
        "role" => "assistant",
        "content" => $content,
      ];

      return $content;
    }
  }

}

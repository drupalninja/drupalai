<?php

namespace Drupal\drupalai;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Llama3 implementation of DrupalAiChat.
 */
class DrupalAiChatLlama3 implements DrupalAiChatInterface {

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
    $client = new Client();

    $url = 'http://host.docker.internal:11434/api/chat';

    $this->contents[] = [
      "role" => "user",
      "content" => $prompt,
    ];

    try {
      $response = $client->request('POST', $url, [
        'json' => [
          "model" => "llama3",
          "messages" => $this->contents,
          "stream" => FALSE,
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
      $data = json_decode($response->getBody()->getContents());

      if (!empty($data->message->content)) {
        $text = $data->message->content;

        // Regex to match everything between <filse> and </files>.
        preg_match('/(<files>.*?<\/files>)/s', $text, $matches);
        $text = $matches[1];

        if ($text) {
          $this->contents[] = [
            "role" => "assistant",
            "content" => $text,
          ];

          return $text;
        }
      }
    }
  }

}

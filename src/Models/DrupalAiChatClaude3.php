<?php

namespace Drupal\drupalai\Models;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Drupal\drupalai\DrupalAiChatInterface;

/**
 * Claude3 implementation of DrupalAiChat.
 */
class DrupalAiChatClaude3 implements DrupalAiChatInterface {

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
    $config = \Drupal::config('drupalai.settings');
    $api_key = $config->get('claude3_api_key');

    if (!$api_key) {
      \Drupal::logger('drupalai')->error('Claude3 API key not set.');
      return FALSE;
    }

    $url = 'https://api.anthropic.com/v1/messages';

    $client = new Client();

    $this->contents[] = [
      "role" => "user",
      "content" => $prompt,
    ];

    try {
      $response = $client->request('POST', $url, [
        'headers' => [
          'content-type' => 'application/json',
          'anthropic-version' => '2023-06-01',
          'x-api-key' => $api_key,
        ],
        'json' => [
          "model" => "claude-3-opus-20240229",
          "max_tokens" => 4096,
          "messages" => $this->contents,
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
      $text = json_decode($data)->content[0]->text;

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

<?php

namespace Drupal\drupalai\Models;

use Drupal\drupalai\DrupalAiChatInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Claude3 implementation of DrupalAiChat.
 */
class DrupalAiChatClaude3 implements DrupalAiChatInterface {

  /**
   * Get Chat.
   *
   * @param array $messages
   *   The AI messages.
   *
   * @return string
   *   The response from the API.
   */
  public function chat(array $messages): string {
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
          "model" => "claude-3-5-sonnet-20240620",
          "max_tokens" => 4096,
          "messages" => $messages,
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
      return json_decode($data)->content[0]->text;
    }
  }

}

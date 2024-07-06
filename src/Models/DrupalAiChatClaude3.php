<?php

namespace Drupal\drupalai\Models;

use Drupal\drupalai\DrupalAiChatInterface;
use Drupal\drupalai\DrupalAiHelper;
use GuzzleHttp\Client;

/**
 * Claude3 implementation of DrupalAiChat.
 */
class DrupalAiChatClaude3 implements DrupalAiChatInterface {

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
          "model" => "claude-3-haiku-20240307",
          "max_tokens" => 4096,
          'system' => $systemPrompt,
          "messages" => $messages,
          'tools' => DrupalAiHelper::getChatTools(),
          'tool_choice' => [
            'type' => "auto",
          ],
        ],
      ]);
    }
    catch (\Exception $e) {
      \Drupal::logger('drupalai')->error('Error calling Claude API: ' . $e->getMessage());
      return FALSE;
    }

    if ($response->getStatusCode() != 200) {
      \Drupal::logger('drupalai')->error('Error calling Claude API: ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase());
      return FALSE;
    }
    else {
      $data = $response->getBody()->getContents();
      return json_decode($data);
    }
  }

}

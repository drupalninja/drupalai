<?php

namespace Drupal\drupalai\Models;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Drupal\drupalai\DrupalAiChatInterface;

/**
 * Gemini implementation of DrupalAiChat.
 */
class DrupalAiChatGemini implements DrupalAiChatInterface {

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
    $api_key = $config->get('gemini_api_key');

    if (!$api_key) {
      \Drupal::logger('drupalai')->error('Gemini API key not set.');
      return FALSE;
    }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro-latest:generateContent?key=' . $api_key;

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

    $this->contents[] = [
      "role" => "user",
      "parts" => [
        [
          "text" => $prompt,
        ],
      ],
    ];

    try {
      $response = $client->request('POST', $url, [
        'headers' => [
          'Content-Type' => 'application/json',
        ],
        'json' => [
          'contents' => $this->contents,
          'generationConfig' => $generation_config,
          'safetySettings' => $safety_settings,
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
      $text = json_decode($data)->candidates[0]->content->parts[0]->text;

      // Regex to match everything between <filse> and </files>.
      preg_match('/(<files>.*?<\/files>)/s', $text, $matches);
      $text = $matches[1];

      if ($text) {
        $this->contents[] = [
          "role" => "model",
          "parts" => [
            [
              "text" => $text,
            ],
          ],
        ];

        return $text;
      }
    }
  }

}

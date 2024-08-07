<?php

namespace Drupal\drupalai;

use Drupal\drupalai\Models\DrupalAiChatClaude3;
use Drupal\drupalai\Models\DrupalAiChatGemini;
use Drupal\drupalai\Models\DrupalAiChatOpenAi;

/**
 * Drupal AI Factory.
 */
class DrupalAiFactory {

  /**
   * Build DrupalAiChat instance.
   *
   * @param string $model
   *   Model name.
   *
   * @return \Drupal\drupalai\DrupalAiChatOpenAi
   *   DrupalAiChatOpenAi instance.
   */
  public static function build($model): DrupalAiChatInterface {
    $config = \Drupal::config('drupalai.settings');

    if (strstr($model, 'fireworks')) {
      $api_key = $config->get('fireworks_api_key');

      if (!$api_key) {
        throw new \Exception('Fireworks AI key not set.');
      }

      return new DrupalAiChatOpenAi($model, 'fireworks');
    }
    elseif (strpos($model, 'gemini') === 0) {
      $api_key = $config->get('gemini_api_key');

      if (!$api_key) {
        throw new \Exception('Gemini API key not set.');
      }

      return new DrupalAiChatGemini($model);
    }
    elseif (strpos($model, 'claude') === 0) {
      $api_key = $config->get('claude3_api_key');

      if (!$api_key) {
        throw new \Exception('Claude 3 API key not set.');
      }

      return new DrupalAiChatClaude3($model);
    }
    elseif (strpos($model, 'gpt') === 0) {
      $api_key = $config->get('openai_api_key', 'openai');

      if (!$api_key) {
        throw new \Exception('OpenAI key not set.');
      }

      return new DrupalAiChatOpenAi($model);
    }
    elseif (strpos($model, 'llama') === 0) {
      $api_key = $config->get('groq_api_key');

      if (!$api_key) {
        throw new \Exception('Groq key not set.');
      }

      return new DrupalAiChatOpenAi($model, 'groq');
    }

    throw new \Exception('Invalid model');
  }

}

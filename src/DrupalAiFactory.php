<?php

namespace Drupal\drupalai;

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
   * @return \Drupal\drupalai\DrupalAiChat
   *   DrupalAiChat instance.
   */
  public static function build($model): DrupalAiChatInterface {
    $config = \Drupal::config('drupalai.settings');

    if ($model == 'gemini') {
      $api_key = $config->get('gemini_api_key');

      if (!$api_key) {
        throw new \Exception('Gemini API key not set.');
      }

      return new DrupalAiChatGemini();
    }
    elseif ($model == 'llama3') {
      return new DrupalAiChatLlama3();
    }
    elseif ($model == 'openai') {
      $api_key = $config->get('openai_api_key');

      if (!$api_key) {
        throw new \Exception('OpenAI key not set.');
      }

      return new DrupalAiChatOpenAi();
    }

    throw new \Exception('Invalid model');
  }

}

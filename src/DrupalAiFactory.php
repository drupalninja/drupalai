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
    if ($model == 'gemini') {
      return new DrupalAiChatGemini();
    }
    elseif ($model == 'llama3') {
      return new DrupalAiChatLlama3();
    }
    elseif ($model == 'openai') {
      return new DrupalAiChatOpenAi();
    }

    throw new \Exception('Invalid model');
  }

}

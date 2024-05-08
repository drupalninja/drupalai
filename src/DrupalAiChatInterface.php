<?php

namespace Drupal\drupalai;

/**
 * Drupal AI Chat Interface.
 */
interface DrupalAiChatInterface {

  /**
   * Get Chat.
   *
   * @param string $prompt
   *   The AI prompt.
   *
   * @return string
   *   The AI completion response.
   */
  public function getChat(string $prompt): string;

}

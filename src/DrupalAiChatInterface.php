<?php

namespace Drupal\drupalai;

/**
 * Drupal AI Chat Interface.
 */
interface DrupalAiChatInterface {

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
  public function chat(string $systemPrompt, array $messages): object|bool;

}

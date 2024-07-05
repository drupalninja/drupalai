<?php

namespace Drupal\drupalai;

/**
 * Drupal AI Chat Interface.
 */
interface DrupalAiChatInterface {

  /**
   * Chat.
   *
   * @param array $messages
   *   The AI messages.
   *
   * @return string
   *   The AI completion response.
   */
  public function chat(array $messages): string;

}

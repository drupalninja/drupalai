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
   * @param string $toolChoice
   *   The tool choice.
   *
   * @return array|bool
   *   The array of message objects from the API.
   */
  public function chat(string $systemPrompt, array $messages, string $toolChoice = 'auto'): array|bool;

  /**
   * Create an image message for AI chat.
   *
   * @param string $imageBase64
   *   The base64 encoded image data.
   * @param string $userInput
   *   The user input.
   *
   * @return array
   *   The message array.
   */
  public function createImageMessage(string $imageBase64, string $userInput) : array;

  /**
   * Create a user input message for AI chat.
   *
   * @param string $userInput
   *   The user input.
   *
   * @return array
   *   The message array.
   */
  public function createUserInputMessage(string $userInput) : array;

  /**
   * Create an assistant message for AI chat.
   *
   * @param string $assistantResponse
   *   The assistant response.
   *
   * @return array
   *   The message array.
   */
  public function createAssistantMessage(string $assistantResponse) : array;

  /**
   * Create a tool result message for AI chat.
   *
   * @param string $toolUseId
   *   The tool use ID.
   * @param string $result
   *   The tool result.
   *
   * @return array
   *   The message array.
   */
  public function createToolResultMessage(string $toolUseId, string $result) : array;

  /**
   * Create a tool use message for AI chat.
   *
   * @param string $toolUseId
   *   The tool use ID.
   * @param string $toolName
   *   The tool name.
   * @param object $toolInput
   *   The tool input.
   *
   * @return array
   *   The message array.
   */
  public function createToolUseMessage(string $toolUseId, string $toolName, object $toolInput) : array;

  /**
   * Check if the message is a text message.
   *
   * @param object $message
   *   The message object.
   *
   * @return bool
   *   TRUE if the message is a text message, FALSE otherwise.
   */
  public function isTextMessage(object $message): bool;

  /**
   * Check if the message is a tool message.
   *
   * @param object $message
   *   The message object.
   *
   * @return bool
   *   TRUE if the message is a tool message, FALSE otherwise.
   */
  public function isToolMessage(object $message): bool;

  /**
   * Get tool calls from a message.
   *
   * @param object $message
   *   The message object.
   *
   * @return array
   *   The tool calls array.
   */
  public function toolCalls(object $message): array;

}

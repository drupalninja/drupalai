<?php

namespace Drupal\drupalai\Commands;

use Drupal\drupalai\DrupalAiFactory;
use Drupal\drupalai\DrupalAiHelper;
use Drush\Commands\DrushCommands;
use GuzzleHttp\Client;

/**
 * Defines a command to start a chat with an AI Chat.
 *
 * @package Drupal\drupalai\Commands
 */
class DrupalAiChat extends DrushCommands {

  // Define colors.
  const USER_COLOR = "\e[37m";
  const MODEL_COLOR = "\e[34m";
  const TOOL_COLOR = "\e[33m";
  const RESULT_COLOR = "\e[32m";

  /**
   * The conversation history.
   *
   * @var array
   */
  protected $conversationHistory = [];

  /**
   * The automode status.
   *
   * @var bool
   */
  protected $automode = FALSE;

  /**
   * The system prompt.
   *
   * @var string
   */
  protected $systemPrompt;

  /**
   * The continuation exit phrase.
   *
   * @var string
   */
  protected $continuationExitPhrase;

  /**
   * The maximum continuation iterations.
   *
   * @var int
   */
  protected $maxContinuationIterations;

  /**
   * The AI model.
   *
   * @var \Drupal\drupalai\DrupalAiChatInterface
   */
  protected $model;

  /**
   * Constructs a new DrupalAiChat object.
   */
  public function __construct() {
    $this->continuationExitPhrase = 'AUTOMODE_COMPLETE';
    $this->maxContinuationIterations = 25;
    $this->systemPrompt = drupalai_get_prompt('chat');
  }

  /**
   * Command to start chat.
   *
   * @command drupaai:chatStart
   * @aliases ai-chat
   * @usage drupaai:chatStart
   *   Starts the chat with the Chat AI.
   */
  public function chatStart() {
    $this->printColored("Welcome to DrupalAI Chat with Image Support!", self::MODEL_COLOR, FALSE);
    $this->printColored("Type 'exit' to end the conversation.", self::MODEL_COLOR, FALSE);
    $this->printColored("Type 'image' to include an image in your message.", self::MODEL_COLOR, FALSE);
    $this->printColored("Type 'automode [number]' to enter Autonomous mode with a specific number of iterations.", self::MODEL_COLOR, FALSE);
    $this->printColored("While in automode, press Ctrl+C at any time to exit the automode to return to regular chat.", self::MODEL_COLOR, FALSE);

    $this->model = DrupalAiFactory::build('claude3');

    while (TRUE) {
      $userInput = $this->io()->ask(self::USER_COLOR . "You");

      if (trim($userInput) == '') {
        // Tell the user to enter something.
        $this->printColored("Please enter a message.", self::MODEL_COLOR);
        continue;
      }

      if (strtolower($userInput) == 'exit') {
        $this->printColored("Thank you for chatting. Goodbye!", self::MODEL_COLOR);
        break;
      }

      if (strtolower($userInput) == 'image') {
        $imagePath = $this->io()->ask(self::USER_COLOR . "Drag and drop your image here");
        $imagePath = str_replace("'", "", $imagePath);

        if (file_exists($imagePath)) {
          $userInput = $this->io()->ask(self::USER_COLOR . "You (prompt for image)");
          [$response] = $this->chatWithModel($userInput, $imagePath);
          $this->processAndDisplayResponse($response);
        }
        else {
          $this->printColored("Invalid image path. Please try again.", self::MODEL_COLOR);
          continue;
        }
      }
      elseif (strpos(strtolower($userInput), 'automode') === 0) {
        $parts = explode(" ", $userInput);
        $maxIterations = count($parts) > 1 && is_numeric($parts[1]) ? intval($parts[1]) : $this->maxContinuationIterations;
        $this->automode = TRUE;
        $this->printColored("Entering automode with $maxIterations iterations. Press Ctrl+C to exit automode at any time.", self::TOOL_COLOR);
        $this->printColored("Press Ctrl+C at any time to exit the automode loop.", self::TOOL_COLOR);

        $userInput = $this->io()->ask(self::USER_COLOR . "You");
        $iterationCount = 0;

        try {
          while ($this->automode && $iterationCount < $maxIterations) {
            [$response, $exitContinuation] = $this->chatWithModel($userInput, NULL, $iterationCount + 1, $maxIterations);
            $this->processAndDisplayResponse($response);

            if ($exitContinuation || strpos($response, $this->continuationExitPhrase) !== FALSE) {
              $this->printColored("Automode completed.", self::TOOL_COLOR);
              $this->automode = FALSE;
            }
            else {
              $this->printColored("Continuation iteration " . ($iterationCount + 1) . " completed.", self::TOOL_COLOR);
              $this->printColored("Press Ctrl+C to exit automode.", self::TOOL_COLOR);
              $userInput = "Continue with the next step.";
              $iterationCount++;
            }

            if ($iterationCount >= $maxIterations) {
              $this->printColored("Max iterations reached. Exiting automode.", self::TOOL_COLOR);
              $this->automode = FALSE;
            }
          }
        }
        catch (\Exception $e) {
          $this->printColored("\nAutomode interrupted by user. Exiting automode.", self::TOOL_COLOR);
          $this->automode = FALSE;
          if (end($this->conversationHistory)['role'] == "user") {
            // Add the assistant message to the conversation history.
            $this->conversationHistory[] = $this->model->createAssistantMessage("Automode interrupted. How can I assist you further?");
          }
        }
      }
      else {
        [$response] = $this->chatWithModel($userInput);
        $this->processAndDisplayResponse($response);
      }
    }
  }

  /**
   * Searches with Tavily.
   *
   * @param string $query
   *   The query to search with.
   *
   * @return string|null
   *   The search response.
   */
  protected function tavilySearch($query) {
    $config = \Drupal::config('drupalai.settings');

    try {
      $api_key = $config->get('tavily_api_key');
      $url = 'https://api.tavily.com/search';

      $client = new Client();

      $response = $client->request('POST', $url, [
        'headers' => [
          'content-type' => 'application/json',
        ],
        'json' => [
          'api_key' => $api_key,
          'query' => $query,
          'search_depth' => 'basic',
          'include_answer' => FALSE,
          'include_images' => TRUE,
          'include_raw_content' => FALSE,
          'max_results' => 5,
          'include_domains' => [],
          'exclude_domains' => [],
        ],
      ]);

      if ($response->getStatusCode() == 200) {
        return $response->getBody()->getContents();
      }

      return NULL;
    }
    catch (\Exception $e) {
      return "Error performing search: " . $e->getMessage();
    }
  }

  /**
   * Executes a tool.
   *
   * @param string $toolName
   *   The name of the tool to execute.
   * @param object $toolInput
   *   The input of the tool.
   *
   * @return string
   *   The result of the tool execution.
   */
  protected function executeTool($toolName, object $toolInput): string {
    switch ($toolName) {
      case 'create_files':
        $results = [];

        if (is_array($toolInput->files)) {
          foreach ($toolInput->files as $file) {
            $results[] = DrupalAiHelper::createFile($file->path, $file->content ?? '');
          }
          return "\n" . implode("\n", $results);
        }
        else {
          return "Invalid input for create_files tool.";
        }

      case 'write_to_file':
        if (!empty($toolInput->path) && !empty($toolInput->content)) {
          return DrupalAiHelper::writeToFile($toolInput->path, $toolInput->content);
        }
        else {
          return "Invalid input for write_to_file tool.";
        }

      case 'read_file':
        return DrupalAiHelper::readFile($toolInput->path);

      case 'list_files':
        return DrupalAiHelper::listFiles($toolInput->path ?? '.');

      case 'tavily_search':
        return $this->tavilySearch($toolInput->query);

      default:
        return "Unknown tool: $toolName";
    }
  }

  /**
   * Chats with the AI.
   *
   * @param string $userInput
   *   The user input.
   * @param string|null $imagePath
   *   The image path.
   * @param int|null $currentIteration
   *   The current iteration.
   * @param int|null $maxIterations
   *   The maximum iterations.
   *
   * @return array
   *   The assistant response and the exit continuation status.
   */
  protected function chatWithModel($userInput, $imagePath = NULL, $currentIteration = NULL, $maxIterations = NULL) {
    if ($imagePath) {
      $this->printColored("Processing image at path: $imagePath", self::TOOL_COLOR);
      $imageBase64 = DrupalAiHelper::encodeImageToBase64($imagePath);
      if (strpos($imageBase64, "Error") === 0) {
        $this->printColored("Error encoding image: $imageBase64", self::TOOL_COLOR);
        return [
          "I'm sorry, there was an error processing the image. Please try again.",
          FALSE,
        ];
      }

      // Add the image message to the conversation history.
      $this->conversationHistory[] = $this->model->createImageMessage($imageBase64, $userInput);

      $this->printColored("Image message added to conversation history", self::TOOL_COLOR);
    }
    else {
      // Add the user input message to the conversation history.
      $this->conversationHistory[] = $this->model->createUserInputMessage($userInput);
    }

    $systemPrompt = $this->updateSystemPrompt($currentIteration, $maxIterations);
    $messages = $this->conversationHistory;

    $data = $this->model->chat($systemPrompt, $messages);

    if (!$data) {
      return [
        "I'm sorry, there was an error processing the message. Please try again.",
        FALSE,
      ];
    }

    $assistantResponse = "";
    $exitContinuation = FALSE;

    foreach ($data->content as $contentBlock) {
      if ($contentBlock->type == "text") {
        $assistantResponse .= $contentBlock->text . " ";
        if (strpos($contentBlock->text, $this->continuationExitPhrase) !== FALSE) {
          $exitContinuation = TRUE;
        }
      }
      elseif ($contentBlock->type == "tool_use") {
        $toolName = $contentBlock->name;
        $toolInput = $contentBlock->input;
        $toolUseId = $contentBlock->id;

        $this->printColored("Tool Used: $toolName", self::TOOL_COLOR);

        $result = $this->executeTool($toolName, $toolInput);

        $this->printColored("Tool Result: $result", self::RESULT_COLOR);

        // Add the tool use message to the conversation history.
        $this->conversationHistory[] = $this->model->createToolUseMessage($toolUseId, $toolName, $toolInput);

        // Add the tool result message to the conversation history.
        $this->conversationHistory[] = $this->model->createToolResultMessage($toolUseId, $result);

        $messages = $this->conversationHistory;
        $data = $this->model->chat($systemPrompt, $messages);

        if (!$data) {
          return [
            "I'm sorry, there was an error processing the message. Please try again.",
            FALSE,
          ];
        }

        foreach ($data->content as $toolContentBlock) {
          if ($toolContentBlock->type == "text") {
            $assistantResponse .= $toolContentBlock->text . " ";
          }
        }
      }
    }

    if ($assistantResponse) {
      // Add the assistant message to the conversation history.
      $this->conversationHistory[] = $this->model->createAssistantMessage($assistantResponse);
    }

    return [$assistantResponse, $exitContinuation];
  }

  /**
   * Processes and displays a response.
   *
   * @param string $response
   *   The response to process and display.
   */
  protected function processAndDisplayResponse($response): void {
    if (strpos($response, "Error") === 0 || strpos($response, "I'm sorry") === 0) {
      $this->printColored($response, self::TOOL_COLOR);
    }
    else {
      if (strpos($response, "```") !== FALSE) {
        $parts = explode("```", $response);
        foreach ($parts as $i => $part) {
          if ($i % 2 == 0) {
            $this->printColored($part, self::MODEL_COLOR);
          }
          else {
            $lines = explode("\n", $part);
            $code = implode("\n", array_slice($lines, 1));
            if (trim($code)) {
              $this->printColored("Code:\n$code", self::RESULT_COLOR);
            }
            else {
              $this->printColored($part, self::MODEL_COLOR);
            }
          }
        }
      }
      else {
        $this->printColored($response, self::MODEL_COLOR);
      }
    }
  }

  /**
   * Updates the system prompt.
   *
   * @param int|null $currentIteration
   *   The current iteration.
   * @param int|null $maxIterations
   *   The maximum iterations.
   *
   * @return string
   *   The updated system prompt.
   */
  protected function updateSystemPrompt($currentIteration = NULL, $maxIterations = NULL): string {
    $automodeStatus = $this->automode ? "You are currently in automode." : "You are not in automode.";
    $iterationInfo = "";

    if ($currentIteration !== NULL && $maxIterations !== NULL) {
      $iterationInfo = sprintf("You are currently on iteration %d out of %d in automode.", $currentIteration, $maxIterations);
    }

    return str_replace(['{automode_status}', '{iteration_info}'], [$automodeStatus, $iterationInfo], $this->systemPrompt);
  }

  /**
   * Prints colored text.
   *
   * @param string $text
   *   The text to print.
   * @param string $color
   *   The color of the text.
   * @param bool $newLine
   *   Whether to add a new line.
   */
  protected function printColored($text, $color, $newLine = TRUE): void {
    $this->output()->writeln($color . $text . ($newLine ? "\n" : " "));
  }

}

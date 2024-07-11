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
   * AI model name.
   *
   * @var string
   */
  protected $modelName;

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
   * @command drupalai:chatStart
   * @aliases ai-chat
   * @usage drupalai:chatStart
   *   Starts the chat with the Chat AI.
   */
  public function chatStart() {
    $this->printColored("Welcome to DrupalAI Chat with Image Support!", self::MODEL_COLOR, FALSE);
    $this->printColored("Type 'exit' to end the conversation.", self::MODEL_COLOR, FALSE);
    $this->printColored("Type 'image' to include an image in your message.", self::MODEL_COLOR, FALSE);
    $this->printColored("Type 'automode [number]' to enter Autonomous mode with a specific number of iterations.", self::MODEL_COLOR, FALSE);
    $this->printColored("Type 'scrape' to scrape a website page.", self::MODEL_COLOR, FALSE);
    $this->printColored("While in automode, press Ctrl+C at any time to exit the automode to return to regular chat.", self::MODEL_COLOR, FALSE);

    // Prompt user for the AI model to use.
    $this->modelName = $this->io()->choice('Select the AI model to use', DrupalAiHelper::getModels(), 0);
    $this->model = DrupalAiFactory::build($this->modelName);

    while (TRUE) {
      $userInput = $this->io()->ask(self::USER_COLOR . "You");

      if (trim($userInput) == '') {
        // Tell the user to enter something.
        $this->printColored("Please enter a message.", self::MODEL_COLOR);
        continue;
      }

      if (strtolower($userInput) == 'exit') {
        $this->printColored("Thank you for chatting. Goodbye!", self::MODEL_COLOR, FALSE);
        break;
      }

      if (strtolower($userInput) == 'scrape') {
        $url = $this->io()->ask(self::USER_COLOR . "Enter URL to scrape here");

        // Check if the URL is valid.
        if (filter_var($url, FILTER_VALIDATE_URL)) {
          $this->printColored("Scraping URL: $url", self::TOOL_COLOR);
          $html = DrupalAiHelper::scrapeUrl($url);

          // Check if the scraping was successful.
          if (strpos($html, "Error") === 0) {
            $this->printColored($html, self::TOOL_COLOR);
            continue;
          }

          $userInput = "Describe this web page: $html";

          [$response] = $this->chatWithModel($userInput);
          $this->processAndDisplayResponse($response);
        }
        else {
          $this->printColored("Invalid URL. Please try again.", self::MODEL_COLOR);
          continue;
        }
      }
      elseif (strtolower($userInput) == 'image') {
        if ($this->modelName == 'gpt-3.5-turbo-0125' || $this->modelName == 'gemini') {
          $this->printColored("Image support supported for this model.", self::MODEL_COLOR);
          continue;
        }

        $imageUrl = $this->io()->ask(self::USER_COLOR . "Enter URL for image here");

        // Check if the image URL is valid.
        if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
          $userInput = $this->io()->ask(self::USER_COLOR . "You (prompt for image)");
          [$response] = $this->chatWithModel($userInput, $imageUrl);
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

        if (isset($toolInput->files) && is_array($toolInput->files)) {
          foreach ($toolInput->files as $file) {
            $content = trim(stripcslashes($file->content));
            $results[] = DrupalAiHelper::createFile($file->path, $content ?? '');
          }
          return "\n" . implode("\n", $results);
        }
        else {
          return "Invalid input for create_files tool.";
        }

      case 'write_to_file':
        if (!empty($toolInput->path) && !empty($toolInput->content)) {
          $content = trim(stripcslashes($toolInput->content));
          return DrupalAiHelper::writeToFile($toolInput->path, $content);
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
   * @param string|null $imageUrl
   *   The image path.
   * @param int|null $currentIteration
   *   The current iteration.
   * @param int|null $maxIterations
   *   The maximum iterations.
   *
   * @return array
   *   The assistant response and the exit continuation status.
   */
  protected function chatWithModel($userInput, $imageUrl = NULL, $currentIteration = NULL, $maxIterations = NULL) {
    if ($imageUrl) {
      $this->printColored("Processing image at Url: $imageUrl", self::TOOL_COLOR);

      // Encode the image to base64 if this is not a GPT model.
      if ($this->modelName == 'gpt-4o') {
        // Add the image message to the conversation history.
        $this->conversationHistory[] = $this->model->createImageMessage($imageUrl, $userInput);
      }
      else {
        $imageBase64 = DrupalAiHelper::encodeImageToBase64($imageUrl);
        if (strpos($imageBase64, "Error") === 0) {
          $this->printColored("Error encoding image: $imageBase64", self::TOOL_COLOR);
          return [
            "I'm sorry, there was an error processing the image. Please try again.",
            FALSE,
          ];
        }

        // Add the image message to the conversation history.
        $this->conversationHistory[] = $this->model->createImageMessage($imageBase64, $userInput);
      }

      $this->printColored("Image message added to conversation history", self::TOOL_COLOR);
    }
    else {
      $tool = NULL;

      // Check if the user input is a command to write to a file.
      if (preg_match('/^(add|edit|update|change|modify)\s/i', $userInput)) {
        $userInput .= " (write_to_file)";
        $tool = 'write_to_file';
      }

      // Add the user input message to the conversation history.
      $this->conversationHistory[] = $this->model->createUserInputMessage($userInput, $tool);
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

    foreach ($data as $message) {
      if ($this->model->isToolMessage($message)) {
        foreach ($this->model->toolCalls($message) as $toolCall) {
          $toolName = $toolCall->name;
          $toolInput = $toolCall->input;
          $toolId = $toolCall->id;

          $this->printColored("Tool Used: $toolName", self::TOOL_COLOR);

          $result = $this->executeTool($toolName, $toolInput);
          $resultExcerpt = strlen($result) > 100 ? substr($result, 0, 300) . "..." : $result;

          $this->printColored("Tool Result: $resultExcerpt", self::RESULT_COLOR, FALSE);

          // Add the tool use message to the conversation history.
          $this->conversationHistory[] = $this->model->createToolUseMessage($toolId, $toolName, $toolInput);

          // Add the tool result message to the conversation history.
          $this->conversationHistory[] = $this->model->createToolResultMessage($toolId, $result);
        }

        $messages = $this->conversationHistory;
        $data = $this->model->chat($systemPrompt, $messages);

        if (!$data) {
          return [
            "I'm sorry, there was an error processing the message. Please try again.",
            FALSE,
          ];
        }

        foreach ($data as $message) {
          if ($this->model->isTextMessage($message)) {
            $assistantResponse = $this->model->getTextMessage($message);
          }
        }
      }
      else {
        $text = $this->model->getTextMessage($message);
        $assistantResponse .= $text;
        if (strpos($text, $this->continuationExitPhrase) !== FALSE) {
          $exitContinuation = TRUE;
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
            $this->printColored($part, self::MODEL_COLOR, FALSE);
          }
          else {
            $lines = explode("\n", $part);
            $code = implode("\n", array_slice($lines, 1));
            if (trim($code)) {
              $this->printColored("Code:\n$code", self::RESULT_COLOR, FALSE);
            }
            else {
              $this->printColored($part, self::MODEL_COLOR, FALSE);
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

    $themeHandler = \Drupal::service('theme_handler');
    $themePath = $themeHandler->getTheme($themeHandler->getDefault())->getPath();

    return str_replace(
      [
        '{automode_status}',
        '{iteration_info}',
        '{active_theme_folder}',
      ],
      [
        $automodeStatus,
        $iterationInfo,
        $themePath,
      ],
      $this->systemPrompt);
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

<?php

namespace Drupal\my_module\Commands;

use Drush\Commands\DrushCommands;
use GuzzleHttp\Client;

/**
 * Defines a command to start a chat with the Claude AI.
 *
 * @package Drupal\my_module\Commands
 */
class DrupalAiChat extends DrushCommands {

  // Define colors.
  const USER_COLOR = "\e[37m";
  const CLAUDE_COLOR = "\e[34m";
  const TOOL_COLOR = "\e[33m";
  const RESULT_COLOR = "\e[32m";

  /**
   * The Anthropic client.
   */
  protected $client;

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
   * The tools available for use.
   *
   * @var array
   */
  protected $tools = [
    [
      "name" => "create_folder",
      "description" => "Create a new folder at the specified path. Use this when you need to create a new directory in the project structure.",
      "input_schema" => [
        "type" => "object",
        "properties" => [
          "path" => [
            "type" => "string",
            "description" => "The path where the folder should be created",
          ]
        ],
        "required" => [
          "path",
        ],
      ],
    ],
    [
      "name" => "create_file",
      "description" => "Create a new file at the specified path with optional content. Use this when you need to create a new file in the project structure.",
      "input_schema" => [
        "type" => "object",
        "properties" => [
          "path" => [
            "type" => "string",
            "description" => "The path where the file should be created",
          ],
          "content" => [
            "type" => "string",
            "description" => "The initial content of the file (optional)",
          ]
        ],
        "required" => [
          "path",
        ],
      ],
    ],
    [
      "name" => "write_to_file",
      "description" => "Write content to a file at the specified path. If the file exists, only the necessary changes will be applied. If the file doesn't exist, it will be created. Always provide the full intended content of the file.",
      "input_schema" => [
        "type" => "object",
        "properties" => [
          "path" => [
            "type" => "string",
            "description" => "The path of the file to write to",
          ],
          "content" => [
            "type" => "string",
            "description" => "The full content to write to the file",
          ],
        ],
        "required" => [
          "path",
          "content",
        ],
      ],
    ],
    [
      "name" => "read_file",
      "description" => "Read the contents of a file at the specified path. Use this when you need to examine the contents of an existing file.",
      "input_schema" => [
        "type" => "object",
        "properties" => [
          "path" => [
            "type" => "string",
            "description" => "The path of the file to read",
          ],
        ],
        "required" => [
          "path",
        ],
      ],
    ],
    [
      "name" => "list_files",
      "description" => "List all files and directories in the root folder where the script is running. Use this when you need to see the contents of the current directory.",
      "input_schema" => [
        "type" => "object",
        "properties" => [
          "path" => [
            "type" => "string",
            "description" => "The path of the folder to list (default: current directory)",
          ],
        ],
      ],
    ],
    [
      "name" => "tavily_search",
      "description" => "Perform a web search using Tavily API to get up-to-date information or additional context. Use this when you need current information or feel a search could provide a better answer.",
      "input_schema" => [
        "type" => "object",
        "properties" => [
          "query" => [
            "type" => "string",
            "description" => "The search query",
          ],
        ],
        "required" => [
          "query",
        ],
      ],
    ],
  ];

  /**
   * Constructs a new DrupalAiChat object.
   */
  public function __construct() {
    // Placeholder values for important constants and settings.
    $this->continuationExitPhrase = 'AUTOMODE_COMPLETE';
    $this->maxContinuationIterations = 25;

    // System prompt.
    $this->systemPrompt = <<<EOT
    (Your system prompt text here)
    EOT;
  }

  /**
   * Command to start chat.
   *
   * @command chat:start
   * @alias cs
   * @usage chat:start
   *   Starts the chat with the Claude AI.
   */
  public function chatStart() {
    $this->printColored("Welcome to the Claude-3.5-Sonnet Engineer Chat with Image Support!", CLAUDE_COLOR);
    $this->printColored("Type 'exit' to end the conversation.", CLAUDE_COLOR);
    $this->printColored("Type 'image' to include an image in your message.", CLAUDE_COLOR);
    $this->printColored("Type 'automode [number]' to enter Autonomous mode with a specific number of iterations.", CLAUDE_COLOR);
    $this->printColored("While in automode, press Ctrl+C at any time to exit the automode to return to regular chat.", CLAUDE_COLOR);

    while (TRUE) {
      echo "\n" . USER_COLOR . "You: ";
      $userInput = trim(fgets(STDIN));

      if (strtolower($userInput) == 'exit') {
        $this->printColored("Thank you for chatting. Goodbye!", CLAUDE_COLOR);
        break;
      }

      if (strtolower($userInput) == 'image') {
        echo USER_COLOR . "Drag and drop your image here: ";
        $imagePath = trim(fgets(STDIN));
        $imagePath = str_replace("'", "", $imagePath);

        if (file_exists($imagePath)) {
          echo USER_COLOR . "You (prompt for image): ";
          $userInput = trim(fgets(STDIN));
          [$response] = $this->chatWithClaude($userInput, $imagePath);
          $this->processAndDisplayResponse($response);
        }
        else {
          $this->printColored("Invalid image path. Please try again.", CLAUDE_COLOR);
          continue;
        }
      }
      elseif (strpos(strtolower($userInput), 'automode') === 0) {
        $parts = explode(" ", $userInput);
        $maxIterations = count($parts) > 1 && is_numeric($parts[1]) ? intval($parts[1]) : $this->maxContinuationIterations;
        $this->automode = TRUE;
        $this->printColored("Entering automode with $maxIterations iterations. Press Ctrl+C to exit automode at any time.", TOOL_COLOR);
        $this->printColored("Press Ctrl+C at any time to exit the automode loop.", TOOL_COLOR);

        echo "\n" . USER_COLOR . "You: ";
        $userInput = trim(fgets(STDIN));
        $iterationCount = 0;

        try {
          while ($this->automode && $iterationCount < $maxIterations) {
            [$response, $exitContinuation] = $this->chatWithClaude($userInput, NULL, $iterationCount + 1, $maxIterations);
            $this->processAndDisplayResponse($response);

            if ($exitContinuation || strpos($response, $this->continuationExitPhrase) !== FALSE) {
              $this->printColored("Automode completed.", TOOL_COLOR);
              $this->automode = FALSE;
            }
            else {
              $this->printColored("Continuation iteration " . ($iterationCount + 1) . " completed.", TOOL_COLOR);
              $this->printColored("Press Ctrl+C to exit automode.", TOOL_COLOR);
              $userInput = "Continue with the next step.";
              $iterationCount++;
            }

            if ($iterationCount >= $maxIterations) {
              $this->printColored("Max iterations reached. Exiting automode.", TOOL_COLOR);
              $this->automode = FALSE;
            }
          }
        }
        catch (\Exception $e) {
          $this->printColored("\nAutomode interrupted by user. Exiting automode.", TOOL_COLOR);
          $this->automode = FALSE;
          if (end($this->conversationHistory)['role'] == "user") {
            $this->conversationHistory[] = ["role" => "assistant", "content" => "Automode interrupted. How can I assist you further?"];
          }
        }
      }
      else {
        [$response] = $this->chatWithClaude($userInput);
        $this->processAndDisplayResponse($response);
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
  protected function updateSystemPrompt($currentIteration = NULL, $maxIterations = NULL) {
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
   */
  protected function printColored($text, $color) {
    $this->output()->writeln($color . $text);
  }

  /**
   * Creates a folder.
   *
   * @param string $path
   *   The path of the folder to create.
   *
   * @return string
   *   The result of the folder creation.
   */
  protected function createFolder($path) {
    try {
      if (!file_exists($path)) {
        mkdir($path, 0777, TRUE);
      }
      return "Folder created: $path";
    }
    catch (\Exception $e) {
      return "Error creating folder: " . $e->getMessage();
    }
  }

  /**
   * Creates a file.
   *
   * @param string $path
   *   The path of the file to create.
   * @param string $content
   *   The content of the file.
   *
   * @return string
   *   The result of the file creation.
   */
  protected function createFile($path, $content = "") {
    try {
      file_put_contents($path, $content);
      return "File created: $path";
    }
    catch (\Exception $e) {
      return "Error creating file: " . $e->getMessage();
    }
  }

  /**
   * Generates and applies a diff to a file.
   *
   * @param string $originalContent
   *   The original content of the file.
   * @param string $newContent
   *   The new content of the file.
   * @param string $path
   *   The path of the file.
   *
   * @return string
   *   The result of the diff generation and application.
   */
  protected function generateAndApplyDiff($originalContent, $newContent, $path) {
    $originalLines = explode("\n", $originalContent);
    $newLines = explode("\n", $newContent);
    $diff = [];

    $maxLines = max(count($originalLines), count($newLines));
    for ($i = 0; $i < $maxLines; $i++) {
      if (!isset($originalLines[$i]) || !isset($newLines[$i]) || $originalLines[$i] !== $newLines[$i]) {
        $diff[] = $originalLines[$i] ?? '---' . PHP_EOL . $newLines[$i] ?? '+++';
      }
    }

    if (empty($diff)) {
      return "No changes detected.";
    }

    try {
      file_put_contents($path, $newContent);
      return "Changes applied to $path:\n" . implode('', $diff);
    }
    catch (\Exception $e) {
      return "Error applying changes: " . $e->getMessage();
    }
  }

  /**
   * Writes to a file.
   *
   * @param string $path
   *   The path of the file to write to.
   * @param string $content
   *   The content to write to the file.
   *
   * @return string
   *   The result of the file writing.
   */
  protected function writeToFile($path, $content) {
    try {
      if (file_exists($path)) {
        $originalContent = file_get_contents($path);
        $result = $this->generateAndApplyDiff($originalContent, $content, $path);
      }
      else {
        file_put_contents($path, $content);
        $result = "New file created and content written to: $path";
      }
      return $result;
    }
    catch (\Exception $e) {
      return "Error writing to file: " . $e->getMessage();
    }
  }

  /**
   * Reads a file.
   *
   * @param string $path
   *   The path of the file to read.
   *
   * @return string
   *   The content of the file.
   */
  protected function readFile($path) {
    try {
      return file_get_contents($path);
    }
    catch (\Exception $e) {
      return "Error reading file: " . $e->getMessage();
    }
  }

  /**
   * Lists files in a directory.
   *
   * @param string $path
   *   The path of the directory to list files in.
   *
   * @return string
   *   The list of files.
   */
  protected function listFiles($path = ".") {
    try {
      $files = array_diff(scandir($path), ['.', '..']);
      return implode("\n", $files);
    }
    catch (\Exception $e) {
      return "Error listing files: " . $e->getMessage();
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
    try {
      $api_key = 'your-actual-api-key';
      $url = 'https://api.tavily.com/search';

      $client = new Client();

      $response = $client->request('POST', $url, [
        'api_key' => $api_key,
        'query' => $query,
        'search_depth' => 'basic',
        'include_answer' => FALSE,
        'include_images' => TRUE,
        'include_raw_content' => FALSE,
        'max_results' => 5,
        'include_domains' => [],
        'exclude_domains' => [],
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
   * @param array $toolInput
   *   The input of the tool.
   *
   * @return string
   *   The result of the tool execution.
   */
  protected function executeTool($toolName, array $toolInput) {
    switch ($toolName) {
      case 'create_folder':
        return $this->createFolder($toolInput['path']);

      case 'create_file':
        return $this->createFile($toolInput['path'], $toolInput['content'] ?? '');

      case 'write_to_file':
        return $this->writeToFile($toolInput['path'], $toolInput['content']);

      case 'read_file':
        return $this->readFile($toolInput['path']);

      case 'list_files':
        return $this->listFiles($toolInput['path'] ?? '.');

      case 'tavily_search':
        return $this->tavilySearch($toolInput['query']);

      default:
        return "Unknown tool: $toolName";
    }
  }

  /**
   * Encodes an image to base64.
   *
   * @param string $imagePath
   *   The path of the image to encode.
   *
   * @return string
   *   The base64 encoded image.
   */
  protected function encodeImageToBase64($imagePath) {
    try {
      $img = file_get_contents($imagePath);
      $base64 = base64_encode($img);
      return $base64;
    }
    catch (\Exception $e) {
      return "Error encoding image: " . $e->getMessage();
    }
  }

  /**
   * Chats with the Claude AI.
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
  protected function chatWithClaude($userInput, $imagePath = NULL, $currentIteration = NULL, $maxIterations = NULL) {
    $currentConversation = [];

    if ($imagePath) {
      $this->printColored("Processing image at path: $imagePath", TOOL_COLOR);
      $imageBase64 = $this->encodeImageToBase64($imagePath);
      if (strpos($imageBase64, "Error") === 0) {
        $this->printColored("Error encoding image: $imageBase64", TOOL_COLOR);
        return [
          "I'm sorry, there was an error processing the image. Please try again.",
          FALSE,
        ];
      }

      $imageMessage = [
        "role" => "user",
        "content" => [
          [
            "type" => "image",
            "source" => [
              "type" => "base64",
              "media_type" => "image/jpeg",
              "data" => $imageBase64,
            ]
          ],
          [
            "type" => "text",
            "text" => "User input for image: $userInput",
          ]
        ]
      ];

      $currentConversation[] = $imageMessage;
      $this->printColored("Image message added to conversation history", TOOL_COLOR);
    }
    else {
      $currentConversation[] = ["role" => "user", "content" => $userInput];
    }

    $messages = array_merge($this->conversationHistory, $currentConversation);

    $config = \Drupal::config('drupalai.settings');

    $url = 'https://api.anthropic.com/v1/messages';
    $api_key = $config->get('claude3_api_key');

    if (!$api_key) {
      \Drupal::logger('drupalai')->error('Claude3 API key not set.');
      return FALSE;
    }
    $client = new Client();

    try {
      $response = $client->request('POST', $url, [
        'headers' => [
          'content-type' => 'application/json',
          'anthropic-version' => '2023-06-01',
          'x-api-key' => $api_key,
        ],
        'json' => [
          "model" => "claude-3-5-sonnet-20240620",
          "max_tokens" => 4096,
          'system' => $this->updateSystemPrompt($currentIteration, $maxIterations),
          "messages" => $messages,
          'tools' => $this->tools,
          'tool_choice' => [
            'type' => "auto",
          ],
        ],
      ]);
    }
    catch (\Exception $e) {
      $this->printColored("Error calling Claude API: " . $e->getMessage(), TOOL_COLOR);
      return [
        "I'm sorry, there was an error communicating with the AI. Please try again.",
        FALSE,
      ];
    }

    $assistantResponse = "";
    $exitContinuation = FALSE;

    foreach ($response->content as $contentBlock) {
      if ($contentBlock['type'] == "text") {
        $assistantResponse .= $contentBlock['text'];
        if (strpos($contentBlock['text'], $this->continuationExitPhrase) !== FALSE) {
          $exitContinuation = TRUE;
        }
      }
      elseif ($contentBlock['type'] == "tool_use") {
        $toolName = $contentBlock['name'];
        $toolInput = $contentBlock['input'];
        $toolUseId = $contentBlock['id'];

        $this->printColored("Tool Used: $toolName", TOOL_COLOR);
        $this->printColored("Tool Input: " . json_encode($toolInput), TOOL_COLOR);

        $result = $this->executeTool($toolName, $toolInput);

        $this->printColored("Tool Result: $result", RESULT_COLOR);

        $currentConversation[] = [
          "role" => "assistant",
          "content" => [
            [
              "type" => "tool_use",
              "id" => $toolUseId,
              "name" => $toolName,
              "input" => $toolInput,
            ],
          ],
        ];

        $currentConversation[] = [
          "role" => "user",
          "content" => [
            [
              "type" => "tool_result",
              "tool_use_id" => $toolUseId,
              "content" => $result,
            ],
          ],
        ];
        $messages = array_merge($this->conversationHistory, $currentConversation);

        $config = \Drupal::config('drupalai.settings');

        $url = 'https://api.anthropic.com/v1/messages';
        $api_key = $config->get('claude3_api_key');

        if (!$api_key) {
          \Drupal::logger('drupalai')->error('Claude3 API key not set.');
          return FALSE;
        }
        $client = new Client();

        try {
          $toolResponse = $client->request('POST', $url, [
            'headers' => [
              'content-type' => 'application/json',
              'anthropic-version' => '2023-06-01',
              'x-api-key' => $api_key,
            ],
            'json' => [
              "model" => "claude-3-5-sonnet-20240620",
              "max_tokens" => 4096,
              'system' => $this->updateSystemPrompt($currentIteration, $maxIterations),
              "messages" => $messages,
              'tools' => $this->tools,
              'tool_choice' => [
                'type' => "auto",
              ],
            ],
          ]);

          foreach ($toolResponse->content as $toolContentBlock) {
            if ($toolContentBlock['type'] == "text") {
              $assistantResponse .= $toolContentBlock['text'];
            }
          }
        }
        catch (\Exception $e) {
          $this->printColored("Error in tool response: " . $e->getMessage(), TOOL_COLOR);
          $assistantResponse .= "\nI encountered an error while processing the tool result. Please try again.";
        }
      }
    }

    if ($assistantResponse) {
      $currentConversation[] = [
        "role" => "assistant",
        "content" => $assistantResponse,
      ];
    }

    $this->conversationHistory = array_merge($this->conversationHistory, [["role" => "assistant", "content" => $assistantResponse]]);
    return [$assistantResponse, $exitContinuation];
  }

  /**
   * Processes and displays a response.
   *
   * @param string $response
   *   The response to process and display.
   */
  protected function processAndDisplayResponse($response) {
    if (strpos($response, "Error") === 0 || strpos($response, "I'm sorry") === 0) {
      $this->printColored($response, TOOL_COLOR);
    }
    else {
      if (strpos($response, "```") !== FALSE) {
        $parts = explode("```", $response);
        foreach ($parts as $i => $part) {
          if ($i % 2 == 0) {
            $this->printColored($part, CLAUDE_COLOR);
          }
          else {
            $lines = explode("\n", $part);
            $code = implode("\n", array_slice($lines, 1));
            if ($code) {
              $this->printColored("Code:\n$code", CLAUDE_COLOR);
            }
            else {
              $this->printColored($part, CLAUDE_COLOR);
            }
          }
        }
      }
      else {
        $this->printColored($response, CLAUDE_COLOR);
      }
    }
  }

}

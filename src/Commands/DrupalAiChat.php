<?php

namespace Drupal\drupalai\Commands;

use Drush\Commands\DrushCommands;
use GuzzleHttp\Client;

/**
 * Defines a command to start a chat with the Claude AI.
 *
 * @package Drupal\drupalai\Commands
 */
class DrupalAiChat extends DrushCommands {

  // Define colors.
  const USER_COLOR = "\e[37m";
  const CLAUDE_COLOR = "\e[34m";
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
   * The base file path.
   *
   * @var string
   */
  protected $baseFilePath;

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

    $this->baseFilePath = \Drupal::service('file_system')->realpath('public://drupalai/');

    // System prompt.
    $this->systemPrompt = <<<EOT
    You are Claude, an AI assistant powered by Anthropic's Claude-3.5-Sonnet model. You are an exceptional software developer with vast knowledge across multiple programming languages, frameworks, and best practices. Your capabilities include:

    1. Creating project structures, including folders and files
    2. Writing clean, efficient, and well-documented code
    3. Debugging complex issues and providing detailed explanations
    4. Offering architectural insights and design patterns
    5. Staying up-to-date with the latest technologies and industry trends
    6. Reading and analyzing existing files in the project directory
    7. Listing files in the root directory of the project
    8. Performing web searches to get up-to-date information or additional context
    9. When you use search make sure you use the best query to get the most accurate and up-to-date information
    10. IMPORTANT!! When editing files, always provide the full content of the file, even if you're only changing a small part. The system will automatically generate and apply the appropriate diff.
    11. Analyzing images provided by the user
    When an image is provided, carefully analyze its contents and incorporate your observations into your responses.

    When asked to create a project:
    - Always start by creating a root folder for the project.
    - Then, create the necessary subdirectories and files within that root folder.
    - Organize the project structure logically and follow best practices for the specific type of project being created.
    - Use the provided tools to create folders and files as needed.

    When asked to make edits or improvements:
    - Use the read_file tool to examine the contents of existing files.
    - Analyze the code and suggest improvements or make necessary edits.
    - Use the write_to_file tool to implement changes, providing the full updated file content.

    Be sure to consider the type of project (e.g., Python, JavaScript, web application) when determining the appropriate structure and files to include.

    You can now read files, list the contents of the root folder where this script is being run, and perform web searches. Use these capabilities when:
    - The user asks for edits or improvements to existing files
    - You need to understand the current state of the project
    - You believe reading a file or listing directory contents will be beneficial to accomplish the user's goal
    - You need up-to-date information or additional context to answer a question accurately

    When you need current information or feel that a search could provide a better answer, use the tavily_search tool. This tool performs a web search and returns a concise answer along with relevant sources.

    Always strive to provide the most accurate, helpful, and detailed responses possible. If you're unsure about something, admit it and consider using the search tool to find the most current information.

    {automode_status}

    When in automode:
    1. Set clear, achievable goals for yourself based on the user's request
    2. Work through these goals one by one, using the available tools as needed
    3. REMEMBER!! You can Read files, write code, LIST the files, and even SEARCH and make edits, use these tools as necessary to accomplish each goal
    4. ALWAYS READ A FILE BEFORE EDITING IT IF YOU ARE MISSING CONTENT. Provide regular updates on your progress
    5. IMPORTANT RULe!! When you know your goals are completed, DO NOT CONTINUE IN POINTLESS BACK AND FORTH CONVERSATIONS with yourself, if you think we achieved the results established to the original request say "AUTOMODE_COMPLETE" in your response to exit the loop!
    6. ULTRA IMPORTANT! You have access to this {iteration_info} amount of iterations you have left to complete the request, you can use this information to make decisions and to provide updates on your progress knowing the amount of responses you have left to complete the request.
    Answer the user's request using relevant tools (if they are available). Before calling a tool, do some analysis within <thinking></thinking> tags. First, think about which of the provided tools is the relevant tool to answer the user's request. Second, go through each of the required parameters of the relevant tool and determine if the user has directly provided or given enough information to infer a value. When deciding if the parameter can be inferred, carefully consider all the context to see if it supports a specific value. If all of the required parameters are present or can be reasonably inferred, close the thinking tag and proceed with the tool call. BUT, if one of the values for a required parameter is missing, DO NOT invoke the function (not even with fillers for the missing params) and instead, ask the user to provide the missing parameters. DO NOT ask for more information on optional parameters if it is not provided.
    EOT;
  }

  /**
   * Command to start chat.
   *
   * @command drupaai:chatStart
   * @aliases ai-chat
   * @usage drupaai:chatStart
   *   Starts the chat with the Claude AI.
   */
  public function chatStart() {
    $this->printColored("Welcome to the Claude-3.5-Sonnet DrupalAI Chat with Image Support!", self::CLAUDE_COLOR);
    $this->printColored("Type 'exit' to end the conversation.", self::CLAUDE_COLOR);
    $this->printColored("Type 'image' to include an image in your message.", self::CLAUDE_COLOR);
    $this->printColored("Type 'automode [number]' to enter Autonomous mode with a specific number of iterations.", self::CLAUDE_COLOR);
    $this->printColored("While in automode, press Ctrl+C at any time to exit the automode to return to regular chat.", self::CLAUDE_COLOR);

    while (TRUE) {
      echo "\n" . self::USER_COLOR . "You: ";
      $userInput = trim(fgets(STDIN));

      if (strtolower($userInput) == 'exit') {
        $this->printColored("Thank you for chatting. Goodbye!", self::CLAUDE_COLOR);
        break;
      }

      if (strtolower($userInput) == 'image') {
        echo self::USER_COLOR . "Drag and drop your image here: ";
        $imagePath = trim(fgets(STDIN));
        $imagePath = str_replace("'", "", $imagePath);

        if (file_exists($imagePath)) {
          echo self::USER_COLOR . "You (prompt for image): ";
          $userInput = trim(fgets(STDIN));
          [$response] = $this->chatWithClaude($userInput, $imagePath);
          $this->processAndDisplayResponse($response);
        }
        else {
          $this->printColored("Invalid image path. Please try again.", self::CLAUDE_COLOR);
          continue;
        }
      }
      elseif (strpos(strtolower($userInput), 'automode') === 0) {
        $parts = explode(" ", $userInput);
        $maxIterations = count($parts) > 1 && is_numeric($parts[1]) ? intval($parts[1]) : $this->maxContinuationIterations;
        $this->automode = TRUE;
        $this->printColored("Entering automode with $maxIterations iterations. Press Ctrl+C to exit automode at any time.", self::TOOL_COLOR);
        $this->printColored("Press Ctrl+C at any time to exit the automode loop.", self::TOOL_COLOR);

        echo "\n" . self::USER_COLOR . "You: ";
        $userInput = trim(fgets(STDIN));
        $iterationCount = 0;

        try {
          while ($this->automode && $iterationCount < $maxIterations) {
            [$response, $exitContinuation] = $this->chatWithClaude($userInput, NULL, $iterationCount + 1, $maxIterations);
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
            $this->conversationHistory[] = [
              "role" => "assistant",
              "content" => "Automode interrupted. How can I assist you further?",
            ];
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
    $this->output()->writeln($color . $text . " ");
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
    $fullPath = $this->baseFilePath . '/' . $path;

    try {
      if (!file_exists($fullPath)) {
        mkdir($fullPath, 0777, TRUE);
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
    $fullPath = $this->baseFilePath . '/' . $path;

    try {
      file_put_contents($fullPath, $content);
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
    $fullPath = $this->baseFilePath . '/' . $path;

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
      file_put_contents($fullPath, $newContent);
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
    $fullPath = $this->baseFilePath . '/' . $path;

    try {
      if (file_exists($fullPath)) {
        $originalContent = file_get_contents($fullPath);
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
    $fullPath = $this->baseFilePath . '/' . $path;

    try {
      return file_get_contents($fullPath);
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
    $fullPath = $this->baseFilePath . '/' . $path;

    try {
      $files = array_diff(scandir($fullPath), ['.', '..']);
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
    $config = \Drupal::config('drupalai.settings');

    try {
      $api_key = $config->get('tavily_api_key');
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
   * @param object $toolInput
   *   The input of the tool.
   *
   * @return string
   *   The result of the tool execution.
   */
  protected function executeTool($toolName, object $toolInput) {
    switch ($toolName) {
      case 'create_folder':
        return $this->createFolder($toolInput->path);

      case 'create_file':
        return $this->createFile($toolInput->path, $toolInput->content ?? '');

      case 'write_to_file':
        return $this->writeToFile($toolInput->path, $toolInput->content);

      case 'read_file':
        return $this->readFile($toolInput->path);

      case 'list_files':
        return $this->listFiles($toolInput->path ?? '.');

      case 'tavily_search':
        return $this->tavilySearch($toolInput->query);

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
    if ($imagePath) {
      $this->printColored("Processing image at path: $imagePath", self::TOOL_COLOR);
      $imageBase64 = $this->encodeImageToBase64($imagePath);
      if (strpos($imageBase64, "Error") === 0) {
        $this->printColored("Error encoding image: $imageBase64", self::TOOL_COLOR);
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

      $this->conversationHistory[] = $imageMessage;
      $this->printColored("Image message added to conversation history", self::TOOL_COLOR);
    }
    else {
      $this->conversationHistory[] = [
        "role" => "user",
        "content" => $userInput,
      ];
    }

    $messages = $this->conversationHistory;

    $config = \Drupal::config('drupalai.settings');

    $url = 'https://api.anthropic.com/v1/messages';
    $api_key = $config->get('claude3_api_key');

    if (!$api_key) {
      \Drupal::logger('drupalai')->error('Claude3 API key not set.');
      return FALSE;
    }
    $client = new Client();

    try {
      print_r($messages);

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
      $this->printColored("Error calling Claude API: " . $e->getMessage(), self::TOOL_COLOR);
      return [
        "I'm sorry, there was an error communicating with the AI. Please try again.",
        FALSE,
      ];
    }

    $assistantResponse = "";
    $exitContinuation = FALSE;

    $data = json_decode($response->getBody()->getContents());

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
        $this->printColored("Tool Input: " . json_encode($toolInput), self::TOOL_COLOR);

        $result = $this->executeTool($toolName, $toolInput);

        $this->printColored("Tool Result: $result", self::RESULT_COLOR);

        $this->conversationHistory[] = [
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

        $this->conversationHistory[] = [
          "role" => "user",
          "content" => [
            [
              "type" => "tool_result",
              "tool_use_id" => $toolUseId,
              "content" => $result,
            ],
          ],
        ];
        $messages = $this->conversationHistory;

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

          $data = json_decode($toolResponse->getBody()->getContents());

          foreach ($data->content as $toolContentBlock) {
            if ($toolContentBlock->type == "text") {
              $assistantResponse .= $toolContentBlock->text . " ";
            }
          }
        }
        catch (\Exception $e) {
          $this->printColored("Error in tool response: " . $e->getMessage(), self::TOOL_COLOR);
          $assistantResponse .= "\nI encountered an error while processing the tool result. Please try again. ";
        }
      }
    }

    if ($assistantResponse) {
      $this->conversationHistory[] = [
        "role" => "assistant",
        "content" => $assistantResponse,
      ];
    }

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
      $this->printColored($response, self::TOOL_COLOR);
    }
    else {
      if (strpos($response, "```") !== FALSE) {
        $parts = explode("```", $response);
        foreach ($parts as $i => $part) {
          if ($i % 2 == 0) {
            $this->printColored($part, self::CLAUDE_COLOR);
          }
          else {
            $lines = explode("\n", $part);
            $code = implode("\n", array_slice($lines, 1));
            if ($code) {
              $this->printColored("Code:\n$code", self::CLAUDE_COLOR);
            }
            else {
              $this->printColored($part, self::CLAUDE_COLOR);
            }
          }
        }
      }
      else {
        $this->printColored($response, self::CLAUDE_COLOR);
      }
    }
  }

}

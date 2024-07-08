<?php

namespace Drupal\drupalai;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Site\Settings;

/**
 * A Drush commandfile.
 */
class DrupalAiHelper {

  /**
   * Models.
   *
   * @var array
   */
  protected static $models = [
    'gpt-4o' => 'ChatGPT-4o',
    'gpt-3.5-turbo-0125' => 'ChatGPT 3.5 Turbo',
    'gemini-1.5-flash' => 'Gemini 1.5 Flash',
    'gemini-1.5-pro' => 'Gemini 1.5 Pro',
    'claude-3-haiku-20240307' => 'Claude 3 Haiku',
    'claude-3-opus-20240229' => 'Claude 3 Opus',
    'claude-3-sonnet-20240229' => 'Claude 3.5 Sonnet',
  ];

  /**
   * The Chat tools available for use.
   *
   * @var array
   */
  protected static $tools = [
    [
      "name" => "create_files",
      "description" => "Create new files at the specified path with content. Use this when you need to create new files in the project structure.",
      "input_schema" => [
        "type" => "object",
        "properties" => [
          "files" => [
            "type" => "array",
            "items" => [
              "type" => "object",
              "properties" => [
                "path" => [
                  "type" => "string",
                  "description" => "The path of the file.",
                ],
                "content" => [
                  "type" => "string",
                  "description" => "The contents of the file. Do not use literal block scalar (|) for content.",
                ],
              ],
              "required" => [
                "path",
                "content",
              ],
            ],
            "description" => "An array of files with their properties.",
          ],
        ],
        "required" => ["files"],
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
            "description" => "The path of the file to read relative to a Drupal root directory.",
          ],
        ],
        "required" => [
          "path",
        ],
      ],
    ],
    [
      "name" => "list_files",
      "description" => "List all files and directories for a path relative to the Drupal root directory. Use this when you need to see the contents of the current directory.",
      "input_schema" => [
        "type" => "object",
        "properties" => [
          "path" => [
            "type" => "string",
            "description" => "The path of the folder to list files in. Defaults to the current project directory.",
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
  public static function createFile($path, $content = ""): string {
    $fullPath = DRUPAL_ROOT . '/' . $path;

    // Extract directory path and file name.
    $directory = dirname($fullPath);

    // Create directories recursively if they don't exist.
    if (!is_dir($directory)) {
      mkdir($directory, 0777, TRUE);
    }

    try {
      file_put_contents($fullPath, $content);
      return "File created: $path";
    }
    catch (\Exception $e) {
      return "Error creating file: " . $e->getMessage();
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
  public static function writeToFile($path, $content): string {
    $fullPath = DRUPAL_ROOT . '/' . $path;

    try {
      if (file_exists($fullPath)) {
        $originalContent = file_get_contents($fullPath);
        $result = self::generateAndApplyDiff($originalContent, $content, $path);
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
  public static function readFile($path): string {
    $fullPath = DRUPAL_ROOT . '/' . $path;

    if (is_dir($fullPath)) {
      $files = scandir($fullPath);
      $files = array_diff($files, ['.', '..']);
      $content = '';
      foreach ($files as $file) {
        $filePath = $fullPath . '/' . $file;
        if (is_file($filePath)) {
          $content .= file_get_contents($filePath) . "\n";
        }
      }
      return $content;
    }
    else {
      if (file_exists($fullPath)) {
        try {
          return "\n" . file_get_contents($fullPath);
        }
        catch (\Exception $e) {
          return "Error reading file: " . $e->getMessage();
        }
      }
      else {
        return "File not found: $path";
      }
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
  public static function listFiles($path = "."): string {
    // Get the full path relative to the Drupal root directory.
    $fullPath = DRUPAL_ROOT . '/' . $path;

    try {
      $files = array_diff(scandir($fullPath), ['.', '..']);
      return "\n" . implode("\n", $files);
    }
    catch (\Exception $e) {
      return "Error listing files: " . $e->getMessage();
    }
  }

  /**
   * Encodes an image to base64.
   *
   * @param string $imageUrl
   *   The path of the image to encode.
   *
   * @return string
   *   The base64 encoded image.
   */
  public static function encodeImageToBase64($imageUrl) {
    try {
      $img = file_get_contents($imageUrl);
      $base64 = base64_encode($img);

      return $base64;
    }
    catch (\Exception $e) {
      return "Error encoding image: " . $e->getMessage();
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
  public static function generateAndApplyDiff($originalContent, $newContent, $path): string {
    $fullPath = DRUPAL_ROOT . '/' . $path;

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
   * Get Models.
   *
   * @return array
   *   The models.
   */
  public static function getModels(): array {
    return self::$models;
  }

  /**
   * Get Chat Tools.
   *
   * @param string $type
   *   The type of chat tool.
   *
   * @return array
   *   The chat tools.
   */
  public static function getChatTools($type = 'claude'): array {
    $tools = self::$tools;

    // Other tools have a different structure.
    if ($type != 'claude') {
      $new_tools = [];
      foreach ($tools as &$tool) {
        $tool['parameters'] = $tool['input_schema'];
        unset($tool['input_schema']);
        if ($type == 'openai') {
          $new_tools[] = [
            'type' => 'function',
            'function' => $tool,
          ];
        }
        else {
          $new_tools[] = $tool;
        }
      }
      $tools = $new_tools;
    }

    //print_r($tools);die;

    return $tools;
  }

  /**
   * Get Component Folders.
   *
   * @return array
   *   An array of component folders.
   */
  public static function getComponentFolders(): array {
    // Get the active theme path.
    $themePath = \Drupal::theme()->getActiveTheme()->getPath();
    $componentsDir = $themePath . '/components';

    if (!is_dir($componentsDir)) {
      return [];
    }

    $folders = scandir($componentsDir);
    $folders = array_diff($folders, ['.', '..']);

    return $folders;
  }

  /**
   * Get Story Content.
   *
   * @return string
   *   The story component content.
   */
  public static function getStoryContent(): string {
    // Get the active theme path.
    $themePath = \Drupal::theme()->getActiveTheme()->getPath();
    $componentDir = $themePath . '/components';

    if (!is_dir($componentDir)) {
      return '';
    }

    // Get the first subfolder in /components/.
    $subfolders = scandir($componentDir);
    $subfolders = array_diff($subfolders, ['.', '..']);
    $firstSubfolder = '';

    foreach ($subfolders as $subfolder) {
      if (is_dir($componentDir . '/' . $subfolder)) {
        $firstSubfolder = $componentDir . '/' . $subfolder;
        break;
      }
    }

    if ($firstSubfolder === '') {
      return '';
    }

    $content = '';

    // Get content from the first subfolder, only including specified file types.
    $items = scandir($firstSubfolder);
    $items = array_diff($items, ['.', '..']);

    foreach ($items as $item) {
      $path = $firstSubfolder . '/' . $item;
      if (!is_dir($path)) {
        // Only include *.twig, *.scss, and *.stories.js files.
        if (preg_match('/\.(twig|scss|stories\.js)$/', $item)) {
          // Get file content.
          $content .= "Filename: $item\n";
          $content .= "Content:\n";
          $content .= file_get_contents($path) . "\n";
        }
      }
    }

    return $content;
  }

  /**
   * Create File With Path.
   *
   * @param string $file_path
   *   The file path.
   *
   * @return string
   *   The file path.
   */
  public static function createFileWithPath($file_path): string {
    // Extract directory path and file name.
    $directory = dirname($file_path);

    // Create directories recursively if they don't exist.
    if (!is_dir($directory)) {
      mkdir($directory, 0777, TRUE);
    }

    // Create the file if it doesn't exist.
    if (!file_exists($file_path)) {
      fopen($file_path, 'w');
    }

    return $file_path;
  }

  /**
   * Search Files.
   *
   * @param string $search_text
   *   The search text.
   *
   * @return array
   *   The search results.
   */
  public static function searchFiles($search_text) {
    $results = [];

    // Locate the Drupal configuration directory.
    $config_path = Settings::get('config_sync_directory');

    $file_system = \Drupal::service('file_system');

    // Build a regular expression for case-insensitive search.
    $pattern = "/.*{$search_text}.*/i";

    $search_results = $file_system->scanDirectory($config_path, $pattern);

    if ($search_results) {
      foreach ($search_results as $item) {
        $results[] = $item->filename;
      }
    }

    $files = scandir($config_path);

    foreach ($files as $file) {
      // Skip . and .. directories.
      if ($file == '.' || $file == '..') {
        continue;
      }

      // Get the file contents.
      $contents = file_get_contents($config_path . '/' . $file);

      // Search for the text in the file contents.
      if (stripos($contents, $search_text) !== FALSE) {
        $results[] = $file;
      }
    }

    return array_unique($results);
  }

  /**
   * Method to get field definitions of block content types and paragraph types.
   *
   * @return string
   *   The field definitions in plain text format.
   */
  public static function getBlockFieldDefinitions() {
    // Check if the field definitions data is in the cache.
    $cache = \Drupal::cache()->get('block_field_definitions_data');

    if ($cache) {
      // Return the cached data if available.
      return $cache->data;
    }

    // Load the entity type manager service.
    $entity_type_manager = \Drupal::entityTypeManager();

    // Get all custom block content types.
    $block_content_type_storage = $entity_type_manager->getStorage('block_content_type');

    // Load all custom block content types.
    $block_content_types = $block_content_type_storage->loadMultiple();

    // Get all paragraph types.
    $paragraph_type_storage = $entity_type_manager->getStorage('paragraphs_type');

    // Load all paragraph types.
    $paragraph_types = $paragraph_type_storage->loadMultiple();

    // Initialize an array to store field definitions.
    $field_definitions_data = [];

    // Process block content types.
    foreach ($block_content_types as $block_content_type) {
      // Get block type ID and label.
      $block_type_id = $block_content_type->id();
      $block_type_label = $block_content_type->label();

      // Load field definitions for this block type.
      $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('block_content', $block_type_id);

      // Add block content type field definitions to the data array.
      self::addFieldDefinitionsToData($field_definitions_data, 'block_content', $block_type_id, $block_type_label, $field_definitions);
    }

    // Process paragraph types.
    foreach ($paragraph_types as $paragraph_type) {
      // Get paragraph type ID and label.
      $paragraph_type_id = $paragraph_type->id();
      $paragraph_type_label = $paragraph_type->label();

      // Load field definitions for this paragraph type.
      $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('paragraph', $paragraph_type_id);

      // Add paragraph type field definitions to the data array.
      self::addFieldDefinitionsToData($field_definitions_data, 'paragraph', $paragraph_type_id, $paragraph_type_label, $field_definitions);
    }

    // Convert the data array to plain text.
    $plain_text_data = "Entity Type, Type ID, Type Label, Field Name, Field Type, Required\n";
    foreach ($field_definitions_data as $definition) {
      $plain_text_data .= implode(", ", $definition) . "\n";
    }

    // Store the data in cache.
    \Drupal::cache()->set('block_field_definitions_data', $plain_text_data, CacheBackendInterface::CACHE_PERMANENT);

    // Return the plain text data.
    return $plain_text_data;
  }

  /**
   * Function to add field definitions to the data array.
   *
   * @param array $data
   *   The data array to which field definitions will be added.
   * @param string $entity_type
   *   The entity type.
   * @param string $type_id
   *   The type ID.
   * @param string $type_label
   *   The type label.
   * @param array $field_definitions
   *   The field definitions to be added.
   */
  public static function addFieldDefinitionsToData(array &$data, $entity_type, $type_id, $type_label, array $field_definitions) {
    foreach ($field_definitions as $field_name => $field_definition) {
      // Check if the field is custom, or if it is 'title' or 'body'.
      if (
        $field_definition->getName() !== 'id' && $field_definition->getName() !== 'uuid' &&
        ($field_definition->getName() == 'title' || $field_definition->getName() == 'body' ||
        strpos($field_definition->getName(), 'field_') === 0)
      ) {
        // Get field details.
        $field_type = $field_definition->getType();
        $is_required = $field_definition->isRequired() ? 'Yes' : 'No';

        // Add the field definition to the data array.
        $data[] = [
          'entity_type' => $entity_type,
          'type_id' => $type_id,
          'type_label' => $type_label,
          'field_name' => $field_name,
          'field_type' => $field_type,
          'is_required' => $is_required,
        ];
      }
    }
  }

}

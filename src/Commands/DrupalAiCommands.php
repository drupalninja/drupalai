<?php

namespace Drupal\drupalai\Commands;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Site\Settings;
use Drush\Commands\DrushCommands;
use Drupal\drupalai\DrupalAiChatInterface;
use Drupal\drupalai\DrupalAiFactory;

/**
 * A Drush commandfile.
 */
class DrupalAiCommands extends DrushCommands {

  /**
   * Module name.
   *
   * @var string
   */
  private $moduleName = '';

  /**
   * Module instructions.
   *
   * @var string
   */
  private $moduleInstructions = '';

  /**
   * Refactor instructions.
   *
   * @var string
   */
  private $refactorInstructions = '';

  /**
   * Block instructions.
   *
   * @var string
   */
  private $blockInstructions = '';

  /**
   * Refactor content.
   *
   * @var string
   */
  private $refactorContent = '';

  /**
   * AI model.
   *
   * @var \Drupal\drupalai\DrupalAiChat
   */
  private DrupalAiChatInterface $aiModel;

  /**
   * Models.
   *
   * @var array
   */
  private $models = [
    'openai' => 'ChatGPT-4o',
    'gpt-3.5-turbo-0125' => 'ChatGPT 3.5 Turbo',
    'gemini' => 'Gemini',
    'claude3' => 'Claude 3',
    'llama3' => 'Llama 3 (ollama)',
  ];

  /**
   * Generate Block configuration using AI.
   *
   * @command drupalai:createBlock
   * @aliases ai-create-block
   */
  public function createBlock() {
    $model = $this->io()->choice('Select the model type', $this->models, 0);

    // Build AI model.
    $this->aiModel = DrupalAiFactory::build($model);

    // Prompt user for the new configuration.
    $this->blockInstructions = $this->io()->ask('Describe the block type you would like to create', 'Create a Hero Overlay component that is full-width with text, link that overlays a media image');

    // Log to drush console that configuration is being created.
    $this->io()->write("Creating block configuration ...\n\n");

    // Refactor files using AI.
    $this->generateBlockFilesFromAi();
  }

  /**
   * Generate Block Files using AI.
   */
  public function generateBlockFilesFromAi() {
    // Pass in Drupal configuration types for context.
    $drupal_config_types = $this->getBlockFieldDefinitions();

    $prompt = str_replace('DRUPAL_TYPES', $drupal_config_types, DRUPALAI_BLOCK_PROMPT);
    $prompt = str_replace('CONFIG_INSTRUCTIONS', $this->blockInstructions, $prompt);

    $contents = $this->aiModel->getChat($prompt);

    $xml = @simplexml_load_string($contents);

    if (!empty($xml)) {
      foreach ($xml->file as $file) {
        $filename = (string) $file->filename;
        $content = (string) $file->content;

        $path = Settings::get('config_sync_directory') . '/' . trim($filename);

        // Create configuration file.
        $this->io()->write("- Creating file: {$filename} ...\n");

        // Update file contents.
        file_put_contents($path, trim($content));
      }
    }
  }

  /**
   * Create module with AI.
   *
   * @command drupalai:createModule
   * @aliases ai-create-module
   */
  public function createModule() {
    $model = $this->io()->choice('Select the model type', $this->models, 0);

    // Build AI model.
    $this->aiModel = DrupalAiFactory::build($model);

    // Prompt user for name of the module.
    $this->moduleName = $this->io()->ask('Enter the name of the module', 'contact_form');

    // Prompt user for the functionality of the module.
    $this->moduleInstructions = $this->io()->ask('What would you like this module to do?', 'Create a basic contact form with title, body, and email');

    // Log to drush console that a module is being created.
    $this->io()->write("Creating module: {$this->moduleName} ...\n\n");

    // Generate module files using AI.
    $module_created = $this->generateModuleFilesFromAi();

    if ($module_created) {
      // Log to drush console that a module is created.
      $this->io()->write("Module created: {$this->moduleName}\n");

      // Prompt user to enable the module.
      $enable = $this->io()->confirm('Would you like to enable the module?', TRUE);

      if ($enable) {
        // Log to drush console that a module is being enabled.
        $this->io()->write("Enabling module: {$this->moduleName} ...\n\n");

        // Enable the module programmatically.
        \Drupal::service('module_installer')->install([$this->moduleName]);
        $this->io()->write("Module enabled: {$this->moduleName}\n");
      }
    }
  }

  /**
   * Generate Code/Config using AI.
   *
   * @command drupalai:refactorConfig
   * @aliases ai-refactor-config
   */
  public function refactorConfig() {
    $model = $this->io()->choice('Select the model type', $this->models, 0);

    // Build AI model.
    $this->aiModel = DrupalAiFactory::build($model);

    // Prompt user for search string.
    $search_text = $this->io()->ask('Enter search string to find files you would like to change', '^block_content.type');

    $results = $this->searchFiles($search_text);

    // Show files to the user and prompt to continue.
    $this->io()->write("Files found:\n");
    $this->io()->listing($results);

    // Prompt user to continue.
    $continue = $this->io()->confirm('Would you like to refactor these files?', TRUE);

    if ($continue) {
      // Get the list of files to refactor and content of those files.
      foreach ($results as $file) {
        $contents = file_get_contents(Settings::get('config_sync_directory') . '/' . $file);
        $this->refactorContent .= "File: {$file}\nContent: {$contents}\n\n";
      }

      // Prompt user for the new configuration.
      $this->refactorInstructions = $this->io()->ask('Enter the changes you would like to make', 'Rewrite the block descriptions in old English');

      // Log to drush console that configuration is being refactored.
      $this->io()->write("Refactoring configuration ...\n\n");

      // Refactor files using AI.
      $this->refactorFilesFromAi();
    }
  }

  /**
   * Refactor Files using AI.
   */
  public function refactorFilesFromAi() {
    $config = \Drupal::config('drupalai.settings');

    $prompt = str_replace('REFACTOR_INSTRUCTIONS', $this->refactorInstructions, $config->get('refactor_prompt_template') ?? DRUPALAI_REFACTOR_PROMPT);
    $prompt = str_replace('REFACTOR_FILES', $this->refactorContent, $prompt);

    $contents = $this->aiModel->getChat($prompt);

    $xml = @simplexml_load_string($contents);

    if (!empty($xml)) {
      foreach ($xml->file as $file) {
        $filename = (string) $file->filename;
        $newfilename = (string) $file->newfilename;
        $content = (string) $file->content;

        $path = Settings::get('config_sync_directory') . '/' . trim($filename);

        if (!file_exists($path)) {
          $this->io()->write("File not found: {$path}\n");
          continue;
        }
        else {
          // Log to drush console that a file is being refactored.
          $this->io()->write("- Updating file contents: {$path}\n");

          // Update file contents.
          file_put_contents($path, trim($content));
        }

        // If new filename is provided, rename the file.
        if (!empty($newfilename)) {
          $new_path = Settings::get('config_sync_directory') . '/' . trim($newfilename);

          // Log to drush console that a file is being renamed.
          $this->io()->write("- Renaming file: {$path} to {$new_path}\n");

          rename($path, $new_path);
        }
      }
    }
  }

  /**
   * Generate Module Files using AI.
   */
  public function generateModuleFilesFromAi(): bool {
    $config = \Drupal::config('drupalai.settings');

    $prompt = str_replace('MODULE_NAME', $this->moduleName, $config->get('module_prompt_template') ?? DRUPALAI_MODULE_PROMPT);
    $prompt = str_replace('MODULE_INSTRUCTIONS', $this->moduleInstructions, $prompt);

    $contents = $this->aiModel->getChat($prompt);

    $xml = @simplexml_load_string($contents);

    if (!empty($xml)) {
      // Create an empty folder with the module name.
      $path = 'modules/custom/' . $this->moduleName;

      // Log to drush console that a folder is being created.
      $this->io()->write("- Creating folder: {$path}\n");

      if (!file_exists('modules/custom')) {
        mkdir('modules/custom');
      }

      if (!file_exists($path)) {
        mkdir($path);
      }

      foreach ($xml->file as $file) {
        $filename = (string) $file->filename;
        $content = (string) $file->content;

        $path = 'modules/custom/' . $this->moduleName . '/' . trim($filename);

        // Create file and any subdirectories.
        // Log to drush console that a file is being generated.
        $this->io()->write("- Creating file and subdirectories: {$path}\n");

        $file_path = self::createFileWithPath($path);

        // Log to drush console that a file is being populated.
        $this->io()->write("- Populating file: {$file_path}\n");

        // Update file contents.
        file_put_contents($file_path, trim($content));
      }
    }
    else {
      $this->io()->write("No files generated.\n");
      return FALSE;
    }
    return TRUE;
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
  public function searchFiles($search_text) {
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
  public function getBlockFieldDefinitions() {
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
      $this->addFieldDefinitionsToData($field_definitions_data, 'block_content', $block_type_id, $block_type_label, $field_definitions);
    }

    // Process paragraph types.
    foreach ($paragraph_types as $paragraph_type) {
      // Get paragraph type ID and label.
      $paragraph_type_id = $paragraph_type->id();
      $paragraph_type_label = $paragraph_type->label();

      // Load field definitions for this paragraph type.
      $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('paragraph', $paragraph_type_id);

      // Add paragraph type field definitions to the data array.
      $this->addFieldDefinitionsToData($field_definitions_data, 'paragraph', $paragraph_type_id, $paragraph_type_label, $field_definitions);
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
  public function addFieldDefinitionsToData(&$data, $entity_type, $type_id, $type_label, $field_definitions) {
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

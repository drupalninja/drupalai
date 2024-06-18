<?php

namespace Drupal\drupalai\Commands;

use Drupal\drupalai\DrupalAiChatInterface;
use Drupal\drupalai\DrupalAiFactory;
use Drush\Commands\DrushCommands;
use Drupal\Core\Site\Settings;

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
   * Create module with AI.
   *
   * @command drupalai:createModule
   * @aliases ai-create-module
   */
  public function createModule() {
    // List of available models.
    $models = [
      'openai' => 'ChatGPT-4o',
      'gemini' => 'Gemini',
      'claude3' => 'Claude 3',
      'llama3' => 'Llama 3 (ollama)',
    ];

    // Present 3 model type options: llama3, openai, or gemini.
    $model = $this->io()->choice('Select the model type', $models, 0);

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
    // List of available models.
    $models = [
      'openai' => 'ChatGPT-4o',
      'gemini' => 'Gemini',
      'claude3' => 'Claude 3',
      'llama3' => 'Llama 3 (ollama)',
    ];

    // Present 3 model type options: llama3, openai, or gemini.
    $model = $this->io()->choice('Select the model type', $models, 0);

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

}

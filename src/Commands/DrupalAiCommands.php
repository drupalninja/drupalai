<?php

namespace Drupal\drupalai\Commands;

use Drupal\Core\Site\Settings;
use Drush\Commands\DrushCommands;
use Drupal\drupalai\DrupalAiChatInterface;
use Drupal\drupalai\DrupalAiFactory;
use Drupal\drupalai\DrupalAiHelper;

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
   * Component instructions.
   *
   * @var string
   */
  private $componentInstructions = '';

  /**
   * Component name.
   *
   * @var string
   */
  private $componentName = '';

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
    'gpt-4o' => 'ChatGPT-4o',
    'gpt-3.5-turbo-0125' => 'ChatGPT 3.5 Turbo',
    'gemini' => 'Gemini',
    'claude3' => 'Claude 3',
    'llama3' => 'Llama 3 (ollama)',
  ];

  /**
   * Generate Component configuration using AI.
   *
   * @command drupalai:createComponent
   * @aliases ai-create-component
   */
  public function createComponent() {
    $model = $this->io()->choice('Select the model type', $this->models, 0);

    // Build AI model.
    $this->aiModel = DrupalAiFactory::build($model);

    // Prompt user for the new configuration.
    $this->componentInstructions = $this->io()->ask('Describe the component you would like to create', 'Create a new component for a carousel of client testimonials');

    // Prompt user for the component machine name.
    $this->componentName = $this->io()->ask('Enter the machine name of the component', 'testimonial_carousel');

    // Log to drush console that configuration is being created.
    $this->io()->write("Creating component configuration ...\n\n");

    // Get a list of folders in the components directory from the active theme.
    $folders = DrupalAiHelper::getComponentFolders();

    // Create component files using AI.
    $this->generateComponentFilesFromAi();
  }

  /**
   * Generate Component Files using AI.
   */
  public function generateComponentFilesFromAi() {
    // Pass in Drupal configuration types for context.
    $drupal_config_types = DrupalAiHelper::getComponentFolders();

    // Get the content of the example component (if components dir exists).
    $example_component_content = DrupalAiHelper::getComponentContent();

    $prompt = str_replace('DRUPAL_TYPES', $drupal_config_types, drupalai_get_prompt('component'));
    $prompt = str_replace('COMPONENT_INSTRUCTIONS', $this->componentInstructions, $prompt);
    $prompt = str_replace('EXAMPLE_COMPONENT', $example_component_content, $prompt);

    $contents = $this->aiModel->getChat($prompt);

    $xml = @simplexml_load_string($contents);

    if (!empty($xml)) {
      foreach ($xml->file as $file) {
        $filename = (string) $file->filename;
        $content = (string) $file->content;

        // Path to the components directory in the active theme.
        $themePath = \Drupal::theme()->getActiveTheme()->getPath();
        $componentDir = $themePath . '/components/' . $this->componentName . '/' . trim($filename);

        // Create component file.
        $this->io()->write("- Creating file: {$filename} ...\n");

        // Add file contents.
        file_put_contents($componentDir, trim($content));
      }
    }
  }

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
    $this->blockInstructions = $this->io()->ask('Describe the block type you would like to create', 'Create a new block type for a carousel of client testimonials');

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
    $drupal_config_types = DrupalAiHelper::getBlockFieldDefinitions();

    $prompt = str_replace('DRUPAL_TYPES', $drupal_config_types, drupalai_get_prompt('block'));
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

    $results = DrupalAiHelper::searchFiles($search_text);

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

    $prompt = str_replace('REFACTOR_INSTRUCTIONS', $this->refactorInstructions, $config->get('refactor_prompt_template') ?? drupalai_get_prompt('refactor'));
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

    $prompt = str_replace('MODULE_NAME', $this->moduleName, $config->get('module_prompt_template') ?? drupalai_get_prompt('module'));
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

        $file_path = DrupalAiHelper::createFileWithPath($path);

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

}

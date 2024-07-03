<?php

namespace Drupal\drupalai\Commands;

use Drush\Commands\DrushCommands;
use Drupal\drupalai\DrupalAiChatInterface;
use Drupal\drupalai\DrupalAiFactory;
use Drupal\drupalai\DrupalAiHelper;

/**
 * A Drush commandfile.
 */
class DrupalAiModule extends DrushCommands {

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
    $model = $this->io()->choice('Select the model type', DrupalAiHelper::getModels(), 0);

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

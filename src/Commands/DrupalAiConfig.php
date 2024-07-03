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
class DrupalAiConfig extends DrushCommands {

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
   * Generate Code/Config using AI.
   *
   * @command drupalai:refactorConfig
   * @aliases ai-refactor-config
   */
  public function refactorConfig() {
    $model = $this->io()->choice('Select the model type', DrupalAiHelper::getModels(), 0);

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
    else {
      $this->io()->write("No files refactored.\n");
    }
  }

}

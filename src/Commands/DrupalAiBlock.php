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
class DrupalAiBlock extends DrushCommands {

  /**
   * Block instructions.
   *
   * @var string
   */
  private $blockInstructions = '';

  /**
   * AI model.
   *
   * @var \Drupal\drupalai\DrupalAiChat
   */
  private DrupalAiChatInterface $aiModel;

  /**
   * Generate Block configuration using AI.
   *
   * @command drupalai:createBlock
   * @aliases ai-create-block
   */
  public function createBlock() {
    $model = $this->io()->choice('Select the model type', DrupalAiHelper::getModels(), 0);

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
    $config = \Drupal::config('drupalai.settings');

    // Pass in Drupal configuration types for context.
    $drupal_config_types = DrupalAiHelper::getBlockFieldDefinitions();

    $prompt = str_replace('DRUPAL_TYPES', $drupal_config_types, $config->get('block_prompt_template') ?? drupalai_get_prompt('block'));
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
    else {
      $this->io()->write("No files generated.\n");
    }
  }

}

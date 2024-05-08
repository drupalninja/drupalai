<?php

namespace Drupal\drupalai\Commands;

use Drupal\drupalai\DrupalAiFactory;
use Drupal\drupalai\DrupalAiChatInterface;
use Drush\Commands\DrushCommands;

const DRUPAL_AI_MODULE_PROMPT = <<<EOT
  You are an expert Drupal 10 developer.
  You will be writing a module called "MODULE_NAME"
  MODULE_INSTRUCTIONS
  Before proceeding, think about Drupal best practices for this module.
  If there is an issue, an error should be output as a Drupal message.
  Give me a the response in XML format, no comments or explanation.
  Example structure is:
  <files><file><filename>filename.php</filename><content><![CDATA[ <?php ... ?> ]]></content></file></files>
  where each item is element <file> and underneath the <file> element there are
  two child elements <filename> and <content>. The first child object is <filename></filename>
  which is the file name and the second child object is <content></content> which uses <![CDATA[ ... ]]>
  to wrap the file's content. Make sure any .module file content begins with <?php.
  Double check the syntax to make sure there are no syntax errors and the code
  is following Drupal coding standards. Make sure all XML tags are properly closed.
  EOT;

/**
 * A Drush commandfile.
 */
class DrupalAiCommands extends DrushCommands {

  /**
   * Module name.
   *
   * @var string
   */
  private $moduleName;

  /**
   * Module instructions.
   *
   * @var string
   */
  private $moduleInstructions;

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
   * @aliases ai-module
   */
  public function createModule() {
    // List of available models.
    $models = [
      'llama3' => 'Llama 3',
      'openai' => 'OpenAI',
      'gemini' => 'Gemini',
    ];

    // Present 3 model type options: llama3, openai, or gemini.
    $model = $this->io()->choice('Select the model type', $models, 0);

    // Build AI model.
    $this->aiModel = DrupalAiFactory::build($model);

    // Prompt user for name of the module.
    $this->moduleName = $this->io()->ask('Enter the name of the module', 'acme_articles');

    // Prompt user for the functionality of the module.
    $this->moduleInstructions = $this->io()->ask('What would you like this module to do?', 'Create 5 article nodes.');

    // Log to drush console that a module is being created.
    $this->io()->write('Creating module: ' . $this->moduleName . ' ...');

    // Generate module files using AI.
    $this->generateModuleFilesFromAi();
  }

  /**
   * Generate Module Files using AI.
   */
  public function generateModuleFilesFromAi(): void {

    $prompt = str_replace('MODULE_NAME', $this->moduleName, DRUPAL_AI_MODULE_PROMPT);
    $prompt = str_replace('MODULE_INSTRUCTIONS', $this->moduleInstructions, $prompt);

    $contents = $this->aiModel->getChat($prompt);

    $xml = simplexml_load_string($contents);

    if (!empty($xml)) {
      // Create an empty folder with the module name.
      $path = 'modules/custom/' . $this->moduleName;
      if (!file_exists($path)) {
        mkdir($path);
      }

      foreach ($xml->file as $file) {
        $filename = (string) $file->filename;
        $content = (string) $file->content;

        $path = 'modules/custom/' . $this->moduleName . '/' . trim($filename);

        // Create file and any subdirectories.
        $file_path = self::createFileWithPath($path);

        // Log to drush console that a file is being updated.
        \Drupal::logger('drupalai')->notice('Updating file: ' . $file_path);

        // Update file contents.
        file_put_contents($file_path, trim($content));
      }
    }
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

}

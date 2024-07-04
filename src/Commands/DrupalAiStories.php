<?php

namespace Drupal\drupalai\Commands;

use Drush\Commands\DrushCommands;
use Drupal\drupalai\DrupalAiChatInterface;
use Drupal\drupalai\DrupalAiFactory;
use Drupal\drupalai\DrupalAiHelper;

/**
 * A Drush commandfile.
 */
class DrupalAiStories extends DrushCommands {

  /**
   * Story instructions.
   *
   * @var string
   */
  private $storyInstructions = '';

  /**
   * Story name.
   *
   * @var string
   */
  private $storyName = '';

  /**
   * AI model.
   *
   * @var \Drupal\drupalai\DrupalAiChat
   */
  private DrupalAiChatInterface $aiModel;

  /**
   * Generate Storybook component using AI.
   *
   * @command drupalai:createStory
   * @aliases ai-create-story
   */
  public function createStory() {
    $model = $this->io()->choice('Select the model type', DrupalAiHelper::getModels(), 0);

    // Build AI model.
    $this->aiModel = DrupalAiFactory::build($model);

    // Prompt user for the new story.
    $this->storyInstructions = $this->io()->ask('Describe the Storybook component you would like to create', 'Row of 3 testimonial cards (equal height, centered) with name, image, title, and quote');

    // Prompt user for the name of the Storybook component.
    $this->storyName = $this->io()->ask('Enter the name of the Storybook component', 'testimonial-cards');

    // Log to drush console that the component is being created.
    $this->io()->write("Creating Storybook component: {$this->storyName} ...\n\n");

    // Create storybook component files using AI.
    $this->generateStoryFilesFromAi();
  }

  /**
   * Generate Story Files using AI.
   */
  public function generateStoryFilesFromAi() {
    $config = \Drupal::config('drupalai.settings');

    // Get the content of the example story (if components dir exists).
    $example_story_content = DrupalAiHelper::getStoryContent();

    $prompt = str_replace('COMPONENT_INSTRUCTIONS', $this->storyInstructions, $config->get('component_prompt_template') ?? drupalai_get_prompt('story'));
    $prompt = str_replace('STORY_NAME', $this->storyName, $prompt);

    if (!empty($example_story_content)) {
      $prompt = str_replace('EXAMPLE_COMPONENT', $example_story_content, $prompt);
    }

    $contents = $this->aiModel->getChat($prompt);

    $xml = @simplexml_load_string($contents);

    if (!empty($xml)) {
      // Path to the components directory in the active theme.
      $themePath = \Drupal::theme()->getActiveTheme()->getPath();
      $directory = $this->storyName;

      foreach ($xml->file as $file) {
        $filename = (string) $file->filename;
        $content = (string) $file->content;

        // Create component file and any subdirectories in the main directory.
        $file_path = DrupalAiHelper::createFileWithPath($themePath . '/components/' . $directory . '/' . trim($filename));

        // Create component file.
        $this->io()->write("- Creating file: {$filename} ...\n");

        // Add file contents.
        file_put_contents($file_path, trim($content) . "\n");
      }
    }
    else {
      $this->io()->write("No files generated.\n");
    }
  }

  /**
   * Generate multiple stories from an image using GPT-4 model.
   *
   * @command drupalai:createStoriesFromImage
   * @aliases ai-create-stories-image
   */
  public function createStoriesFromImage() {
    $model = 'gpt-4o';
    $model_name = DrupalAiHelper::getModels()[$model];

    // Log that the AI model is being used.
    $this->io()->write("Using AI model for image translation: {$model_name}\n");

    // Build AI model.
    $this->aiModel = DrupalAiFactory::build($model);

    // Prompt user for the image URL.
    $last_image_url = \Drupal::cache()->get('last_image_url_prompt')->data ?? '';
    $image_url = $this->io()->ask("Enter the URL of the web design image:\n", $last_image_url);

    if (!empty($image_url)) {
      $this->io()->write("Translating image to text ...\n\n");

      // Cache image url.
      \Drupal::cache()->set('last_image_url_prompt', $image_url);
    }
    else {
      $this->io()->write("No image URL provided. Unable to continue.\n");
      return;
    }

    // Get description of the image using AI.
    $image_text = $this->generateDescriptionFromImageAi($image_url);

    // Log to drush console that the description is being created.
    if (!empty($image_text)) {
      $this->io()->write("Please select the model type for generating stories from the image.\n");

      $model = $this->io()->choice('Select the model type', DrupalAiHelper::getModels(), 0);

      // Build AI model (for next step).
      $this->aiModel = DrupalAiFactory::build($model);

      $this->generateStoriesFromImageAi($image_text);
    }
    else {
      $this->io()->write("No description generated.\n");
    }
  }

  /**
   * Generate description from Image URL using AI.
   *
   * @param string $image_url
   *   The URL of the image.
   *
   * @return string
   *   The description of the image.
   */
  public function generateDescriptionFromImageAi($image_url): string {
    $config = \Drupal::config('drupalai.settings');
    $prompt = $config->get('image_prompt_template') ?? drupalai_get_prompt('image');

    $text = '';

    // Check if the description is already cached.
    $cached_text = \Drupal::cache()->get('image_description_' . $image_url);

    if ($cached_text) {
      $this->io()->write("Using cached image description ...\n");
      $text = $cached_text->data;
    }
    else {
      // Pass text prompt and image URL to AI model.
      $text = $this->aiModel->getImageDescription($prompt, $image_url);

      // Cache the text for this image URL.
      if (!empty($text)) {
        \Drupal::cache()->set('image_description_' . $image_url, $text);
      }
    }

    return $text;
  }

  /**
   * Generate Stories using AI.
   *
   * @param string $image_text
   *   The translated text from the image.
   */
  public function generateStoriesFromImageAi($image_text) {
    $config = \Drupal::config('drupalai.settings');

    // Get the content of the example story (if components dir exists).
    $example_story_content = DrupalAiHelper::getStoryContent();

    $prompt = str_replace('COMPONENTS_DESCRIPTION', $image_text, $config->get('stories_prompt_template') ?? drupalai_get_prompt('stories'));

    if (!empty($example_story_content)) {
      $prompt = str_replace('EXAMPLE_COMPONENT', $example_story_content, $prompt);
    }

    // Pass text prompt to AI model.
    $contents = $this->aiModel->getChat($prompt);

    $xml = @simplexml_load_string($contents);

    if (!empty($xml)) {
      // Path to the components directory in the active theme.
      $themePath = \Drupal::theme()->getActiveTheme()->getPath();

      foreach ($xml->file as $file) {
        [$directory, $filename] = explode('/', (string) $file->filename);
        $content = (string) $file->content;

        // Create component file and any subdirectories in the main directory.
        $file_path = DrupalAiHelper::createFileWithPath($themePath . '/components/' . $directory . '/' . trim($filename));

        // Create component file.
        $this->io()->write("- Creating file: {$filename} ...\n");

        // Add file contents.
        file_put_contents($file_path, trim($content) . "\n");
      }
    }
    else {
      $this->io()->write("No files generated.\n");
    }
  }

}

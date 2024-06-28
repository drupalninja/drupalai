<?php

namespace Drupal\drupalai;

use Drupal\Core\Cache\CacheBackendInterface;

/**
 * A Drush commandfile.
 */
class DrupalAiHelper {

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
   * Get Component Content.
   *
   * @return string
   *   The component content.
   */
  public static function getComponentContent(): string {
    // Get the active theme path.
    $themePath = \Drupal::theme()->getActiveTheme()->getPath();
    $componentDir = $themePath . '/components';

    if (!is_dir($componentDir)) {
      return '';
    }

    $content = '';

    // Function to recursively get content from all subdirectories, excluding .css files.
    $getFilesContent = function ($dir) use (&$getFilesContent, &$content) {
      $items = scandir($dir);
      $items = array_diff($items, ['.', '..']);

      foreach ($items as $item) {
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
          // Recur into subdirectory.
          $getFilesContent($path);
        }
        else {
          // Skip .css files.
          if (substr($item, -4) !== '.css') {
            // Get file content.
            $content .= "Filename: $item\n";
            $content .= "Content:\n";
            $content .= file_get_contents($path) . "\n";
          }
        }
      }
    };

    // Start the recursion from the first component directory.
    $firstComponentDir = $componentDir . '/' . scandir($componentDir)[2];
    $getFilesContent($firstComponentDir);

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
  public static function addFieldDefinitionsToData(&$data, $entity_type, $type_id, $type_label, $field_definitions) {
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

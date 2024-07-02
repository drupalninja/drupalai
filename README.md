# Drupal AI Module

## Description
The Drupal AI module provides drush commands for generating code with AI.

## Features
- Module code generation supporting multiple AI models (Gemini, ChatGPT, Claude 3, Llama 3)
- Easy-to-use CLI command(s) powered by Drush
- Simple configuration for managing prompt template(s)

## Installation
1. To install with Composer run the command:
  
   ```
   composer require drupalninja/drupalai
   ```

3. Enable the module through the Drupal admin interface or using Drush:

   ```
   drush en drupalai
   ```

4. Configure the module settings under "Admin -> Configuration -> Drupal AI Settings".
- API keys required to use Gemini or ChatGPT (OpenAI)
- Prompt template can and should be configured for best results (i.e. trial and error)
- The Llama 3 expects the 'ollama' tool to be running the Llama 3 module locally.

## Requirements
- Drupal 10.x
- PHP 7.4 or higher

## Usage

   ```
   drush ai-create-module
   ```

You will be prompted to for the following inputs:
- Which model you would like to use.
- The machine name of the module you would like to create.
- What you would like the module to do.

NOTE: This is experimental, use with caution.

## License
This module is licensed under the GNU General Public License.

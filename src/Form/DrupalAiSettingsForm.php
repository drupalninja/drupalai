<?php

namespace Drupal\drupalai\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

const DRUPAL_AI_MODULE_PROMPT = <<<EOT
  You are an expert Drupal 10 developer.
  You will be writing a module called "MODULE_NAME"
  MODULE_INSTRUCTIONS
  Before proceeding, think about Drupal best practices for this module.
  If there is an issue, an error should be output as a Drupal message.
  Entity queries must explicitly set whether the query should be access checked or not. See Drupal\Core\Entity\Query\QueryInterface::accessCheck().
  One of the files must be a .info.yml file.
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
 * Defines a form to configure settings for the Drupalai module.
 */
class DrupalAiSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['drupalai.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'drupalai_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('drupalai.settings');

    $form['api_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('API Settings'),
    ];

    $form['api_settings']['openai_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('OpenAI API Key'),
      '#default_value' => $config->get('openai_api_key') ?? '',
      '#description' => $this->t('Enter the API key for OpenAI.'),
    ];

    $form['api_settings']['gemini_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Gemini API Key'),
      '#default_value' => $config->get('gemini_api_key') ?? '',
      '#description' => $this->t('Enter the API key for Gemini.'),
    ];

    $form['api_settings']['claude3_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Claude 3 API Key'),
      '#default_value' => $config->get('claude3_api_key') ?? '',
      '#description' => $this->t('Enter the API key for Claude 3.'),
    ];

    $form['ai_settings_instructions'] = [
      '#type' => 'item',
      '#markup' => '<p>' . $this->t('These settings are used to configure the AI settings for the module.') . '</p>',
    ];

    $form['module_prompt_template'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Module Prompt Template'),
      '#description' => $this->t('Enter the template for the module prompt.
        The keywords \'MODULE_NAME\' and \'MODULE_INSTRUCTIONS\' are needed as well
        as the XML format: <files><file><filename></filename><content></content></file></files>.'),
      '#required' => TRUE,
      '#default_value' => $config->get('module_prompt_template') ?? DRUPAL_AI_MODULE_PROMPT,
      '#rows' => 15,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('drupalai.settings');
    $config->set('module_prompt_template', $form_state->getValue('module_prompt_template'));
    $config->set('openai_api_key', $form_state->getValue('openai_api_key'));
    $config->set('gemini_api_key', $form_state->getValue('gemini_api_key'));
    $config->set('claude3_api_key', $form_state->getValue('claude3_api_key'));
    $config->save();

    parent::submitForm($form, $form_state);
  }

}

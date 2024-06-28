<?php

namespace Drupal\drupalai\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

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

    // Configure Ollama address.
    $form['api_settings']['ollama_address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Ollama Address'),
      '#default_value' => $config->get('ollama_address') ?? 'http://host.docker.internal:11434',
      '#description' => $this->t('Address for Ollama, defaults to http://host.docker.internal:11434.'),
    ];

    $form['module_prompt_template'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Module Prompt Template'),
      '#description' => $this->t('Enter the template for the module prompt.
        The keywords \'MODULE_NAME\' and \'MODULE_INSTRUCTIONS\' are needed as well
        as the XML format: <files><file><filename></filename><content></content></file></files>.'),
      '#required' => TRUE,
      '#default_value' => $config->get('module_prompt_template') ?? drupalai_get_prompt('module'),
      '#rows' => 15,
    ];

    $form['refactor_prompt_template'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Refactor Prompt Template'),
      '#description' => $this->t('Enter the template for the module prompt.
        The keyword \'REFACTOR_INSTRUCTIONS\' are needed as well
        as the XML format: <files><file><filename></filename><content></content></file></files>.'),
      '#required' => TRUE,
      '#default_value' => $config->get('refactor_prompt_template') ?? drupalai_get_prompt('refactor'),
      '#rows' => 15,
    ];

    $form['block_prompt_template'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Block Prompt Template'),
      '#description' => $this->t('Enter the template for the block prompt.
        The keywords \'BLOCK_NAME\' and \'BLOCK_INSTRUCTIONS\' are needed as well
        as the XML format: <files><file><filename></filename><content></content></file></files>.'),
      '#required' => TRUE,
      '#default_value' => $config->get('block_prompt_template') ?? drupalai_get_prompt('block'),
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
    $config->set('refactor_prompt_template', $form_state->getValue('refactor_prompt_template'));
    $config->set('block_prompt_template', $form_state->getValue('block_prompt_template'));
    $config->set('openai_api_key', $form_state->getValue('openai_api_key'));
    $config->set('gemini_api_key', $form_state->getValue('gemini_api_key'));
    $config->set('claude3_api_key', $form_state->getValue('claude3_api_key'));
    $config->set('ollama_address', $form_state->getValue('ollama_address'));
    $config->save();

    parent::submitForm($form, $form_state);
  }

}

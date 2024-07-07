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

    $form['system_prompt'] = [
      '#type' => 'textfield',
      '#title' => $this->t('System Prompt'),
      '#default_value' => $config->get('system_prompt') ?? drupalai_get_prompt('chat'),
      '#description' => $this->t('Enter the system prompt for the AI.'),
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

    $form['api_settings']['tavily_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tavily API Key'),
      '#default_value' => $config->get('tavily_api_key') ?? '',
      '#description' => $this->t('Enter the API key for Tavily.'),
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

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('drupalai.settings');
    $config->set('system_prompt', $form_state->getValue('system_prompt'));
    $config->set('openai_api_key', $form_state->getValue('openai_api_key'));
    $config->set('gemini_api_key', $form_state->getValue('gemini_api_key'));
    $config->set('claude3_api_key', $form_state->getValue('claude3_api_key'));
    $config->set('tavily_api_key', $form_state->getValue('tavily_api_key'));
    $config->set('ollama_address', $form_state->getValue('ollama_address'));
    $config->save();

    parent::submitForm($form, $form_state);
  }

}

<?php

namespace Drupal\custom_oauth2\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\Role;

/**
 * Configure custom_oauth2 settings for this site.
 */
class Co2SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'custom_oauth2_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['custom_oauth2.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['resend_otp_limitation'] = [
      '#type' => 'number',
      '#title' => $this->t('Re-send email OTP Limitation'),
      '#default_value' => $this->config('custom_oauth2.settings')
        ->get('resend_otp_limitation'),
    ];
    $form['otp_expired_time'] = [
      '#type' => 'number',
      '#title' => $this->t('Time OTP will be expired <i>(count in second)</i>'),
      '#default_value' => $this->config('custom_oauth2.settings')
        ->get('otp_expired_time'),
    ];
    $form['resend_time_gap'] = [
      '#type' => 'number',
      '#title' => $this->t('Time resend otp gap <i>(count in second)</i>'),
      '#default_value' => $this->config('custom_oauth2.settings')
        ->get('resend_time_gap'),
    ];

    $roles = Role::loadMultiple();

    $role_options = [];
    $exlude_id = ['authenticated', 'administrator' , 'anonymous'];
    foreach($roles as $role_id => $role) {
      /**
       * @var Role $role
       */
      if (!in_array($role_id, $exlude_id)) {
      $role_options[$role_id] = $role->label();
      }
    }

    $default_roles = $this->config('custom_oauth2.settings')
      ->get('roles_assign');
    $form['roles_assign'] = [
      '#type' => "checkboxes",
      '#title' => $this->t('Role assign'),
      '#options' => $role_options,
      '#default_value' => $default_roles ?? [],
      '#description' => $this->t('These will roles will be assign when new user create with sign up API, Authenticated always assigned by default'),
      '#required' => true
    ];

    $form['otp_sandbox'] = [
      '#type' => "checkbox",
      '#title' => $this->t('Enable OTP sandbox'),
      '#default_value' => $this->config('custom_oauth2.settings')
        ->get('otp_sandbox'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $resend_otp_limitation = $form_state->getValue('resend_otp_limitation');
    $expired_time = (int) $form_state->getValue('otp_expired_time');
    $resend_time_gap = (int) $form_state->getValue('resend_time_gap');
    if ($resend_otp_limitation <= 0) {
      $form_state->setErrorByName('resend_otp_limitation', $this->t("@label must be larger than 0", ['@label' => $form['resend_otp_limitation']['#title']]));
    }

    if ($expired_time <= 180) {
      $form_state->setErrorByName('otp_expired_time', $this->t("@label can't be less 180 seconds (3 minutes)", ['@label' => $form['otp_expired_time']['#title']]));
    }

    if ($resend_time_gap < 180) {
      $form_state->setErrorByName('resend_time_gap', $this->t("@label can't be less than 180 seconds (3 minutes)", ['@label' => $form['resend_time_gap']['#title']]));
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $resend_otp_limitation = $form_state->getValue('resend_otp_limitation');
    $expired_time = $form_state->getValue('otp_expired_time');
    $resend_time_gap = $form_state->getValue('resend_time_gap');
    $roles = $form_state->getValue('roles_assign');
    $sandbox = $form_state->getValue('otp_sandbox');
    $this->config('custom_oauth2.settings')
      ->set('resend_otp_limitation', $resend_otp_limitation)
      ->save();
    $this->config('custom_oauth2.settings')
      ->set('otp_expired_time', $expired_time)
      ->save();
    $this->config('custom_oauth2.settings')
      ->set('resend_time_gap', $resend_time_gap)
      ->save();
    $this->config('custom_oauth2.settings')
      ->set('otp_sandbox', $sandbox)
      ->save();
    $this->config('custom_oauth2.settings')
      ->set('roles_assign', $roles)
      ->save();
    parent::submitForm($form, $form_state);
  }

}

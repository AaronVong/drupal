<?php

namespace Drupal\goodevening_account\Services;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Mail\MailManager;
use Drupal\goodevening_account\AccountOtpTrait;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AccountVerify {

  use AccountOtpTrait;

  /**
   * @var \Drupal\Core\Mail\MailManager
   */
  protected $mail_manager;

  /**
   * @var  LoggerInterface
   */
  protected $logger;

  public function __construct(LoggerChannelFactory $logger, MailManager $mail_manager) {
    $this->mail_manager = $mail_manager;
    $this->logger = $logger->get('mail');
  }

  protected function sendEmail(string $email, string $module, string $template_key, array $template_variables, string $langcode = 'en') {
    $token_service = \Drupal::token();
    $user_mail_settings = \Drupal::config('user.mail');
    $param["subject"] = PlainTextOutput::renderFromHtml($token_service
      ->replace($user_mail_settings->get($template_key . '.subject'), $template_variables));
    $param["body"] = [
      PlainTextOutput::renderFromHtml($token_service->replace($user_mail_settings->get($template_key . '.body'), $template_variables)),
    ];
    $this->mail_manager->mail($module, $template_key, $email, $langcode, $param);
  }

  public function sendOtpToEmail(UserInterface $user): void {
    $email = $user->getEmail();
    $otp = $this->generateOTP();
    $variables = ['user' => $user, 'otp_code' => $otp];
    $this->sendEmail($email, 'goodevening_account', 'email_pending_verify', $variables);
    $this->saveOtp($user, $otp);
  }

  /**
   *
   * @param \Drupal\user\UserInterface $user
   * @param bool $reset if TRUE, otp re-send limitation will reset for this
   *   user, default is FALSE
   *
   * @return bool
   */
  public function reSendOtpToEmail(UserInterface $user, bool $reset = FALSE): bool {
    $email = $user->getEmail();
    if ($user->isActive()) {
      return FALSE;
    }
    $otp = $this->generateOTP();
    $variables = ['user' => $user, 'otp_code' => $otp];
    $time = time();
    $effected_rows = $this->updateOtp($user, $otp, $time, $reset);
    if (!empty($effected_rows)) {
      $this->sendEmail($email, 'goodevening_account', 'email_pending_verify', $variables);
      return TRUE;
    }
    return FALSE;
  }


  public function activateUser(UserInterface $user): void {
    try {
      $user->activate();
      $user->set('field_u_otp_verified', '1');
      $user->save();
    } catch (\Exception $exception) {
      $this->logger->error($exception->getMessage());
      throw $exception;
    }
  }

}

<?php

namespace Drupal\goodevening_account;

use Drupal\user\UserInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

trait AccountOtpTrait {

  protected function generateOTP() {
    return rand(100000, 999999);
  }

  protected function generateHash(string $email): string {
    $time = time();
    $hash_string = $email . $time;
    return hash('md5', $hash_string);
  }

  protected function saveOtp(UserInterface $user, int $otp): void {
    $time = time();
    $query = \Drupal::database()
      ->insert('goodevening_account')
      ->fields([
        'uid' => $user->id(),
        'otp_code' => $otp,
        'created' => $time,
        'expired' => $time + (int) \Drupal::config('goodevening_account.settings')
            ->get('otp_expired_time'),
        'next_send' => $time + \Drupal::config('goodevening_account.settings')
            ->get('resend_time_gap'),
      ]);
    $query->execute();
  }

  public function verifyOTP(UserInterface $user, int $otp): array {
    if ($user->isActive()) {
      return [
        'status' => FALSE,
        'message' => 'Your account is already verified.',
      ];
    }

    $connect = \Drupal::database();
    $query = $connect->select('goodevening_account')
      ->condition('uid', $user->id())
      ->condition('otp_code', $otp);
    $query->fields('goodevening_account', [
      'uid',
      'otp_code',
      'status',
      'created',
      'expired',
    ]);
    $record = $query->execute()->fetchAssoc();
    $result = [
      'status' => TRUE,
      'message' => '',
    ];
    if (empty($result)) {
      $result['status'] = FALSE;
      $result['message'] = 'OTP does not correct';
      return $result;
    }

    if ((int) $record['expired'] < time()) {
      $result['status'] = FALSE;
      $result['message'] = 'OTP was expired';
      return $result;
    }
    return $result;
  }

  protected function updateOtp(UserInterface $user, int $otp, int $current_time, bool $reset = FALSE): int|null {
    $query = \Drupal::database()
      ->update('goodevening_account')
      ->condition('uid', $user->id());
    $fields = [
      'otp_code' => $otp,
      'created' => $current_time,
      'status' => '1',
      'expired' => $current_time + (int) \Drupal::config('goodevening_account.settings')
          ->get('otp_expired_time'),
      'next_send' => $current_time + \Drupal::config('goodevening_account.settings')
          ->get('resend_time_gap'),
    ];
    if (!$reset) {
      $query = $query->condition('resend_counter', \Drupal::config('goodevening_account.settings')
        ->get('resend_otp_limitation'), '<')
        ->expression('resend_counter', 'resend_counter + 1')
        ->condition('next_send', $current_time, '<');
    }
    else {
      $fields['resend_counter'] = 1;
    }
    $query->fields($fields);
    return $query->execute();
  }

}

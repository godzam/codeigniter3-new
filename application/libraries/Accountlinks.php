<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH.'src/autoload.php';

use App\Auth\Tokens;

/**
 * Makes the e-mailed links for an account and sends them: the one that confirms
 * an address and the one that resets a password.
 *
 *   $sent = $this->accountlinks->send_verification($user);   // false when the mail could not be sent
 *
 * The e-mail never contains anything but the link and fixed text.
 *
 * @property Usertokens $usertokens
 * @property Mailer $mailer
 * @property Settings $settings
 */
class Accountlinks
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->library(['usertokens', 'mailer']);
    }

    /**
     * @param object $user row with id, name, email
     */
    public function send_verification($user)
    {
        $token = $this->CI->usertokens->issue($user->id, Tokens::VERIFY_EMAIL);

        return $this->CI->mailer->send($user->email, 'Confirm your email address', $this->body(
            $user,
            "Confirm your email address to finish setting up your account (the link works for 3 days):\n".site_url('verify-email/'.$token)
            ."\n\nIf you did not create an account, ignore this message."
        ));
    }

    /**
     * @param object $user row with id, name, email
     */
    public function send_reset($user)
    {
        $token = $this->CI->usertokens->issue($user->id, Tokens::RESET_PASSWORD);

        return $this->CI->mailer->send($user->email, 'Reset your password', $this->body(
            $user,
            "Someone asked to reset the password of your account.\n\nChoose a new password here (the link works for 1 hour):\n".site_url('reset-password/'.$token)
            ."\n\nIf that was not you, ignore this message: your password stays as it is."
        ));
    }

    /**
     * @param object $user
     */
    protected function body($user, $text)
    {
        return 'Hello '.preg_replace('/[\r\n]+/', ' ', (string) $user->name).",\n\n".$text."\n\n-- ".$this->CI->settings->get('app_name')."\n";
    }
}

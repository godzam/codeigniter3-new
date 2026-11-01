<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
| Mail settings for CodeIgniter's Email class, all from .env (nothing secret in this file):
|
|   MAIL_PROTOCOL   mail (PHP's mail(), default) | smtp | sendmail | log (development only: writes
|                   every e-mail to application/logs/mail-YYYY-MM-DD.log instead of sending it)
|   SMTP_HOST, SMTP_PORT (587), SMTP_USER, SMTP_PASS, SMTP_CRYPTO (tls | ssl | empty)
|   MAIL_FROM_ADDRESS, MAIL_FROM_NAME   defaults for Settings -> Accounts & e-mail
|
| `php index.php console mailtest you@example.com` sends a test message.
*/
$protocol = strtolower((string) (getenv('MAIL_PROTOCOL') ?: 'mail'));

$config['protocol'] = in_array($protocol, ['smtp', 'sendmail'], true) ? $protocol : 'mail';
$config['mailtype'] = 'text';
$config['charset'] = 'utf-8';
$config['wordwrap'] = true;
$config['newline'] = "\r\n";
$config['crlf'] = "\r\n";

$config['smtp_host'] = (string) (getenv('SMTP_HOST') ?: '');
$config['smtp_port'] = (int) (getenv('SMTP_PORT') ?: 587);
$config['smtp_user'] = (string) (getenv('SMTP_USER') ?: '');
$config['smtp_pass'] = (string) (getenv('SMTP_PASS') ?: '');
$config['smtp_crypto'] = in_array((string) getenv('SMTP_CRYPTO'), ['tls', 'ssl'], true) ? (string) getenv('SMTP_CRYPTO') : '';
$config['smtp_timeout'] = 10;

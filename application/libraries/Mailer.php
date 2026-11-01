<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Sends the application's e-mails (verification links, password resets).
 *
 *   $this->load->library('mailer');
 *   $ok = $this->mailer->send('ann@example.com', 'Subject', "Plain text body");
 *
 * Transport comes from .env (see application/config/email.php). In development
 * MAIL_PROTOCOL=log writes each message to application/logs/mail-DATE.log so
 * links can be followed without a mail server; that mode is refused in production,
 * because those files would contain working links.
 *
 * send() never throws and never echoes: it returns false and logs the reason, so a
 * mail outage cannot break (or reveal anything through) the page that asked.
 *
 * @property CI_Email $email
 */
class Mailer
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /**
     * @return string mail | smtp | sendmail | log
     */
    public function transport()
    {
        $protocol = strtolower((string) (getenv('MAIL_PROTOCOL') ?: 'mail'));

        return in_array($protocol, ['mail', 'smtp', 'sendmail', 'log'], true) ? $protocol : 'mail';
    }

    /**
     * Whether a message could plausibly be delivered (SMTP needs a host; log is development only).
     */
    public function configured()
    {
        switch ($this->transport()) {
            case 'smtp':
                return (string) getenv('SMTP_HOST') !== '';
            case 'log':
                return ENVIRONMENT !== 'production';
            default:
                return true;
        }
    }

    public function from_address()
    {
        $address = trim((string) $this->CI->settings->get('email_from_address'));
        if ($address === '') {
            $address = trim((string) getenv('MAIL_FROM_ADDRESS'));
        }
        if ($address === '' || filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            $host = preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $address = 'no-reply@'.(preg_match('/^[A-Za-z0-9.-]+$/', $host) ? $host : 'localhost');
        }

        return $address;
    }

    public function from_name()
    {
        $name = trim((string) $this->CI->settings->get('email_from_name'));
        if ($name === '') {
            $name = trim((string) getenv('MAIL_FROM_NAME'));
        }

        return $name !== '' ? $name : (string) $this->CI->settings->get('app_name');
    }

    /**
     * @param string $to      one validated address
     * @param string $subject a fixed subject (no user input)
     * @param string $body    plain text
     */
    public function send($to, $subject, $body)
    {
        $to = trim((string) $to);
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false || preg_match('/[\r\n]/', $to.$subject)) {
            log_message('error', 'Mailer: refused an unusable recipient or subject.');

            return false;
        }

        if (!$this->configured()) {
            log_message('error', 'Mailer: the "'.$this->transport().'" transport is not usable here (set MAIL_PROTOCOL and SMTP_HOST in .env).');

            return false;
        }

        try {
            if ($this->transport() === 'log') {
                return $this->write_log($to, $subject, $body);
            }

            $this->CI->load->library('email');
            $this->CI->email->clear();
            $this->CI->email->from($this->from_address(), $this->from_name());
            $this->CI->email->to($to);
            $this->CI->email->subject($subject);
            $this->CI->email->message($body);

            if (!$this->CI->email->send(false)) {
                log_message('error', 'Mailer: sending failed ('.$this->transport().'): '.trim(strip_tags($this->CI->email->print_debugger(['headers']))));

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            log_message('error', 'Mailer: '.get_class($e).': '.$e->getMessage());

            return false;
        }
    }

    protected function write_log($to, $subject, $body)
    {
        $file = APPPATH.'logs/mail-'.date('Y-m-d').'.log';
        $entry = '=== '.date('c').PHP_EOL.'To: '.$to.PHP_EOL.'From: '.$this->from_name().' <'.$this->from_address().'>'.PHP_EOL
            .'Subject: '.$subject.PHP_EOL.PHP_EOL.$body.PHP_EOL.PHP_EOL;

        return @file_put_contents($file, $entry, FILE_APPEND | LOCK_EX) !== false;
    }
}

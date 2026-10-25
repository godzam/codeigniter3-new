<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Email Configuration
|--------------------------------------------------------------------------
| This file contains the configuration for sending email.
|
| Note: This is read by the Email class when the 'mail' driver is used.
| Adjust the values below for your mail server.
|
| For Gmail/Google Workspace, see:
| https://github.com/bcit/CodeIgniter3-Email-Class/wiki/Gmail-SMTP-Configuration
|
--------------------------------------------------------------------------
*/

$config['mailtype'] = 'text'; // or 'html'
$config['charset'] = 'utf-8';
$config['wordwrap'] = TRUE;
$config['wrap'] = "76";
$config['mailpath'] = '/usr/sbin/sendmail'; // for sendmail

// SMTP Configuration
$config['protocol'] = 'smtp';
$config['smtp_host'] = 'smtp.gmail.com';
$config['smtp_port'] = 587;
$config['smtp_user'] = 'your-email@gmail.com';
$config['smtp_pass'] = 'your-app-password';
$config['smtp_crypto'] = 'tls';
$config['smtp_timeout'] = 30;
$config['smtp_debug'] = FALSE; // Set to 2 for full debugging

// Set this to TRUE if you want to use SMTP even if sendmail is available
$config['smtp_mode'] = 'smtp';

// CRLF may also be specified
$config['CRLF'] = "\r\n";

// New line character used to separate multiple recipients
$config['newline'] = "\r\n";

// Read timeout in seconds for generating headers
$config['validate'] = TRUE;
$config['priority'] = 3;
$config['crlf'] = "\r\n";
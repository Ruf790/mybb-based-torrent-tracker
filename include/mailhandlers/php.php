<?php
declare(strict_types=1);


if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

class PhpMail extends MailHandler
{
    /**
     * Additional parameters to pass to PHP's mail() function.
     */
    public string $additional_parameters = '';

    /**
     * Path where the sendmail program can be found.
     */
    public string $sendmail = '';

    /**
     * Which "From:" address should be used when sending via sendmail.
     */
    public string $sendmail_from = '';

    /**
     * Sends the email.
     *
     * @return bool Whether the email was sent successfully.
     */
    public function send(): bool
    {
        global $lang, $mybb;

        // Fix sendmail_from if it doesn't match admin email
        $this->sendmail_from = (string) ini_get('sendmail_from');
        if ($this->sendmail_from !== $mybb->settings['adminemail']) {
            ini_set('sendmail_from', $mybb->settings['adminemail']);
        }

        // Temporarily fix PHP_SELF when sending from AdminCP
        $temp_script_path = null;
        $dir = "/{$mybb->config['admin_dir']}/";
        $pos = strrpos($_SERVER['PHP_SELF'], $dir);
        if (defined('IN_ADMINCP') && $pos !== false) {
            $temp_script_path    = $_SERVER['PHP_SELF'];
            $_SERVER['PHP_SELF'] = substr($_SERVER['PHP_SELF'], $pos + strlen($dir) - 1);
        }

        // safe_mode removed in PHP 8 — always send additional parameters
        $sent = @mail(
            $this->to,
            $this->subject,
            $this->message,
            trim($this->headers),
            $this->additional_parameters !== '' ? $this->additional_parameters : null
        );

        if ($temp_script_path !== null) {
            $_SERVER['PHP_SELF'] = $temp_script_path;
        }

        if (!$sent) {
            $this->fatal_error('MyBB was unable to send the email using the PHP mail() function.');
            return false;
        }

        return true;
    }
}
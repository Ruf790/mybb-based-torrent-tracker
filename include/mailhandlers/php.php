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
        global $SITEEMAIL;

        // The site's address as sendmail_from (Windows: mail() talks to the SMTP server from php.ini)
        $site_email = (string) ($SITEEMAIL ?? '');
        $this->sendmail_from = (string) ini_get('sendmail_from');
        if ($site_email !== '' && $this->sendmail_from !== $site_email) {
            ini_set('sendmail_from', $site_email);
        }

        // (strict_types: the 5th argument must be a string, never null)
        $sent = @mail(
            $this->to,
            $this->subject,
            $this->message,
            trim($this->headers),
            $this->additional_parameters
        );

        if (!$sent) {
            $this->fatal_error('MyBB was unable to send the email using the PHP mail() function.');
            return false;
        }

        return true;
    }
}
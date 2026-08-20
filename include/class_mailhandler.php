<?php
declare(strict_types=1);

/**
 * Base mail handler class.
 */
class MailHandler
{
    public string $to           = '';
    public int    $show_errors  = 1;
    public string $from         = '';
    public string $from_named   = '';
    public string $return_email = '';
    public string $subject      = '';
    public string $orig_subject = '';
    public string $message      = '';
    public string $headers      = '';
    public string $charset      = 'utf-8';
    public string $delimiter    = "\r\n";
    public string $parse_format = 'text';
    public string $data         = '';

    /**
     * Last received response code — stored as string ("220", "250" etc.)
     */
    public string $code = '';

    private ?object $db = null;

    public function __construct(?object $db = null)
    {
        if ($db !== null) {
            $this->db = $db;
        }
    }

    public function get_from_email(): string
    {
        global $SITEEMAIL;
        return trim((string)($SITEEMAIL ?? '')) ?: 'admin@example.com';
    }

    public function build_message(
        string $to,
        string $subject,
        string $message,
        string $from         = '',
        string $charset      = '',
        string $headers      = '',
        string $format       = 'text',
        string $message_text = '',
        string $return_email = ''
    ): void {
        global $SITENAME;

        $this->message = '';
        $this->headers = $headers;

        if ($from) {
            $this->from       = $from;
            $this->from_named = $from;
        } else {
            $this->from       = $this->get_from_email();
            $this->from_named = '"' . $this->utf8_encode($SITENAME) . '"';
            $this->from_named .= ' <' . $this->from . '>';
        }

        $this->return_email = $return_email ?: $this->get_from_email();

        $this->set_to($to);
        $this->set_subject($subject);

        if ($charset) {
            $this->set_charset($charset);
        }

        $this->parse_format = $format;
        $this->set_common_headers();
        $this->set_message($message, $message_text);
    }

    public function set_charset(string $charset): void
    {
        $this->charset = $charset;
    }

    public function set_message(string $message, string $message_text = ''): void
    {
        $message = $this->cleanup_crlf($message);
        if ($message_text) {
            $message_text = $this->cleanup_crlf($message_text);
        }

        if ($this->parse_format === 'html' || $this->parse_format === 'both') {
            $this->set_html_headers($message, $message_text);
        } else {
            $this->message = $message;
            $this->set_plain_headers();
        }
    }

    public function set_subject(string $subject): void
    {
        $this->orig_subject = $this->cleanup($subject);
        $this->subject      = $this->utf8_encode($this->orig_subject);
    }

    public function set_to(string $to): void
    {
        $this->to = $this->cleanup($to);
    }

    public function set_plain_headers(): void
    {
        $this->headers .= "Content-Type: text/plain; charset={$this->charset}{$this->delimiter}";
    }

    public function set_html_headers(string $message, string $message_text = ''): void
    {
        if (!$message_text && $this->parse_format === 'both') {
            $message_text = strip_tags($message);
        }

        if ($this->parse_format === 'both') {
            $mime_boundary = '=_NextPart' . md5((string) TIMENOW);

            $this->headers .= "Content-Type: multipart/alternative; boundary=\"{$mime_boundary}\"{$this->delimiter}";
            $this->message  = "This is a multi-part message in MIME format.{$this->delimiter}{$this->delimiter}";

            $this->message .= "--{$mime_boundary}{$this->delimiter}";
            $this->message .= "Content-Type: text/plain; charset=\"{$this->charset}\"{$this->delimiter}";
            $this->message .= "Content-Transfer-Encoding: 8bit{$this->delimiter}{$this->delimiter}";
            $this->message .= $message_text . "{$this->delimiter}{$this->delimiter}";

            $this->message .= "--{$mime_boundary}{$this->delimiter}";
            $this->message .= "Content-Type: text/html; charset=\"{$this->charset}\"{$this->delimiter}";
            $this->message .= "Content-Transfer-Encoding: 8bit{$this->delimiter}{$this->delimiter}";
            $this->message .= $message . "{$this->delimiter}{$this->delimiter}";
            $this->message .= "--{$mime_boundary}--{$this->delimiter}{$this->delimiter}";
        } else {
            $this->headers .= "Content-Type: text/html; charset=\"{$this->charset}\"{$this->delimiter}";
            $this->headers .= "Content-Transfer-Encoding: 8bit{$this->delimiter}{$this->delimiter}";
            $this->message  = $message . "{$this->delimiter}{$this->delimiter}";
        }
    }

    public function set_common_headers(): void
    {
        $this->headers .= "From: {$this->from_named}{$this->delimiter}";

        if ($this->return_email) {
            $this->headers .= "Return-Path: {$this->return_email}{$this->delimiter}";
            $this->headers .= "Reply-To: {$this->return_email}{$this->delimiter}";
        }

        $http_host = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? 'unknown.local';
        $msg_id    = md5(uniqid((string) TIMENOW, true)) . '@' . $http_host;

        $this->headers .= "Message-ID: <{$msg_id}>{$this->delimiter}";
        $this->headers .= "Content-Transfer-Encoding: 8bit{$this->delimiter}";
        $this->headers .= "X-Priority: 3{$this->delimiter}";
        $this->headers .= "X-Mailer: MyBB{$this->delimiter}";
        $this->headers .= "MIME-Version: 1.0{$this->delimiter}";
    }

    public function fatal_error(string $error): void
    {
        if (!$this->db) {
            global $db;
            if (isset($db) && is_object($db)) {
                $this->db = $db;
            }
        }

        if (!$this->db) {
            error_log('Email error (DB not available): ' . $error . ' | Subject: ' . $this->orig_subject);
            return;
        }

        $this->db->sql_query_prepared(
            'INSERT INTO mailerrors (subject, message, toaddress, fromaddress, dateline, error, smtperror, smtpcode) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $this->orig_subject,
                $this->message,
                $this->to,
                $this->from,
                TIMENOW,
                $error,
                $this->data,
                $this->code,
            ]
        );
    }

    public function cleanup(string $string): string
    {
        return trim(str_replace(["\r", "\n", "\r\n"], '', $string));
    }

    public function cleanup_crlf(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r",    "\n", $text);
        return str_replace("\n", "\r\n", $text);
    }

    public function utf8_encode(string $string): string
    {
        if (strtolower($this->charset) === 'utf-8' && preg_match('/[^\x20-\x7E]/', $string)) {
            $chunk_size = 47;
            $len        = strlen($string);
            $output     = '';
            $pos        = 0;

            while ($pos < $len) {
                $newpos = min($pos + $chunk_size, $len);
                while ($newpos !== $len && isset($string[$newpos]) && ord($string[$newpos]) >= 0x80 && ord($string[$newpos]) < 0xC0) {
                    $newpos--;
                }
                $output .= ' =?UTF-8?B?' . base64_encode(substr($string, $pos, $newpos - $pos)) . "?=\n";
                $pos = $newpos;
            }
            return trim($output);
        }
        return $string;
    }

    public function getDb(): ?object { return $this->db; }
    public function setDb(object $db): void { $this->db = $db; }
}
<?php


declare(strict_types=1);

/**
 * Base mail handler class.
 */
class MailHandler
{
    /**
     * Which email it should send to.
     */
    public string $to = '';

    /**
     * 1/0 value whether it should show errors or not.
     */
    public int $show_errors = 1;

    /**
     * Who it is from.
     */
    public string $from = '';

    /**
     * Full from string including name in format "name" <email>
     */
    public string $from_named = '';

    /**
     * Who the email should return to.
     */
    public string $return_email = '';

    /**
     * The subject of mail.
     */
    public string $subject = '';

    /**
     * The unaltered subject of mail.
     */
    public string $orig_subject = '';

    /**
     * The message of the mail.
     */
    public string $message = '';

    /**
     * The headers of the mail.
     */
    public string $headers = '';

    /**
     * The charset of the mail.
     * @default utf-8
     */
    public string $charset = "utf-8";

    /**
     * The currently used delimiter new lines.
     */
    public string $delimiter = "\r\n";

    /**
     * How it should parse the email (HTML or plain text?)
     */
    public string $parse_format = 'text';

    /**
     * The last received response from the SMTP server.
     */
    public string $data = '';

    /**
     * The last received response code from the SMTP server.
     */
    public int $code = 0;

    /**
     * Database connection (injected dependency)
     */
    private ?object $db = null;

    /**
     * Constructor with optional dependency injection
     */
    public function __construct(?object $db = null)
    {
        if ($db !== null) {
            $this->db = $db;
        }
        
    }

    /**
     * Selects between AdminEmail and ReturnEmail, dependent on if ReturnEmail is filled.
     */
    public function get_from_email(): string
    {
        global $mybb;
        
        $returnemail = "";
        $adminemail = "admin@example.com";
        
        return trim($returnemail) ?: $adminemail;
    }

    /**
     * Builds the whole mail.
     * To be used by the different email classes later.
     *
     * @param string $to to email.
     * @param string $subject subject of email.
     * @param string $message message of email.
     * @param string $from from email.
     * @param string $charset charset of email.
     * @param string $headers headers of email.
     * @param string $format format of the email (HTML, plain text, or both?).
     * @param string $message_text plain text version of the email.
     * @param string $return_email the return email address.
     */
    public function build_message(
        string $to,
        string $subject,
        string $message,
        string $from = "",
        string $charset = "",
        string $headers = "",
        string $format = "text",
        string $message_text = "",
        string $return_email = ""
    ): void {
        global $SITENAME;

        $this->message = '';
        $this->headers = $headers;
        
        if ($from) {
            $this->from = $from;
            $this->from_named = $this->from;
        } else {
            $this->from = $this->get_from_email();
            $this->from_named = '"' . $this->utf8_encode($SITENAME) . '"';
            $this->from_named .= " <" . $this->from . ">";
        }

        if ($return_email) {
            $this->return_email = $return_email;
        } else {
            $this->return_email = $this->get_from_email();
        }

        $this->set_to($to);
        $this->set_subject($subject);

        if ($charset) {
            $this->set_charset($charset);
        }

        $this->parse_format = $format;
        $this->set_common_headers();
        $this->set_message($message, $message_text);
    }

    /**
     * Sets the charset.
     */
    public function set_charset(string $charset): void
    {
        $this->charset = $charset;
    }

    /**
     * Sets and formats the email message.
     */
    public function set_message(string $message, string $message_text = ""): void
    {
        $message = $this->cleanup_crlf($message);

        if ($message_text) {
            $message_text = $this->cleanup_crlf($message_text);
        }

        if ($this->parse_format == "html" || $this->parse_format == "both") {
            $this->set_html_headers($message, $message_text);
        } else {
            $this->message = $message;
            $this->set_plain_headers();
        }
    }

    /**
     * Sets and formats the email subject.
     */
    public function set_subject(string $subject): void
    {
        $this->orig_subject = $this->cleanup($subject);
        $this->subject = $this->utf8_encode($this->orig_subject);
    }

    /**
     * Sets and formats the recipient address.
     */
    public function set_to(string $to): void
    {
        $this->to = $this->cleanup($to);
    }

    /**
     * Sets the plain headers, text/plain
     */
    public function set_plain_headers(): void
    {
        $this->headers .= "Content-Type: text/plain; charset={$this->charset}{$this->delimiter}";
    }

    /**
     * Sets the alternative headers, text/html and text/plain.
     */
    public function set_html_headers(string $message, string $message_text = ""): void
    {
        if (!$message_text && $this->parse_format == 'both') {
            $message_text = strip_tags($message);
        }

        if ($this->parse_format == 'both') {
            $mime_boundary = "=_NextPart" . md5((string)TIMENOW);

            $this->headers .= "Content-Type: multipart/alternative; boundary=\"{$mime_boundary}\"{$this->delimiter}";
            $this->message = "This is a multi-part message in MIME format.{$this->delimiter}{$this->delimiter}";

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
            $this->message = $message . "{$this->delimiter}{$this->delimiter}";
        }
    }

    /**
     * Sets the common headers.
     */
    public function set_common_headers(): void
    {
        // Build mail headers
        $this->headers .= "From: {$this->from_named}{$this->delimiter}";

        if ($this->return_email) {
            $this->headers .= "Return-Path: {$this->return_email}{$this->delimiter}";
            $this->headers .= "Reply-To: {$this->return_email}{$this->delimiter}";
        }

        $http_host = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? "unknown.local";

        $msg_id = md5(uniqid((string)TIMENOW, true)) . "@" . $http_host;

        $mail_message_id = "1";
        
        if ($mail_message_id) {
            $this->headers .= "Message-ID: <{$msg_id}>{$this->delimiter}";
        }
        
        $this->headers .= "Content-Transfer-Encoding: 8bit{$this->delimiter}";
        $this->headers .= "X-Priority: 3{$this->delimiter}";
        $this->headers .= "X-Mailer: MyBB{$this->delimiter}";
        $this->headers .= "MIME-Version: 1.0{$this->delimiter}";
    }

    /**
     * Log a fatal error message to the database.
     *
     * @throws RuntimeException if database insertion fails
     */
    public function fatal_error(string $error): void
    {
        // Попробуем получить соединение с БД если оно еще не установлено
        if (!$this->db) {
            global $db;
            if (isset($db) && is_object($db)) {
                $this->db = $db;
            }
        }
        
        if (!$this->db) {
            // Если БД все еще недоступна, просто логируем ошибку в файл или ничего не делаем
            error_log("Email error (DB not available): " . $error . " | Subject: " . $this->orig_subject);
            return; // Выходим без выбрасывания исключения
        }

        $mail_error = [
            "subject" => $this->db->escape_string($this->orig_subject),
            "message" => $this->db->escape_string($this->message),
            "toaddress" => $this->db->escape_string($this->to),
            "fromaddress" => $this->db->escape_string($this->from),
            "dateline" => TIMENOW,
            "error" => $this->db->escape_string($error),
            "smtperror" => $this->db->escape_string($this->data),
            "smtpcode" => $this->code
        ];
        
        $result = $this->db->insert_query("mailerrors", $mail_error);
        
        if (!$result) {
            // Не выбрасываем исключение, а просто логируем
            error_log("Failed to log email error to database");
        }
    }

    /**
     * Rids pesky characters from subjects, recipients, from addresses etc (prevents mail injection too)
     *
     * @return string The cleaned string
     */
    public function cleanup(string $string): string
    {
        $string = str_replace(["\r", "\n", "\r\n"], "", $string);
        return trim($string);
    }

    /**
     * Converts message text to suit the correct delimiter
     * See dev.mybb.com/issues/1735 (Jorge Oliveira)
     *
     * @return string The converted string
     */
    public function cleanup_crlf(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);
        $text = str_replace("\n", "\r\n", $text);

        return $text;
    }

    /**
     * Encode a string based on the character set enabled. Used to encode subjects
     * and recipients in email messages going out so that they show up correctly
     * in email clients.
     *
     * @return string The encoded string.
     */
    public function utf8_encode(string $string): string
    {
        if (strtolower($this->charset) == 'utf-8' && preg_match('/[^\x20-\x7E]/', $string)) {
            $chunk_size = 47; // Derived from floor((75 - strlen("=?UTF-8?B??=")) * 0.75);
            $len = strlen($string);
            $output = '';
            $pos = 0;

            while ($pos < $len) {
                $newpos = min($pos + $chunk_size, $len);

                if ($newpos != $len) {
                    while (isset($string[$newpos]) && ord($string[$newpos]) >= 0x80 && ord($string[$newpos]) < 0xC0) {
                        // Reduce len until it's safe to split UTF-8.
                        $newpos--;
                    }
                }

                $chunk = substr($string, $pos, $newpos - $pos);
                $pos = $newpos;

                $output .= " =?UTF-8?B?" . base64_encode($chunk) . "?=\n";
            }
            return trim($output);
        }
        return $string;
    }

    /**
     * Getter for database connection (for testing purposes)
     */
    public function getDb(): ?object
    {
        return $this->db;
    }
    
    /**
     * Setter for database connection
     */
    public function setDb(object $db): void
    {
        $this->db = $db;
    }
}
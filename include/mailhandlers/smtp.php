<?php
declare(strict_types=1);


if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

defined('MYBB_SSL') || define('MYBB_SSL', 1);
defined('MYBB_TLS') || define('MYBB_TLS', 2);

class SmtpMail extends MailHandler
{
    /** @var resource|false */
    public $connection = false;

    public string  $username      = '';
    public string  $password      = '';
    public string  $helo          = 'localhost';
    public bool    $authenticated = false;
    public int     $timeout       = 5;
    public int     $status        = 0;
    public int     $port          = 25;
    public int     $secure_port   = 465;
    public string  $host          = '';
    public string  $last_error    = '';
    public bool    $keep_alive    = false;
    public bool    $use_tls       = false;

    public function __construct()
    {
        global $secure_smtp, $smtp_host, $smtp_pass, $smtp_port, $smtp_user;

        $protocol = match((int) $secure_smtp) {
            MYBB_SSL => 'ssl://',
            MYBB_TLS => (function() { $this->use_tls = true; return ''; })(),
            default  => '',
        };

        $this->host = !empty($smtp_host)
            ? $smtp_host
            : (string) ini_get('SMTP');

        // Determine HELO hostname
        $local = ['127.0.0.1', '::1', 'localhost'];
        if (!in_array($this->host, $local, true)) {
            if (($hostname = gethostname()) !== false) {
                $this->helo = $hostname;
            } elseif (!empty($helo = php_uname('n'))) {
                $this->helo = $helo;
            } elseif (!empty($_SERVER['SERVER_NAME'])) {
                $this->helo = $_SERVER['SERVER_NAME'];
            }
        }

        $this->host = $protocol . $this->host;

        $ini_port = (int) ini_get('smtp_port');
        $this->port = match(true) {
            !empty($smtp_port)              => (int) $smtp_port,
            empty($smtp_port) && $ini_port  => $ini_port,
            !empty($protocol)               => $this->secure_port,
            default                         => $this->port,
        };

        $this->username = (string) $smtp_user;
        $this->password = (string) $smtp_pass;
    }

    public function send(): bool
    {
        global $lang, $mybb;

        if (!$this->connected() && !$this->connect()) {
            $this->close();
            return false;
        }

        if (!$this->connected()) {
            return false;
        }

        if (!$this->send_data('MAIL FROM:<' . $this->from . '>', 250)) {
            $this->fatal_error('The mail server does not understand the MAIL FROM command. Reason: ' . $this->get_error());
            return false;
        }

        foreach (explode(',', $this->to) as $to) {
            $to = trim($to);
            if (!$this->send_data('RCPT TO:<' . $to . '>', 250)) {
                $this->fatal_error('The mail server does not understand the RCPT TO command. Reason: ' . $this->get_error());
                return false;
            }
        }

        if (!$this->send_data('DATA', 354)) {
            $this->fatal_error('The mail server did not understand the DATA command');
            return false;
        }

        $this->send_data('Date: ' . gmdate('r'));
        $this->send_data('To: ' . $this->to);
        $this->send_data('Subject: ' . $this->subject);

        if (trim($this->headers) !== '') {
            $this->send_data(trim($this->headers));
        }

        $this->send_data('');
        $this->send_data(str_replace("\n.", "\n..", $this->message));

        if (!$this->send_data('.', 250)) {
            $this->fatal_error('Mail may not be delivered. Reason: ' . $this->get_error());
        }

        if (!$this->keep_alive) {
            $this->close();
        }

        return true;
    }

    public function connect(): bool
    {
        $this->connection = @fsockopen($this->host, $this->port, $error_number, $error_string, $this->timeout);

        if (!is_resource($this->connection)) {
            $this->fatal_error("Unable to connect to the mail server with the given details. Reason: {$error_number}: {$error_string}");
            return false;
        }

        // Set timeout (skip on Windows — DIRECTORY_SEPARATOR is \\)
        if (DIRECTORY_SEPARATOR !== '\\') {
            stream_set_timeout($this->connection, $this->timeout, 0);
        }

        $this->status = 1;
        $this->get_data();

        if (!$this->check_status('220')) {
            $this->fatal_error('The mail server is not ready, it did not respond with a 220 status message.');
            return false;
        }

        $helo = ($this->use_tls || ($this->username !== '' && $this->password !== '')) ? 'EHLO' : 'HELO';
        $data = $this->send_data("{$helo} {$this->helo}", 250);

        if (!$data) {
            $this->fatal_error("The server did not understand the {$helo} command");
            return false;
        }

        // STARTTLS
        if ($this->use_tls && preg_match('#250( |-)STARTTLS#mi', $data)) {
            if (!$this->send_data('STARTTLS', 220)) {
                $this->fatal_error('The server did not understand the STARTTLS command. Reason: ' . $this->get_error());
                return false;
            }

            $crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT
                           | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
                           | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;

            if (!@stream_socket_enable_crypto($this->connection, true, $crypto_method)) {
                $this->fatal_error('Failed to start TLS encryption');
                return false;
            }

            $data = $this->send_data("{$helo} {$this->helo}", 250);
            if (!$data) {
                $this->fatal_error('The server did not understand the EHLO command');
                return false;
            }
        }

        // AUTH
        if ($this->username !== '' && $this->password !== '') {
            if (!preg_match('#250( |-)AUTH( |=)(.+)$#mi', $data, $matches)) {
                $this->fatal_error('The server did not understand the AUTH command');
                return false;
            }
            if (!$this->auth($matches[3])) {
                return false;
            }
        }

        return true;
    }

    public function auth(string $auth_methods): bool
    {
        $methods = explode(' ', trim($auth_methods));

        if (in_array('LOGIN', $methods, true)) {
            if (!$this->send_data('AUTH LOGIN', 334)) {
                if ($this->code == 503) return true;
                $this->fatal_error('The SMTP server did not respond correctly to the AUTH LOGIN command');
                return false;
            }
            if (!$this->send_data(base64_encode($this->username), 334)) {
                $this->fatal_error('The SMTP server rejected the supplied SMTP username. Reason: ' . $this->get_error());
                return false;
            }
            if (!$this->send_data(base64_encode($this->password), 235)) {
                $this->fatal_error('The SMTP server rejected the supplied SMTP password. Reason: ' . $this->get_error());
                return false;
            }

        } elseif (in_array('PLAIN', $methods, true)) {
            if (!$this->send_data('AUTH PLAIN', 334)) {
                if ($this->code == 503) return true;
                $this->fatal_error('The SMTP server did not respond correctly to the AUTH PLAIN command');
                return false;
            }
            $auth = base64_encode(chr(0) . $this->username . chr(0) . $this->password);
            if (!$this->send_data($auth, 235)) {
                $this->fatal_error('The SMTP server rejected the supplied login username and password. Reason: ' . $this->get_error());
                return false;
            }

        } elseif (in_array('CRAM-MD5', $methods, true)) {
            $data = $this->send_data('AUTH CRAM-MD5', 334);
            if (!$data) {
                if ($this->code == 503) return true;
                $this->fatal_error('The SMTP server did not respond correctly to the AUTH CRAM-MD5 command');
                return false;
            }
            $challenge = base64_decode(substr($data, 4));
            $auth      = base64_encode($this->username . ' ' . $this->cram_md5_response($this->password, $challenge));
            if (!$this->send_data($auth, 235)) {
                $this->fatal_error('The SMTP server rejected the supplied login username and password. Reason: ' . $this->get_error());
                return false;
            }

        } else {
            $this->fatal_error('The SMTP server does not support any of the AUTH methods that MyBB supports');
            return false;
        }

        return true;
    }

    public function get_data(): string
    {
        $string = '';
        while (($line = fgets($this->connection, 515)) !== false) {
            $string .= $line;
            if ($line[3] === ' ') {
                break;
            }
        }
        $string     = trim($string);
        $this->data = $string;
        $this->code = substr($string, 0, 3);
        return $string;
    }

    public function connected(): bool
    {
        return $this->status === 1;
    }

    public function send_data(string $data, int|false $status_num = false): string|bool
    {
        if (!$this->connected()) {
            return false;
        }

        if (!fwrite($this->connection, $data . "\r\n")) {
            $this->fatal_error('Unable to send the data to the SMTP server');
            return false;
        }

        if ($status_num !== false) {
            $rec = $this->get_data();
            if ($this->check_status($status_num)) {
                return $rec;
            }
            $this->set_error($rec);
            return false;
        }

        return true;
    }

    public function check_status(int|string $status_num): string|false
    {
        return $this->code == $status_num ? $this->data : false;
    }

    public function close(): void
    {
        if ($this->status === 1) {
            $this->send_data('QUIT');
            fclose($this->connection);
            $this->status = 0;
        }
    }

    public function get_error(): string
    {
        return $this->last_error ?: 'N/A';
    }

    public function set_error(string $error): void
    {
        $this->last_error = $error;
    }

    public function cram_md5_response(string $password, string $challenge): string
    {
        if (strlen($password) > 64) {
            $password = pack('H32', md5($password));
        }
        $password = str_pad($password, 64, chr(0));

        $k_ipad = substr($password, 0, 64) ^ str_repeat(chr(0x36), 64);
        $k_opad = substr($password, 0, 64) ^ str_repeat(chr(0x5C), 64);

        return md5($k_opad . pack('H32', md5($k_ipad . $challenge)));
    }
}
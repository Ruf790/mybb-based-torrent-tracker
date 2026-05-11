<?php
declare(strict_types=1);

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

class UserDataHandler extends DataHandler
{
    public $language_file = 'datahandler_user';
	public $language_prefix = 'userdata';
	
	
    public array  $user_insert_data = [];
    public array  $user_update_data = [];
    public int    $uid              = 0;
    public array  $return_values    = [];
    public $delete_uids = [];
    public int    $deleted_users    = 0;

    // ── Validation helpers ────────────────────────────────────────────────────

    public function verify_username(): bool
    {
        global $mybb, $maxnamelength, $minnamelength;

        $username = &$this->data['username'];
        require_once INC_PATH . '/functions_user.php';

        $username = trim_blank_chrs($username);
        $username = str_replace(
            [unichr(160), unichr(173), unichr(0xCA), dec_to_utf8(8238), dec_to_utf8(8237), dec_to_utf8(8203)],
            [' ', '-', '', '', '', ''],
            $username
        );
        $username = preg_replace('#\s{2,}#', ' ', $username);

        if ($username === '') {
            $this->set_error('missing_username');
            return false;
        }

        if (is_banned_username($username, true)) {
            $this->set_error('banned_username');
            return false;
        }

        if (
            str_contains($username, '<')  || str_contains($username, '>')  ||
            str_contains($username, '&')  || str_contains($username, '\\') ||
            str_contains($username, ';')  || str_contains($username, ',')  ||
            !validate_utf8_string($username, false, false)
        ) {
            $this->set_error('bad_characters_username');
            return false;
        }

        $len = my_strlen($username);
        if (($maxnamelength !== 0 && $len > $maxnamelength) || ($minnamelength !== 0 && $len < $minnamelength)) {
            $this->set_error('invalid_username_length', [$minnamelength, $maxnamelength]);
            return false;
        }

        return true;
    }

    public function verify_usertitle(): bool
    {
        $usertitle    = &$this->data['usertitle'];
        $maxlength    = 40;

        if ($maxlength !== 0 && my_strlen($usertitle) > $maxlength) {
            $this->set_error('invalid_usertitle_length', $maxlength);
            return false;
        }

        return true;
    }

    public function verify_invitehash(): void
    {
        global $user, $db;

        $hash = trim((string)($user['invitehash'] ?? ''));

        if ($hash === '') {
            $this->set_error('The invite code you specified is invalid!');
            return;
        }

        $code = $db->escape_string($hash);
        $now  = time();

        $q = $db->simple_select(
            'invites', '*',
            "code='{$code}' AND status='pending' AND (expires_at IS NULL OR expires_at > {$now})"
        );

        if ($db->num_rows($q)) {
            $row = $db->fetch_array($q);
            $user['invite_id'] = (int)$row['id'];
        } else {
            $this->set_error('The invite code you specified is invalid or has expired!');
        }
    }

    public function verify_username_exists(): bool
    {
        $username = &$this->data['username'];
        $user     = get_user_by_username(trim($username));

        if (!empty($this->data['id']) && !empty($user['id']) && $user['id'] == $this->data['id']) {
            unset($user);
        }

        if (!empty($user['id'])) {
            $this->set_error('username_exists', [$username]);
            return true;
        }

        return false;
    }

    public function verify_password(): bool
    {
        global $requirecomplexpasswords, $minpasswordlength, $maxpasswordlength;

        $user = &$this->data;
        $len  = my_strlen($user['password']);

        if ($len < $minpasswordlength || $len > $maxpasswordlength) {
            $this->set_error('invalid_password_length', [$minpasswordlength, $maxpasswordlength]);
            return false;
        }

        if (!empty($user['email']) && !empty($user['username'])) {
            $p = $user['password'];
            $e = $user['email'];
            $u = $user['username'];

            if (
                $e === $p || $u === $p ||
                str_contains($p, $e) || str_contains($p, $u) ||
                str_contains($e, $p) || str_contains($u, $p)
            ) {
                $this->set_error('bad_password_security');
                return false;
            }
        }

        if ($requirecomplexpasswords == 1) {
            if (!preg_match('/^.*(?=.{' . $minpasswordlength . ',})(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).*$/', $user['password'])) {
                $this->set_error('no_complex_characters', [$minpasswordlength]);
                return false;
            }
        }

        if (isset($user['password2']) && $user['password'] !== $user['password2']) {
            $this->set_error('passwords_dont_match');
            return false;
        }

        $user['loginkey']    = generate_loginkey();
        $password_fields     = create_password($user['password'], false, $user);
        $user                = array_merge($user, $password_fields);

        return true;
    }

    public function verify_usergroup(): bool
    {
        return true;
    }

    public function verify_email(): bool
    {
        $user = &$this->data;

        if (trim_blank_chrs($user['email']) === '') {
            $this->set_error('missing_email');
            return false;
        }

        if (!validate_email_format($user['email'])) {
            $this->set_error('invalid_email_format');
            return false;
        }

        if (is_banned_email($user['email'], true)) {
            $this->set_error('banned_email');
            return false;
        }

        $allowmultipleemails = '0';

        if ($allowmultipleemails == 0 && !defined('IN_ADMINCP')) {
            $uid = (int)($user['uid'] ?? 0);
            if (email_already_in_use($user['email'], $uid)) {
                $this->set_error('email_already_in_use');
                return false;
            }
        }

        if (isset($user['email2']) && $user['email'] !== $user['email2']) {
            $this->set_error('emails_dont_match');
            return false;
        }

        return true;
    }

    public function verify_birthday(): bool
    {
        $user     = &$this->data;
        $birthday = &$user['birthday'];

        if (!is_array($birthday)) {
            return true;
        }

        $birthday['day']   = (int)$birthday['day'];
        $birthday['month'] = (int)$birthday['month'];
        $birthday['year']  = (int)$birthday['year'];

        if ($birthday['day'] !== 0 || $birthday['month'] !== 0) {
            if (
                $birthday['day']   < 1  || $birthday['day']   > 31 ||
                $birthday['month'] < 1  || $birthday['month'] > 12 ||
                ($birthday['month'] == 2 && $birthday['day'] > 29)
            ) {
                $this->set_error('invalid_birthday');
                return false;
            }
        }

        $months = get_bdays($birthday['year']);
        if ($birthday['month'] !== 0 && $birthday['day'] > $months[$birthday['month'] - 1]) {
            $this->set_error('invalid_birthday');
            return false;
        }

        if (
            ($birthday['year'] !== 0 && $birthday['year'] < (date('Y') - 100)) ||
            $birthday['year'] > (int)date('Y')
        ) {
            $this->set_error('invalid_birthday');
            return false;
        }

        if ($birthday['year'] == (int)date('Y')) {
            if (
                $birthday['month'] > (int)date('m') ||
                ($birthday['month'] == (int)date('m') && $birthday['day'] > (int)date('d'))
            ) {
                $this->set_error('invalid_birthday');
                return false;
            }
        }

        $user['bday'] = match(true) {
            $birthday['year'] !== 0        => $birthday['day'] . '-' . $birthday['month'] . '-' . $birthday['year'],
            $birthday['day'] && $birthday['month'] => $birthday['day'] . '-' . $birthday['month'] . '-',
            default                        => '',
        };

        return true;
    }

    public function verify_birthday_privacy(): bool
    {
        $privacy  = &$this->data['birthdayprivacy'];
        $accepted = ['none', 'age', 'all'];

        if (!in_array($privacy, $accepted, true)) {
            $this->set_error('invalid_birthday_privacy');
            return false;
        }

        if ($privacy === 'age' && empty($this->data['birthday']['year'])) {
            $this->set_error('conflicted_birthday_privacy');
            return false;
        }

        return true;
    }

    public function verify_postnum(): bool
    {
        if (isset($this->data['postnum']) && $this->data['postnum'] < 0) {
            $this->set_error('invalid_postnum');
            return false;
        }
        return true;
    }

    public function verify_threadnum(): bool
    {
        if (isset($this->data['threadnum']) && $this->data['threadnum'] < 0) {
            $this->set_error('invalid_threadnum');
            return false;
        }
        return true;
    }



    public function verify_options(): void
    {
        global $mybb;

        $options = &$this->data['options'];

        if (!is_array($options)) {
            $options = [];
        }

        $yes_no_defaults = [
            'allownotices'       => 1, 'hideemail'         => 0, 'receivepms'        => 1,
            'receivefrombuddy'   => 0, 'pmnotice'          => 1, 'pmnotify'          => 1,
            'invisible'          => 0, 'showimages'        => 1, 'showvideos'        => 1,
            'showsigs'           => 1, 'showavatars'       => 1, 'showquickreply'    => 1,
            'showredirect'       => 1, 'showcodebuttons'   => 1, 'sourceeditor'      => 0,
            'buddyrequestspm'    => 1, 'buddyrequestsauto' => 0,
        ];

        foreach ($yes_no_defaults as $key => $default) {
            $this->verify_yesno_option($options, $key, $default);
        }

        $this->verify_yesno_option($options, 'classicpostbit', $mybb->settings['postlayout'] === 'classic' ? 1 : 0);

        if (array_key_exists('subscriptionmethod', $options)) {
            $options['subscriptionmethod'] = (int)$options['subscriptionmethod'];
            if ($options['subscriptionmethod'] < 0 || $options['subscriptionmethod'] > 3) {
                $options['subscriptionmethod'] = 0;
            }
        }

        if (array_key_exists('dstcorrection', $options)) {
            $options['dstcorrection'] = (int)$options['dstcorrection'];
            if ($options['dstcorrection'] < 0 || $options['dstcorrection'] > 2) {
                $options['dstcorrection'] = 0;
            }
            $options['dst'] = match($options['dstcorrection']) {
                1 => 1,
                0 => 0,
                default => $options['dst'] ?? 0,
            };
        }

        if ($this->method === 'insert' || (isset($options['threadmode']) && !in_array($options['threadmode'], ['linear', 'threaded', ''], true))) {
            $options['threadmode'] = '';
        }

        foreach (['tpp' => '10,15,20,25,30,40,50', 'ppp' => '5,10,15,20,25,30,40,50'] as $key => $allowed) {
            if ($this->method === 'insert' || (array_key_exists($key, $options) && $allowed)) {
                $options[$key] ??= 0;
                $exploded = explode(',', $allowed);
                asort($exploded);
                $biggest = (int)end($exploded);
                $options[$key] = min((int)$options[$key], $biggest);
            }
        }

        if ($this->method === 'insert' || array_key_exists('daysprune', $options)) {
            $options['daysprune'] = max(0, (int)($options['daysprune'] ?? 0));
        }

        $this->data['options'] = $options;
    }

    public function verify_regdate(): bool
    {
        $regdate = &$this->data['regdate'];
        $regdate = (int)$regdate;
        if ($regdate <= 0) $regdate = TIMENOW;
        return true;
    }

    public function verify_lastvisit(): bool
    {
        $lastvisit = &$this->data['lastvisit'];
        $lastvisit = (int)$lastvisit;
        if ($lastvisit <= 0) $lastvisit = TIMENOW;
        return true;
    }

    public function verify_lastactive(): bool
    {
        $lastactive = &$this->data['lastactive'];
        $lastactive = (int)$lastactive;
        if ($lastactive <= 0) $lastactive = TIMENOW;
        return true;
    }

    public function verify_language(): bool
    {
        global $lang;

        $language = &$this->data['language'];

        if ($language !== '' && !$lang->language_exists($language)) {
            $this->set_error('invalid_language');
            return false;
        }

        return true;
    }

    public function verify_style(): bool
    {
        $user = &$this->data;

        if (!empty($user['style'])) {
            $theme = get_theme($user['style']);

            if (empty($theme) || (!is_member($theme['allowedgroups'], $user) && $theme['allowedgroups'] !== 'all')) {
                $this->set_error('invalid_style');
                return false;
            }
        }

        return true;
    }

    public function verify_checkfields(): bool
    {
        $user = &$this->data;

        if ($user['regcheck1'] !== '' || $user['regcheck2'] !== 'true') {
            $this->set_error('invalid_checkfield');
            return false;
        }

        return true;
    }

    public function verify_timezone(): bool
    {
        global $timezoneoffset;

        $user      = &$this->data;
        $timezones = get_supported_timezones();

        if (!isset($user['timezone']) || !array_key_exists($user['timezone'], $timezones)) {
            $user['timezone'] = $timezoneoffset;
            return false;
        }

        return true;
    }

    public function verify_signature(): bool
    {
        global $parser;

        if (!isset($this->data['signature'])) {
            return true;
        }

        if (!isset($parser)) {
            require_once INC_PATH . '/class_parser.php';
            $parser = new postParser();
        }

        $parser_options = [
            'allow_html'     => 1, 'allow_mycode' => 1,
            'allow_smilies'  => 1, 'allow_imgcode' => 1,
            'filter_badwords' => 1,
        ];

        $parsed_sig   = $parser->parse_message($this->data['signature'], $parser_options);
        $maxsigimages = 2;
        $sigsmilies   = 1;
        $sigimgcode   = 1;

        $img_count = substr_count($parsed_sig, '<img');
        if (
            (($sigimgcode == 0 && $sigsmilies != 1) && $img_count > 0) ||
            (($sigimgcode == 1 || $sigsmilies == 1) && $img_count > $maxsigimages)
        ) {
            $this->set_error('too_many_sig_images2', [$sigimgcode == 1 ? $maxsigimages : 0]);
        }

        $sigcountmycode = 1;
        $parsed_sig = $sigcountmycode == 0
            ? $parser->text_parse_message($this->data['signature'], ['signature_parse' => '1'])
            : $this->data['signature'];

        $siglength = 255;

        if ($siglength > 0) {
            $clean    = preg_replace('#\s#', '', $parsed_sig);
            $sig_len  = my_strlen($clean);

            if ($sig_len > $siglength) {
                $this->set_error('sig_too_long', [$siglength]);
                $diff = $sig_len - $siglength;
                $this->set_error($diff > 1 ? 'sig_remove_chars_plural' : 'sig_remove_chars_singular', $diff > 1 ? [$diff] : []);
            }
        }

        return count($this->get_errors()) === 0;
    }

    // ── Main validate ─────────────────────────────────────────────────────────

    public function validate_user(): bool
    {
        global $plugins, $regtype;

        $user     = &$this->data;
        $old_user = !empty($user['uid']) ? get_user($user['uid']) : [];

        if ($this->method === 'insert' || array_key_exists('username', $user)) {
            if (!isset($old_user['username']) || $user['username'] !== $old_user['username']) {
                $this->verify_username();
                if ($regtype === 'invite') {
                    $this->verify_invitehash();
                }
                $this->verify_username_exists();
            } else {
                unset($user['username']);
            }
        }

        $checks = [
            'usertitle'      => 'verify_usertitle',
            'password'       => 'verify_password',
            'usergroup'      => 'verify_usergroup',
            'email'          => 'verify_email',
            'postnum'        => 'verify_postnum',
            'threadnum'      => 'verify_threadnum',
            'options'        => 'verify_options',
            'regdate'        => 'verify_regdate',
            'lastvisit'      => 'verify_lastvisit',
            'lastactive'     => 'verify_lastactive',
            'timezone'       => 'verify_timezone',
            'style'          => 'verify_style',
            'signature'      => 'verify_signature',
        ];

        foreach ($checks as $key => $method) {
            if ($this->method === 'insert' || array_key_exists($key, $user)) {
                $this->$method();
            }
        }

        // Birthday needs array check
        if ($this->method === 'insert' || (isset($user['birthday']) && is_array($user['birthday']))) {
            $this->verify_birthday();
        }

        if ($this->method === 'insert' && array_key_exists('regcheck1', $user) && array_key_exists('regcheck2', $user)) {
            $this->verify_checkfields();
        }

        if (array_key_exists('birthdayprivacy', $user)) {
            $this->verify_birthday_privacy();
        }

        $plugins->run_hooks('datahandler_user_validate', $this);

        $this->set_validated(true);
        return count($this->get_errors()) === 0;
    }

    // ── Insert ────────────────────────────────────────────────────────────────

    public function insert_user(): array
    {
        global $db, $cache, $plugins, $regtype, $_d_usergroup, $autogigsignup, $autosbsignup, $invite_count;

        if (!$this->get_validated()) {
            die('The user needs to be validated before inserting it into the DB.');
        }
        if (count($this->get_errors()) > 0) {
            die('The user is not valid.');
        }

        $user = &$this->data;

        foreach (['postnum', 'threadnum', 'avatar', 'avatartype', 'additionalgroups', 'displaygroup', 'bday', 'signature', 'style', 'dateformat', 'timeformat', 'notepad', 'regip', 'lastip'] as $k) {
            $user[$k] ??= '';
        }
        foreach (['subscriptionmethod', 'dstcorrection'] as $k) {
            $user['options'][$k] ??= '';
        }

        if (defined('IN_ADMINCP')) {
            $user['lastvisit'] = $user['lastactive'] = 0;
        }

        $uploaded   = $autogigsignup > 0 ? (int)$autogigsignup * 1024 * 1024 * 1024 : 0;
        $seedbonus  = $autosbsignup  > 0 ? (int)$autosbsignup : 0;
        $usergroup  = $_d_usergroup  ?: 2;
        $invites    = (int)$invite_count;
        $invited_by = 0;

        if ($regtype === 'invite') {
            $code = $db->escape_string(trim($user['invitehash'] ?? ''));
            $now  = time();
            $q    = $db->simple_select('invites', '*', "code='{$code}' AND status='pending' AND (expires_at IS NULL OR expires_at > {$now})");
            if ($db->num_rows($q)) {
                $row            = $db->fetch_array($q);
                $invited_by     = (int)$row['inviter_id'];
                $user['invite_id'] = (int)$row['id'];
            }
        }

        $this->user_insert_data = [
            'username'           => $db->escape_string($user['username']),
            'password'           => $user['password'],
            'salt'               => $user['salt'],
            'loginkey'           => $user['loginkey'],
            'uploaded'           => $uploaded,
            'seedbonus'          => $seedbonus,
            'invites'            => $invites,
            'invited_by'         => $invited_by,
            'email'              => $db->escape_string($user['email']),
            'postnum'            => (int)$user['postnum'],
            'threadnum'          => (int)$user['threadnum'],
            'avatar'             => $db->escape_string($user['avatar']),
            'avatartype'         => $db->escape_string($user['avatartype']),
            'usergroup'          => $db->escape_string((string)$usergroup),
            'additionalgroups'   => $db->escape_string($user['additionalgroups']),
            'displaygroup'       => (int)$user['displaygroup'],
            'usertitle'          => $db->escape_string(htmlspecialchars_uni($user['usertitle'])),
            'added'              => (int)$user['regdate'],
            'lastactive'         => (int)$user['lastactive'],
            'lastvisit'          => (int)$user['lastvisit'],
            'birthday'           => $user['bday'],
            'signature'          => $db->escape_string($user['signature']),
            'allownotices'       => (int)$user['options']['allownotices'],
            'hideemail'          => (int)$user['options']['hideemail'],
            'subscriptionmethod' => (int)$user['options']['subscriptionmethod'],
            'receivepms'         => (int)$user['options']['receivepms'],
            'receivefrombuddy'   => (int)$user['options']['receivefrombuddy'],
            'pmnotice'           => (int)$user['options']['pmnotice'],
            'pmnotify'           => (int)$user['options']['pmnotify'],
            'showsigs'           => (int)$user['options']['showsigs'],
            'showavatars'        => (int)$user['options']['showavatars'],
            'showredirect'       => (int)$user['options']['showredirect'],
            'invisible'          => (int)$user['options']['invisible'],
            'timezone'           => $db->escape_string($user['timezone']),
            'dstcorrection'      => (int)$user['options']['dstcorrection'],
            'threadmode'         => $user['options']['threadmode'],
            'dateformat'         => $db->escape_string($user['dateformat']),
            'timeformat'         => $db->escape_string($user['timeformat']),
            'regip'              => $db->escape_binary($user['regip']),
            'lastip'             => $db->escape_binary($user['lastip']),
            'buddyrequestspm'    => (int)$user['options']['buddyrequestspm'],
            'buddyrequestsauto'  => (int)$user['options']['buddyrequestsauto'],
            'buddylist'          => '',
            'ignorelist'         => '',
            'pmfolders'          => '0**$%%$1**$%%$2**$%%$3**$%%$4**',
            'moderateposts'      => 0,
            'moderationtime'     => 0,
        ];

        $dst = (int)($user['options']['dstcorrection'] ?? 2);
        if ($dst === 1) $this->user_insert_data['dst'] = 1;
        if ($dst === 0) $this->user_insert_data['dst'] = 0;

        $plugins->run_hooks('datahandler_user_insert', $this);

        $this->uid              = $db->insert_query('users', $this->user_insert_data);
        
	
        if ($regtype === 'invite' && !empty($user['invite_id'])) {
            $db->update_query('invites', [
                'status'     => 'used',
                'invitee_id' => (int)$this->uid,
                'used_at'    => time(),
                'ip_used'    => $db->escape_string(get_ip()),
            ], "id='" . (int)$user['invite_id'] . "'");
        }

       
        update_stats(['numusers' => '+1']);

        if ((int)$user['usergroup'] == 5) {
            $cache->update_awaitingactivation();
        }

        $this->return_values = [
            'uid'       => $this->uid,
            'username'  => $user['username'],
            'loginkey'  => $user['loginkey'],
            'email'     => $user['email'],
            'password'  => $user['password'],
            'usergroup' => $user['usergroup'],
        ];

        $plugins->run_hooks('datahandler_user_insert_end', $this);

        return $this->return_values;
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update_user(): bool
    {
        global $db, $plugins, $cache;

        if (!$this->get_validated()) die('The user needs to be validated before inserting it into the DB.');
        if (count($this->get_errors()) > 0) die('The user is not valid.');

        $user      = &$this->data;
        $user['uid'] = (int)$user['uid'];
        $this->uid   = $user['uid'];

        $string_fields = ['username', 'email', 'additionalgroups', 'usertitle', 'signature', 'bday' => 'birthday',
                          'birthdayprivacy', 'timezone', 'dateformat', 'timeformat', 'notepad', 'usernotes'];
        $int_fields    = ['postnum', 'threadnum', 'displaygroup', 'regdate', 'lastactive', 'lastvisit', 'style'];

        $map = [
            'username'          => fn($v) => ['username',         $db->escape_string($v)],
            'password'          => fn($v) => ['password',         $v],
            'salt'              => fn($v) => ['salt',             $v],
            'loginkey'          => fn($v) => ['loginkey',         $v],
            'email'             => fn($v) => ['email',            $db->escape_string($v)],
            'postnum'           => fn($v) => ['postnum',          (int)$v],
            'threadnum'         => fn($v) => ['threadnum',        (int)$v],
            'usergroup'         => fn($v) => ['usergroup',        (int)$v],
            'additionalgroups'  => fn($v) => ['additionalgroups', $db->escape_string($v)],
            'displaygroup'      => fn($v) => ['displaygroup',     (int)$v],
            'usertitle'         => fn($v) => ['usertitle',        $db->escape_string($v)],
            'regdate'           => fn($v) => ['regdate',          (int)$v],
            'lastactive'        => fn($v) => ['lastactive',       (int)$v],
            'lastvisit'         => fn($v) => ['lastvisit',        (int)$v],
            'signature'         => fn($v) => ['signature',        $db->escape_string($v)],
            'bday'              => fn($v) => ['birthday',         $v],
            'birthdayprivacy'   => fn($v) => ['birthdayprivacy',  $db->escape_string($v)],
            'style'             => fn($v) => ['style',            (int)$v],
            'timezone'          => fn($v) => ['timezone',         $db->escape_string($v)],
            'dateformat'        => fn($v) => ['dateformat',       $db->escape_string($v)],
            'timeformat'        => fn($v) => ['timeformat',       $db->escape_string($v)],
            'regip'             => fn($v) => ['regip',            $db->escape_binary($v)],
            'lastip'            => fn($v) => ['lastip',           $db->escape_binary($v)],
            'notepad'           => fn($v) => ['notepad',          $db->escape_string($v)],
            'usernotes'         => fn($v) => ['usernotes',        $db->escape_string($v)],
        ];

        foreach ($map as $key => $fn) {
            if (isset($user[$key])) {
                [$col, $val]                       = $fn($user[$key]);
                $this->user_update_data[$col]      = $val;
            }
        }

        if (isset($user['avatar'])) {
            $this->user_update_data['avatar']     = $db->escape_string($user['avatar']);
            $this->user_update_data['avatartype'] = $db->escape_string($user['avatartype']);
        }

        if (isset($user['options']) && is_array($user['options'])) {
            foreach ($user['options'] as $k => $v) {
                $this->user_update_data[$k] = $v;
            }
        }

        $old_user = get_user($user['uid']);

        if (
            isset($this->user_update_data['pmnotice']) &&
            $old_user['pmnotice'] == '2' &&
            $this->user_update_data['pmnotice'] == 1
        ) {
            unset($this->user_update_data['pmnotice']);
        }

        $plugins->run_hooks('datahandler_user_update', $this);

        if (count($this->user_update_data) < 1) {
            return false;
        }

        if (count($this->user_update_data) > 0) {
            $db->update_query('users', $this->user_update_data, "id='{$user['uid']}'");
        }

        if (isset($user['bday']) || isset($user['username'])) {
            $cache->update_birthdays();
        }

        

        if (
            !empty($this->user_update_data['username']) &&
            $this->user_update_data['username'] !== $old_user['username']
        ) {
            $un_update     = ['username'   => $this->user_update_data['username']];
            $poster_update = ['lastposter' => $this->user_update_data['username']];

            $db->update_query('posts',   $un_update,     "uid='{$user['uid']}'");
            $db->update_query('threads', $un_update,     "uid='{$user['uid']}'");
            $db->update_query('threads', $poster_update, "lastposteruid='{$user['uid']}'");
            $db->update_query('forums',  $poster_update, "lastposteruid='{$user['uid']}'");

            $stats = $cache->read('stats');
            if ($stats['lastuid'] == $user['uid']) {
                update_stats(['numusers' => '+0']);
            }
        }

        return true;
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function delete_user(array|int $delete_uids, int $prunecontent = 0): array
    {
        global $db, $plugins, $cache, $CURUSER;

        if (count($this->get_errors()) > 0) die('The user is not valid.');

        $this->delete_uids = array_filter(
            array_map('intval', (array)$delete_uids),
            fn($uid) => $uid && !is_super_admin($uid) && $uid !== (int)$CURUSER['id']
        );

        $plugins->run_hooks('datahandler_user_delete_start', $this);

        $this->delete_uids = implode(',', $this->delete_uids);

        if (empty($this->delete_uids)) {
            return $this->return_values = ['deleted_users' => 0];
        }

        $this->delete_content();

        $query               = $db->delete_query('users', "id IN({$this->delete_uids})");
        $this->deleted_users = $db->affected_rows($query);

        if ($prunecontent === 1) {
            $this->delete_posts();
			$db->delete_query('announcements', "type IN ('forum', 'global') AND uid IN({$this->delete_uids})");
        } else {
            foreach (['pollvotes', 'posts', 'threads', 'attachments', 'announcements'] as $t) {
                $db->update_query($t, ['uid' => 0], "uid IN({$this->delete_uids})");
            }
        }

        $db->update_query('privatemessages', ['fromid'       => 0], "fromid IN({$this->delete_uids})");
        $db->update_query('forums',      ['lastposteruid' => 0], "lastposteruid IN({$this->delete_uids})");
        $db->update_query('threads',     ['lastposteruid' => 0], "lastposteruid IN({$this->delete_uids})");

        update_stats(['numusers' => '-' . $this->deleted_users]);

        $this->return_values = ['deleted_users' => $this->deleted_users];

        $plugins->run_hooks('datahandler_user_delete_end', $this);

        $cache->update_forumsdisplay();
        $cache->update_awaitingactivation();
        $cache->update_birthdays();

        return $this->return_values;
    }

    public function delete_content(array|bool $delete_uids = false): void
    {
        global $db, $plugins, $CURUSER;

        if ($delete_uids !== false) {
            $this->delete_uids = implode(',', array_filter(
                array_map('intval', (array)$delete_uids),
                fn($uid) => $uid && !is_super_admin($uid) && $uid !== (int)$CURUSER['id']
            ));
        }

        $plugins->run_hooks('datahandler_user_delete_content', $this);

        if (empty($this->delete_uids)) return;

        $db->sql_query("DELETE FROM reports WHERE type='user' AND reported_user_id IN({$this->delete_uids})");

        $tables = [
            'privatemessages'          => 'uid',
            'snatched'                 => 'userid',
            'users_perm'               => 'userid',
            'inactivity'               => 'userid',
            'comments'                 => 'user',
            'bookmarks'                => 'userid',
            'peers'                    => 'userid',
            'forumsubscriptions'   => 'uid',
            'threadsubscriptions'  => 'uid',
            'forumsread'           => 'uid',
            'threadsread'          => 'uid',
            'sessions'                 => 'uid',
            'banned'                   => 'uid',
            'awaitingactivation'       => 'uid',
        ];

        foreach ($tables as $table => $col) {
            $db->delete_query($table, "{$col} IN({$this->delete_uids})");
        }

        $db->delete_query('buddyrequests', "uid IN({$this->delete_uids}) OR touid IN({$this->delete_uids})");
        $db->delete_query('posts',    "uid IN({$this->delete_uids}) AND visible = -2");
        $db->delete_query('threads',  "uid IN({$this->delete_uids}) AND visible = -2");

        require_once INC_PATH . '/functions_upload.php';
        foreach (explode(',', $this->delete_uids) as $uid) {
            remove_avatars((int)$uid);
        }
    }

    public function delete_posts(array|bool $delete_uids = false): void
    {
        global $db, $plugins, $CURUSER;

        if ($delete_uids !== false) {
            $this->delete_uids = implode(',', array_filter(
                array_map('intval', (array)$delete_uids),
                fn($uid) => $uid && !is_super_admin($uid) && $uid !== (int)$CURUSER['id']
            ));
        }

        require_once INC_PATH . '/class_moderation.php';
        $moderation = new Moderation();

        $plugins->run_hooks('datahandler_user_delete_posts', $this);

        if (empty($this->delete_uids)) return;

        $q = $db->simple_select('threads', 'tid', "uid IN({$this->delete_uids})");
        while ($tid = $db->fetch_field($q, 'tid')) {
            $moderation->delete_thread($tid);
        }

        $q = $db->simple_select('posts', 'pid', "uid IN({$this->delete_uids})");
        while ($pid = $db->fetch_field($q, 'pid')) {
            $moderation->delete_post($pid);
        }
    }

    public function clear_profile(array|bool $delete_uids = false, int $gid = 0): void
    {
        global $db, $plugins, $CURUSER;

        if ($delete_uids !== false) {
            $this->delete_uids = implode(',', array_filter(
                array_map('intval', (array)$delete_uids),
                fn($uid) => $uid && !is_super_admin($uid) && $uid !== (int)$CURUSER['id']
            ));
        }

        $update = [
            'birthday' => '', 'usertitle' => '', 'additionalgroups' => '',
            'displaygroup' => 0, 'signature' => '', 'avatar' => '',
            'avatardimensions' => '', 'avatartype' => '',
        ];

        if ($gid > 0) $update['usergroup'] = $gid;

        $plugins->run_hooks('datahandler_user_clear_profile', $this);

        if (empty($this->delete_uids)) return;

        $db->update_query('users', $update, "id IN({$this->delete_uids})");
        

        require_once INC_PATH . '/functions_upload.php';
        foreach (explode(',', $this->delete_uids) as $uid) {
            remove_avatars((int)$uid);
        }
    }
}
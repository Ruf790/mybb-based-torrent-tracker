<?php

declare(strict_types=1);

// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

/**
 * Login handling class, provides common structure to handle login events.
 */
class LoginDataHandler extends DataHandler
{
    
    public $language_file = 'datahandler_login';

    public $language_prefix = 'logindata';

    public array $login_data = [];


    /**
     * Verify username exists
     */
    public function verify_username(): bool
    {
        $this->get_login_data();

        if (empty($this->login_data) || empty($this->login_data['id'])) {
            $this->invalid_combination();
            return false;
        }

        return true;
    }

    /**
     * Verify password matches
     */
    public function verify_password(bool $strict = true): bool
    {
        global $db, $mybb, $plugins;

        $this->get_login_data();

        if (empty($this->login_data['username'])) {
            // Username must be validated to apply a password to
            $this->invalid_combination();
            return false;
        }

        $args = [
            'this' => &$this,
            'strict' => &$strict,
        ];

        $plugins->run_hooks('datahandler_login_verify_password_start', $args);

        $user = &$this->data;

        if (empty($this->login_data['id'])) {
            $this->invalid_combination();
        }

        if ($strict) {
            if (empty($this->login_data['loginkey'])) {
                $this->login_data['loginkey'] = generate_loginkey();

                $db->sql_query_prepared(
                    "UPDATE users SET loginkey = ? WHERE id = ?",
                    [$this->login_data['loginkey'], $this->login_data['id']]
                );
            }
        }

        $plugins->run_hooks('datahandler_login_verify_password_end', $args);

        if (!verify_user_password($this->login_data, $user['password'])) {
            $this->invalid_combination(true);
            return false;
        }

        return true;
    }

    /**
     * Handle invalid login combination
     */
    public function invalid_combination(bool $show_login_attempts = false): void
    {
        global $db, $lang, $mybb, $failedlogincount, $failedlogintext;
        

		$login_text = '';
        if($show_login_attempts)
        {
           if($failedlogincount != 0 && $failedlogintext == 1 && !empty($this->login_data['id']))
           {
              $logins = login_attempt_check($this->login_data['id'], false) + 1;
			  $login_text = sprintf($lang->member['failed_login_again'] ?? 'Failed login attempts: %d', $failedlogincount - $logins);
			  
			   
           }
        }

       
        $this->set_error('invalidpwordusername', $login_text);
        
		
    }

    /**
     * Get login data for user
     */
    public function get_login_data(): void
    {
        $user = &$this->data;
 
        $options = ['fields' => '*'];
 
		$this->login_data = get_user_by_username($user['username'] ?? '', $options) ?: [];
    }

    /**
     * Validate login credentials
     */
	 
    public function validate_login(): bool
    {
        global $plugins;

        $user = &$this->data;

        $plugins->run_hooks('datahandler_login_validate_start', $this);

        // CAPTCHA verification completely removed from login process

        if (array_key_exists('username', $user)) {
            $this->verify_username();
        }

        if (array_key_exists('password', $user)) {
            $this->verify_password();
        }

        $plugins->run_hooks('datahandler_login_validate_end', $this);

        $this->set_validated(true);
        
        return count($this->get_errors()) === 0;
    }

    /**
     * Complete login process
     */
    public function complete_login(): bool
    {
        global $plugins, $db, $mybb, $session;

        $user = &$this->login_data;

        $plugins->run_hooks('datahandler_login_complete_start', $this);

        // Login to MyBB
        my_setcookie('loginattempts', "1");
        my_setcookie("sid", $session->sid, -1, true);

        $db->sql_query_prepared("UPDATE sessions SET uid = ? WHERE sid = ?", [$user['id'], $session->sid]);
        $db->sql_query_prepared("UPDATE users SET loginattempts = ? WHERE id = ?", [1, $user['id']]);

        $remember = null;
        if (!isset($mybb->input['remember']) || $mybb->input['remember'] != "yes") {
            $remember = -1;
        }

        my_setcookie("mybbuser", $user['id'] . "_" . $user['loginkey'], $remember, true, "lax");

        $plugins->run_hooks('datahandler_login_complete_end', $this);

        return true;
    }
}
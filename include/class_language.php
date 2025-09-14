<?php

class trackerlanguage
{
    var $path = null;
    var $language = null;

    // Предзаданные свойства для всех языковых ключей
    public $adduser;
    public $announcements;
    public $badusers;
    public $browse;
    public $checkuser;
    public $clear_ann;
    public $comment;
    public $confirm;
    public $confirmemail;
    public $contact;
    public $contactstaff;
    public $contactus;
    public $cronjobs;
    public $delete;
    public $details;
    public $download;
    public $editor;
    public $editpost;
    public $faq;
    public $ff;
    public $findnotconnectable;
    public $finduser;
    public $formats;
    public $forumdisplay;
    public $getrss;
    public $global = [];
    public $header;
    public $helpdocs;
    public $helpsections;
    public $index;
    public $invite;
    public $links;
    public $login;
    public $member;
    public $memberlist;
    public $messages;
    public $misc;
    public $modcp;
    public $moderation;
    public $modrules;
    public $modtask;
    public $mybonus;
    public $newreply;
    public $newthread;
    public $ok;
    public $online;
    public $polls;
    public $port_check;
    public $printthread;
    public $private;
    public $quick_editor;
    public $ratethread;
    public $recover;
    public $referrals;
    public $report;
    public $search;
    public $showteam;
    public $showthread;
    public $signup;
    public $stats;
    public $stats2;
    public $syndication;
    public $takeflush;
    public $takewhatever;
    public $timezone;
    public $top_stats;
    public $topten;
    public $transfer;
    public $ts_social_groups;
    public $ts_tutorials;
    public $tsf_forums;
    public $unbaniprequest;
    public $upload;
    public $uploaderform;
    public $user_awaiting_activation;
    public $usercp;
    public $usercpnav;
    public $userdetails;
    public $usersearch;
    public $viewsnatches;
    public $watch_list;
    public $xmlhttp;

    function set_path($path)
    {
        $this->path = $path;
    }

    function set_language($language = 'english')
    {
        $language = str_replace(array('/', '\\', '..'), '', trim($language));
        if ($language == '') {
            $language = 'english';
        }

        $this->language = $language;
    }

    function load($section)
    {
        global $rootpath;
        $lfile = $this->path . '/' . $this->language . '/' . $section . '.lang.php';
        if (file_exists($lfile)) {
            require_once $lfile;
        } else {
            define('errorid', 3);
            include_once TSDIR . '/ts_error.php';
            exit();
        }

        if ((isset($language) AND is_array($language))) {
            foreach ($language as $key => $val) {
                if ((!isset($this->$key) OR $this->$key != $val)) {
                    $val = preg_replace('#\\{([0-9]+)\\}#', '%$1\\$s', $val);
                    $this->$key = $val;
                }
            }
        }
    }
}

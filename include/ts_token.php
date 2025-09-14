<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  class ts_token
  {
    var $tdeadin = 300;
    var $url = '';
    var $redirect = '';
    function create_new_hash ()
    {
      $this->clear ();
      $token_code = md5 (uniqid (rand (), true));
      $_SESSION['token_code'] = $token_code;
      $_SESSION['token_created'] = TIMENOW;
    }

    function create_return ()
    {
      $this->create_new_hash ();
      return $_SESSION['token_code'];
    }

    function create ()
    {
      if ((empty ($_GET['sure']) OR $_GET['sure'] != 1))
      {
        $this->clear ();
        $this->create_new_hash ();
        //stderr ('Sanity Check', str_replace ('{1}', htmlspecialchars_uni ($_SESSION['token_code']), $this->url), false);
		
		stderr (str_replace ('{1}', htmlspecialchars_uni ($_SESSION['token_code']), $this->url), false);
		
        return null;
      }

      if (!$this->validate ())
      {
        $this->clear ();
        redirect ($this->redirect, 'Invalid Hash!');
        exit ();
        return null;
      }

      $this->clear ();
      return '';
    }

    function validate ()
    {
      $deadin = ($this->tdeadin < TIMENOW - $_SESSION['token_created'] ? false : true);
      if ((((((empty ($_SESSION['token_code']) OR strlen ($_SESSION['token_code']) != 32) OR empty ($_GET['hash'])) OR strlen ($_GET['hash']) != 32) OR $_GET['hash'] != $_SESSION['token_code']) OR !$deadin))
      {
        $this->clear ();
        return false;
      }

      $this->clear ();
      return true;
    }

    function clear ()
    {
      unset ($_SESSION[token_code]);
      unset ($_SESSION[token_created]);
    }
  }

  @session_name ('TSSE_Session');
  session_start ();
  if (!defined ('IN_SCRIPT_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

?>

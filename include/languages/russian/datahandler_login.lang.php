<?php


 if(!defined('IN_TRACKER'))
  die('Hacking attempt!');


$language['datahandler_login'] = array 
(

'logindata_invalidpwordusername' => 'Вы ввели неверную пару имя пользователя/пароль. <br /><br />Если вы забыли свой пароль, пожалуйста, <a href="member.php?action=lostpw">восстановите новый</a>.',
'logindata_invalidpwordusernameemail' => 'Вы ввели неверную пару email/пароль. <br /><br />Если вы забыли свой пароль, пожалуйста, <a href="member.php?action=lostpw">восстановите новый</a>.',
'logindata_invalidpwordusernamecombo' => 'Вы ввели неверную пару имя пользователя/пароль или email/пароль. <br /><br />Если вы забыли свой пароль, пожалуйста, <a href="member.php?action=lostpw">восстановите новый</a>.',

'logindata_regimageinvalid' => "Введенный вами код проверки изображения неверен. Пожалуйста, введите код точно так, как он отображается на изображении.",
'logindata_regimagerequired' => "Пожалуйста, введите код проверки изображения для продолжения процесса входа. Введите код точно так, как он отображается на изображении.",

'logindata_failed_login_again' => "<br />У вас осталось <strong>{1}</strong> попыток входа.",

);
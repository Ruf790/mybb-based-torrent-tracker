<?php

if(!defined('IN_TRACKER'))
  die('Hacking attempt!');

// userhistory.php
$language['userhistory'] = array
(
	'chistory'         => 'История комментариев: {1}',
	'invaliduser'      => 'Указан неверный пользователь. Если вы перешли по правильной ссылке, сообщите <a href="/contactstaff.php">администратору.</a>',

	'lbl_uh_total'     => 'Комментарии',
	'lbl_uh_torrents'  => 'Раздач прокомментировано',
	'lbl_uh_month'     => 'За 30 дней',
	'lbl_uh_last'      => 'Последний комментарий',
	'txt_uh_empty'     => 'Комментарии не найдены',
	'txt_uh_deleted'   => '[Раздача удалена]',
	'tip_uh_permalink' => 'Постоянная ссылка',

	'ph_uh_search'     => 'Поиск по тексту комментария или названию раздачи',
	'lbl_uh_only_att'  => 'Только с вложениями',
	'btn_uh_reset'     => 'Сбросить',
);
?>
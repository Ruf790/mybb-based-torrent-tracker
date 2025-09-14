<?php


/** MyBB Generated Cache - Do Not Alter
 * Cache Name: mycode
 * Generated: Mon, 11 Dec 2023 09:38:48 +0000
*/

$mycode = array (
  0 => 
  array (
    'regex' => '\\[spoiler=?(.*?)\\](.*?)\\[/spoiler\\]',
    'replacement' => '<div class="spoilerBox"><div style="cursor:pointer;" class="spoilerTitle" onclick="jQuery(this).next().slideToggle(); jQuery(this).children(\'.spoilerOpen\').toggle();jQuery(this).children(\'.spoilerClose\').toggle();"><span class="spoilerOpen">Show</span><span class="spoilerClose" style="display:none;">Hide</span>&nbsp;$1</div><div style="display: none;">$2</div></div>',
  ),
  1 => 
  array (
    'regex' => '\\[insta\\]https://www.instagram.com/p/(.*?)/\\[/insta\\]',
    'replacement' => '<div style="text-align:center">
<iframe src="//instagram.com/p/$1/embed/" style=" color:#000; font-family:Arial,sans-serif; font-size:14px; font-style:normal; font-weight:normal; line-height:17px; text-decoration:none; word-wrap:break-word;" align="center" width="450" height="610" frameborder="0" scrolling="auto" allowtransparency="true" style="border: 1px #b2a998 solid; border-radius: 5px; box-shadow: 0px 0px 7px 0px rgba(106, 101, 91, 0.5); " text-align:center; text-decoration:none; width:100%;" target="_blank"> 
</iframe></div>',
  ),
  2 => 
  array (
    'regex' => '\\[SC\\](.*?)\\[/SC\\]',
    'replacement' => '<iframe width="100%" height=330" scrolling="no" frameborder="no" src="https://w.soundcloud.com/player/?url=$1&amp;color=0066cc&amp;auto_play=false&amp;hide_related=false&amp;show_comments=false&amp;show_user=false&amp;show_reposts=false"></iframe>',
  ),
  3 => 
  array (
    'regex' => '\\[insta\\]https://www.instagram.com/reel/(.*?)/\\[/insta\\]',
    'replacement' => '<div style="text-align:center">
<iframe src="//instagram.com/reel/$1/embed/" style=" color:#000; font-family:Arial,sans-serif; font-size:14px; font-style:normal; font-weight:normal; line-height:17px; text-decoration:none; word-wrap:break-word;" align="center" width="450" height="610" frameborder="0" scrolling="auto" allowtransparency="true" style="border: 1px #b2a998 solid; border-radius: 5px; box-shadow: 0px 0px 7px 0px rgba(106, 101, 91, 0.5); " text-align:center; text-decoration:none; width:100%;" target="_blank"> 
</iframe></div>',
  ),
  4 => 
  array (
    'regex' => '\\[twitter\\](.*?)\\[/twitter\\]',
    'replacement' => '<blockquote class="twitter-tweet"><a href="$1%"></a></blockquote> <script async="true" src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>',
  ),
  5 => 
  array (
    'regex' => '\\[gifv\\]https?\\://i\\.imgur\\.com/([a-z0-9]+)\\.gifv\\[/gifv\\]',
    'replacement' => '<center><iframe allowfullscreen="" frameborder="0" scrolling="no"  width="660" height="370" src="//i.imgur.com/$1.gifv#embed"></iframe></center>',
  ),
  6 => 
  array (
    'regex' => '\\[announce\\](.*?)\\[/announce\\]',
    'replacement' => '<div style="border: 2px dashed rgb(204, 51, 68); margin: 2ex; padding: 2ex; color: black; background-color: rgb(255, 228, 233); align: center;"><div style="float: left; width: 2ex; font-size: 2em; color: red;"><strong>!!</strong></div><b style="text-decoration: underline;">Announcement</b><br/><div style="padding-left: 6ex;">$1</div></div>',
  )
  
);

?>
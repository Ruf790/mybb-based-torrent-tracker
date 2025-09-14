<?
/***********************************************/
/*=========[TS Special Edition v.5.6]==========*/
/*=============[Special Thanks To]=============*/
/*        DrNet - wWw.SpecialCoders.CoM        */
/*          Vinson - wWw.Decode4u.CoM          */
/*    MrDecoder - wWw.Fearless-Releases.CoM    */
/*           Fynnon - wWw.BvList.CoM           */
/***********************************************/


  function deletetorrent ($id, $permission = false)
  {
    global $torrent_dir;
    global $usergroups;
	global $db;
    if ((($permission OR is_mod ($usergroups)) AND is_valid_id ($id)))
    {
      $id = intval ($id);
      $file = TSDIR . '/' . $torrent_dir . '/' . $id . '.torrent';
      if (@file_exists ($file))
      {
        $image_types = array ('gif', 'jpg', 'png', 'webp');
        foreach ($image_types as $image)
        {
          if (@file_exists (TSDIR . '/' . $torrent_dir . '/images/' . $id . '.' . $image))
          {
            @unlink (TSDIR . '/' . $torrent_dir . '/images/' . $id . '.' . $image);
            continue;
          }
        }
		
		
		$image_types2 = array ('gif', 'jpg', 'png', 'webp');
        foreach ($image_types2 as $image2)
        {
          if (@file_exists (TSDIR . '/' . $torrent_dir . '/images/' . $id . '_2.' . $image2))
          {
            @unlink (TSDIR . '/' . $torrent_dir . '/images/' . $id . '_2.' . $image2);
            continue;
          }
        }
		
		
		
		$query = $db->simple_select("torrents", "t_link", "id = '{$id}'");
		
        if ($db->num_rows($query)) 
		{
            $Result = $db->fetch_array($query);
            $t_link = $Result["t_link"];
            $regex = "#https://www.imdb.com/title/(.*)/#U";
            preg_match($regex, $t_link, $_id_);
            if (isset($_id_[1]) && $_id_[1]) 
			{
                $_id_ = $_id_[1];
                foreach ($image_types as $image) 
				{
                    if (@file_exists(TSDIR . "/" . $torrent_dir . "/imdb/" . $_id_ . "." . $image)) 
					{
                        @unlink(TSDIR . "/" . $torrent_dir . "/imdb/" . $_id_ . "." . $image);
                    }
                }
                for ($i = 0; $i <= 10; $i++) 
				{
                    foreach ($image_types as $image) 
					{
                        if (@file_exists(TSDIR . "/" . $torrent_dir . "/imdb/" . $_id_ . "_photo" . $i . "." . $image)) 
						{
                            @unlink(TSDIR . "/" . $torrent_dir . "/imdb/" . $_id_ . "_photo" . $i . "." . $image);
                        }
                    }
                }
            }
        }

        @unlink ($file);
      }
	  
	  
	  // ✅ Удаляем скрины из папки /torrents/screens/ и из таблицы
      $screenshots = $db->query("SELECT filename FROM `screenshots` WHERE torrent_id = '{$id}'");
      while ($shot = $db->fetch_array($screenshots))
      {
            $screenshotFile = $_SERVER['DOCUMENT_ROOT'] . '/torrents/screens/' . $shot['filename'];
            if (file_exists($screenshotFile))
            {
                @unlink($screenshotFile);
            }
      }
      $db->delete_query("screenshots", "torrent_id='{$id}'");
	  
	  
	  // === Удаляем картинки, привязанные к torrent ===
      $files = $db->simple_select("comment_files", "*", "torrent_id = " . (int)$id);
      while ($file = $db->fetch_array($files)) 
	  {
        // Проверка, если файл существует на сервере
        if (is_file($file['file_path'])) 
		{
            @unlink($file['file_path']); // Удаляем файл с диска
        }
      }

      // Удаляем записи из таблицы comment_files
      $db->delete_query("comment_files", "torrent_id = " . (int)$id);
		
		

  
	  $db->delete_query("peers", "torrent='$id'");
      $db->delete_query("comments", "torrent='$id'");
	  $db->delete_query("bookmarks", "torrentid='$id'");
	  $db->delete_query("snatched", "torrentid='$id'");
	  $db->delete_query("torrents", "id='$id'");		
	  $db->delete_query("ratings", "type='1' AND rating_id='$id'");
	  $db->delete_query("reports", "type='torrent' AND votedfor ='$id'");
	  $db->delete_query("ts_nfo", "id='$id'");
	  
	  
	  
      return null;
	  
    }

    print_no_permission (true);
  }

  if (!defined ('IN_SCRIPT_TSSEv56'))
  {
    exit ('<font face=\'verdana\' size=\'2\' color=\'darkred\'><b>Error!</b> Direct initialization of this file is not allowed.</font>');
  }

?>

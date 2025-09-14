<?php
/**
*
* @ iDezender 7.0
* @ Developed by Qarizma
*
* @   Visit our website:
* @   www.iRadikal.com
* @   For cheap decoding service :)
* @   And for the ionCube Decoder!
*
*/
   
function MakeFriendlyText( $text, $delimer = "_" )
{
    $text = unhtmlspecialchars( $text );
    $replace = array( "`", "~", "!", "@", "#", "\$", "%", "^", "&", "*", "(", ")", "-", "_", "+", "=", "\\", "|", "<", ">", ",", ".", "/", "?", "{", "}", "[", "]", ":", ";", "'", "\"" );
    $text = str_replace( $replace, " ", $text );
    $text = preg_replace( "/\\s\\s+/", " ", $text );
    $text = str_replace( " ", $delimer, $text );
    $text = strip_tags( $text );
    return trim( $text, $delimer );
}



   
   function show_upload_errors() 
   {
      global $UploadErrors;
      global $lang;
      global $BASEURL;
      global $pic_base_url;

      if (0 < count( $UploadErrors )) 
      {
         $Errors = '';
         foreach ($UploadErrors as $Error) 
		 {
            $Errors .= '' . $Error . '<br />';
         }

         //echo show_notice( $Errors, 1, $lang->upload['error'] );
		 
		 echo '
		 
		 <div class="container mt-3">
         <div class="red_alert mb-3" role="alert">
          '.$Errors.'
        </div>
        </div>
		 
		 ';
		 
         unset( $Errors );
      }

   }

   define( 'TU_VERSION', '3.0.7 by xam' );
   define("SCRIPTNAME", "upload.php");
   require( './global.php' );
   //require_once(INC_PATH.'/class_plugins.php');
   //$plugins = new pluginSystem;
   
   $lang->load ('global');

   if (!isset($CURUSER)) 
   {
     print_no_permission();
   }

   @ini_set("upload_max_filesize", 1000 < $max_torrent_size ? $max_torrent_size : 10485760);
   @ini_set("memory_limit", "20000M");
   $lang->load('upload');
   
   require_once( INC_PATH . '/editor.php' );
   
   $is_mod = is_mod($usergroups);

  
   $xbt_active = 'no';
   $UseNFOasDescr = 'no';
   
  
   $CanUploadExternalTorrent = $externalscrape == 'yes';
   $AnnounceURL = trim($announce_urls[0] . "?passkey=" . $CURUSER["passkey"]);
  
   $postoptions = '';
   $postoptionstitle = '';
   $UploadErrors = array(  );

   $id = (isset ($_GET['id']) ? intval ($_GET['id']) : (isset ($_POST['id']) ? intval ($_POST['id']) : 0));

   if (( isset( $_GET['id'] ) AND is_valid_id( $_GET['id'] ) )) 
   {
      if ($EditTorrentID = $id)
      {
         
		 $Query = $db->simple_select('torrents', '*', "id='{$EditTorrentID}'");
		 
		 
         if ($db->num_rows($Query)) 
		 {
            $EditTorrent = mysqli_fetch_assoc($Query);

            if (( $EditTorrent['owner'] != $CURUSER['id'] AND !$is_mod )) 
            {
               print_no_permission( true );
            }

            $name = $EditTorrent['name'];
            $descr = $EditTorrent['descr'];
            $IsExternalTorrent = (( $EditTorrent['ts_external'] == 'yes' AND $CanUploadExternalTorrent ) ? true : false);
            $category = intval( $EditTorrent['category'] );
            $t_image_url = $EditTorrent['t_image'];
			$t_image_url2 = $EditTorrent['t_image2'];
            $t_link = $EditTorrent['t_link'];

            if (($t_link AND preg_match( '@https:\/\/www.imdb.com\/title\/(.*)\/@isU', $t_link, $result))) 
            {
               $t_link = $result['0'];
               unset($result);
            }

			
            $anonymous = $EditTorrent['anonymous'];
            $free = $EditTorrent['free'];
            $silver = $EditTorrent['silver'];
            $doubleupload = $EditTorrent['doubleupload'];
            $allowcomments = $EditTorrent['allowcomments'];
            $sticky = $EditTorrent['sticky'];
            $isrequest = $EditTorrent['isrequest'];
            $isnuked = $EditTorrent['isnuked'];
            $WhyNuked = $EditTorrent['WhyNuked'];

          
         } 
         else 
		 {
            print_no_permission();
         }
      } 
      else 
	  {
         print_no_permission();
      }
   }

   
   $query = $db->simple_select("ts_u_perm", "userid", "userid='".$db->escape_string($CURUSER['id'])."' AND canupload = '0'");

   if ($db->num_rows($query)) 
   {
      print_no_permission(false, true, $lang->upload["uploaderform"]);
   }


   if (strtoupper($_SERVER["REQUEST_METHOD"]) == "POST")
   {
      $name = TS_Global('subject');
      $descr = TS_Global('message' );
      $torrentfile = $_FILES['torrentfile'];
      $IsExternalTorrent = (( ( isset( $_POST['IsExternalTorrent'] ) AND $_POST['IsExternalTorrent'] == 'yes' ) AND $CanUploadExternalTorrent ) ? true : false);
      $nfofile = $_FILES['nfofile'];
      $UseNFOasDescr = TS_Global('UseNFOasDescr');
      $category = intval(TS_Global('category'));
      $t_image_url = TS_Global('t_image_url');
      $t_image_file = $_FILES['t_image_file'];
	  
	  $t_image_url2 = TS_Global('t_image_url2');
      $t_image_file2 = $_FILES['t_image_file2'];
	  
      $t_link = TS_Global('t_link');
      $anonymous = TS_Global('anonymous');
      $free = TS_Global('free');
      $silver = TS_Global('silver');
      $doubleupload = TS_Global('doubleupload');
      $allowcomments = TS_Global('allowcomments');
      $sticky = TS_Global('sticky');
      $isrequest = TS_Global('isrequest');
      $isnuked = TS_Global( 'isnuked');
      $WhyNuked = TS_Global('WhyNuked');



      if (( isset( $nfofile['name'] ) AND !empty( $nfofile['name'] ) )) 
      {
         if (get_extension( $nfofile['name'] ) != 'nfo') 
         {
            $UploadErrors[] = $lang->upload['error10'];
         }


         if ($nfofile['size'] == '0') 
         {
            $UploadErrors[] = $lang->upload['error11'];
         }


         if ($nfofile['error'] != '0') 
         {
            $UploadErrors[] = $lang->upload['error6'] . ' {' . intval( $nfofile['error'] ) . '}';
         }


         if (!is_file( $nfofile['tmp_name'] )) 
         {
            $UploadErrors[] = $lang->upload['error6'] . ' {' . intval( $nfofile['error'] ) . '}';
         }


         if (count( $UploadErrors ) == 0) 
         {
            $NfoContents = file_get_contents( $nfofile['tmp_name'] );
         }
      }


      if (( empty( $name ) || strlen( $name ) < 5 )) 
      {
         if (( isset( $torrentfile['name'] ) AND !empty( $torrentfile['name'] ) )) 
         {
            $name = str_replace( '.torrent', '', $torrentfile['name'] );
         } 
         else 
		 {
            $UploadErrors[] = $lang->upload['error1'];
         }
      }


      if (( empty( $descr ) || strlen( $descr ) < 10 )) 
      {
         if (( ( $UseNFOasDescr == 'yes' AND isset( $NfoContents ) ) AND !empty( $NfoContents ) )) 
         {
         } 
         else 
		 {
            $UploadErrors[] = $lang->upload['error2'];
         }
      }


      if (( ( isset( $torrentfile['name'] ) AND !empty( $torrentfile['name'] ) ) AND !$IsExternalTorrent )) 
      {
         $UpdateHash = true;
      }


      if ((isset($EditTorrent) AND empty($torrentfile['name']))) 
	  {
         $torrentfile['name'] = $EditTorrent['filename'];
         $torrentfile['type'] = 'application/x-bittorrent';
         $torrentfile['size'] = $EditTorrent['size'];
         $torrentfile['error'] = 0;
         $torrentfile['tmp_name'] = $torrent_dir . '/' . $EditTorrentID . '.torrent';
      }


      if ((isset($torrentfile['name']) AND !empty($torrentfile['name']))) 
      {
         if (get_extension($torrentfile['name']) != 'torrent') 
		 {
            $UploadErrors[] = $lang->upload['error3'];
         }


         if ($torrentfile['size'] == '0') 
         {
            $UploadErrors[] = $lang->upload['error5'];
         }


         if ($torrentfile['error'] != '0') 
         {
            $UploadErrors[] = $lang->upload['error6'] . ' {' . intval( $torrentfile['error'] ) . '}';
         }


         if (!is_file( $torrentfile['tmp_name'] )) 
         {
            $UploadErrors[] = $lang->upload['error6'] . ' {' . intval( $torrentfile['error'] ) . '}';
         }
      } 
      else 
	  {
         $UploadErrors[] = $lang->upload['error3'];
      }


      if (!$category) 
      {
         $UploadErrors[] = $lang->upload['error9'];
      }


      if (count( $UploadErrors ) == 0) 
      {
         require_once( INC_PATH . '/class_torrent_php8.php' );

        if ($Data = file_get_contents($torrentfile["tmp_name"])) 
		{
            $Torrent = new Torrent();
            if ($Torrent->load($Data)) {
                $info_hash = $Torrent->getHash();
                if ($privatetrackerpatch == "yes" && $IsExternalTorrent && $Torrent->getWhatever("announce") == $AnnounceURL && $Torrent->getWhatever("announce-list") == NULL) 
				{
                    $IsExternalTorrent = false;
                }
                if ($privatetrackerpatch == "yes" && !$IsExternalTorrent) 
				{
                    $Torrent->removeWhatever("announce-list");
                    $Torrent->removeWhatever("nodes");
                    if ($Torrent->getPrivate() != "1") 
					{
                        $Torrent->setPrivate("1");
                    }
                    if ($Torrent->getWhatever("announce") != $AnnounceURL) 
					{
                        $Torrent->setTrackers([$AnnounceURL]);
                    }
                }
                if ($privatetrackerpatch != "yes" && !$IsExternalTorrent && $Torrent->getWhatever("announce") != $AnnounceURL) 
				{
                    $Torrent->addTracker($AnnounceURL);
                }
                if (strlen($Torrent->getPieces()) % 20 != 0) {
                    $UploadErrors[] = $lang->upload["error7"];
                }
            } 
			else 
			{
                $UploadErrors[] = $lang->global["error"] . ": " . htmlspecialchars_uni($Torrent->error);
            }
         } 
		 
         else 
		 {
            $UploadErrors[] = $lang->upload['error7'];
         }
      }


      if (count( $UploadErrors ) == 0) 
      {
        
		
		$Torrent->setComment($lang->upload["DefaultTorrentComment"]);
        $Torrent->setCreatedBy(sprintf($lang->upload["CreatedBy"], $anonymous == "yes" ? "" : $CURUSER["username"]) . " [" . $SITENAME . "]");
        $Torrent->setSource($BASEURL);
        $Torrent->setCreationDate(TIMENOW);
        $check_info_hash = $Torrent->getHash();
        if ($info_hash != $check_info_hash) 
		{
            $info_hash = $check_info_hash;
            unset($check_info_hash);
            $UpdateHash = true;
            $Info_hash_changed = true;
        }
        $numfiles = 0;
        $size = 0;
        $IncludedFiles = $Torrent->getFiles();
        foreach ($IncludedFiles as $File) 
		{
            $numfiles++;
            $size += $File->length;
        }

         $filename = str_replace( '.torrent', '', $torrentfile['name'] );
         $filename = MakeFriendlyText( $filename );
		
         
		 $filename = $filename . '.torrent';
         $UpdateSet = array();

        
		 
         if (( isset( $UpdateHash ) || !isset($EditTorrent) )) 
		 {
            $UpdateSet['info_hash'] = $db->escape_string($info_hash);
         }

         $UpdateSet['name'] = $db->escape_string($name);
         $UpdateSet['filename'] = $db->escape_string($filename);
         $UpdateSet['category'] = $db->escape_string($category);
         $UpdateSet['size'] = $db->escape_string($size);
         $UpdateSet['numfiles'] = $db->escape_string($numfiles);
		 

         if ($descr) 
         {
            $UpdateSet['descr'] = $db->escape_string($descr);
         }


         if (!isset( $EditTorrent )) 
         {
            $UpdateSet['added'] = TIMENOW;
            $UpdateSet['ctime'] = TIMENOW;
            $UpdateSet['owner'] = $db->escape_string($CURUSER['id']);
         } 
         else 
		 {
            $UpdateSet['mtime'] = TIMENOW;
         }


         if ($is_mod) 
         {
            if ($free == 'yes') 
            {
               $UpdateSet['free'] = 'yes';
               $UpdateSet['silver'] = 'no';
            } 
            else 
			{
               if ($silver == 'yes') 
               {
                  $UpdateSet['silver'] = 'yes';
                  $UpdateSet['free'] = 'no';                  
               } 
               else 
			   {
                  $UpdateSet['silver'] = 'no';
                  $UpdateSet['free'] = 'no';                  
               }
            }


            if ($doubleupload == 'yes') 
            {
               $UpdateSet['doubleupload'] = 'yes';
            } 
            else 
			{
               $UpdateSet['doubleupload'] = 'no';
            }

          

            if ($allowcomments == 'no') 
            {
               $UpdateSet['allowcomments'] = 'no';
            } 
            else 
			{
               $UpdateSet['allowcomments'] = 'yes';
            }


            if ($sticky == 'yes') 
            {
               $UpdateSet['sticky'] = 'yes';
            } 
            else 
			{
               $UpdateSet['sticky'] = 'no';
            }


            if ($isnuked == 'yes') 
            {
               $UpdateSet['WhyNuked'] = $db->escape_string($WhyNuked);
               $UpdateSet['isnuked'] = 'yes';
            } 
            else 
			{
               $UpdateSet['WhyNuked'] = '';
               $UpdateSet['isnuked'] = 'no';
            }
         }


         if ($isrequest == 'yes') 
         {
            $UpdateSet['isrequest'] = 'yes';
         } 
         else 
		 {
            $UpdateSet['isrequest'] = 'no';
         }


         if ($anonymous == 'yes') 
         {
            $UpdateSet['anonymous'] = 'yes';
         } 
         else 
		 {
            $UpdateSet['anonymous'] = 'no';
         }




         if ($IsExternalTorrent) 
         {
            $UpdateSet['ts_external'] = 'yes';
            $UpdateSet['ts_external_url'] = $db->escape_string($Torrent->getWhatever("announce"));
            $UpdateSet['visible'] = 'yes';
         } 
         else 
		 {
            $UpdateSet['ts_external'] = 'no';
            $UpdateSet['ts_external_url'] = '';

            if (( !isset( $EditTorrent ) AND $xbt_active != 'yes' )) 
            {
               $UpdateSet['visible'] = 'no';
            }
         }





         if (empty($t_link)) 
         {
            $UpdateSet['t_link'] = '';
			$UpdateSet['tags'] = '';
         } 
         else 
		 {
            if (substr( $t_link, 0 - 1, 1 ) != '/') 
			{
               $t_link = $t_link . '/';
            }


            if (preg_match( '@^https:\/\/www.imdb.com\/title\/(.*)\/$@isU', $t_link, $result )) 
            {
               if ($result['0']) 
			   {
                  $t_link = $result['0'];
                  include_once(INC_PATH . '/ts_imdb.php');
                  $UpdateSet['t_link'] = $db->escape_string($t_link);
                  unset($result);
				  
		          $UpdateSet['tags'] = $db->escape_string($Genre);
               }
            }
         }


         if (empty($t_image_url)) 
         {
            $UpdateSet['t_image'] = '';
         }
		 
		 if (empty($t_image_url2)) 
         {
            $UpdateSet['t_image2'] = '';
         }
		 
		 
	
		 
		 if (isset($EditTorrent))
		 {
			 $db->update_query("torrents", $UpdateSet, "id='".$EditTorrentID."'");
		 }
		 else
		 {
			 $db->insert_query("torrents", $UpdateSet);
		 }


    
         if (isset( $EditTorrent )) 
         {
            $NewTID = $EditTorrentID;

            if (is_file( $torrent_dir . '/' . $NewTID . '.torrent' )) 
            {
               @unlink( $torrent_dir . '/' . $NewTID . '.torrent' );
            }
         } 
         else 
		 {
            $NewTID = $db->insert_id();
         }


         if (!empty($t_image_url)) 
         {
            $t_image_url = fix_url($t_image_url);
            $AllowedFileTypes = array('jpg', 'gif', 'png', 'webp');
            $ImageExt = get_extension($t_image_url);

            if (in_array($ImageExt, $AllowedFileTypes, true)) 
			{
               $AllowedMimeTypes = array('image/jpeg', 'image/gif', 'image/png', 'image/webp');
               $ImageDetails = getimagesize($t_image_url);

               if (($ImageDetails AND in_array($ImageDetails['mime'], $AllowedMimeTypes, true) )) 
			   {
                  include_once(INC_PATH . '/functions_ts_remote_connect.php');

                  if ($ImageContents = TS_Fetch_Data($t_image_url, false)) 
				  {
                     $NewImageURL = $torrent_dir . '/images/' . $NewTID . '.' . $ImageExt;

                     if (file_exists($NewImageURL)) 
					 {
                        @unlink($NewImageURL);
                     }


                     if (file_put_contents($NewImageURL, $ImageContents)) 
					 {
                        $COVERIMAGEUPDATED = true;
                       
						$update_image = array(
			                 "t_image" => $db->escape_string($BASEURL . '/' . $NewImageURL)
		                );
						
						$db->update_query("torrents", $update_image, "id='{$NewTID}'");
								
                     }
                  }
               }
            }
         }


         if (!empty($t_image_file)) 
         {
            if (((( 0 < $t_image_file['size'] AND $t_image_file['error'] === 0 ) AND $t_image_file['tmp_name']) AND $t_image_file['name'])) 
			{
               $t_image_url = fix_url($t_image_file['name']);
               $AllowedFileTypes = array('jpeg', 'jpg', 'gif', 'png', 'webp');
               $ImageExt = get_extension($t_image_url);

               if (in_array($ImageExt, $AllowedFileTypes, true)) 
			   {
                  $AllowedMimeTypes = array('image/jpeg', 'image/gif', 'image/png', 'image/webp');
                  $ImageDetails = getimagesize($t_image_file['tmp_name']);

                  if (( $ImageDetails AND in_array($ImageDetails['mime'], $AllowedMimeTypes, true ))) 
				  {
                     if ($ImageContents = file_get_contents($t_image_file['tmp_name'])) 
					 {
                        $NewImageURL = $torrent_dir . '/images/' . $NewTID . '.' . $ImageExt;

                        if (file_exists($NewImageURL)) 
						{
                           @unlink($NewImageURL);
                        }


                        if (file_put_contents($NewImageURL, $ImageContents)) 
						{
                           $COVERIMAGEUPDATED = true;
                           
						   $update_image2 = array(
			                 "t_image" => $db->escape_string($BASEURL . '/' . $NewImageURL)
		                   );
						
						   $db->update_query("torrents", $update_image2, "id='{$NewTID}'");
						   
						   
                        }
                     }
                  }
               }
            }
         }
		 
		 
		 
		 ///////////////////////////////////////////////////////////////////////// Image 2
		 
		 
		 if (!empty($t_image_url2)) 
         {
            $t_image_url2 = fix_url($t_image_url2);
            $AllowedFileTypes = array('jpg', 'gif', 'png', 'webp');
            $ImageExt = get_extension($t_image_url2);

            if (in_array($ImageExt, $AllowedFileTypes, true)) 
			{
               $AllowedMimeTypes = array('image/jpeg', 'image/gif', 'image/png', 'image/webp');
               $ImageDetails = getimagesize($t_image_url2);

               if (($ImageDetails AND in_array($ImageDetails['mime'], $AllowedMimeTypes, true) )) 
			   {
                  include_once(INC_PATH . '/functions_ts_remote_connect.php');

                  if ($ImageContents = TS_Fetch_Data($t_image_url2, false)) 
				  {
                     $NewImageURL = $torrent_dir . '/images/' . $NewTID . '_2.' . $ImageExt;

                     if (file_exists($NewImageURL)) 
					 {
                        @unlink($NewImageURL);
                     }


                     if (file_put_contents($NewImageURL, $ImageContents)) 
					 {
                        $COVERIMAGEUPDATED = true;
                       
						$update_image23 = array(
			                 "t_image2" => $db->escape_string($BASEURL . '/' . $NewImageURL)
		                );
						
						$db->update_query("torrents", $update_image23, "id='{$NewTID}'");
								
                     }
                  }
               }
            }
         }


         if (!empty($t_image_file2)) 
         {
            if (((( 0 < $t_image_file2['size'] AND $t_image_file2['error'] === 0 ) AND $t_image_file2['tmp_name']) AND $t_image_file2['name'])) 
			{
               $t_image_url2 = fix_url($t_image_file2['name']);
               $AllowedFileTypes = array('jpeg', 'jpg', 'gif', 'png', 'webp');
               $ImageExt = get_extension( $t_image_url2 );

               if (in_array($ImageExt, $AllowedFileTypes, true)) 
			   {
                  $AllowedMimeTypes = array('image/jpeg', 'image/gif', 'image/png', 'image/webp');
                  $ImageDetails = getimagesize($t_image_file2['tmp_name']);

                  if (( $ImageDetails AND in_array($ImageDetails['mime'], $AllowedMimeTypes, true))) 
				  {
                     if ($ImageContents = file_get_contents( $t_image_file2['tmp_name'] )) 
					 {
                        $NewImageURL = $torrent_dir . '/images/' . $NewTID . '_2.' . $ImageExt;

                        if (file_exists($NewImageURL)) 
						{
                           @unlink($NewImageURL);
                        }


                        if (file_put_contents($NewImageURL, $ImageContents)) 
						{
                           $COVERIMAGEUPDATED = true;
                           
						   $update_image22 = array(
			                 "t_image2" => $db->escape_string($BASEURL . '/' . $NewImageURL)
		                   );
						
						   $db->update_query("torrents", $update_image22, "id='{$NewTID}'");
						   
						   
                        }
                     }
                  }
               }
            }
         }
		 
		 
		 
		 


         if (( !isset( $COVERIMAGEUPDATED ) AND isset( $cover_photo_name ) )) 
         {
         
			$update_image3 = array(
			    "t_image" => $db->escape_string($BASEURL . '/' . $cover_photo_name)
		    );
						
			$db->update_query("torrents", $update_image3, "id='{$NewTID}'");
			
         }


         if (isset( $EditTorrent )) 
         {
             write_log( sprintf($lang->upload['editedtorrent'], '[URL='.$BASEURL."/".get_torrent_link($EditTorrentID).']<font color=red>' . $name . '</font>[/URL]', '[URL='.$BASEURL . '/'.get_profile_link($CURUSER['id']).']' . format_name($CURUSER['username'],$CURUSER['usergroup']) . '[/URL]'));
		     $cache->update_torrents();
         } 
         else 
		 {
			write_log( sprintf($lang->upload['newtorrent'], '[URL='.$BASEURL."/".get_torrent_link($NewTID).']<font color=red>' . $name . '</font>[/URL]','[URL='.$BASEURL . '/'.get_profile_link($CURUSER['id']).']' . format_name($CURUSER['username'],$CURUSER['usergroup']) . '[/URL]'));
			$cache->update_torrents();
         }

         $TorrentContents = $Torrent->bencode();

         if (( $TorrentContents AND file_put_contents( $torrent_dir . '/' . $NewTID . '.torrent', $TorrentContents) )) 
         {
            if ($IsExternalTorrent) 
			{
               $externaltorrent = $torrent_dir . '/' . $NewTID . '.torrent';
               $id = $NewTID;
               include_once( INC_PATH . '/ts_external_scrape/ts_external.php' );
            }


            if (( isset( $NfoContents ) AND !empty( $NfoContents ) )) 
			{
               
			   
			   if ($UseNFOasDescr == 'yes') 
			   {
                  $NewDescr = $BASEURL . '/viewnfo.php?id=' . $NewTID;
				  
				  $update_torr = array(
			            "descr" => $db->escape_string($NewDescr)
		          );
		          $db->update_query("torrents", $update_torr, "id='{$NewTID}'");

               }

			 
			   $replace_array = array(
			       "id" => $NewTID,
			       "nfo" => $db->escape_string($NfoContents)
		       );
		       $db->replace_query("ts_nfo", $replace_array, "", false);
			   
			    
            }

           
               if (!isset( $EditTorrent )) 
               {

                  $res = $db->sql_query( 'SELECT name FROM categories WHERE id = ' . $db->sqlesc( $category ) );
				  $Result =$db->fetch_array($res);
                  $cat = $Result["name"];
					
                 
				  $res = $db->sql_query("SELECT u.email FROM users u 
				  LEFT JOIN usergroups g ON (u.usergroup=g.gid) 
				  WHERE u.enabled='yes' AND u.status='confirmed' 
				  AND u.notifs LIKE '%[cat" . $category . "]%' 
				  AND u.notifs LIKE '%[email]%' 
				  AND u.notifs != '' 
				  AND g.isvipgroup='yes'");
				  
                  $size = mksize( $size );
                  $body = sprintf($lang->upload["emailbody"], $name, $size, $cat, $anonymous == "yes" ? "N/A" : $CURUSER["username"], $descr, $BASEURL, $NewTID, $SITENAME);
                  $to = '';
                  $nmax = 100;
                  $nthis = $ntotal = 0;
                  $total = $db->num_rows($res);

                  if (0 < $total) 
				  {
                     while ($arr = mysqli_fetch_row($res)) 
					 {
                        if ($nthis == 0) 
						{
                           $to = $arr[0];
                        } 
                        else 
						{
                           $to .= ',' . $arr[0];
                        }

                        ++$nthis;
                        ++$ntotal;

                        if (( $nthis == $nmax || $ntotal == $total )) 
						{
						   $sm = sent_mail($to, sprintf($lang->upload["emailsubject"], $SITENAME, $name), $body, "ts_upload_torrent", false);
                           $nthis = 0;
                           continue;
                        }
                     }
                  }
             
               }
            


            if (isset($Info_hash_changed)) 
            {
               stdhead($lang->upload['title']);
			   
			   echo '
			   <div class="container mt-3">
			     <div class="alert alert-primary" role="alert">
	              '.sprintf($lang->upload["done"], "download.php?id=" . $NewTID, "details.php?id=" . $NewTID), false, $lang->upload["title"].'
	             </div>
              </div>';
			   
			   
               stdfoot();
               exit();
            } 
            else 
			{
			   redirect(get_torrent_link($NewTID));
               exit();
            }
         } 
         else 
		 {
            $UploadErrors[] = $lang->upload['error8'];
         }
      }
   }

   require( INC_PATH . '/functions_category.php' );
   $postoptionstitle = array( 
   3 => $lang->upload['category'], 
   4 => '<br /><br />'.$lang->upload['cover'].'', 
   
   44 => '<br /><br />'.$lang->upload['cover5'].'', 
   
   5 => '<br />'.$lang->upload['t_link'].'<br />',  
   7 => '<br />Tags<br />', 
   8 => '<br />'.$lang->upload['anonymous1'], 
   9 => '<br /><br />'.$lang->upload['isrequest1'] );
   
   $postoptions = array( 
   3 => ts_category_list('category', (isset($category) ? $category : '')), 
   4 => '
     
	  <br />
	  
	  
	  <input type="radio" class="form-check-input" name="nothingtopost" value="1" onclick="ChangeBox(this.value);" checked="checked" /> ' . $lang->upload['cover1'] . '
      <div style="display: inline;" id="nothingtopost1">
         <br />
		  
		  <label>
		  <input type="text" class="form-control mt-3" name="t_image_url" size="70" value="' . (isset($t_image_url) ? htmlspecialchars_uni($t_image_url) : '') . '" />
		  </label>
		  

		  
		  <a href="#myModal" type="" class="" data-bs-toggle="modal" data-bs-target="#myModal">
          <img src="'.htmlspecialchars_uni($t_image_url).'" class="rounded" border="0" width="100"></a>
		  
		  
		  
		  <div class="modal fade" id="myModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">'.htmlspecialchars_uni($EditTorrent['name']).'</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <!--        include image here-->
        <img src="'.$t_image_url.'" alt="Image Title" class="img-fluid">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
		   
      
	  </div>
	  
	  
	  
	  
	  
      <br />
      
      <input type="radio" class="form-check-input" name="nothingtopost" value="2" onclick="ChangeBox(this.value);" /> ' . $lang->upload['cover2'] . '
      <div style="display: none;" id="nothingtopost2">
         <br />
		 
		 
		 



<div class="container py-5">
 <div class="row py-4">
        <div class="col-lg-6 mx-auto">

            <!-- Upload image input-->
            <div class="input-group mb-3 px-2 py-2 rounded-pill bg-white shadow-sm">
                <input name="t_image_file" id="t_image_file" type="file" onchange="readURL(this);" class="form-control border-0">
                <label id="upload-label" for="t_image_file" class="font-weight-light text-muted">Choose file</label>
                <div class="input-group-append">
                    <label for="t_image_file" class="btn btn-light m-0 rounded-pill px-4"> <i class="fa fa-cloud-upload mr-2 text-muted"></i><small class="text-uppercase font-weight-bold text-muted">Choose file</small></label>
                </div>
            </div>

            <!-- Uploaded image area-->
            <p class="font-italic text-white text-center">The image uploaded will be rendered inside the box below.</p>
            <div class="image-area mt-4"><img id="imageResult" src="#" alt="" class="img-fluid rounded shadow-sm mx-auto d-block"></div>

        </div>
    </div>
</div>












		 
		 
      </div>', 
	  
	  
	  
	  
	   44 => '
     
	  <br />
	  <input type="radio" class="form-check-input" name="nothingtopost" value="3" onclick="ChangeBox(this.value);" checked="checked" /> ' . $lang->upload['cover3'] . '
      <div style="display: inline;" id="nothingtopost3">
         <br />
		  
		  <label>
		  <input type="text" class="form-control mt-3" name="t_image_url2" size="70" value="' . (isset($t_image_url2) ? htmlspecialchars_uni($t_image_url2) : '') . '" />
		  </label>
		  
		  
		  
		  
		  
		  <a href="#myModal2" type="" class="" data-bs-toggle="modal" data-bs-target="#myModal2">
          <img src="'.htmlspecialchars_uni($t_image_url2).'" class="rounded" border="0" width="100"></a>
		  
		  
		  <div class="modal fade" id="myModal2" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">'.htmlspecialchars_uni($EditTorrent['name']).'</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <!--        include image here-->
        <img src="'.$t_image_url2.'" alt="Image Title" class="img-fluid">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
		  
 
		  
      </div>
      <br />
      
      <input type="radio" class="form-check-input" name="nothingtopost" value="4" onclick="ChangeBox(this.value);" /> ' . $lang->upload['cover4'] . '
      <div style="display: none;" id="nothingtopost4">
         <br />
		 
		 
		 



 <div class="row py-4">
        <div class="col-lg-6 mx-auto">

            <!-- Upload image input-->
            <div class="input-group mb-3 px-2 py-2 rounded-pill bg-white shadow-sm">
                <input name="t_image_file2" id="t_image_file2" type="file" onchange="readURL2(this);" class="form-control border-0">
                <label id="upload-label2" for="t_image_file2" class="font-weight-light text-muted">Choose file</label>
                <div class="input-group-append">
                    <label for="t_image_file2" class="btn btn-light m-0 rounded-pill px-4"> <i class="fa fa-cloud-upload mr-2 text-muted"></i>
					<small class="text-uppercase font-weight-bold text-muted">Choose file</small></label>
                </div>
            </div>

            <!-- Uploaded image area-->
            <p class="font-italic text-white text-center">The image uploaded will be rendered inside the box below.</p>
            <div class="image-area mt-4"><img id="imageResult2" src="#" alt="" class="img-fluid rounded shadow-sm mx-auto d-block"></div>

        </div>
    </div>

		 
		 
		 
		 
		 
		 
		 
		 
		 
		 
		 
		 
		 
		 
		 
      </div>', 
	  
	  
	  
	  
	  
	  
	  
	  
	  
	  
	  
	  
	  
	  5 => '<label><input type="text" class="form-control mt-3" name="t_link" size="70" value="' . (isset($t_link) ? htmlspecialchars_uni($t_link) : '') . '" /></label><br />' . $lang->upload['t_link2'], 
	  
	  8 => '<input type="checkbox" class="form-check-input" name="anonymous" value="yes"' . (((isset($anonymous) AND $anonymous == 'yes') || ( TS_Match( $CURUSER['options'], 'I3' ) || TS_Match( $CURUSER['options'], 'I4' ) ) ) ? ' checked="checked"' : '') . ' class="inlineimg" /> ' . $lang->upload['anonymous2'], 
	  
	  9 => '<input type="checkbox" class="form-check-input" name="isrequest" value="yes"' . ((isset($isrequest) AND $isrequest == 'yes') ? ' checked="checked"' : '') . ' class="inlineimg" />' . $lang->upload['isrequest2'].'<br />' );

   if ($is_mod) 
   {
      $postoptionstitle[] = '<br />
	  
	  <div class="container-mt3">
  <div class="card border-0 mb-4">
	<div class="card-header rounded-bottom text-19 fw-bold">
		' . $lang->upload['moptions'] . '
	</div>
	 </div>
		</div>';
		
		
      $postoptions[] = '
	  
	  <table>
	  
      <tr>
         <td class="none">
            <b>' . $lang->upload['free1'] . '</b><br />
            <input type="checkbox" class="form-check-input" name="free" value="yes"' . ((isset($free) AND $free == 'yes' ) ? ' checked="checked"' : '') . ' class="inlineimg" /> ' . $lang->upload['free2'] . '
         </td>
		 
         <td class="none">
            <b>' . $lang->upload['silver1'] . '</b><br />
            <input type="checkbox" class="form-check-input" name="silver" value="yes"' . ((isset($silver) AND $silver == 'yes' ) ? ' checked="checked"' : '') . ' class="inlineimg" /> ' . $lang->upload['silver2'] . '
         </td>
      </tr>
      
      <tr>
         <td class="none">
            <b>' . $lang->upload['doubleupload1'] . '</b><br />
            <input type="checkbox" class="form-check-input" name="doubleupload" value="yes"' . ((isset($doubleupload) AND $doubleupload == 'yes' ) ? ' checked="checked"' : '') . ' class="inlineimg" /> ' . $lang->upload['doubleupload2'] . '
         </td>
         <td class="none">
            <b>' . $lang->upload['allowcomments1'] . '</b><br />
            <input type="checkbox" class="form-check-input" name="allowcomments" value="no"' . ((isset($allowcomments) AND $allowcomments == 'no' ) ? ' checked="checked"' : '') . ' class="inlineimg" /> ' . $lang->upload['allowcomments2'] . '
         </td>
      </tr>

      <tr>
         <td class="none">
            <b>' . $lang->upload['sticky1'] . '</b><br />
            <input type="checkbox" class="form-check-input" name="sticky" value="yes"' . ((isset($sticky) AND $sticky == 'yes' ) ? ' checked="checked"' : '') . ' class="inlineimg" /> ' . $lang->upload['sticky2'] . '
         </td>
         <td class="none">
            <b>' . $lang->upload['nuked1'] . '</b><br />
            <input type="checkbox" class="form-check-input" name="isnuked" value="yes"' . (( isset( $isnuked ) AND $isnuked == 'yes' ) ? ' checked="checked"' : '') . ' class="inlineimg" onclick="ShowHideField(\'nukereason\');" /> ' . $lang->upload['nuked2'] . '
            <div style="display:' . (( isset( $isnuked ) AND $isnuked == 'yes' ) ? 'inline' : 'none') . ';" id="nukereason">
               <br /><b>' . $lang->upload['nreason'] . '</b> <input type="text" name="WhyNuked" value="' . (isset( $WhyNuked ) ? htmlspecialchars_uni( $WhyNuked ) : '') . '" size="40" />
            </div>
         </td>
      </tr>
   </table>
   ';
   }

   $str = '';



   $str .= '
<div id="ts_uploading_progress">
</div>
<div id="ts_upload_form">
   <form method="post" action="' . $_SERVER['SCRIPT_NAME'] . (isset( $EditTorrent ) ? '?id=' . $EditTorrentID : '') . '" name="ts_upload_torrent" enctype="multipart/form-data" onsubmit="return ProcessUpload();">';
    
    $FirstTabs = array( $lang->upload['torrentfile'] => '

	<div class="mb-3">
  <label for="torrentfile" class="form-label">
  <input class="form-control" type="file" name="torrentfile" id="torrentfile" onchange="checkFileName(this.value);" />
  </label>

	<input class="form-check-input" type="checkbox"" name="IsExternalTorrent" value="yes"' . (( isset( $IsExternalTorrent ) AND $IsExternalTorrent == true ) ? ' checked="checked"' : '') . ' />
	' . $lang->upload['isexternal'].' </div>',
	
	
	$lang->upload['nfofile'] => '
	
	
	<div class="mb-3">
  <label for="nfofile" class="form-label">
  <input class="form-control" type="file" name="nfofile" id="nfofile" onchange="checknfoFileName(this.value);" />
  </label>

<input class="form-check-input" type="checkbox" name="UseNFOasDescr" value="yes"' . (( isset( $UseNFOasDescr ) AND $UseNFOasDescr == 'yes' ) ? ' checked="checked"' : '') . ' onclick="enableDisableTA(this.checked);" />
 ' . $lang->upload['UseNFOasDescr'].'</div>', 
 
 

$lang->upload['tname'] => 
	
	'<input type="text" class="form-control mt-3" name="subject" id="subject" style="width: 650px" value="' . (isset( $name ) ? htmlspecialchars_uni( $name ) : '') . '" tabindex="1"' . (defined( 'SUBJECT_EXTRA' ) ? SUBJECT_EXTRA : '') . ' autocomplete="off" /><br />' );
    $str .= insert_editor(false, "", isset($descr) ? $descr : "", $lang->upload["title"], sprintf($lang->upload["title2"], $AnnounceURL), $postoptionstitle, $postoptions, false, $FirstTabs, isset($EditTorrent) ? $lang->upload["savechanges"] : $lang->upload["title"]);
   $str .= '
   </form>
</div>
<script type="text/javascript">
   function file_get_ext(filename)
   {
      return typeof filename != "undefined" ? filename.substring(filename.lastIndexOf(".")+1, filename.length).toLowerCase() : false;
   }

   function checkFileName(path)
   {
      var ext = file_get_ext(path);
      //var fullpath = path;
      //var newpath = fullpath.substring(0, fullpath.length-8);
      //TSGetID("subject").value = newpath;

      if (!ext || ext != "torrent")
      {
         alert("' . strip_tags( $lang->upload['error3'] ) . '");
      }
   }

   function checknfoFileName(path)
   {
      var ext = file_get_ext(path);

      if (!ext || ext != "nfo")
      {
         alert("' . strip_tags( $lang->upload['error10'] ) . '");
      }
   }

   function enableDisableTA(cStatus)
   {
      if (cStatus == true)
      {
         TSGetID("message_new").disabled = "disabled";
         TSGetID("message_old").disabled = "disabled";
         TSGetID("message_new").value = "";
         TSGetID("message_old").value = "";
      }
      else
      {
         TSGetID("message_new").disabled = "";
         TSGetID("message_old").disabled = "";
      }
   }

   function ProcessUpload()
   {
      TSGetID(\'ts_uploading_progress\').innerHTML = \'<div class="container mt-3"><table width="100%" border="0" class="tborder" cellpadding="5" cellspacing="0"><tr></tr><tr><td><button class="btn btn-primary"><span class="spinner-border spinner-border-sm"></span> ' . trim($lang->upload['uploading']) . '</button></td></tr></table></div>\';
      TSGetID(\'ts_upload_form\').style.display = \'none\';
      return true;
   }

   function ChangeBox(BoxValue)
   {
      TSGetID("nothingtopost1").style.display = "none";
      TSGetID("nothingtopost2").style.display = "none";
	  TSGetID("nothingtopost3").style.display = "none";
      TSGetID("nothingtopost4").style.display = "none";
      TSGetID("nothingtopost"+BoxValue).style.display = "inline";
   }
   
 </script>';

   if ((isset( $upload_page_notice) AND !empty($upload_page_notice ))) 
   {
      $str = show_notice( $upload_page_notice ) . $str;
   }

  
   stdhead($lang->upload["title"]);
   

?>

<script>
function readURL(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();

        reader.onload = function (e) {
            $('#imageResult')
                .attr('src', e.target.result);
        };
        reader.readAsDataURL(input.files[0]);
    }
}

$(function () {
    $('#t_image_file').on('change', function () {
        readURL(input);
    });
});

/*  ==========================================
    SHOW UPLOADED IMAGE NAME
* ========================================== */
var input = document.getElementById( 't_image_file' );
var infoArea = document.getElementById( 'upload-label' );

input.addEventListener( 'change', showFileName );
function showFileName( event ) {
  var input = event.srcElement;
  var fileName = input.files[0].name;
  infoArea.textContent = 'File name: ' + fileName;
}

</script>



<script>
function readURL2(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();

        reader.onload = function (e) {
            $('#imageResult2')
                .attr('src', e.target.result);
        };
        reader.readAsDataURL(input.files[0]);
    }
}

$(function () {
    $('#t_image_file2').on('change', function () {
        readURL2(input);
    });
});

/*  ==========================================
    SHOW UPLOADED IMAGE 2 NAME
* ========================================== */
var input = document.getElementById( 't_image_file2' );
var infoArea = document.getElementById( 'upload-label2' );

input.addEventListener( 'change', showFileName );
function showFileName( event ) {
  var input = event.srcElement;
  var fileName = input.files[0].name;
  infoArea.textContent = 'File name: ' + fileName;
}
</script>
<?   



   show_upload_errors();
   echo $str;
   
   
   stdfoot();
   
?>
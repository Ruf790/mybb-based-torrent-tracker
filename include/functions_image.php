<?php


/**
 * Generates a thumbnail based on specified dimensions (supports png, jpg, and gif)
 *
 * @param string $file the full path to the original image
 * @param string $path the directory path to where to save the new image
 * @param string $filename the filename to save the new image as
 * @param integer $maxheight maximum hight dimension
 * @param integer $maxwidth maximum width dimension
 * @return array thumbnail on success, error code 4 on failure
 */
 
function generate_thumbnail($file, $path, $filename, $maxheight, $maxwidth)
{
	$thumb = [];

	if(!function_exists("imagecreate"))
	{
		$thumb['code'] = 3;
		return $thumb;
	}

	$imgdesc = @getimagesize($file);
	if(!$imgdesc || !isset($imgdesc[0]) || !isset($imgdesc[1]) || !isset($imgdesc[2]))
	{
		$thumb['code'] = 3;
		return $thumb;
	}

	$imgwidth = $imgdesc[0];
	$imgheight = $imgdesc[1];
	$imgtype = $imgdesc[2];
	$imgbits = $imgdesc['bits'] ?? null;
	$imgchan = $imgdesc['channels'] ?? null;

	if($imgwidth == 0 || $imgheight == 0)
	{
		$thumb['code'] = 3;
		return $thumb;
	}

	if(($imgwidth >= $maxwidth) || ($imgheight >= $maxheight))
	{
		check_thumbnail_memory($imgwidth, $imgheight, $imgtype, $imgbits, $imgchan);

		$im = false;
		if($imgtype == IMAGETYPE_PNG && function_exists("imagecreatefrompng"))
		{
			$im = @imagecreatefrompng($file);
		}
		elseif($imgtype == IMAGETYPE_JPEG && function_exists("imagecreatefromjpeg"))
		{
			$im = @imagecreatefromjpeg($file);
		}
		elseif($imgtype == IMAGETYPE_GIF && function_exists("imagecreatefromgif"))
		{
			$im = @imagecreatefromgif($file);
		}
		elseif($imgtype == IMAGETYPE_WEBP && function_exists("imagecreatefromwebp"))
		{
			$im = @imagecreatefromwebp($file);
		}
		else
		{
			$thumb['code'] = 3;
			return $thumb;
		}

		if(!$im)
		{
			$thumb['code'] = 3;
			return $thumb;
		}

		$scale = scale_image($imgwidth, $imgheight, $maxwidth, $maxheight);
		$thumbwidth = $scale['width'];
		$thumbheight = $scale['height'];
		$thumbim = @imagecreatetruecolor($thumbwidth, $thumbheight);

		if(!$thumbim)
		{
			$thumbim = @imagecreate($thumbwidth, $thumbheight);
			$resized = true;
		}

		// Preserve transparency
		if($imgtype == IMAGETYPE_PNG || $imgtype == IMAGETYPE_WEBP)
		{
			imagealphablending($thumbim, false);
			imagefill($thumbim, 0, 0, imagecolorallocatealpha($thumbim, 0, 0, 0, 127));
			imagesavealpha($thumbim, true);
		}
		elseif($imgtype == IMAGETYPE_GIF)
		{
			$trans_color = imagecolortransparent($im);
			if($trans_color >= 0 && $trans_color < imagecolorstotal($im))
			{
				$trans = imagecolorsforindex($im, $trans_color);
				$new_trans_color = imagecolorallocate($thumbim, $trans['red'], $trans['green'], $trans['blue']);
				imagefill($thumbim, 0, 0, $new_trans_color);
				imagecolortransparent($thumbim, $new_trans_color);
			}
		}

		if(!isset($resized))
		{
			@imagecopyresampled($thumbim, $im, 0, 0, 0, 0, $thumbwidth, $thumbheight, $imgwidth, $imgheight);
		}
		else
		{
			@imagecopyresized($thumbim, $im, 0, 0, 0, 0, $thumbwidth, $thumbheight, $imgwidth, $imgheight);
		}
		@imagedestroy($im);

		// Save thumbnail
		switch($imgtype)
		{
			case IMAGETYPE_GIF:
				if(function_exists("imagegif"))
				{
					@imagegif($thumbim, $path . "/" . $filename);
				}
				else
				{
					@imagejpeg($thumbim, $path . "/" . $filename);
				}
				break;
			case IMAGETYPE_JPEG:
				@imagejpeg($thumbim, $path . "/" . $filename, 90);
				break;
			case IMAGETYPE_PNG:
				@imagepng($thumbim, $path . "/" . $filename, 6);
				break;
			case IMAGETYPE_WEBP:
				if(function_exists("imagewebp"))
				{
					@imagewebp($thumbim, $path . "/" . $filename, 80);
				}
				else
				{
					@imagejpeg($thumbim, $path . "/" . $filename);
				}
				break;
		}

		@my_chmod($path . "/" . $filename, '0644');
		@imagedestroy($thumbim);

		$thumb['code'] = 1;
		$thumb['filename'] = $filename;
		return $thumb;
	}
	else
	{
		return ["code" => 4];
	}
}

 
 

/**
 * Attempts to allocate enough memory to generate the thumbnail
 *
 * @param integer $width width dimension
 * @param integer $height height dimension
 * @param string $type one of the IMAGETYPE_XXX constants indicating the type of the image
 * @param string $bitdepth the bits area the number of bits for each color
 * @param string $channels the channels - 3 for RGB pictures and 4 for CMYK pictures
 * @return bool
 */
function check_thumbnail_memory($width, $height, $type, $bitdepth, $channels)
{
	if(!function_exists("memory_get_usage"))
	{
		return false;
	}

	$memory_limit = @ini_get("memory_limit");
	if(!$memory_limit || $memory_limit == -1)
	{
		return false;
	}

	$limit = preg_match("#^([0-9]+)\s?([kmg])b?$#i", trim(my_strtolower($memory_limit)), $matches);
	$memory_limit = (int)$memory_limit;
	if($matches[1] && $matches[2])
	{
		switch($matches[2])
		{
			case "k":
				$memory_limit = $matches[1] * 1024;
				break;
			case "m":
				$memory_limit = $matches[1] * 1048576;
				break;
			case "g":
				$memory_limit = $matches[1] * 1073741824;
		}
	}
	$current_usage = memory_get_usage();
	$free_memory = $memory_limit - $current_usage;

	$thumbnail_memory = round(($width * $height * $bitdepth * $channels / 8) * 5);
	$thumbnail_memory += 2097152;

	if($thumbnail_memory > $free_memory)
	{
		if($matches[1] && $matches[2])
		{
			switch($matches[2])
			{
				case "k":
					$memory_limit = ceil((($memory_limit+$thumbnail_memory) / 1024))."K";
					break;
				case "m":
					$memory_limit = ceil((($memory_limit+$thumbnail_memory) / 1048576))."M";
					break;
				case "g":
					$memory_limit = ceil((($memory_limit+$thumbnail_memory) / 1073741824))."G";
			}
		}

		@ini_set("memory_limit", $memory_limit);
	}

	return true;
}

/**
 * Figures out the correct dimensions to use
 *
 * @param integer $width current width dimension
 * @param integer $height current height dimension
 * @param integer $maxwidth max width dimension
 * @param integer $maxheight max height dimension
 * @return array correct height & width
 */
function scale_image($width, $height, $maxwidth, $maxheight)
{
	$width = (int)$width;
	$height = (int)$height;

	if(!$width) $width = $maxwidth;
	if(!$height) $height = $maxheight;

	$newwidth = $width;
	$newheight = $height;

	if($width > $maxwidth)
	{
		$newwidth = $maxwidth;
		$newheight = ceil(($height*(($maxwidth*100)/$width))/100);
		$height = $newheight;
		$width = $newwidth;
	}
	if($height > $maxheight)
	{
		$newheight = $maxheight;
		$newwidth = ceil(($width*(($maxheight*100)/$height))/100);
	}
	$ret['width'] = $newwidth;
	$ret['height'] = $newheight;
	return $ret;
}

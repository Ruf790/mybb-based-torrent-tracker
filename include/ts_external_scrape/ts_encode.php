<?php
/*
 * @ https://EasyToYou.eu - IonCube v11 Decoder Online
 * @ PHP 7.2 & 7.3
 * @ Decoder version: 1.0.6
 * @ Release: 10/08/2022
 */

class BEncode
{
    public function makeSorted($array)
    {
        $i = 0;
        if (empty($array)) {
            return $array;
        }
        foreach ($array as $key => $value) {
            $keys[$i++] = stripslashes($key);
        }
        sort($keys);
        for ($i = 0; isset($keys[$i]); $i++) {
            $return[addslashes($keys[$i])] = $array[addslashes($keys[$i])];
        }
        return $return;
    }
    public function encodeEntry($entry, &$fd, $unstrip = false)
    {
        if (is_bool($entry)) {
            $fd .= "de";
        } else {
            if (is_int($entry) || is_float($entry)) {
                $fd .= "i" . $entry . "e";
            } else {
                if ($unstrip) {
                    $myentry = stripslashes($entry);
                } else {
                    $myentry = $entry;
                }
                $length = strlen($myentry);
                $fd .= $length . ":" . $myentry;
            }
        }
    }
    public function encodeList($array, &$fd)
    {
        $fd .= "l";
        if (empty($array)) {
            $fd .= "e";
        } else {
            for ($i = 0; isset($array[$i]); $i++) {
                $this->decideEncode($array[$i], $fd);
            }
            $fd .= "e";
        }
    }
    public function decideEncode($unknown, &$fd)
    {
        if (is_array($unknown)) {
            if (isset($unknown[0]) || empty($unknown)) {
                return $this->encodeList($unknown, $fd);
            }
            return $this->encodeDict($unknown, $fd);
        }
        $this->encodeEntry($unknown, $fd);
    }
    public function encodeDict($array, &$fd)
    {
        $fd .= "d";
        if (is_bool($array)) {
            $fd .= "e";
        } else {
            $newarray = $this->makeSorted($array);
            foreach ($newarray as $left => $right) {
                $this->encodeEntry($left, $fd, true);
                $this->decideEncode($right, $fd);
            }
            $fd .= "e";
            return NULL;
        }
    }
}
function BEncode($array)
{
    $string = "";
    $encoder = new BEncode();
    $encoder->decideEncode($array, $string);
    return $string;
}

?>
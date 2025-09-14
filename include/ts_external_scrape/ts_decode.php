<?php
/*
 * @ https://EasyToYou.eu - IonCube v11 Decoder Online
 * @ PHP 7.2 & 7.3
 * @ Decoder version: 1.0.6
 * @ Release: 10/08/2022
 */

class BDecode
{
    public function numberdecode($wholefile, $start)
    {
        $ret[0] = 0;
        $offset = $start;
        $negative = false;
        if ($wholefile[$offset] == "-") {
            $negative = true;
            $offset++;
        }
        if ($wholefile[$offset] == "0") {
            $offset++;
            if ($negative) {
                return [false];
            }
            if ($wholefile[$offset] == ":" || $wholefile[$offset] == "e") {
                $offset++;
                $ret[0] = 0;
                $ret[1] = $offset;
                return $ret;
            }
            return [false];
        }
        while (true) {
            if ("0" <= $wholefile[$offset] && $wholefile[$offset] <= "9") {
                $ret[0] *= 10;
                $ret[0] += ord($wholefile[$offset]) - ord("0");
                $offset++;
            } else {
                if ($wholefile[$offset] == "e" || $wholefile[$offset] == ":") {
                    $ret[1] = $offset + 1;
                    if ($negative) {
                        if ($ret[0] == 0) {
                            return [false];
                        }
                        $ret[0] = -1 * $ret[0];
                    }
                    return $ret;
                }
                return [false];
            }
        }
    }
    public function decodeEntry($wholefile, $offset = 0)
    {
        if ($wholefile[$offset] == "d") {
            return $this->decodeDict($wholefile, $offset);
        }
        if ($wholefile[$offset] == "l") {
            return $this->decodelist($wholefile, $offset);
        }
        if ($wholefile[$offset] == "i") {
            $offset++;
            return $this->numberdecode($wholefile, $offset);
        }
        $info = $this->numberdecode($wholefile, $offset);
        if ($info[0] === false) {
            return [false];
        }
        $ret[0] = substr($wholefile, $info[1], $info[0]);
        $ret[1] = $info[1] + strlen($ret[0]);
        return $ret;
    }
    public function decodeList($wholefile, $start)
    {
        $offset = $start + 1;
        $i = 0;
        if ($wholefile[$start] != "l") {
            return [false];
        }
        $ret = [];
        while (true) {
            if ($wholefile[$offset] != "e") {
                $value = $this->decodeEntry($wholefile, $offset);
                if ($value[0] === false) {
                    return [false];
                }
                list($ret[$i], $offset) = $value;
                $i++;
            }
        }
        $final[0] = $ret;
        $final[1] = $offset + 1;
        return $final;
    }
    public function decodeDict($wholefile, $start = 0)
    {
        $offset = $start;
        if ($wholefile[$offset] == "l") {
            return $this->decodeList($wholefile, $start);
        }
        if ($wholefile[$offset] != "d") {
            return false;
        }
        $ret = [];
        $offset++;
        while (true) {
            if ($wholefile[$offset] == "e") {
                $offset++;
            } else {
                $left = $this->decodeEntry($wholefile, $offset);
                if (!$left[0]) {
                    return false;
                }
                $offset = $left[1];
                if ($wholefile[$offset] == "d") {
                    $value = $this->decodedict($wholefile, $offset);
                    if (!$value[0]) {
                        return false;
                    }
                    list($ret[addslashes($left[0])], $offset) = $value;
                } else {
                    if ($wholefile[$offset] == "l") {
                        $value = $this->decodeList($wholefile, $offset);
                        if (!$value[0] && is_bool($value[0])) {
                            return false;
                        }
                        list($ret[addslashes($left[0])], $offset) = $value;
                    } else {
                        $value = $this->decodeEntry($wholefile, $offset);
                        if ($value[0] === false) {
                            return false;
                        }
                        list($ret[addslashes($left[0])], $offset) = $value;
                    }
                }
            }
        }
        if (empty($ret)) {
            $final[0] = true;
        } else {
            $final[0] = $ret;
        }
        $final[1] = $offset;
        return $final;
    }
}
function BDecode($wholefile)
{
    $decoder = new BDecode();
    $return = $decoder->decodeEntry($wholefile);
    return $return[0];
}

?>
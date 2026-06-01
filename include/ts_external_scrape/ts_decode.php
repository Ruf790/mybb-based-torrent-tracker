<?php
declare(strict_types=1);

class BDecode
{
    public function numberdecode(string $wholefile, int $start): array
    {
        $len    = strlen($wholefile);
        $ret    = [0, 0];
        $offset = $start;

        if ($offset >= $len) return [false];

        $negative = false;
        if ($wholefile[$offset] === '-') {
            $negative = true;
            $offset++;
        }

        if ($offset >= $len) return [false];

        if ($wholefile[$offset] === '0') {
            $offset++;
            if ($negative) return [false];
            if ($offset >= $len) return [false];
            if ($wholefile[$offset] === ':' || $wholefile[$offset] === 'e') {
                $ret[0] = 0;
                $ret[1] = $offset + 1;
                return $ret;
            }
            return [false];
        }

        while (true) {
            if ($offset >= $len) return [false];
            $c = $wholefile[$offset];
            if ($c >= '0' && $c <= '9') {
                $ret[0] = $ret[0] * 10 + ord($c) - 48;
                $offset++;
            } elseif ($c === 'e' || $c === ':') {
                $ret[1] = $offset + 1;
                if ($negative) {
                    if ($ret[0] === 0) return [false];
                    $ret[0] = -$ret[0];
                }
                return $ret;
            } else {
                return [false];
            }
        }
    }

    public function decodeEntry(string $wholefile, int $offset = 0): array
    {
        $len = strlen($wholefile);
        if ($offset >= $len) return [false];

        $c = $wholefile[$offset];

        if ($c === 'd') return $this->decodeDict($wholefile, $offset);
        if ($c === 'l') return $this->decodeList($wholefile, $offset);

        if ($c === 'i') {
            return $this->numberdecode($wholefile, $offset + 1);
        }

        $info = $this->numberdecode($wholefile, $offset);
        if ($info[0] === false) return [false];

        $str    = substr($wholefile, $info[1], $info[0]);
        $ret[0] = $str;
        $ret[1] = $info[1] + strlen($str);
        return $ret;
    }

    public function decodeList(string $wholefile, int $start): array
    {
        $len = strlen($wholefile);
        if ($start >= $len || $wholefile[$start] !== 'l') return [false];

        $offset = $start + 1;
        $ret    = [];
        $i      = 0;

        while (true) {
            if ($offset >= $len) return [false];
            if ($wholefile[$offset] === 'e') {
                break;
            }
            $value = $this->decodeEntry($wholefile, $offset);
            if ($value[0] === false) return [false];
            [$ret[$i], $offset] = $value;
            $i++;
        }

        return [$ret, $offset + 1];
    }

    public function decodeDict(string $wholefile, int $start = 0): array|false
    {
        $len    = strlen($wholefile);
        $offset = $start;

        if ($offset >= $len) return false;

        if ($wholefile[$offset] === 'l') {
            return $this->decodeList($wholefile, $start);
        }

        if ($wholefile[$offset] !== 'd') return false;

        $ret = [];
        $offset++;

        while (true) {
            if ($offset >= $len) return false;

            if ($wholefile[$offset] === 'e') {
                $offset++;
                break;
            }

            $left = $this->decodeEntry($wholefile, $offset);
            if (!$left[0]) return false;

            $offset = $left[1];
            if ($offset >= $len) return false;

            $key = addslashes($left[0]);
            $c   = $wholefile[$offset];

            if ($c === 'd') {
                $value = $this->decodeDict($wholefile, $offset);
                if ($value === false || !$value[0]) return false;
                [$ret[$key], $offset] = $value;

            } elseif ($c === 'l') {
                $value = $this->decodeList($wholefile, $offset);
                if ($value[0] === false) return false;
                [$ret[$key], $offset] = $value;

            } else {
                $value = $this->decodeEntry($wholefile, $offset);
                if ($value[0] === false) return false;
                [$ret[$key], $offset] = $value;
            }
        }

        $final[0] = empty($ret) ? true : $ret;
        $final[1] = $offset;
        return $final;
    }
}

function BDecode(string $wholefile): mixed
{
    $decoder = new BDecode();
    $return  = $decoder->decodeEntry($wholefile);
    return $return[0];
}
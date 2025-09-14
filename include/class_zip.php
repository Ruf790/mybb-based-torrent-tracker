<?php
/*
 * @ https://EasyToYou.eu - IonCube v11 Decoder Online
 * @ PHP 7.2 & 7.3
 * @ Decoder version: 1.0.6
 * @ Release: 10/08/2022
 */

if (!defined("IN_TRACKER")) {
    exit("<font face='verdana' size='2' color='darkred'><b>Error!</b> Direct initialization of this file is not allowed.</font>");
}
class createZip
{
    public $compressedData = [];
    public $centralDirectory = [];
    public $endOfCentralDirectory = "PK\5\6\0\0\0\0";
    public $oldOffset = 0;
    public function addDirectory($directoryName)
    {
        $directoryName = str_replace("\\", "/", $directoryName);
        $feedArrayRow = "PK\3\4";
        $feedArrayRow .= "\n\0";
        $feedArrayRow .= "\0\0";
        $feedArrayRow .= "\0\0";
        $feedArrayRow .= "\0\0\0\0";
        $feedArrayRow .= pack("V", 0);
        $feedArrayRow .= pack("V", 0);
        $feedArrayRow .= pack("V", 0);
        $feedArrayRow .= pack("v", strlen($directoryName));
        $feedArrayRow .= pack("v", 0);
        $feedArrayRow .= $directoryName;
        $feedArrayRow .= pack("V", 0);
        $feedArrayRow .= pack("V", 0);
        $feedArrayRow .= pack("V", 0);
        $this->compressedData[] = $feedArrayRow;
        $newOffset = strlen(implode("", $this->compressedData));
        $addCentralRecord = "PK\1\2";
        $addCentralRecord .= "\0\0";
        $addCentralRecord .= "\n\0";
        $addCentralRecord .= "\0\0";
        $addCentralRecord .= "\0\0";
        $addCentralRecord .= "\0\0\0\0";
        $addCentralRecord .= pack("V", 0);
        $addCentralRecord .= pack("V", 0);
        $addCentralRecord .= pack("V", 0);
        $addCentralRecord .= pack("v", strlen($directoryName));
        $addCentralRecord .= pack("v", 0);
        $addCentralRecord .= pack("v", 0);
        $addCentralRecord .= pack("v", 0);
        $addCentralRecord .= pack("v", 0);
        $ext = "\0\0\20\0";
        $ext = "����";
        $addCentralRecord .= pack("V", 16);
        $addCentralRecord .= pack("V", $this->oldOffset);
        $this->oldOffset = $newOffset;
        $addCentralRecord .= $directoryName;
        $this->centralDirectory[] = $addCentralRecord;
    }
    public function addFile($data, $directoryName)
    {
        $directoryName = str_replace("\\", "/", $directoryName);
        $feedArrayRow = "PK\3\4";
        $feedArrayRow .= "\24\0";
        $feedArrayRow .= "\0\0";
        $feedArrayRow .= "\10\0";
        $feedArrayRow .= "\0\0\0\0";
        $uncompressedLength = strlen($data);
        $compression = crc32($data);
        $gzCompressedData = gzcompress($data);
        $gzCompressedData = substr(substr($gzCompressedData, 0, strlen($gzCompressedData) - 4), 2);
        $compressedLength = strlen($gzCompressedData);
        $feedArrayRow .= pack("V", $compression);
        $feedArrayRow .= pack("V", $compressedLength);
        $feedArrayRow .= pack("V", $uncompressedLength);
        $feedArrayRow .= pack("v", strlen($directoryName));
        $feedArrayRow .= pack("v", 0);
        $feedArrayRow .= $directoryName;
        $feedArrayRow .= $gzCompressedData;
        $feedArrayRow .= pack("V", $compression);
        $feedArrayRow .= pack("V", $compressedLength);
        $feedArrayRow .= pack("V", $uncompressedLength);
        $this->compressedData[] = $feedArrayRow;
        $newOffset = strlen(implode("", $this->compressedData));
        $addCentralRecord = "PK\1\2";
        $addCentralRecord .= "\0\0";
        $addCentralRecord .= "\24\0";
        $addCentralRecord .= "\0\0";
        $addCentralRecord .= "\10\0";
        $addCentralRecord .= "\0\0\0\0";
        $addCentralRecord .= pack("V", $compression);
        $addCentralRecord .= pack("V", $compressedLength);
        $addCentralRecord .= pack("V", $uncompressedLength);
        $addCentralRecord .= pack("v", strlen($directoryName));
        $addCentralRecord .= pack("v", 0);
        $addCentralRecord .= pack("v", 0);
        $addCentralRecord .= pack("v", 0);
        $addCentralRecord .= pack("v", 0);
        $addCentralRecord .= pack("V", 32);
        $addCentralRecord .= pack("V", $this->oldOffset);
        $this->oldOffset = $newOffset;
        $addCentralRecord .= $directoryName;
        $this->centralDirectory[] = $addCentralRecord;
    }
    public function getZippedfile()
    {
        $data = implode("", $this->compressedData);
        $controlDirectory = implode("", $this->centralDirectory);
        return $data . $controlDirectory . $this->endOfCentralDirectory . pack("v", count($this->centralDirectory)) . pack("v", count($this->centralDirectory)) . pack("V", strlen($controlDirectory)) . pack("V", strlen($data)) . "\0\0";
    }
    public function forceDownload($archiveName)
    {
        $headerInfo = "";
        if (ini_get("zlib.output_compression")) {
            ini_set("zlib.output_compression", "Off");
        }
        if ($archiveName == "") {
            echo "<html><title>Download </title><body><br /><B>ERROR:</B> The download file was NOT SPECIFIED.</body></html>";
            exit;
        }
        if (!file_exists($archiveName)) {
            echo "<html><title>Download </title><body><br /><B>ERROR:</B> File not found.</body></html>";
            exit;
        }
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Disposition: attachment; filename=" . basename($archiveName) . ";");
        header("Content-Transfer-Encoding: binary");
        header("Content-Type: application/x-zip-compressed");
        header("Content-Length: " . filesize($archiveName));
        readfile((string) $archiveName);
        @unlink($archiveName);
        exit;
    }
}

?>
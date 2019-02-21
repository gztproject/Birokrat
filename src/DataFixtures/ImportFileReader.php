<?php
namespace App\DataFixtures;
class ImportFileReader
{
    // <summary>
    //
    // </summary>
    // <param name="filename">File to read</param>
    // <param name="offset">Number of rows from the top to read as common header
    // intwo columns: Key\tValue (0 by default)</param>
    // <returns></returns>
    function GetRows (string $path, int $offset = 0): array
    {        
        $result = array();
        
        // check if file exists
        if (! file_exists($path)) {
            //$logger->Log("The requested file doesn't exist.");
            return $result;
        }
        
        // Open the file to read from.
        $readText = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($readText)) {
            //$logger->Log("Error opening the file");
            return $result;
        }
        //$logger->Log("Opening '" . $path . "'...: OK");
        
        //$readText = array_map("utf8_encode", $readText);
        //$parameters = array();
        $headers = array();
        $firstLine = true;
        $lineCount = 0;
        foreach ($readText as $line) {
            //$logger->Log($line);
            $lineCount ++;
            if ($lineCount <= $offset) {
                $param = explode("\t", $line);
                if (! empty($param[0]) || (count($param) < 1)) {
                    continue;
                }
                $key = $param[0];
                //$value = empty($param[1]) ? "unknown " . $lineCount : $param[1];
                //$parameters[$key] = $value;
                continue;
            }
            // if first line, parse headers
            if ($firstLine) {
                $headers = explode("\t", $line);
                for ($i = 0; $i < count($headers); $i ++) {
                    $headers[$i] = empty($headers[$i]) ? "unknown " . $i : $headers[$i];
                }
                $firstLine = false;
                //$logger->Log("Headers in first line: [" . implode(",", $headers) . "]");
                
                continue;
            }
            // else try to parse line
            $fields = explode("\t", $line);
            if (count($fields) < 1) {
                //$logger->Log("Line " . $lineCount . " is empty, will be ignored");
                continue;
            }
            if ($this->isAllEmpty($fields)) {
                //$logger->Log(" Line " . $lineCount . " has only empty fields, will be ignored");
                continue;
            }
            $lineParams = array();
            for ($i = 0; $i < count($fields); $i ++) {
                $key = count($headers) > $i && ! empty($headers[$i]) ? $headers[$i] : "unknown " .
                         $i;
                $lineParams[$key] = $fields[$i];
            }
            array_push($result, $lineParams);
        }
        //if (count($result) > 0)
            //$logger->Log(" " . count($result) . " lines will be processed.");
        //else
            //$logger->Log(" There's no valid lines in file, no lines will be processed.");
            
            // var_dump($result);
        return $result;
    }

    private function isAllEmpty (array $array): bool
    {
        $isEmpty = true;
        foreach ($array as $item) {
            $isEmpty &= empty($item);
        }
        return $isEmpty;
    }
}
?>
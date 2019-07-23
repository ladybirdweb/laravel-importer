<?php

namespace LWS\Import;

class CsvIterator
{
    protected $file;

    public function __construct($file)
    {
        $this->file = fopen($file, 'r');
    }

    public function parse()
    {
        $headers = array_map('trim', fgetcsv($this->file, 4096));
        while (! feof($this->file)) {
            $row = array_map('trim', (array) fgetcsv($this->file, 4096));
            if (count($headers) !== count($row)) {
                continue;
            }
            $row = array_combine($headers, $row);
            yield $row;
        }
    }
}

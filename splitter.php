<?php

declare(strict_types = 1);

/**
 * Class Splitter
 */
class Splitter
{

    /** @var resource $fp File pointer of the main SQL file */
    private $fp;

    /** @var resource $chunk_fp File pointer to the chunk file that being created */
    private $chunk_fp;

    private const OUTPUT_DIR = './output';

    private const START_GRIP = '/^-- Table structure for table `(.*)`/i';

    /**
     * Splitter constructor.
     *
     * @param string $file_path
     */
    public function __construct(string $file_path)
    {
        $this->fp = fopen($file_path, 'rb');
        while (!feof($this->fp)) {
            $line = fgets($this->fp);
            if (!$line) continue;
            // Matched table definition
            if (($table_name = $this->hasTableStart($line))) {
                $this->chunkCreate($table_name);
            }
            $this->chunkRecord($line);
        }
        fclose($this->chunk_fp);
        fclose($this->fp);
    }

    private function hasTableStart(string $line): ?string
    {
        return ($line[0] === '-' && $line[1] === '-' && preg_match(self::START_GRIP, $line, $m))
            ? $m[1]
            : null;
    }

    private function chunkCreate(string $filename): void
    {
        if ($this->chunk_fp) {
            fclose($this->chunk_fp);
        }
        $filename .= '.sql';
        $this->chunk_fp = fopen(self::OUTPUT_DIR . '/'.$filename, 'w');
        echo "Created file: $filename\n";
    }

    private function chunkRecord(string $to_write): void
    {
        if ($this->chunk_fp) {
            fwrite($this->chunk_fp, $to_write);
        }
    }
}

new Splitter('output/nightly');

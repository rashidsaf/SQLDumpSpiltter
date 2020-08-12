#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Splits single SQL database dump into table per file.
 */
class SQLSplitter
{
    /** @var resource $fp File pointer of the main SQL file. */
    private $fp;

    /** @var resource $chunk_fp File pointer to the chunk file that being created. */
    private $chunk_fp;

    /** @var string|null Current table name. */
    private $current_table;

    /** @var string|null Finished table name. */
    private $previous_table;

    /** @var string The output directory for the table sql files. */
    private $output_dir;

    private const START_GRIP = '/^-- Table structure for table `(.*)`/i';

    /**
     * Splitter constructor.
     *
     * @param string $source_file The SQL dump source file.
     * @param string $dest_dir The output directory for the table sql files.
     */
    public function __construct(string $source_file, string $dest_dir)
    {
        $this->timer = microtime(true);
        $this->setOutputDir($dest_dir);
        $this->openSourceFile($source_file);
        $this->process();
        $this->endPreviousTable();
        $this->result();
    }

    /**
     * Main action happening here.
     */
    private function process(): void
    {
        while (!feof($this->fp)) {
            $line = fgets($this->fp);
            if (!$line) continue;
            if ($this->newTableDetected($line)) {
                $this->endPreviousTable();
                $this->startNewTable();
            }
            $this->writeToTableFile($line);
        }
    }

    /**
     * Prints of the final result.
     */
    private function result(): void
    {
        fclose($this->fp);
        print "All done. Execution time: ".round(microtime(true) - $this->timer, 3)."s\n";
    }

    /**
     * Sets up outout directory.
     *
     * @param string $dest_dir the output dir.
     */
    private function setOutputDir(string $dest_dir): void
    {
        if (!is_dir($dest_dir)) {
            if (!mkdir($dest_dir, 0777, true)) {
                print "Could not create directory '$dest_dir'. Make sure you have necessary permissions";
                exit(1);
            }
        } elseif (!is_writable($dest_dir)) {
            print "The directory '$dest_dir' is not writable";
            exit(1);
        }

        $this->output_dir = realpath(rtrim($dest_dir, '/')). '/';
    }

    /**
     * Validate and opens the dump file.
     *
     * @param string $path_to_source_file Path to the main source file.
     */
    private function openSourceFile(string $path_to_source_file): void
    {
        $path_to_source_file = realpath($path_to_source_file);
        if (!file_exists($path_to_source_file) || !is_readable($path_to_source_file)) {
            print "Make sure '$path_to_source_file' file exists and readable";
            exit(1);
        }

        $this->fp = fopen($path_to_source_file, 'rb');
    }

    /**
     * Determine if the new table definition has started.
     *
     * @param string $line One line read from the main source.
     * @return bool
     */
    private function newTableDetected(string $line): bool
    {
        if ($line[0] === '-' && $line[1] === '-' && preg_match(self::START_GRIP, $line, $m)) {
            $this->previous_table = $this->current_table;
            $this->current_table = $m[1];

            return true;
        }

        return false;
    }

    /**
     * Closes the previous table.
     */
    private function endPreviousTable(): void
    {
        if ($this->previous_table) {
            print "Finished parsing: $this->previous_table\n";
            if ($this->chunk_fp) {
                fclose($this->chunk_fp);
            }
        }
    }

    /**
     * Open the current table for writing.
     */
    private function startNewTable(): void
    {
        $filename = $this->current_table . '.sql';
        $this->chunk_fp = fopen($this->output_dir . $filename, 'w');
        print "Parsing table: $this->current_table\n";
    }

    /**
     * Writes string line to the current chunk file.
     *
     * @param string $to_write The string from the main sql dump.
     */
    private function writeToTableFile(string $to_write): void
    {
        if ($this->chunk_fp) {
            fwrite($this->chunk_fp, $to_write);
        }
    }
}

// Receive argument from console

if ($argc >= 1) {
    $source_file = $argv[1];
    $output_dir  = $argv[2] ?? './output';

    new SQLSplitter($source_file, $output_dir);
} else {
    print 'Please pass arguments: path to the source.sql';
    exit(1);
}

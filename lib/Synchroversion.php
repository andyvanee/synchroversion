<?php

namespace Synchroversion;

class Synchroversion {
    // Root directory of assets
    protected $directory;

    // Name of this asset
    protected $name;

    // How many state files to retain
    protected $retain_state = 3;

    // The default umask for files and directories, can be configured with setUmask()
    protected $umask = 0022;

    // File mode of created files, minus umask
    private $fmode = 0666;

    // File mode of created directories, minus umask
    private $dmode = 0777;

    // TODO: more logging in verbose mode, perhaps a logging callback?
    public $verbose = false;

    /**
     * Create or connect to Synchroversion repository
     **/
    public function __construct($directory, $name) {
        $this->directory = $directory;
        $this->name = $name;
    }

    /**
     * Synchroversion::exec
     *
     * Populate or update the content of the repository
     *
     * @var $content A string or callable that produces the content you want to store
     **/
    public function exec($content) {
        // #1 - Timestamp the start of execution
        $timestamp = strftime('%Y%m%d-%H%M%S');

        // #2 - Touch state directory, version directory and latest state file
        $this->touchDir($this->stateDir());
        $this->touchDir($this->versionDir());
        touch($this->latestStateFile());

        // #3 - Create temporary files for $current and $diff
        $current = $this->tempFile();
        $diff = $this->tempFile();

        // #4 - Place the content in the $current temp file
        if (is_callable($content)) {
            file_put_contents($current, $content());
        } else {
            file_put_contents($current, $content);
        }

        // #5 - Run the diff command and place the result in the $diff temp file
        exec($this->diffCommand($this->latestStateFile(), $current, $diff));

        if (filesize($diff) > 0) {
            // TODO: figure out if there is a more transactional way to
            // generate links. An empty or unlinked latest state file would
            // cause a problem for subsequent updates

            // #6 - If a diff exists, timestamp and link new diff
            $this->link($diff, $this->stateFile($timestamp));

            // #7 - If a diff exists, timestamp and link a version
            $this->link($current, $this->versionFile($timestamp));

            // #8 - If a diff exists, unlink previous state and link new one
            $this->unlink($this->latestStateFile());
            $this->link($current, $this->latestStateFile());
        }

        // #9 - unlink temp files and purge past state files
        $this->unlink($current);
        $this->unlink($diff);
        $this->purgeStateFiles();
    }

    /**
    Return the current content of the file - it's latest state
     */
    public function latest() {
        $vfiles = $this->versionFiles();
        $f = array_shift($vfiles);
        if ($f && is_file($f)) {
            return file_get_contents($f);
        } else {
            return '';
        }
    }

    /**
    Configure how many past states to retain
     */
    public function retainState($retain_state = 3) {
        if ($retain_state < 1) {
            throw new \Exception("Synchroversion requires at least one state file", 1);
        }
        $this->retain_state = $retain_state;
        return $this;
    }

    public function purgeStateFiles() {
        array_map(function ($f) {
            $this->unlink($f);
        }, array_slice($this->versionFiles(), $this->retain_state));
    }

    public function setUmask($umask) {
        $this->umask = $umask;
    }

    private function diffCommand($state, $current, $diff) {
        return sprintf(
            'diff -u --label previous --label current %s %s > %s'
            , escapeshellarg($state)
            , escapeshellarg($current)
            , escapeshellarg($diff)
        );
    }

    public function versionFiles() {
        $version_files = glob($this->versionDir() . '/*');
        arsort($version_files); // Reverse sort
        return $version_files;
    }

    private function link($target, $link) {
        if ($this->verbose) {
            echo "Linking: $target $link\n";
        }
        chmod($target, $this->fileMode());
        return link($target, $link);
    }

    private function fileMode() {
        return $this->fmode & ~$this->umask;
    }

    private function dirMode() {
        return $this->dmode & ~$this->umask;
    }

    private function unlink($filename) {
        if ($this->verbose) {
            echo "Unlinking: $filename\n";
        }
        return unlink($filename);
    }

    private function latestStateFile() {
        return sprintf('%s/%s/%s', $this->directory, $this->name, 'latest.txt');
    }

    private function stateDir() {
        return sprintf('%s/%s/%s', $this->directory, $this->name, 'state');
    }

    private function versionDir() {
        return sprintf('%s/%s/%s', $this->directory, $this->name, 'latest');
    }

    private function stateFile($timestamp) {
        return sprintf('%s/%s.txt', $this->stateDir(), $timestamp);
    }

    private function versionFile($timestamp) {
        return sprintf('%s/%s.txt', $this->versionDir(), $timestamp);
    }

    private function touchDir($dir) {
        is_dir($dir) || mkdir($dir, $this->dirMode(), true);
    }

    private function tempFile() {
        return tempnam(
            sprintf('%s/%s', $this->directory, $this->name),
            $this->name
        );
    }
}

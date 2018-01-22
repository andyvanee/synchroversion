<?php

namespace Synchroversion;

class Synchroversion {
    // Root directory of assets
    protected $directory;

    // Name of this asset
    protected $name;

    // How many state files to retain
    protected $retain_state;

    // TODO: more logging in verbose mode, perhaps a logging callback?
    public $verbose = false;

    /**
     * Create or connect to Synchroversion repository
     **/
    public function __construct($directory, $name, $retain_state = 3) {
        $this->directory = $directory;
        $this->name = $name;
        $this->retain_state = $retain_state;

        $this->touchDir($this->stateDir());
        $this->touchDir($this->versionDir());
    }

    /**
     * Synchroversion::exec
     *
     * Populate or update the content of the repository
     *
     * @var $content A string or callable that produces the content you want to store
     **/
    public function exec($content) {
        $timestamp = strftime('%Y%m%d-%H%M%S');
        $current = $this->tempFile();
        $diff = $this->tempFile();

        touch($this->latestStateFile());

        if (is_callable($content)) {
            file_put_contents($current, $content());
        } else {
            file_put_contents($current, $content);
        }

        exec($this->diffCommand($this->latestStateFile(), $current, $diff));

        if (filesize($diff) > 0) {
            // TODO: figure out if there is a more transactional way to
            // generate links. An empty or unlinked latest state file would
            // cause a problem for subsequent updates
            $this->link($diff, $this->stateFile($timestamp));
            $this->unlink($this->latestStateFile());
            $this->link($current, $this->latestStateFile());
            $this->link($this->latestStateFile(), $this->versionFile($timestamp));
        }

        $this->unlink($current);
        $this->unlink($diff);

        // Remove all but the latest version files
        array_map(function ($f) {
            $this->unlink($f);
        }, array_slice($this->versionFiles(), $this->retain_state));
    }

    public function latest() {
        $f = array_shift($this->versionFiles());
        if ($f && is_file($f)) {
            return file_get_contents($f);
        } else {
            return '';
        }
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
        return link($target, $link);
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
        is_dir($dir) || mkdir($dir, 0770, true);
    }

    private function tempFile() {
        return tempnam($this->directory, $this->name);
    }
}

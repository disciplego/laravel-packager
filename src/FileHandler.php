<?php

namespace JeroenG\Packager;

use ZipArchive;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use GuzzleHttp\Client;

trait FileHandler
{
    /**
     * Get the path to the packages directory.
     *
     * @return string $path
     */
    public function packagesPath()
    {
        return base_path('packages');
    }

    /**
     * Get the vendor path.
     *
     * @return string $path
     */
    public function vendorPath()
    {
        return $this->packagesPath().'/'.$this->vendor();
    }

    /**
     * Get the full package path.
     *
     * @return string $path
     */
    public function packagePath()
    {
        return $this->vendorPath().'/'.$this->package();
    }

    /**
     * Generate a random temporary filename for the package zipfile.
     *
     * @return string
     */
    public function makeFilename()
    {
        return getcwd().'/package'.md5(time().uniqid()).'.zip';
    }

    /**
     * Check if the package already exists.
     *
     * @return void    Throws error if package exists, aborts process
     */
    public function checkIfPackageExists()
    {
        if (is_dir($this->packagePath())) {
            throw new RuntimeException('Package already exists');
        }
    }

    /**
     * Create a directory if it doesn't exist.
     *
     * @param  string $path Path of the directory to make
     * @return bool
     */
    public function makeDir($path)
    {
        if (! is_dir($path)) {
            return mkdir($path, 0777, true);
        }

        return false;
    }

    /**
     * Remove a directory if it exists.
     *
     * @param  string $path Path of the directory to remove.
     * @return bool
     */
    public function removeDir($path)
    {
        if ($path == 'packages' || $path == '/') {
            return false;
        }

        $files = array_diff(scandir($path), ['.', '..']);
        foreach ($files as $file) {
            if (is_dir("$path/$file")) {
                $this->removeDir("$path/$file");
            } else {
                @chmod("$path/$file", 0777);
                @unlink("$path/$file");
            }
        }

        return rmdir($path);
    }

    /**
     * Download the temporary Zip to the given file.
     *
     * @param  string  $zipFile
     * @param  string  $source
     * @return $this
     */
    public function download($zipFile, $source)
    {
        $client = new Client(['verify' => env('CURL_VERIFY', true)]);
        $response = $client->get($source);
        file_put_contents($zipFile, $response->getBody());

        return $this;
    }

    /**
     * Extract the zip file into the given directory.
     *
     * @param  string  $zipFile
     * @param  string  $directory
     * @return $this
     */
    public function extract($zipFile, $directory)
    {
        $archive = new ZipArchive;
        $archive->open($zipFile);
        $archive->extractTo($directory);
        $archive->close();

        return $this;
    }

    /**
     * Clean-up the Zip file.
     *
     * @param  string  $zipFile
     * @return $this
     */
    public function cleanUp($zipFile)
    {
        @chmod($zipFile, 0777);
        @unlink($zipFile);

        return $this;
    }

    /**
     * Rename generic files to package-specific ones.
     *
     * @param array|null $manifest
     * @return void
     **/
    public function renameFiles($manifest = null)
    {
        $bindings = [
            ['MyVendor', 'MyPackage', 'myvendor', 'mypackage'],
            [$this->vendor(), $this->package(), strtolower($this->vendor()), strtolower($this->package())],
        ];

        $files = new RecursiveDirectoryIterator($this->packagePath());
        foreach(new RecursiveIteratorIterator($files) as $file) {
            if (!$file->isFile()) {
                continue;                
            }
            $replaced = str_replace($bindings[0], $bindings[1], $file->getFilename());
            if ($replaced === $file->getFilename()) {
                continue;
            }
            rename($file->getPath().'/'.$file->getFilename(), $file->getPath().'/'.$replaced);
        }
    }

    public function cleanUpRules()
    {
        $ruleFiles = ['rules.php', 'rewriteRules.php'];

        foreach ($ruleFiles as $file) {
            if (file_exists($this->packagePath().'/'.$file)) {
                unlink($this->packagePath().'/'.$file);
            }
        }
    }
}

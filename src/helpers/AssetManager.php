<?php
declare(strict_types=1);

namespace Bingo\Src\Helpers;

class AssetManager
{
    /**
     * @var bool
     */
    private $debug;

    public function __construct(bool $debug)
    {
        $this->debug = $debug;

        // Set the current directory to outside the public folder
        chdir(getenv('DOCUMENT_ROOT') . '/../');
    }

    public function getFileUrl($file) {
        $fileBits = explode('.', $file);
        $fileName = $fileBits[0];
        $extension = $fileBits[1];

        $minifedFilePath = $fileName . '.min.' . $extension;

        if(file_exists($minifedFilePath) && !$this->debug) {
            $file = $minifedFilePath;
        }

        if(file_exists($file)) {
            return $file . (!$this->debug ? ('?v=' . filemtime($file)) : '');
        }
        
        return null;
    }
}
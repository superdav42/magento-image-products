<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\Model\Product\Gallery;

use Magento\Framework\Exception\FileSystemException;

class UpdateHandler extends \Magento\Catalog\Model\Product\Gallery\UpdateHandler
{

    /**
     * @param string $file
     *
     * @return mixed|string
     * @throws FileSystemException
     */
    protected function moveImageFromTmp($file)
    {
        $file = $this->getFilenameFromTmp($this->getSafeFilename($file));
        $destinationFile = $this->getUniqueFileName($file);

        if ($this->fileStorageDb->checkDbUsage()) {
            $this->fileStorageDb->renameFile(
                $this->mediaConfig->getTmpMediaShortUrl($file),
                $this->mediaConfig->getMediaShortUrl($destinationFile)
            );
        }
        $this->mediaDirectory->renameFile(
            $this->mediaConfig->getTmpMediaPath($file),
            $this->mediaConfig->getMediaPath($destinationFile)
        );

        return str_replace('\\', '/', $destinationFile);
    }

    /**
     * Returns safe filename for posted image
     *
     * @param string $file
     * @return string
     */
    private function getSafeFilename($file)
    {
        $file = DIRECTORY_SEPARATOR . ltrim($file, DIRECTORY_SEPARATOR);

        return $this->mediaDirectory->getDriver()->getRealPathSafety($file);
    }
}

<?php
/**
 * Created by PhpStorm.
 * User: dave
 * Date: 3/15/19
 * Time: 4:53 PM
 */

namespace DevStone\ImageProducts\Model\Product\Gallery;

class CreateHandler extends \Magento\Catalog\Model\Product\Gallery\CreateHandler
{

    /**
     * @param string $file
     *
     * @return mixed|string
     * @throws \Magento\Framework\Exception\FileSystemException
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
            // why we override here?

            $this->mediaDirectory->delete($this->mediaConfig->getTmpMediaPath($file));
        } else {
            $this->mediaDirectory->renameFile(
            $this->mediaConfig->getTmpMediaPath($file),
                $this->mediaConfig->getMediaPath($destinationFile)
            );
        }

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

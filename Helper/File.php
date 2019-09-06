<?php

namespace DevStone\ImageProducts\Helper;


/**
 * Downloadable Products File Helper
 * Bug fix override for duplicate names
 * needed till https://github.com/magento/magento2/issues/13915 is live
 */
class File extends \Magento\Downloadable\Helper\File
{
    
    /**
     *
     * @var \Magento\Framework\Registry
     */
    protected $registry;
    
    public function __construct(
        \Magento\Framework\App\Helper\Context $context, 
        \Magento\MediaStorage\Helper\File\Storage\Database $coreFileStorageDatabase, 
        \Magento\Framework\Filesystem $filesystem, 
        \Magento\Framework\Registry $registry,
        array $mimeTypes = array()
    ) {
        $this->registry = $registry;
        parent::__construct($context, $coreFileStorageDatabase, $filesystem, $mimeTypes);
    }
    
    /**
     * Move file from tmp path to base path
     *
     * @param string $baseTmpPath
     * @param string $basePath
     * @param string $file
     * @return string
     */
    protected function _moveFileFromTmp($baseTmpPath, $basePath, $file)
    {
        if (strrpos($file, '.tmp') == strlen($file) - 4) {
            $file = substr($file, 0, strlen($file) - 4);
        }
        
        $product = $this->registry->registry('product');
        if ($product->getTypeId() === \DevStone\ImageProducts\Model\Product\Type::TYPE_ID) {
            $pathinfo = pathinfo($file);
            $destFile = $product->getSku().'.'.$pathinfo['extension'];

            $destFile = \Magento\MediaStorage\Model\File\Uploader::getCorrectFileName($destFile);
            $dispretionPath = \Magento\MediaStorage\Model\File\Uploader::getDispretionPath($destFile);
            $destFile = $dispretionPath . '/' . $destFile;
            
        } else {
            $destFile = $file;
        }

        if ($this->_coreFileStorageDatabase->checkDbUsage()) {
            $destFile = $this->_coreFileStorageDatabase->getUniqueFilename(
                $basePath,
                $destFile
            );
        } else {
            $destinationFile = $this->_mediaDirectory->getAbsolutePath($this->getFilePath($basePath, $destFile));
            $destFile = dirname($destFile) . '/'
                . \Magento\MediaStorage\Model\File\Uploader::getNewFileName($destinationFile);
        }

        $this->_coreFileStorageDatabase->copyFile(
            $this->getFilePath($baseTmpPath, $file),
            $this->getFilePath($basePath, $destFile)
        );

        $this->_mediaDirectory->renameFile(
            $this->getFilePath($baseTmpPath, $file),
            $this->getFilePath($basePath, $destFile)
        );

        return str_replace('\\', '/', $destFile);
    }
}
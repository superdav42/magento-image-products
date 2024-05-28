<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\Model\Product\Gallery;

use Exception;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Media\Config;
use Magento\Catalog\Model\ResourceModel\Product\Gallery;
use Magento\Framework\Api\Data\ImageContentInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\File\Mime;
use Magento\Framework\Filesystem;
use Magento\MediaStorage\Helper\File\Storage\Database;
use Magento\MediaStorage\Model\File\Uploader;

class Processor extends Product\Gallery\Processor
{
    protected mixed $mime;

    public function __construct(
        ProductAttributeRepositoryInterface $attributeRepository,
        Database $fileStorageDb,
        Config $mediaConfig,
        Filesystem $filesystem,
        Gallery $resourceModel,
        Mime $mime = null
    ) {
        parent::__construct($attributeRepository, $fileStorageDb, $mediaConfig, $filesystem, $resourceModel, $mime);
        $this->mime = $mime ?: ObjectManager::getInstance()->get(Mime::class);
    }

    public function addImage(
        Product $product,
        $file,
        $mediaAttribute = null,
        $move = false,
        $exclude = true
    ) {
        $file = $this->mediaDirectory->getRelativePath($file);
        if (!$this->mediaDirectory->isFile($file)) {
            throw new NotFoundException(__("The image ({$file}) does not exist."));
        }

        $pathinfo = pathinfo($file);
        $imgExtensions = ['jpg', 'jpeg', 'gif', 'png'];
        if (!isset($pathinfo['extension']) || !in_array(strtolower($pathinfo['extension']), $imgExtensions)) {
            throw new LocalizedException(__('Please correct the image file type.'));
        }

        $fileName = $product->getUrlKey() . '-GoodSalt-' . '.' . $pathinfo['extension'];

        $fileName = Uploader::getCorrectFileName($fileName);
        $dispretionPath = Uploader::getDispersionPath($fileName);
        $fileName = $dispretionPath . '/' . $fileName;

        $fileName = $this->getNotDuplicatedFilename($fileName, $dispretionPath);

        $destinationFile = $this->mediaConfig->getTmpMediaPath($fileName);

        try {
            /** @var $storageHelper Database */
            $storageHelper = $this->fileStorageDb;
            if ($move) {
                $this->mediaDirectory->renameFile($file, $destinationFile);

                //If this is used, filesystem should be configured properly
                $storageHelper->saveFile($this->mediaConfig->getTmpMediaShortUrl($fileName));
            } else {
                $this->mediaDirectory->copyFile($file, $destinationFile);

                $storageHelper->saveFile($this->mediaConfig->getTmpMediaShortUrl($fileName));
            }
        } catch (Exception $e) {
            throw new LocalizedException(__('The "%1" file couldn\'t be moved.', $e->getMessage()));
        }

        $fileName = str_replace('\\', '/', $fileName);

        $attrCode = $this->getAttribute()->getAttributeCode();
        $mediaGalleryData = $product->getData($attrCode);
        $position = 0;

        $absoluteFilePath = $this->mediaDirectory->getAbsolutePath($destinationFile);
        $imageMimeType = $this->mime->getMimeType($absoluteFilePath);
        $imageContent = $this->mediaDirectory->readFile($absoluteFilePath);
        $imageBase64 = base64_encode($imageContent);
        $imageName = $pathinfo['filename'];

        if (!is_array($mediaGalleryData)) {
            $mediaGalleryData = ['images' => []];
        }

        foreach ($mediaGalleryData['images'] as &$image) {
            if (isset($image['position']) && $image['position'] > $position) {
                $position = $image['position'];
            }
        }

        $position++;
        $mediaGalleryData['images'][] = [
            'file' => $fileName,
            'position' => $position,
            'label' => '',
            'disabled' => (int)$exclude,
            'media_type' => 'image',
            'types' => $mediaAttribute,
            'content' => [
                'data' => [
                    ImageContentInterface::NAME => $imageName,
                    ImageContentInterface::BASE64_ENCODED_DATA => $imageBase64,
                    ImageContentInterface::TYPE => $imageMimeType,
                ]
            ]
        ];

        $product->setData($attrCode, $mediaGalleryData);

        if ($mediaAttribute !== null) {
            $this->setMediaAttribute($product, $mediaAttribute, $fileName);
        }

        return $fileName;
    }
}

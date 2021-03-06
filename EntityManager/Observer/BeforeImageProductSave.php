<?php

namespace DevStone\ImageProducts\EntityManager\Observer;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class BeforeImageProductSave implements ObserverInterface
{
    /**
     *
     * @var \Magento\Catalog\Model\Product\Media\Config
     */
    protected $mediaConfig;

    /**
     *
     * @var \Magento\Catalog\Model\Product\Gallery\Processor
     */
    protected $mediaGalleryProcessor;

    /**
     * @var \Magento\Framework\Image\Factory
     */
    protected $imageFactory;

    /**
     * @var \Magento\Framework\Filesystem\Directory\WriteInterface
     */
    protected $mediaDirectory;

    /**
     * @var \Magento\Catalog\Api\ProductAttributeRepositoryInterface
     */
    protected $attributeRepository;

    /**
     * @var \Magento\MediaStorage\Helper\File\Storage\Database
     */
    protected $fileStorageDb;

    /**
     *
     * @param \Magento\Catalog\Model\Product\Media\Config $mediaConfig
     * @param \DevStone\ImageProducts\Model\Product\Gallery\Processor $mediaGalleryProcessor
     * @param \Magento\Framework\Image\Factory $imageFactory
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Catalog\Api\ProductAttributeRepositoryInterface $attributeRepository
     */
    public function __construct(
        \Magento\Catalog\Model\Product\Media\Config $mediaConfig,
        \DevStone\ImageProducts\Model\Product\Gallery\Processor $mediaGalleryProcessor,
        \Magento\Framework\Image\Factory $imageFactory,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Catalog\Api\ProductAttributeRepositoryInterface $attributeRepository,
        \Magento\MediaStorage\Helper\File\Storage\Database $fileStorageDb
    ) {
        $this->mediaConfig = $mediaConfig;
        $this->mediaGalleryProcessor = $mediaGalleryProcessor;
        $this->imageFactory = $imageFactory;
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $this->attributeRepository = $attributeRepository;
        $this->fileStorageDb = $fileStorageDb;
    }
    /**
     * Apply model save operation
     *
     * @param Observer $observer
     * @throws \Magento\Framework\Validator\Exception
     * @return void
     */
    public function execute(Observer $observer)
    {
        $entity = $observer->getEvent()->getProduct();
        if ($entity instanceof \Magento\Catalog\Api\Data\ProductInterface &&
                $entity->getTypeId() === \DevStone\ImageProducts\Model\Product\Type::TYPE_ID) {
            $mediaGalleryData = $entity->getData('media_gallery');

            $existingMediaFiles = [];
            $linkFiles = [];

            if (isset($mediaGalleryData['images']) && is_array($mediaGalleryData['images'])) {
                foreach ($mediaGalleryData['images'] as &$image) {
                    $existingMediaFiles[] = $image['file'];
                }
            }

            /** @var \Magento\Downloadable\Api\Data\LinkInterface[] $links */
            $links = $entity->getExtensionAttributes()->getDownloadableProductLinks() ?: [];
            foreach ($links as $link) {
                $file = $link->getLinkFile();
                $linkFiles[] = $file;

                if (!$this->stringInArray(pathinfo($file, PATHINFO_FILENAME), $existingMediaFiles)) {
                    $mediaAttributeCodes = $this->mediaConfig->getMediaAttributeCodes();
                    $file = $this->mediaGalleryProcessor->addImage(
                        $entity,
                        $link->getBasePath() . $file,
                        $mediaAttributeCodes,
                        false,
                        false
                    );
                    $this->processImage($file, $entity);
                }
            }

            $updatedMediaGalleryData = $entity->getData('media_gallery');

            if (isset($updatedMediaGalleryData['images']) && is_array($updatedMediaGalleryData['images'])) {
                foreach ($updatedMediaGalleryData['images'] as &$image) {

                        $filename = pathinfo($image['file'], PATHINFO_FILENAME);
                        $siteIdDelimiter ='-GoodSalt-';
                        $skuFromFileName = str_contains($filename, $siteIdDelimiter) ?
                        substr(
                            strstr(
                                $filename,
                                $siteIdDelimiter
                            ),
                            strlen($siteIdDelimiter)
                        ) : $filename;
                    if (
                        !$this->stringInArray(
                            $skuFromFileName,
                            $linkFiles
                        )
                    ) {
                        $image['removed'] = 1;
                    }
                }

                $entity->setData('media_gallery', $updatedMediaGalleryData);
            }
        }
    }
    /**
     *
     * @param string $file dispersed filename
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     */
    protected function processImage($file, \Magento\Catalog\Api\Data\ProductInterface $product)
    {
        $processor = $this->imageFactory->create(
            $this->mediaDirectory->getAbsolutePath(
                $this->mediaConfig->getTmpMediaPath($file)
            )
        );
        $this->updateAttributes($processor, $product);
        $processor->quality(92);
        $processor->keepAspectRatio(true);
        $processor->constrainOnly(true);
        $processor->resize(1024, 1024);
        $processor->save();
        $this->fileStorageDb->saveFile($this->mediaConfig->getTmpMediaShortUrl($file));
    }

    /**
     *
     * @param \Magento\Framework\Image $processor
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     */
    protected function updateAttributes(
        \Magento\Framework\Image $processor,
        \Magento\Catalog\Api\Data\ProductInterface $product
    ) {
        $width = $processor->getOriginalWidth();
        $height = $processor->getOriginalHeight();
        $product->setWidth($width);
        $product->setHeight($height);
        $dar = $width / $height;

        if ($dar < 0.9) {
            $orientation = 'Vertical';
        } elseif ($dar >= 0.9 && $dar <= 1.1) {
            $orientation = 'Square';
        } else {
            $orientation = 'Horizontal';
        }

        //$product->setClean(true);

        $product->setOrientation($this->getAttributeOptionId('orientation', $orientation));
    }

    /**
     * Get attribute by code.
     *
     * @param string $attributeCode
     * @return \Magento\Catalog\Api\Data\ProductAttributeInterface
     */
    protected function getAttribute($attributeCode)
    {
        return $this->attributeRepository->get($attributeCode);
    }

    protected function getAttributeOptionId($attributeCode, $label)
    {
        /** @var \Magento\Catalog\Model\ResourceModel\Eav\Attribute $attribute */
        $attribute = $this->getAttribute($attributeCode);

        foreach ($attribute->getOptions() as $option) {
            if ($option->getLabel() == $label) {
                return $option->getValue();
            }
        }
        return null;
    }

    private function stringInArray($needle, array $haystack)
    {
        foreach ($haystack as $value) {
            if (strpos($value, $needle) !== false) {
                return true;
            }
        }
        return false;
    }
}

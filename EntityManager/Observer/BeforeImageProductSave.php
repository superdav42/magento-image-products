<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\EntityManager\Observer;

use DevStone\ImageProducts\Model\Product\Gallery\Processor;
use DevStone\ImageProducts\Model\Product\Type;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\Product\Media\Config;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Downloadable\Api\Data\LinkInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Image;
use Magento\Framework\Image\Factory;
use Magento\Framework\Validator\Exception;
use Magento\MediaStorage\Helper\File\Storage\Database;

class BeforeImageProductSave implements ObserverInterface
{
    /**
     *
     * @var Config
     */
    protected Config $mediaConfig;

    /**
     *
     * @var \Magento\Catalog\Model\Product\Gallery\Processor|Processor
     */
    protected \Magento\Catalog\Model\Product\Gallery\Processor|Processor $mediaGalleryProcessor;

    /**
     * @var Factory
     */
    protected Factory $imageFactory;

    /**
     * @var WriteInterface
     */
    protected WriteInterface $mediaDirectory;

    /**
     * @var ProductAttributeRepositoryInterface
     */
    protected ProductAttributeRepositoryInterface $attributeRepository;

    /**
     * @var Database
     */
    protected Database $fileStorageDb;

    /**
     * Request instance
     *
     * @var RequestInterface
     */
    protected RequestInterface $request;

    /**
     *
     * @param Config $mediaConfig
     * @param Processor $mediaGalleryProcessor
     * @param Factory $imageFactory
     * @param Filesystem $filesystem
     * @param ProductAttributeRepositoryInterface $attributeRepository
     * @throws FileSystemException
     */
    public function __construct(
        Config $mediaConfig,
        Processor $mediaGalleryProcessor,
        Factory $imageFactory,
        Filesystem $filesystem,
        ProductAttributeRepositoryInterface $attributeRepository,
        Database $fileStorageDb,
        RequestInterface $request
    ) {
        $this->mediaConfig = $mediaConfig;
        $this->mediaGalleryProcessor = $mediaGalleryProcessor;
        $this->imageFactory = $imageFactory;
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $this->attributeRepository = $attributeRepository;
        $this->fileStorageDb = $fileStorageDb;
        $this->request = $request;
    }
    /**
     * Apply model save operation
     *
     * @param Observer $observer
     * @throws Exception
     * @return void
     */
    public function execute(Observer $observer)
    {
        $entity = $observer->getEvent()->getProduct();

        if ('image' === $this->request->getParam('type', null)) {
            $entity->setTypeId(Type::TYPE_ID);
        }

        if ($entity instanceof ProductInterface &&
                $entity->getTypeId() === Type::TYPE_ID) {
            $mediaGalleryData = $entity->getData('media_gallery');

            $existingMediaFiles = [];

            if (isset($mediaGalleryData['images']) && is_array($mediaGalleryData['images'])) {
                foreach ($mediaGalleryData['images'] as &$image) {
                    if (! isset($image['removed']) || ! $image['removed']) {
                        $existingMediaFiles[] = $image['file'];
                    }
                }
            }

            $linkFileNames = [];
            /** @var LinkInterface[] $links */
            $links = $entity->getExtensionAttributes()->getDownloadableProductLinks() ?: [];
            foreach ($links as $link) {
                if ($link->getLinkType() !== 'file') {
                    continue;
                    // This is special type like gal_* or url;
                }
                $file = $link->getLinkFile();

                $linkFileName = pathinfo($file, PATHINFO_FILENAME);
                $linkFileNames[] = $linkFileName;
                if (!$this->stringInArray($linkFileName, $existingMediaFiles)) {

                    $file = $this->mediaGalleryProcessor->addImage(
                        $entity,
                        $link->getBasePath() . $file,
                        ['image', 'small_image', 'thumbnail'],
                        false,
                        false
                    );
                    $this->processImage($file, $entity);
                }
            }

            $updatedMediaGalleryData = $entity->getData('media_gallery');

            if (isset($updatedMediaGalleryData['images']) && is_array($updatedMediaGalleryData['images'])) {
                foreach ($updatedMediaGalleryData['images'] as &$image) {
                    if (isset($image['types']) && $this->stringInArray('frame_image', $image['types'])) {
                        continue;
                    }
                    $filename = pathinfo($image['file'], PATHINFO_FILENAME);
                    $siteIdDelimiter ='-GoodSalt-';
                    $linkFileName = str_contains($filename, $siteIdDelimiter) ?
                        substr(
                            strstr(
                                $filename,
                                $siteIdDelimiter
                            ),
                            strlen($siteIdDelimiter)
                        ) : $filename;

                    if (empty($linkFileName) ||
                        ! in_array($linkFileName, $linkFileNames)
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
     * @param ProductInterface $product
     */
    protected function processImage($file, ProductInterface $product)
    {
        $absolutePathToImage = $this->mediaDirectory->getAbsolutePath(
            $this->mediaConfig->getTmpMediaPath($file)
        );
        $processor = $this->imageFactory->create($absolutePathToImage);
        $this->updateAttributes($processor, $product);
        $processor->quality(92);
        $processor->keepAspectRatio(true);
        $processor->constrainOnly(true);
        $processor->resize(1024, 1024);
        $processor->save($absolutePathToImage);
        // no workie
        $this->fileStorageDb->saveFile($this->mediaConfig->getTmpMediaShortUrl($file));
    }

    /**
     *
     * @param Image $processor
     * @param ProductInterface $product
     */
    protected function updateAttributes(
        Image $processor,
        ProductInterface $product
    ) {
        $width = $processor->getOriginalWidth();
        $height = $processor->getOriginalHeight();
        $product->setWidth($width);
        $product->setHeight($height);
        $dar = 0;
        if ($height > 0) {
            $dar = $width / $height;
        }
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
     * @return ProductAttributeInterface
     */
    protected function getAttribute($attributeCode): ProductAttributeInterface
    {
        return $this->attributeRepository->get($attributeCode);
    }

    protected function getAttributeOptionId($attributeCode, $label): ?string
    {
        /** @var Attribute $attribute */
        $attribute = $this->getAttribute($attributeCode);

        foreach ($attribute->getOptions() as $option) {
            if ($option->getLabel() == $label) {
                return $option->getValue();
            }
        }
        return null;
    }

    private function stringInArray($needle, array $haystack): bool
    {
        foreach ($haystack as $value) {
            if (str_contains($value, $needle) && !str_contains($value, 'frameImage')) {
                return true;
            }
        }
        return false;
    }
}

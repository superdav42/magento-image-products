<?php
/**
 * Created by PhpStorm.
 * User: dave
 * Date: 6/14/18
 * Time: 5:46 PM
 */

namespace DevStone\ImageProducts\Controller\Downloads;

use DevStone\UsageCalculator\Api\SizeRepositoryInterface;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\Point;
use Imagine\Imagick\Imagine;
use Magento\Downloadable\Helper\Download as DownloadHelper;
use Magento\Downloadable\Model\Link\Purchased\Item as PurchasedLink;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\ItemRepository;

class Download extends \Magento\Downloadable\Controller\Download
{

    /**
     * @var ItemRepository
     */
    private $itemRepository;

    /**
     * @var \DevStone\UsageCalculator\Api\UsageRepositoryInterface
     */
    private $usageRepository;

    /**
     * @var SizeRepositoryInterface
     */
    private $sizeRepository;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    private $serializer;

    /**
     * @var \Magento\Framework\Image\Factory
     */
    private $imageFactory;

    /**
     * @var \Magento\Framework\Filesystem\Directory\WriteInterface
     */
    private $mediaDirectory;

    /**
     * @var \Magento\Downloadable\Helper\File
     */
    private $downloadableFile;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    public function __construct(
        ItemRepository $itemRepository,
        Context $context,
        \DevStone\UsageCalculator\Api\UsageRepositoryInterface $usageRepository,
        \Magento\Framework\Serialize\Serializer\Json $serializer,
        SizeRepositoryInterface $sizeRepository,
        \Magento\Framework\Image\Factory $imageFactory,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Downloadable\Helper\File $downloadableFile,
        \Psr\Log\LoggerInterface $logger,
    ) {
        $this->itemRepository = $itemRepository;
        $this->usageRepository = $usageRepository;
        $this->sizeRepository = $sizeRepository;
        $this->serializer = $serializer;
        $this->imageFactory = $imageFactory;
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $this->downloadableFile = $downloadableFile;
        $this->logger = $logger;
        parent::__construct($context);
    }

    /**
     * Return customer session object
     *
     * @return \Magento\Customer\Model\Session
     */
    private function _getCustomerSession()
    {
        return $this->_objectManager->get(\Magento\Customer\Model\Session::class);
    }
    /**
     * Download link action
     *
     * @return void|ResponseInterface
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @SuppressWarnings(PHPMD.ExitExpression)
     */
    public function execute()
    {
        $session = $this->_getCustomerSession();

        $id = $this->getRequest()->getParam('id', 0);
        /** @var PurchasedLink $linkPurchasedItem */
        $linkPurchasedItem = $this->_objectManager->create(
            \Magento\Downloadable\Model\Link\Purchased\Item::class
        )->load(
            $id,
            'link_hash'
        );
        if (!$linkPurchasedItem->getId()) {
            $this->messageManager->addNotice(__("We can't find the link you requested."));
            return $this->_redirect('*/customer/products');
        }
        if (!$this->_objectManager->get(\Magento\Downloadable\Helper\Data::class)->getIsShareable($linkPurchasedItem)) {
            $customerId = $session->getCustomerId();
            if (!$customerId) {
                /** @var \Magento\Catalog\Model\Product $product */
                $product = $this->_objectManager->create(
                    \Magento\Catalog\Model\Product::class
                )->load(
                    $linkPurchasedItem->getProductId()
                );
                if ($product->getId()) {
                    $notice = __(
                        'Please sign in to download your product or purchase <a href="%1">%2</a>.',
                        $product->getProductUrl(),
                        $product->getName()
                    );
                } else {
                    $notice = __('Please sign in to download your product.');
                }
                $this->messageManager->addNotice($notice);
                $session->authenticate();
                $session->setBeforeAuthUrl(
                    $this->_objectManager->create(
                        \Magento\Framework\UrlInterface::class
                    )->getUrl(
                        'downloadable/customer/products/',
                        ['_secure' => true]
                    )
                );
                return;
            }
            /** @var \Magento\Downloadable\Model\Link\Purchased $linkPurchased */
            $linkPurchased = $this->_objectManager->create(
                \Magento\Downloadable\Model\Link\Purchased::class
            )->load(
                $linkPurchasedItem->getPurchasedId()
            );
            if ($linkPurchased->getCustomerId() != $customerId) {
                $this->messageManager->addNotice(__("We can't find the link you requested."));
                return $this->_redirect('*/customer/products');
            }
        }
        $downloadsLeft = $linkPurchasedItem->getNumberOfDownloadsBought() -
            $linkPurchasedItem->getNumberOfDownloadsUsed();

        $status = $linkPurchasedItem->getStatus();
        if ($status == PurchasedLink::LINK_STATUS_AVAILABLE && ($downloadsLeft ||
                $linkPurchasedItem->getNumberOfDownloadsBought() == 0)
        ) {
            $resource = '';
            $resourceType = '';
            if ($linkPurchasedItem->getLinkType() == DownloadHelper::LINK_TYPE_URL) {
                $resource = $linkPurchasedItem->getLinkUrl();
                $resourceType = DownloadHelper::LINK_TYPE_URL;
            } elseif ($linkPurchasedItem->getLinkType() == DownloadHelper::LINK_TYPE_FILE) {
                $resource = $this->_objectManager->get(
                    \Magento\Downloadable\Helper\File::class
                )->getFilePath(
                    $this->_getLink()->getBasePath(),
                    $linkPurchasedItem->getLinkFile()
                );

                $fileExists = $this->downloadableFile->ensureFileInFilesystem($resource);

                $resourceType = DownloadHelper::LINK_TYPE_FILE;
            }
            try {
                $this->resizeFile($resource, $resourceType, $linkPurchasedItem);
                $linkPurchasedItem->setNumberOfDownloadsUsed($linkPurchasedItem->getNumberOfDownloadsUsed() + 1);

                if ($linkPurchasedItem->getNumberOfDownloadsBought() != 0 && !($downloadsLeft - 1)) {
                    $linkPurchasedItem->setStatus(PurchasedLink::LINK_STATUS_EXPIRED);
                }
                $linkPurchasedItem->save();
                exit(0);
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage(), ['exception' => $e]);
                $this->messageManager->addError(__('Something went wrong while getting the requested content.'));
            }
        } elseif ($status == PurchasedLink::LINK_STATUS_EXPIRED) {
            $this->messageManager->addNotice(__('The link has expired.'));
        } elseif ($status == PurchasedLink::LINK_STATUS_PENDING || $status == PurchasedLink::LINK_STATUS_PAYMENT_REVIEW
        ) {
            $this->messageManager->addNotice(__('The link is not available.'));
        } else {
            $this->messageManager->addError(__('Something went wrong while getting the requested content.'));
        }
        return $this->_redirect('*/customer/products');
    }

    private function resizeFile($path, $resourceType, \Magento\Downloadable\Model\Link\Purchased\Item $linkPurchasedItem)
    {
        $orderItem = $this->itemRepository->get($linkPurchasedItem->getOrderItemId());

        $productOptions = $orderItem->getProductOptions();

        $templateOptionsObject = $productOptions['template_options'] ?? '';

        if ($templateOptionsObject) {
            return $this->generateTemplate(
                $path,
                $resourceType,
                $templateOptionsObject,
                $orderItem->getQuoteItemId()
            );
        }

        $usageIdOption= $productOptions['usage_id'] ?? '';

        if (empty($usageIdOption)) {
            throw new LocalizedException(__("Failed to get usage"));
        }
        $usage = $this->usageRepository->getById((int)$usageIdOption);

        $submittedOptions = $productOptions['usage_options'] ?? [];

        $sizeId = $usage->getSizeId();

        foreach ($usage->getOptions() as $option) {
            if (! empty($submittedOptions[$option->getId()])) {
                if (is_numeric($submittedOptions[$option->getId()])) {
                    $valueSizeId = $option->getValueById($submittedOptions[$option->getId()])->getSizeId();
                    if (! empty($valueSizeId)) {
                        $sizeId = $valueSizeId;
                    }
                }
            }
        }

        $size = $this->sizeRepository->getById($sizeId);

        $cachePath = 'downloadable/cache/' . $size->getCode() . $linkPurchasedItem->getLinkFile();

        if (! $this->mediaDirectory->isExist($cachePath)) {
            $processor = $this->imageFactory->create(
                $this->mediaDirectory->getAbsolutePath(
                    $path
                )
            );
            $processor->quality(98);
            $processor->keepAspectRatio(true);
            $processor->constrainOnly(true);
            $processor->resize($size->getMaxWidth(), $size->getMaxHeight());
            $processor->save($this->mediaDirectory->getAbsolutePath(
                $cachePath
            ));
        }

        $this->_processDownload($cachePath, $resourceType);
    }

    protected function generateTemplate($path, $resourceType, array $options, $id)
    {
        $cachePath = 'downloadable/cache/template/' . $id . '.jpg';

        if (! $this->mediaDirectory->isExist($cachePath)) {
            $imagine = new Imagine();
            $image = $imagine->open($this->mediaDirectory->getAbsolutePath($path));

            $size = $options['size'] ?? 'SD';
            $orientation = $options['orientation'] ?? 'SD';

            $canvasWidth = $size === 'SD' ? 1024 : 1920;

            $backgroundName = $options['backgroundName'] ?? 'Hieroglyphics';
            $background = $imagine->open(
                __DIR__ . '/../../../TemplateBuilder/view/frontend/web/templates_full/' .
                $backgroundName . '/' . $backgroundName . '_' . $size . '_Background.jpg'
            );

            $mask = $imagine->open(
                __DIR__ . '/../../../TemplateBuilder/view/frontend/web/templates_full/Alpha masks/' .
                $size . '_' . $orientation . '.jpg'
            );

            $finalImage = $imagine->create($background->getSize(), $mask->palette()->color('fff', 0));

            $black = $imagine->create($mask->getSize(), $mask->palette()->color('000'));
            $mask = $black->paste($mask, new Point(0, 0));

            $image->resize(
                $image->getSize()->widen(
                    $canvasWidth * ($options['scale'] * 600 / $canvasWidth)
                ),
                ImageInterface::FILTER_LANCZOS
            );

            if ($options['flipImage'] === 'true') {
                $image->flipHorizontally();
            }

            if ($options['left'] < 0 || $options['top'] < 0) {
                $image->crop(
                    new Point(
                        $options['left'] < 0 ? abs($options['left']) : 0,
                        $options['top'] < 0 ? abs($options['top']) : 0
                    ),
                    new Box(
                        min($background->getSize()->getWidth(), $image->getSize()->getWidth() + $options['left']),
                        min($background->getSize()->getHeight(), $image->getSize()->getHeight() +$options['top'])
                    )
                );
                if ($options['left'] < 0) {
                    $options['left'] = 0;
                }
                if ($options['top'] < 0) {
                    $options['top'] = 0;
                }
            }

            $location = new Point($options['left'], $options['top']);
            $finalImage->paste($image, $location)
                ->applyMask($mask);

            if ($options['opacity'] < 1) {
                $finalImage->getImagick()->evaluateImage(
                    \Imagick::EVALUATE_MULTIPLY,
                    $options['opacity'],
                    \Imagick::CHANNEL_ALPHA
                );
            }

            $finalImage = $background->paste($finalImage, new Point(0, 0));
            if ($options['showTitleBar'] === 'true') {
                $titlebar = $imagine->open(
                    __DIR__ . '/../../../TemplateBuilder/view/frontend/web/templates_full/' . $backgroundName . '/' .
                    $backgroundName . '_' . $size . '_TitleBar.png'
                );
                $finalImage->paste($titlebar, new Point(0, $options['titleTop'] < 0 ? 0 : $options['titleTop']));
            }

            if (!$this->mediaDirectory->isWritable(dirname($cachePath))) {
                $this->mediaDirectory->create(dirname($cachePath));
            }

            $finalImage->save($this->mediaDirectory->getAbsolutePath($cachePath));
        }
        return $this->_processDownload($cachePath, $resourceType);
    }
}

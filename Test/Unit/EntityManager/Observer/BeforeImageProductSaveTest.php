<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\Test\Unit\EntityManager\Observer;

use DevStone\ImageProducts\EntityManager\Observer\BeforeImageProductSave;
use DevStone\ImageProducts\Model\Product\Type;
use Magento\Catalog\Api\Data\ProductExtensionInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class BeforeImageProductSaveTest extends TestCase
{
    private TestableBeforeImageProductSave $observer;

    protected function setUp(): void
    {
        $reflection = new ReflectionClass(TestableBeforeImageProductSave::class);
        $this->observer = $reflection->newInstanceWithoutConstructor();
    }

    public function testPartialSaveWithoutDownloadableLinksPreservesGallery(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->with('type', null)->willReturn(null);
        $this->observer->setRequestForTest($request);

        $extensionAttributes = $this->createMock(ProductExtensionInterface::class);
        $extensionAttributes->method('getDownloadableProductLinks')->willReturn([]);

        $product = $this->createMock(Product::class);
        $product->method('getTypeId')->willReturn(Type::TYPE_ID);
        $product->method('getExtensionAttributes')->willReturn($extensionAttributes);
        $product->method('getData')->with('media_gallery')->willReturn([
            'images' => [
                ['file' => '/d/o/doubting-thomas-GoodSalt-rhpas1431.jpg'],
            ],
        ]);
        $product->expects(self::never())->method('setData');

        $event = new Event(['product' => $product]);
        $this->observer->execute(new Observer(['event' => $event]));
    }

    /**
     * @dataProvider linkedFileNameProvider
     *
     * @param string[] $linkFileNames
     */
    public function testResolvesGalleryReplacementToDownloadableLink(
        string $galleryFile,
        array $linkFileNames,
        string $expected
    ): void {
        self::assertSame(
            $expected,
            $this->observer->resolveLinkedFileNameForTest($galleryFile, $linkFileNames)
        );
    }

    /**
     * @return array<string, array{string, string[], string}>
     */
    public static function linkedFileNameProvider(): array
    {
        return [
            'original preview' => [
                '/d/o/doubting-thomas-GoodSalt-rhpas1431.jpg',
                ['rhpas1431'],
                'rhpas1431',
            ],
            'replacement with Magento duplicate suffix' => [
                '/d/o/doubting-thomas-GoodSalt-rhpas1431_1.jpg',
                ['rhpas1431'],
                'rhpas1431',
            ],
            'link whose real filename ends with a numeric suffix' => [
                '/a/r/artwork-GoodSalt-image_2.jpg',
                ['image_2'],
                'image_2',
            ],
            'unrelated gallery image remains unrelated' => [
                '/o/t/other-GoodSalt-unrelated_1.jpg',
                ['rhpas1431'],
                'unrelated_1',
            ],
        ];
    }
}

class TestableBeforeImageProductSave extends BeforeImageProductSave
{
    public function setRequestForTest(RequestInterface $request): void
    {
        $this->request = $request;
    }

    /**
     * @param string[] $linkFileNames
     */
    public function resolveLinkedFileNameForTest(string $file, array $linkFileNames): string
    {
        return $this->resolveLinkedFileName($file, $linkFileNames);
    }
}

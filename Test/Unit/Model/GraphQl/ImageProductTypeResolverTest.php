<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\Test\Unit\Model\GraphQl;

use DevStone\ImageProducts\Model\GraphQl\ImageProductTypeResolver;
use DevStone\ImageProducts\Model\Product\Type;
use PHPUnit\Framework\TestCase;

final class ImageProductTypeResolverTest extends TestCase
{
    private ImageProductTypeResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ImageProductTypeResolver();
    }

    public function testResolvesImageProductAsSimpleProduct(): void
    {
        self::assertSame('SimpleProduct', $this->resolver->resolveType(['type_id' => Type::TYPE_ID]));
    }

    public function testIgnoresOtherProductTypes(): void
    {
        self::assertSame('', $this->resolver->resolveType(['type_id' => 'virtual']));
    }

    public function testIgnoresDataWithoutProductType(): void
    {
        self::assertSame('', $this->resolver->resolveType([]));
    }
}

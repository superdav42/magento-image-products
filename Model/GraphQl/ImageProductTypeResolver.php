<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\Model\GraphQl;

use DevStone\ImageProducts\Model\Product\Type;
use Magento\Framework\GraphQl\Query\Resolver\TypeResolverInterface;

final class ImageProductTypeResolver implements TypeResolverInterface
{
    public function resolveType(array $data): string
    {
        return ($data['type_id'] ?? null) === Type::TYPE_ID ? 'SimpleProduct' : '';
    }
}

<?php
/**
 * Created by PhpStorm.
 * User: dave
 * Date: 7/10/18
 * Time: 5:17 PM
 */

namespace DevStone\ImageProducts\Model\Rule\Related\Condition\Product;

class Attributes extends \Aheadworks\Autorelated\Model\Rule\Related\Condition\Product\Attributes
{
    /**
     * Prepare condition for sql query
     *
     * @param string $field
     * @param string $value
     * @return string
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function prepareSqlCondition($field, $value)
    {
        $method = $this->getMethod();

        if ($this->getAttributeObject()->getFrontendInput() != 'multiselect'
            || $method !== 'in'
        ) {
            return parent::prepareSqlCondition($field, $value);
        }

        $callback = $this->getPrepareValueCallback();

        if ($callback) {
            $value = call_user_func([$this, $callback], $value);
        }

        $conditions = [];
        foreach ($value as $item) {
            $conditions[] = "IF(FIND_IN_SET('{$item}', {$field}),1,0)";
        }

        $condition = join(' + ', $conditions);

        return $condition.' > '.min(floor(count($value) * 0.5), 7);
    }
}
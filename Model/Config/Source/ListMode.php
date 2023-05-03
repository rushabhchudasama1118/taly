<?php
namespace Talypay\Taly\Model\Config\Source;

/**
 * Used in creating options for getting product type value
 *
 */
class ListMode implements \Magento\Framework\Data\OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
        ['value' => '0', 'label' => __('Test Mode')],
        ['value' => '1', 'label' => __('Live Mode')],
        ];
    }
}

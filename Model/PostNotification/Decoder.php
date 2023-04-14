<?php
namespace Taly\Taly\Model\PostNotification;

use Magento\Framework;

/**
 */
class Decoder implements DecoderInterface
{
    /**
     * Decodes the given $data string which is encoded in the x-www-form-urlencoded format of Taly.
     *
     * @param string $data
     * @return mixed
     */
    public function decode($data)
    {
        parse_str($data, $result);
        return $result;
    }
}

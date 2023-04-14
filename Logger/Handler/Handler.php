<?php
namespace Taly\Taly\Logger\Handler;

/**
 * @copyright Copyright © 2020 Taly Taly. All rights reserved.
 * @author    moinpasha
 */

class Handler extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * Logging level
     * @var int
     */
    protected $loggerType = \Monolog\Logger::INFO;

    /**
     * @param \Magento\Framework\Filesystem\DriverInterface $filesystem
     */
    public function __construct(
        \Magento\Framework\Filesystem\DriverInterface $filesystem,
        $filePath = null,
        $fileName = null
    ) {
        $fileName = '/var/log/Taly-' . date('Y-m-d') . '.log';
        parent::__construct($filesystem, $filePath, $fileName);
    }
}

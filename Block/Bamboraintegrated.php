<?php
/**
 * @author    Reign <hello@reign.com.au>
 * @version   1.1.0
 * @copyright Copyright (c) 2019 Reign. All rights reserved.
 * @copyright Copyright (c) 2019 Bambora. All rights reserved.
 * @license   Proprietary/Closed Source
 * By viewing, using, or actively developing this application in any way, you are
 * henceforth bound the license agreement, and all of its changes, set forth by
 * Reign and Bambora. The license can be found, in its entirety, at this address:
 * http://www.reign.com.au/magento-licence
 */

namespace Bambora\Apaccheckout\Block;

use Magento\Framework\View\Element\Template;

class Bamboraintegrated extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Magento\Framework\Registry
     */
    protected $_registry;

    /**
     * Bamboraintegrated constructor.
     * @param Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        \Magento\Framework\Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_registry = $registry;
    }

    /**
     * @return mixed
     */
    public function getSessionId()
    {
        return $this->_registry->registry('sessionid');
    }

    /**
     * @return mixed
     */
    public function getSubmitUrl()
    {
        return $this->_registry->registry('submiturl');
    }

    /**
     * @return mixed
     */
    public function getSst()
    {
        return $this->_registry->registry('sst');
    }
}
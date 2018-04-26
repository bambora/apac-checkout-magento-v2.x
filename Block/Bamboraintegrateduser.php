<?php
/**
 * @author    Reign <hello@reign.com.au>
 * @version   1.0.0
 * @copyright Copyright (c) 2018 Reign. All rights reserved.
 * @copyright Copyright (c) 2018 Bambora. All rights reserved.
 * @license   Proprietary/Closed Source
 * By viewing, using, or actively developing this application in any way, you are
 * henceforth bound the license agreement, and all of its changes, set forth by
 * Reign and Bambora. The license can be found, in its entirety, at this address:
 * http://www.reign.com.au/magento-licence
 */

namespace Bambora\Apaccheckout\Block;

class Bamboraintegrateduser extends \Magento\Framework\View\Element\Template
{
    public function getBaseUrl()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $registry = $objectManager->get('\Magento\Framework\Registry'); 

        return $registry->registry('base_url');
    }

    public function getErrormessage()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $registry = $objectManager->get('\Magento\Framework\Registry'); 

        return $registry->registry('errormessage');
    }
    
    

}
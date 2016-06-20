<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Appota\AppotaPayment\Model;



/**
 * Pay In Store payment method model
 */
class AppotaPaymentMethod extends \Magento\Payment\Model\Method\AbstractMethod
{

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'appotapaymentmethod';

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isOffline = true;
    
//    public function __construct(\Magento\Framework\UrlInterface $urlBuilder) {
//        $this->_urlBuilder = $urlBuilder;
//    }
//    
//    public function getCheckoutRedirectUrl()
//    {
//        return $this->_urlBuilder->getUrl('appotapay/index/index');
//    }
  

}

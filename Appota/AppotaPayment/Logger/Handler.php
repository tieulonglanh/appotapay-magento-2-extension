<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace Appota\AppotaPayment\Logger;
/**
 * Description of Handler
 *
 * @author Longnh
 */
class Handler extends \Magento\Framework\Logger\Handler\Base{
    /**
     * Logging level
     * @var int
     */
    protected $loggerType = Logger::INFO;

    /**
     * File name
     * @var string
     */
    protected $fileName = '/var/log/appota-payment.log';
        
}

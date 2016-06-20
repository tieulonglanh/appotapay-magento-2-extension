<?php

namespace Appota\AppotaPayment\Controller\Payment;

use \Appota\AppotaPayment\Helper\CallApi;

class Cancel extends \Magento\Framework\App\Action\Action {
    protected $messageManager;
    /**
     * @param \Magento\Framework\App\Action\Context $context      
     */
    public function __construct(
            \Magento\Framework\App\Action\Context $context,
            \Magento\Framework\Message\ManagerInterface $messageManager
            ) {
        $this->messageManager = $messageManager;
        parent::__construct($context);
    }

    /**
     * Blog Index, shows a list of recent blog posts.
     *
     * @return \Magento\Framework\View\Result\PageFactory
     */
    public function execute() {
        $order_id = (int)$_GET['order_id'];
        $this->messageManager->addNotice('Bạn đã hủy bỏ giao dịch thanh toán với đơn hàng có order id: ' . $order_id );
        $this->_redirect('/');
    }


}

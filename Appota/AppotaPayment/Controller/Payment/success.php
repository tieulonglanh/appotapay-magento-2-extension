<?php

namespace Appota\AppotaPayment\Controller\Payment;

use \Appota\AppotaPayment\Helper\CallApi;

class Success extends \Magento\Framework\App\Action\Action {

    /**
     * @var  \Magento\Framework\View\Result\Page 
     */
    protected $resultPageFactory;
    protected $callApiHelper;
    protected $logger;
    protected $checkoutSession;
    protected $orderFactory;
    protected $customerSession;
    protected $scopeConfig;
    protected $messageManager;
    const XML_PATH_API_SECRET = 'payment/appotapaymentmethod/api_secret';

    /**
     * @param \Magento\Framework\App\Action\Context $context      
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context, 
        \Magento\Framework\View\Result\PageFactory $resultPageFactory, 
        \Appota\AppotaPayment\Logger\Logger $logger, 
        \Appota\AppotaPayment\Helper\Receiver $receiver, 
        \Magento\Checkout\Model\Session $checkoutSession, 
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        
        $this->resultPageFactory = $resultPageFactory;
        $this->logger = $logger;
        $this->receiver = $receiver;
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->customerSession = $customerSession;
        $this->scopeConfig = $scopeConfig;
        $this->messageManager = $messageManager;
        parent::__construct($context);
    }

    /**
     * Blog Index, shows a list of recent blog posts.
     *
     * @return \Magento\Framework\View\Result\PageFactory
     */
    public function execute() {
        try {
            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
            $api_secret = $this->scopeConfig->getValue(self::XML_PATH_API_SECRET, $storeScope);
            $check_valid_request = $this->receiver->checkValidRequest($_GET, $api_secret);
            if ($check_valid_request['error_code'] == 0) {
                $check_valid_order = $this->receiver->checkValidOrder($_GET);
                if ($check_valid_order['error_code'] == 0) {
                    $order_id = (int) $_GET['merchant_order_id'];
                    $transaction_id = (int) $_GET['transaction_id'];
                    $total_amount = floatval($_GET['total_amount']);
                    $order = $this->orderFactory->create()->load($order_id);
                    $order_status = 'complete';
                    $order->setData('state', "payment_review");
                    $order->setStatus("complete");
                    $order->save();
                    $comment_status = 'Thực hiện thanh toán thành công với đơn hàng ' . $order_id . '. Giao dịch hoàn thành. Cập nhật trạng thái cho đơn hàng thành công';
                    $this->messageManager->addSuccess( __($comment_status) );
                    $message = "Appota Pay xác nhận đơn hàng: [Order ID: {$order_id}] - [Transaction ID: {$transaction_id}] - [Total: {$total_amount}] - [{$order_status}]";
                    $this->logger->info($message);
                }else{
                    $message = "Mã Lỗi: {$check_valid_order['error_code']} - Message: {$check_valid_order['message']}";
                    $this->logger->info($message);
                    $this->messageManager->addError( __($message) );
                }
            }else {
                    $message = "Mã Lỗi: {$check_valid_request['error_code']} - Message: {$check_valid_request['message']}";
                    $this->logger->info($message);
                    $this->messageManager->addError( __($message) );
                    
            }
            
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addExceptionMessage($e, $e->getMessage(). ' Xin hãy liên hệ người bán hàng để thông báo!');
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                    $e, __('Có lỗi hệ thống xảy ra. Xin hãy liên hệ người bán hàng để thông báo!')
            );
        }

        $this->_redirect('/');
    }

    protected function getCheckoutSession() {
        return $this->checkoutSession;
    }

}

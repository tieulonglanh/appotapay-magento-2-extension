<?php

namespace Appota\AppotaPayment\Controller\Payment;

use \Appota\AppotaPayment\Helper\CallApi;

class Start extends \Magento\Framework\App\Action\Action {

    /**
     * @var  \Magento\Framework\View\Result\Page 
     */
    protected $resultPageFactory;
    protected $callApiHelper;
    protected $logger;
    protected $checkoutSession;
    protected $orderFactory;
    protected $scopeConfig;
    protected $messageManager;
    protected $customerSession;
    protected $ssl_verify = false;
    const XML_PATH_API_KEY = 'payment/appotapaymentmethod/api_key';
    const XML_PATH_API_SECRET = 'payment/appotapaymentmethod/api_secret';
    const XML_PATH_API_PRIVATE_KEY = 'payment/appotapaymentmethod/api_private_key';
    private $allow_curency = array('VND');

    /**
     * @param \Magento\Framework\App\Action\Context $context      
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context, 
        \Magento\Framework\View\Result\PageFactory $resultPageFactory, 
        \Appota\AppotaPayment\Logger\Logger $logger, 
        \Magento\Checkout\Model\Session $checkoutSession, 
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Customer\Model\Session $customerSession
    ) {
        $this->scopeConfig = $scopeConfig;
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $api_key = $this->scopeConfig->getValue(self::XML_PATH_API_KEY, $storeScope);
        $api_secret = $this->scopeConfig->getValue(self::XML_PATH_API_SECRET, $storeScope);
        $api_private_key = $this->scopeConfig->getValue(self::XML_PATH_API_PRIVATE_KEY, $storeScope);
        
        $config = array(
            'api_key' => $api_key,
            'lang' => 'vi',
            'secret_key' => $api_secret,
            'ssl_verify' => $this->ssl_verify,
            'private_key' => $api_private_key
        );
        $this->resultPageFactory = $resultPageFactory;
        $this->callApiHelper = new \Appota\AppotaPayment\Helper\CallApi($config);
        $this->logger = $logger;
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->messageManager = $messageManager;
        $this->customerSession = $customerSession;
        parent::__construct($context);
    }

    /**
     * Blog Index, shows a list of recent blog posts.
     *
     * @return \Magento\Framework\View\Result\PageFactory
     */
    public function execute() {
        try {
            $order_id = $this->checkoutSession->getLastRealOrder()->getIncrementId();
            $order = $this->orderFactory->create()->load($order_id);
            $data = $order->getData();
            
            $url_success = $this->_url->getUrl('appotapayment/payment/success');
            $url_cancel = $this->_url->getUrl('appotapayment/payment/cancel');
            $payer_name = $data['customer_lastname'] . $data['customer_firstname'];
            
            $total_amount = $order->getGrandTotal();
            $shipping_amount = $order->getShippingAmount();
            $tax_amount = $order->getTaxAmount();
            $shipping_data = $order->getShippingAddress()->getData();
            
            $params = array();
            $params['order_id'] = strval($data['entity_id']);
            $params['total_amount'] = strval($total_amount);
            $params['shipping_fee'] = strval($shipping_amount); 
            $params['tax_fee'] = strval($tax_amount);
            $params['currency_code'] = $data['order_currency_code'];
            $params['url_success'] = $url_success;
            $params['url_cancel'] = $url_cancel;
            $params['order_description'] = "";
            $params['payer_name'] = $payer_name;
            $params['payer_email'] = $shipping_data['email'];
            $params['payer_phone_no'] = $shipping_data['telephone'];
            $params['payer_address'] = $shipping_data['street'] ." - ". $shipping_data['city'];
            $params['ip'] = $this->auto_reverse_proxy_pre_comment_user_ip();
            $params['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
            $noError = 1;
            $items = $order->getItems();
            $items_data = array();
            foreach($items as $item) {
                $id = $item->getId();
                $items_data[$id]['id'] = $id;
                $items_data[$id]['name'] = $item->getName();
                $items_data[$id]['price'] = $item->getPrice();
                $items_data[$id]['quantity'] = $item->getQtyOrdered();
            }
            $params['product_info'] = json_encode($items_data);
            if(!in_array($data['order_currency_code'], $this->allow_curency)) {
                $message = "Loại tiền tệ thanh toán không được cổng thanh toán chấp nhận. Chỉ cho phép dùng Việt Nam Đồng. Xin hãy báo người quản lý website!";
                $noError = 0;
                $this->logger->info($message);
                $this->messageManager->addError($message);
                
                $order->setData('state', "canceled");
                $order->setStatus("canceled");
                $order->save();
                $this->_redirect('/');
            }
            
            $result = $this->callApiHelper->getPaymentUrl($params);
            
            if(empty($result)) {
                $message = "Không nhận được thông tin trả về!";
                $noError = 0;
                $this->logger->info($message);
                $this->messageManager->addError($message);
            }
            if ($result['error_code'] != 0) {
                $noError = 0;
                $this->logger->info($result['message']);
                $this->messageManager->addError($result['message']);
            }
            if($noError) {
                $appota_payment_url = $result['data']['payment_url'];
                $this->logger->info("Success: Redirect Payment Url -> " . $appota_payment_url);
                $this->_redirect($appota_payment_url);
            }else{
                $isUserLogin = $this->customerSession->isLoggedIn();
                if($isUserLogin) {
                    $this->messageManager->addError('Xin bạn hãy thử thanh toán lại!');
                    $this->_redirect('sales/order/reorder/order_id/' . $order_id);
                }else{
                    $order->setData('state', "canceled");
                    $order->setStatus("canceled");
                    $order->save();
                    $this->_redirect('checkout/cart');
                }
                
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                    $e, __('We can\'t start Appota Payment Checkout: ' . $e->getMessage())
            );
        }

    }

    protected function getCheckoutSession() {
        return $this->checkoutSession;
    }

    protected function getQuote() {
        if (!$this->quote) {
            $this->quote = $this->getCheckoutSession()->getQuote();
        }
        return $this->quote;
    }
    
    function auto_reverse_proxy_pre_comment_user_ip() {
        $REMOTE_ADDR = $_SERVER['REMOTE_ADDR'];
        if (!empty($_SERVER['X_FORWARDED_FOR'])) {
            $X_FORWARDED_FOR = explode(',', $_SERVER['X_FORWARDED_FOR']);
            if (!empty($X_FORWARDED_FOR)) {
                $REMOTE_ADDR = trim($X_FORWARDED_FOR[0]);
            }
        }
        /*
         * Some php environments will use the $_SERVER['HTTP_X_FORWARDED_FOR'] 
         * variable to capture visitor address information.
         */ elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $HTTP_X_FORWARDED_FOR = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            if (!empty($HTTP_X_FORWARDED_FOR)) {
                $REMOTE_ADDR = trim($HTTP_X_FORWARDED_FOR[0]);
            }
        }
        return preg_replace('/[^0-9a-f:\., ]/si', '', $REMOTE_ADDR);
    }

}

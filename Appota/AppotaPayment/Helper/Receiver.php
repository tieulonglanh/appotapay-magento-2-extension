<?php

/**
 * APPOTA RECEIVER
 * Class này có chức năng như sau:
 * - Nhận thông tin giao dịch từ cổng Appota Pay
 * - Xác minh dữ liệu nhận được
 * - Ghi log các dữ liệu và thông báo nhận được
 * - Nếu xác minh thông tin Appota Pay gửi về thành công, cập nhật (hoàn thành) đơn hàng
 *
 * Copy Right by Appota Pay
 * @author tieulonglanh
 */
namespace Appota\AppotaPayment\Helper;
/**
 * CẤU HÌNH HỆ THỐNG
 * @const DIR_LOG   Đường dẫn file log. Thư mục mặc định là appota_receiver
 * @const FILE_NAME Tên file log mặc định.
 *
 */

Class Receiver extends \Magento\Framework\App\Helper\AbstractHelper {
    
    protected $appota_pay_transaction_status_completed = 1;
    protected $orderFactory;
    public function __construct(
            \Magento\Sales\Model\OrderFactory $orderFactory
        ) {
        $this->orderFactory = $orderFactory;
    }

    public function checkValidRequest($data, $api_secret) {
        if (!$this->hasCurl()) {
            return array(
                'error_code' => 102,
                'message' => 'Kiểm tra curl trên server'
            );
        }
        if (!$this->verifySignature($data, $api_secret)) {
            return array(
                'error_code' => 103,
                'message' => 'Sai signature gửi đến. Không thể thực hiện thanh toán!'
            );
        }

        return array(
            'error_code' => 0,
            'message' => 'Thông tin request thành công!'
        );
    }


    public function checkValidOrder($data) {
        $order_id = (int) $data['order_id'];
        $transaction_status = (int) $data['status'];
        $total_amount = $data['amount'];
        
        $confirm = '';

        //Kiểm tra trạng thái giao dịch
        if ($transaction_status == $this->appota_pay_transaction_status_completed) {

            //Lấy thông tin order
            if (!is_numeric($order_id) && ($order_id == 0)) {
                $confirm .= "\r\n" . ' Không nhận được mã đơn hàng nào : ' . $order_id;
            }

            //Kiểm tra sự tồn tại của đơn hàng
            $order = $this->orderFactory->create()->load($order_id);
            $order_info = $order->getData();
            $order_total = $order->getGrandTotal();
            if (empty($order_info)) {
                $confirm .= "\r\n" . ' Đơn hàng với mã đơn : ' . $order_id . ' không tồn tại trên hệ thống';
            }

            //Kiểm tra số tiền đã thanh toán phải >= giá trị đơn hàng
            //Lấy giá trị đơn hàng
            if ($total_amount < $order_total) {
                $confirm .= "\r\n" . ' Số tiền thanh toán: ' . $total_amount . ' cho đơn hàng có mã : ' . $order_id . ' nhỏ hơn giá trị của đơn hàng.';
                $order->setData('state', "holded");
                $order->setStatus("pending");
                $order->save();
            }
        } else {
            $confirm .= "\r\n" . ' Trạng thái giao dịch:' . $transaction_status . ' chưa thành công với mã đơn hàng : ' . $order_id;
        }

        if ($confirm == '') {
            return array(
                'error_code' => 0,
                'message' => 'Đơn hàng hợp lệ'
            );
        }
        
        return array(
            'error_code' => 104,
            'message' => $confirm
        );
    }

    /**
     * Kiểm tra xem server có hỗ trợ curl hay không.
     * @return boolean
     */
    private function hasCurl() {
        return function_exists('curl_version');
    }

    private function verifySignature($data, $secret_key) {
        $signature = $data['signature'];
	unset($data['signature']);
	ksort($data);
        $data_str = implode('', $data);
        $secret = pack('H*', strtoupper(md5($secret_key)));
        $new_signature = hash_hmac('sha256', $data_str, $secret);
        if($signature == $new_signature) {
            return true;
        } else {
            return false;
        }
    }

}

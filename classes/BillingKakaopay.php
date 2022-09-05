<?php
class BillingKakaopay
{
    private $apiUrl = "https://kapi.kakao.com/v1";
    private $apiMethods = [
        'init' => "payment/ready",
        'approve' => "payment/approve",
        'subscription'=> "payment/subscription"
    ];
    private $billing_test_id= 'TCSUBSCRIP';//CT06206006
    private $billing_admin_test_key= '1eb42500c9dcb81bcf59d0e6434cf1f2';

    private $billing_id= 'CT09608347';//CT06206006
    private $billing_admin_key= '1eb42500c9dcb81bcf59d0e6434cf1f2';
    private $data = [];
    public $value = null;
    public $status = null;

    public function __construct($data)
    {
        $this->init($data);
    }

    public function init($data){
        $this->data= $data;
        $this->value= null;
        $this->status= null;
        return $this;
    }

    public function getApiUrl($method)
    {
        if (!isset($this->apiMethods[$method])) return null;

        return "{$this->apiUrl}/{$this->apiMethods[$method]}";
    }

    public function getBillingId(){
        if($this->billing_id===$this->data['pg_id']){
            return $this->billing_id;
        }

        return $this->billing_test_id;
    }

    public function getBillingAuthKey(){
        if($this->billing_id===$this->data['pg_id']){
            return $this->billing_admin_key;
        }

        return $this->billing_admin_test_key;
    }

    public function subscription($isDev= false){
        $url= $this->getApiUrl('subscription');
        $quantity= 1;
        $options = ['post'=>[
            'cid' => $this->getBillingId(),
            'cid_secret'=> $this->getBillingAuthKey(),
            'sid' => $this->data['billing_key'],
            'partner_order_id' => $this->data['order_id'],
            'partner_user_id' => $this->data['member_id'] ?: "",
            'quantity'=> $quantity,
            'total_amount'=>$this->data['price'] * $quantity,
            'tax_free_amount'=> 0
        ]];
        if($isDev){
            error_log(print_r($options, true), 3, dirname(__FILE__)."/../logs/".date("Ymd")."inicis.dev.log");
        }

        $this->value= json_decode($this->post($url, $options), true);
        return $this;
    }

    public function post($url, $options= []){
        $ch = curl_init($url);
        $headers = [
            "Authorization: KakaoAK " . $this->getBillingAuthKey(),
            "Content-Type: application/x-www-form-urlencoded"
        ];
        if(!empty($options) && !empty($options['post'])){
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($options['post']));
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); //header 지정하기
        $result = curl_exec($ch);

        if (!curl_errno($ch)) {
            switch ($http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE)) {
                case 201:  # OK
                case 200:  # OK
                    break;
                default:
                    $this->errors[] = $http_code;
                    break;
            }
            $this->status= $http_code;
        }
        curl_close($ch);
        return $result;
    }


}

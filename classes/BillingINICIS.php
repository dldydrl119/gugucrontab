<?php
class BillingINICIS
{
    private $apiUrl = "https://iniapi.inicis.com/api/v1";
    private $apiMethods = [
        'subscription'=> "billing"
    ];
    private $billing_test_id= 'INIBillTst';//CT06206006
    private $billing_test_key= 'rKnPljRn5m6J9Mzz';

    private $billing_id= 'gugushop01';//CT06206006
    private $billing_key= '8hnSF5JCNUnqcHMA';//SHVEb3RnM1JIdG04cUo0KzVDQTh0Zz09

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
            return $this->billing_key;
        }

        return $this->billing_test_key;
    }

    public function subscription($isDev= false){
        $url= $this->getApiUrl('subscription');
        $quantity= 1;
        $timestamp= date("YmdHis");
        $order= $this->data['order'];
        $type= 'Billing';
        $paymethod= 'Card';//'HPP'
        $price= $this->data['price'] * $quantity;
        $server_ip= $_SERVER['SERVER_ADDR'] ?? '211.47.74.19';//211.47.74.19
        $moid= "S".$this->data['id'];

        $hashData= hash('sha512', $this->getBillingAuthKey().$type.$paymethod.$timestamp.$server_ip
        .$this->getBillingId().$moid.$price.$this->data['billing_key']);
        $options = ['post'=>[
            'type'=> $type,
            'paymethod'=> $paymethod,
            'timestamp'=> $timestamp,
            'clientIp'=> $server_ip,
            'mid' => $this->getBillingId(),
            'url'=> 'https://gugushopping.com',
            'moid'=> $moid,
            'goodName'=> $this->data['goods_name'],
            'buyerName' => $order['name'] ?: $order['b_name'],
            'buyerEmail' => $order['email'],
            'buyerTel'=>$order['cellphone'],
            'partner_user_id' => $this->data['member_id'] ?: "",
            'quantity'=> $quantity,
            'price'=>$price,
            'billKey'=> $this->data['billing_key'],
            'authentification'=> '00',
            'hashData'=> $hashData
        ]];
        if($isDev){
            error_log($this->getBillingAuthKey().$type.$paymethod.$timestamp.$server_ip
                .$this->getBillingId().$moid.$price.$this->data['billing_key'], 3, dirname(__FILE__)."/../logs/".date("Ymd")."inicis.dev.log");
            error_log(print_r($options, true), 3, dirname(__FILE__)."/../logs/".date("Ymd")."inicis.dev.log");
        }
        $this->value= json_decode($this->post($url, $options), true);
        return $this;
    }

    public function post($url, $options= []){
        $ch = curl_init($url);
        $headers = [            
            "Content-type: application/x-www-form-urlencoded;charset=utf-8"
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

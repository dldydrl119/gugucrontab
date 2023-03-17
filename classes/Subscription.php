<?php

include_once(dirname(__FILE__)."/BillingKakaopay.php");
include_once(dirname(__FILE__)."/BillingINICIS.php");

class Subscription
{
    public static $tableName = 'subscriptions';
    private $tableComment = '구독 테이블';
    private $primary = "id";
    private $keys = [
        'id' => 'PRIMARY KEY (`id`)',
        'member_id' => 'KEY `member_id` (`member_id`)'
    ];
    private $error = [];
    private $scheme = [
        'id' => [
            'query' => "bigint unsigned NOT NULL auto_increment"
        ],
        'member_id' => [
            'query' => "int unsigned NOT NULL comment '회원 index_no' default 0"
        ],
        'order_id' => [
            'query' => "varchar(30) NOT NULL comment 'od_id 카카오페이용' default ''"
        ],
        'is_subscribed' => [
            'query' => "tinyint NOT NULL comment '0: 구독취소, 1: 구독중' default 0"
        ],
        'pg' => [
            'query' => "varchar(10) not null comment 'PG사' default 'inicis'"
        ],
        'pg_id' => [
            'query' => "pg_id(40) not null comment 'PG사 결제 아이디' default 'gugushop01'"
        ],
        'billing_key' => [
            'query' => "varchar(52) not null comment '정기결제용 키값' default ''"
        ],
        'price' => [
            'query' => "int unsigned NOT NULL comment '결제금액' default 0"
        ],
        'gs_id' => [
            'query' => "int unsigned NOT NULL comment '상품 index_no' default 0"
        ],
        'io_id' => [
            'query' => "varchar(255) NOT NULL comment '상품옵션 io_id' default ''"
        ],
        'goods_name' => [
            'query' => "varchar(100) NOT NULL default ''"
        ],
        'new_order_data' => [
            'query' => "text comment '신규 주문생성시 데이터(json_encode 데이터)'"
        ],
        'next_billing_date' => [
            'query' => "date comment '다음 결제일' "
        ],
        'payment_cycle' => [
            'query' => "smallint unsigned NOT NULL comment '결제주기 (월단위)' default 0"
        ],
        'billing_day' => [
            'query' => "tinyint unsigned NOT NULL comment '매달 결제일 (next_billing_date용도)' default 1"
        ],
        'memo' => [
            'query' => "text comment '관리자 메모'"
        ],
        'updated_at' => [
            'query' => "datetime DEFAULT CURRENT_TIMESTAMP "
        ],
        'created_at' => [
            'query' => "datetime comment '구독일시 (생성일)' "
        ]
    ];
    private $order_indexes = ['od_no' => null, 'od_id' => null];
    private $usedOrderFields = ['name', 'mb_id', 'od_id', 'cellphone', 'email', 'b_name', 'b_cellphone', 'b_telephone', 'b_zip', 'b_addr1', 'b_addr2', 'b_addr3', 'b_addr_jibeon', 'gs_id', 'receipt_time', 'od_billkey', 'use_price', 'od_time', 'od_goods', 'pt_id', 'shop_id', 'paymethod', 'seller_id', 'sum_qty', 'goods_price', 'supply_price', 'od_pwd', 'od_pg', 'memo'];
    private $response= null;
    public $offset= 30;
    public $page= 1;
    public $lastInsertId = null;
    private $row= null;
    private $pdo= null;
    private $api= null;//정기결제용 API class
    private $newOrderUsedColumns= ['mb_id', 'pt_id', 'shop_id', 'dan', 'paymethod', 'name', 'cellphone', 'telephone', 'email', 'zip', 'addr1', 'addr2', 'addr3', 'addr_jibeon', 'b_name', 'b_cellphone', 'b_telephone', 'b_zip', 'b_addr1', 'b_addr2', 'b_addr3', 'b_addr_jibeon', 'gs_id', 'gs_notax', 'seller_id', 'sellerpay_yes', 'sum_point', 'sum_qty', 'goods_price', 'supply_price', 'coupon_price', 'use_price', 'use_point', 'baesong_price', 'baesong_price2', 'cancel_price', 'refund_price', 'bank', 'deposit_name', 'memo', 'shop_memo', 'taxsave_yes', 'taxbill_yes', 'company_saupja_no', 'company_name', 'company_owner', 'company_addr', 'company_item', 'company_service', 'od_mobile', 'od_mod_history', 'od_pwd', 'od_test', 'od_settle_pid', 'od_pg', 'od_billkey', 'payment_cycle', 'od_app_no', 'od_escrow', 'od_casseqno', 'od_tax_flag', 'od_tax_mny', 'od_vat_mny', 'od_free_mny', 'od_goods', 'od_ip', 'subscription_id'];
    public function __construct($pdo)
    {
        $this->pdo= $pdo;
    }

    public function init($row){
        $this->error= [];
        $this->lastInsertId= null;
        $this->row= $row;
        if(!empty($this->row)){
            $this->row['order']= $this->decodeOrderData();
        }
        switch(strtoupper($row['pg'])){
            case 'INICIS':
                $this->api= new BillingINICIS($this->row);
                break;
                case 'KAKAOPAY':
                    $this->api= new BillingKakaopay($this->row);    
                    break;
            default:
                    $this->api= null;
                    break;
            }

            return $this;
    }

    public function handleCheckNewOrderData(){
        $orderData= $this->decodeOrderData();
       if(!is_array($orderData) || empty($orderData['mb_id'])){
           $this->error[]= '신규 주문용 데이터가 손상되있습니다.';
       }

        return $this;
    }

    public function subscription($isDev= false){
        if(!empty($this->error)) return $this;
        $this->response= $this->api
                                    ->subscription($isDev)
                                    ->value;

        $logPath= dirname(__FILE__)."/../logs/";
        $row= $this->row;

        if($this->api->status !== 200 || (isset($this->response['resultCode']) && $this->response['resultCode'] !== '00')){
            //실패관련 로그들 디비로 변경하면 좋을듯합니다.
            error_log($row['id'].PHP_EOL.print_r($this->response, true), 3, $logPath.date("Ymd").".{$row['pg']}.error.log");
            $this->error[]= 'subscription->status';
            return $this;
        }

        error_log($row['id'].PHP_EOL.print_r($this->response, true), 3, $logPath.date("Ymd").".{$row['pg']}.success.log");

        return $this;
                             
    }

    public function isDone(){
        return empty($this->error);
    }

    public function fetchNextBillingDate(){
        if(!empty($this->error)) return $this;
        $date= $this->getNextBillingDate();
        $logPath= dirname(__FILE__)."/../logs/";
        error_log(PHP_EOL."[Update BillingDate]".$this->row['id'].":".$date.PHP_EOL, 3, $logPath.date("Ymd").".{$this->row['pg']}.success.log");
        $this->update(['next_billing_date'=> $date]);

        return $this;
    }

    public function update($request, $id= null, $key = null)
    {
        if(!empty($this->error)) return $this;
        $key = is_null($key) ? $this->primary : $key;
        $id  = is_null($id) && !empty($this->row)  ? $this->row['id'] : $id;
        if (empty($request) || empty($id)) {
            $this->error[] = "update->Empty request";
            return $this;
        }

        $set = "";
        foreach ($request as $k => $value) {
            if (!isset($this->scheme[$k]) || $k === $this->primary) {
                continue;
            }
            $set .= ($set ? ',' : '') . " {$k} = '{$value}'";
        }
        
        $sql = sprintf("update %s set %s where %s = '%s'", self::$tableName, $set, $key, $id);
        $stmt= $this->pdo->prepare($sql);
        $stmt->execute();        
        if ($this->pdo->errorCode() !== '00000') {
            $this->error[] = 'update';
        }

        return $this;
    }

    public function getNextBillingDate()
    {
        if(empty($this->row['billing_day'])) return "";

        $date= date("Y-m-".$this->row['billing_day']);
        $nextMonth= $this->row['payment_cycle'];
        
        $dateTime = new DateTime($date);
        $beforeMonth = (int)$dateTime->format("m");        
        $dateTime->add(new DateInterval("P{$nextMonth}M"));

        //다다음달로 지정될 경우
        if ($beforeMonth + $nextMonth < (int)$dateTime->format('m')) {
            $dateTime->sub(new DateInterval("P1M"));
            return $dateTime->format("Y-m-t");
        }

        return $dateTime->format('Y-m-d');
    }

    public function getError(){
        return join(PHP_EOL, $this->error);
    }
    
    public function decodeOrderData()
    {
        $orderData = $this->row['new_order_data'];
        if(empty($orderData)) return [];

        $data = json_decode(htmlspecialchars_decode($orderData), true);
        return $data;
    }

    function get_uniqid()
    {
        $server_ip= $_SERVER['REMOTE_ADDR'] ?? '';
        while (1) {
            // 년월일시분초에 100분의 1초 두자리를 추가함 (1/100 초 앞에 자리가 모자르면 0으로 채움)
            $key = (string)date('ymdHis', time()).substr(microtime(), 2, 2);// . str_pad((int)(microtime()*100), 2, '0', STR_PAD_LEFT);
            $stmt= $this->pdo->prepare("INSERT INTO shop_uniqid (uq_id, uq_ip) VALUES (?, ?)");
            
            $stmt->execute([$key, $server_ip]);
            //$id= $this->pdo->lastInsertId();            
            if($this->pdo->errorCode() === '00000') break; // 쿼리가 정상이면 빠진다.
            // insert 하지 못했으면 일정시간 쉰다음 다시 유일키를 만든다.
            usleep(10000); // 100분의 1초를 쉰다
        }
    
        return $key;
    }
    
    function cart_uniqid()
    {
        $i= 0;
        while(1) {
            srand((double)microtime()*1000000);
            $key = rand(1000000000,9999999999);            
            $row1= $this->pdo->query(" select count(*) as cnt from shop_cart where od_no = '{$key}' ")->fetch();
            $row1= $this->pdo->query(" select count(*) as cnt from shop_order where od_no = '{$key}' ")->fetch();
    
            if(empty($row1['cnt']) && empty($row2['cnt'])) return $key;
            ++$i;
            if($i> 300){
                return null;
            }
            // count 하지 못했으면 일정시간 쉰다음 다시 유일키를 검사한다.
            usleep(10000); // 100분의 1초를 쉰다
        }
    
        return null;
    }
    

    public function convertReceiptDate($date){
        $format= "Y-m-d H:i:s";
        if(strpos($date, 'T') !== false){
            $dt= new DateTime($date);
            return $dt->setTimezone(new DateTimeZone('UTC'))->format($format);
        }


        return date($format, strtotime($date));
    }

    public function mapResponseData(){

        switch(strtoupper($this->row['pg'])){
            case 'KAKAOPAY':
                return [
                    'od_app_no'=> $this->response['aid'],
                    'od_tno' => $this->response['tid'],
                    'receipt_time'=> $this->convertReceiptDate($this->response['approved_at']),
                ];
            case 'INICIS':
                return [
                    'od_app_no'=> $this->response['payAuthCode'] ?? '',
                    'od_tno' => $this->response['tid'] ?? '',
                    'receipt_time'=> isset($this->response['payDate']) ? $this->convertReceiptDate($this->response['payDate'].$this->response['payTime']): ''
                ];
            default:
                return [

                ];

        }
    }

    public function insertOrder(){

        if(!empty($this->error) || empty($this->response)) return $this;
        $orderData= $this->mapResponseData();
        if(empty($orderData['od_app_no'])){
            $this->error[]= "insertOrder-> empty od_app_no";
            return $this;
        }

        foreach($this->decodeOrderData() as $key=> $value){
            if(isset($orderData[$key]) || !in_array($key, $this->newOrderUsedColumns) || in_array($key, ['goods','index_no', 'od_id', 'od_no', 'od_time', 'dan', 'od_app_no', 'od_tno'])){
                continue;
            }
            $orderData[$key]= $value;
        }

        // $orderData['receipt_time']= $this->convertReceiptDate($this->response['approved_at'] ?? $this->response['payDate'].$this->response['payTime']);
        // $orderData['od_tno']= $this->response['tid'];
        // $orderData['od_app_no']=$this->response['aid'] ?? $this->response['payAuthCode'];
        $orderData['subscription_id']= $this->row['id'];
        $orderData['od_no']= $this->order_indexes['od_no'];
        $orderData['od_id']= $this->order_indexes['od_id'];
        $orderData['od_time']= date("Y-m-d H:i:s");
        $orderData['dan']= 2;//정기결제 최초상태 

        $keys= array_keys($orderData);

        $stmt = $this->pdo->prepare("INSERT INTO shop_order (".implode(",", $keys).") VALUES (:".implode(", :", $keys).")");
        $stmt->execute($orderData);
        if($this->pdo->errorCode() !== '00000') {
            $this->error[]= "insertOrder-> query error";
            return null;
        }
        $id= $this->pdo->lastInsertId();
        return $this;
    }

    public function showCart($id, $field= '*'){
        return $this->pdo->query("SELECT {$field} FROM shop_goods where index_no= '{$id}' LIMIT 1")->fetch();
    }

    public function insertCart(){
        if(!empty($this->error)) return $this;
        //옵션에 금액이 추가될경우 io_price 별도로 옵션테이블에서 가져와야함.
        $field= "C.gcate as ca_id, now() as ct_time, G.index_no as gs_id, G.goods_price as ct_price, G.gname as ct_option, G.supply_price as ct_supply_price, '0' as io_type, '0' as io_price, G.gpoint as ct_point, '1' as ct_qty";
        $data = $this->pdo->query("SELECT {$field} FROM shop_goods as G inner join shop_goods_cate as C where G.index_no= '{$this->row['gs_id']}' LIMIT 1")->fetch();
        $orderData= $this->decodeOrderData();
        $data['mb_id']= $orderData['mb_id'];
        $data['io_id']= $this->row['io_id'];
        $this->order_indexes['od_no']= $data['od_no']= $this->cart_uniqid();
        $this->order_indexes['od_id']= $data['od_id']= $this->get_uniqid();

        if(empty($this->order_indexes['od_no'])){
            $this->error[]= 'insertCart->empty od_no';
            return $this;
        }
        if(empty($this->order_indexes['od_id'])){
            $this->error[]= 'insertCart->empty od_id';
            return $this;
        }

        $keys= array_keys($data);

        $stmt = $this->pdo->prepare("INSERT INTO shop_cart (".implode(",", $keys).") VALUES (:".implode(", :", $keys).")");
        $stmt->execute($data);
        if($this->pdo->errorCode() !== '00000') {
            $this->error[]= 'insertCart-> query error';
            return $this;
        }

        $id= $this->pdo->lastInsertId();
        return $this;

    }
}

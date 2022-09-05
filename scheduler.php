<?php
$host = '211.47.74.10';
$db   = 'dbgugudev';
$user = 'gugudev';
$pass = 'gugudev0710!';
$charset = 'utf8';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

include_once(dirname(__FILE__)."/classes/Subscription.php");

$logPath= dirname(__FILE__)."/logs/";
//mkdir($logPath, 0707);

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}


try {
    //상용화 
    $stmt = $pdo->prepare("select * from subscriptions where pg_id not in('TCSUBSCRIP', 'INIBillTst') and is_subscribed= 1 and payment_cycle > 0 and next_billing_date <= CURRENT_DATE()");
    
    ////테스트용
    //$stmt = $pdo->prepare("select * from subscriptions where pg_id in('TCSUBSCRIP', 'INIBillTst') and is_subscribed= 1 and payment_cycle > 0 and next_billing_date <= CURRENT_DATE()");
    $stmt->execute();
    //$billingKakaopay= new BillingKakaopay();
    $api= new Subscription($pdo);
    $rows= $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo PHP_EOL."[START]";
    foreach($rows as $row){
        echo $row['id'].PHP_EOL;
        $isDone= $api->init($row)
                        ->handleCheckNewOrderData()
                        ->subscription()
                        ->insertCart()
                        ->insertOrder()
                        ->fetchNextBillingDate()
                        ->isDone();

        if($isDone){
            echo PHP_EOL."OK:({$row['id']})";
            continue;
        }

        echo PHP_EOL."FAIL:({$row['id']}) ".$api->getError().PHP_EOL;
//        echo "<b>".$api->getError()."</b>";
    }

}catch (Exception $e){
    //$pdo->rollback();
    echo PHP_EOL."[Catch Error]";
    throw $e;
    echo PHP_EOL;
}

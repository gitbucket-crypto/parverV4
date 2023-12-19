<?php 
ini_set('display_errors','0');
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

define('WP_DEBUG', false);
session_start();
require_once('functions.php');
require_once('types.php');
require_once('database.php');

global $conn, $short, $datetime;
global $mac;
static $unparsedMac;
static $hdmi;
static $ip;


function setRawMac($anac)
{
    $unparsedMac = $anac;
}


function setMac($amac)
{
    $mac= $amac;
}


$datetime = 'NOW()';

$conn = connect();

$SQL ="SELECT * FROM predatasets WHERE processed='0' ORDER BY random() LIMIT 1";
$stmt = $conn->query($SQL);
$data =  $stmt->fetchAll(\PDO::FETCH_ASSOC) or die('sem dados a serem processados');

#var_dump($data); exit;

$uid = $data[0]['uid'];
$raw = $data[0]["raw_data"];
$datahoraserver =$data[0]["datahoraserver"];
$datahoranuc = $data[0]['datahoranuc'];
$hdmi = $data[0]["hdmistatus"];

$data[0]['raw_data'] = replace(FOOTER.HEADER, FOOTER.'@'.HEADER, $data[0]['raw_data']);
$result = explode("@",$data[0]['raw_data']);


for($i= 0; $i <sizeof($result); $i++)
{
    $result[$i] = replace(HEADER, '' ,  $result[$i]); 
    $result[$i] = replace(FOOTER, '' ,  $result[$i]);
    $result[$i] = removeUnsedBytes($result[$i]);
    if(strval( substr($result[$i] , 0, 2))=='00' |strval( substr($result[$i] , 0, 2))=='88' )
    {
        $dmac = $result[$i];
        unset($result[$i]);
        $dmac = ltrim($dmac,'00');
        $mac = substr($dmac,2,12);
        $unparsedMac = $mac;
        $_SERVER['mac'] = $mac;
        $ip= '192.168.0.237';
        $_SERVER['ip'] = $ip;           
        $SQL = "UPDATE predatasets SET rmcmac=? , processed=? WHERE rmcip=? AND uid=?";
        $conn->prepare($SQL)->execute([$unparsedMac,'1' ,$ip, $uid]);
        echo 'MAC ADDRESS UPDATE TO '.$ip." mac ".$unparsedMac.PHP_EOL; 
    }
}
#var_dump($arr)()


array_filter($result, function($val) use($conn, $uid,  $datetime, $hdmi,$ip , $mac  )
{
    switch(strval( substr($val , 0, 2)))
    {
      
        case DOOR_TYPE:
            $d1= $val;
            unset($val);
           
            $crc = substr($d1, strlen($d1)-2, strlen($d1));
            $d1 = str_replace($crc, "", $d1);  //remover o crc

            $dt =utf8_str_split($d1,2);
            $vv = $dt[3].$dt[4].$dt[5].$dt[6]; //valor variavel não convertido

            $stmt  = $conn -> prepare("INSERT INTO  parseddatasets (uid, tipo, qtde_colunas, crc, datahora, rmc_ip, rmc_mac, hdmistatus) VALUES ( ? , ? , ? , ? , ?  , ?  , ? , ?  )");
            $stmt->execute( [$uid, strval( DOOR_TYPE), strval( hexdec($dt[1])) , strval($crc) , $datetime,  $ip, $mac, $hdmi ] );
            unset($stmt);

            $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
            $stmt->execute( [$uid, strval( DOOR_TYPE ), "'".$dt[1]."'" , "'".$vv."'" , $datetime ] );
            unset($stmt); unset($d1); unset($crc); unset($dt); unset($vv); 
        break; return;
        case WATER_TYPE:
            $d2= $val;
            unset($val);
            $crc = substr($d2, strlen($d2)-2, strlen($d2)); 
            $d2 = str_replace($crc, "", $d2);  //remover o crc

            $dt  = utf8_str_split($d2, 2);  
            $vv = $dt[3].$dt[4].$dt[5].$dt[6]; //valor variavel não convertido

            $stmt  = $conn -> prepare("INSERT INTO  parseddatasets (uid, tipo, qtde_colunas, crc, datahora, rmc_ip, rmc_mac, hdmistatus) VALUES ( ? , ? , ? , ? , ?  , ?  , ? , ?  )");
            $stmt->execute( [$uid, strval(WATER_TYPE), strval( hexdec($dt[1])) , strval($crc) , $datetime ,$ip, $mac , $hdmi] );
            unset($stmt);

            $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
            $stmt->execute( [$uid, WATER_TYPE , "'".$dt[2]."'" , "'".$vv."'" , $datetime ] );
            unset($stmt);
        break;
        case LIGHT_TYPE:
            $d3= $val;
            unset($val);
            $crc = substr($d3, strlen($d3)-2, strlen($d3));  	

            $d3 = trim(str_replace($crc, "", $d3));  //remover o crc   030400e30c000001e30c000002e30c000003e30c0000

            $arr = (utf8_str_split($d3,2));
            
            $cols = $arr[1];

            $stmt  = $conn -> prepare("INSERT INTO  parseddatasets (uid, tipo, qtde_colunas, crc, datahora, rmc_ip, rmc_mac, hdmistatus) VALUES ( ? , ? , ? , ? , ?  , ?  , ? , ?  )");
			$stmt->execute( [$uid, strval( LIGHT_TYPE),  strval( hexdec($cols)) , strval($crc) , $datetime , $ip, $mac, $hdmi ] );
            unset($stmt);
            
            for($i=0 ; $i<sizeof($arr); $i++)
            {
                if($i==2)
                {
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  LIGHT_TYPE , "'".$arr[$i]."'" , "'". $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i]."'" , $datetime ] );
                    unset($stmt);
                }
                if($i==7)
                {
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  LIGHT_TYPE , "'".$arr[$i]."'" , "'". $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i]."'" , $datetime ] );
                    unset($stmt);
                }                
                if($i==12)
                {
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  LIGHT_TYPE , "'".$arr[$i]."'" , "'". $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i]."'" , $datetime ] );
                    unset($stmt);
                }
                if($i==17)
                {
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  LIGHT_TYPE , "'".$arr[$i]."'" , "'". $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i]."'" , $datetime ] ); 
                    unset($stmt);
                    break;
                }
            }
        break;
        case TEMP_HUMIDITY_TYPE:
            $d4= $val;
            unset($val);

            $crc = substr($d4, strlen($d4)-2, strlen($d4));  	
            $d4 = trim(str_replace($crc, "",$d4));  //remover o crc   0401007d5fd54101dfd01e42

            $dt =utf8_str_split($d4,2); 
            $cols = $dt[0];

            $stmt  = $conn -> prepare("INSERT INTO  parseddatasets (uid, tipo, qtde_colunas, crc, datahora, rmc_ip, rmc_mac, hdmistatus) VALUES ( ? , ? , ? , ? , ?  , ?  , ? , ?  )");
            $stmt->execute( [$uid, strval( TEMP_HUMIDITY_TYPE), strval(hexdec($cols)) , strval($crc) , $datetime ,$ip, $mac, $hdmi ] );
            unset($stmt);

            $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
            $stmt->execute( [$uid, strval( TEMP_HUMIDITY_TYPE) , "'".$dt[1]."'" , "'". $dt[2].$dt[3].$dt[4].$dt[5].$dt[6].$dt[7].$dt[8].$dt[9]."'" , $datetime ] ); 
            unset($stmt);
        break;
        case G_SENSOR_TYPE:
            $d6= $val;
            unset($val);
            
            $crc = substr($d6, strlen($d6)-2, strlen($d6));  	
            $d6 = trim(str_replace($crc, "", $d6));  //remover o crc   030400e30c000001e30c000002e30c000003e30c0000
            $arr = (utf8_str_split($d6,2));
            $cols = $arr[0];

            $stmt  = $conn -> prepare("INSERT INTO  parseddatasets (uid, tipo, qtde_colunas, crc, datahora, rmc_ip, rmc_mac, hdmistatus) VALUES ( ? , ? , ? , ? , ?  , ?  , ? , ?  )");
            $stmt->execute( [$uid, strval( G_SENSOR_TYPE), strval($cols) , strval($crc) , $datetime ,$ip, $mac , $hdmi] );
            unset($stmt);
           
            for($i=0 ; $i< sizeof($arr); $i++)
             {
                if($i==2)
                {
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  G_SENSOR_TYPE , "'".$arr[$i]."'" , "'". $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i]."'" , $datetime ] );
                    unset($stmt);
                }
                if($i==7)
                {
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  G_SENSOR_TYPE , "'".$arr[$i]."'" , "'". $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i]."'" , $datetime ] );
                    unset($stmt);
                }
                if($i==12)
                {
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  G_SENSOR_TYPE , "'".$arr[$i]."'" , "'". $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i]."'" , $datetime ] );
                    unset($stmt);
                }
                if($i==17)
                {
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  G_SENSOR_TYPE , "'".$arr[$i]."'" , "'". $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i]."'" , $datetime ] );
                    unset($stmt);
                }
                if($i==22)
                {
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  G_SENSOR_TYPE , "'".$arr[$i]."'" , "'". $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i]."'" , $datetime ] );
                    unset($stmt);
                }
                if($i==27)
                {
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  G_SENSOR_TYPE , "'".$arr[$i]."'" , "'". $arr[1+$i].$arr[2+$i].@$arr[3+$i].@$arr[4+$i]."'" , $datetime ] );
                    unset($stmt); break;
                }
             }
        break;
        case GPIO_TYPE:
            $d8= $val;
            unset($val);
            $crc = substr($d8, strlen($d8)-2, strlen($d8));  	
            //0804000000000001000000000200000000030000000017 
            $d8 = trim(str_replace($crc, "",$d8));  //remover o crc   030400e30c000001e30c000002e30c000003e30c0000
            $arr = (utf8_str_split($d8,2));
            $cols = $arr[1];

            $stmt  = $conn -> prepare("INSERT INTO  parseddatasets (uid, tipo, qtde_colunas, crc, datahora, rmc_ip, rmc_mac, hdmistatus) VALUES ( ? , ? , ? , ? , ?  , ?  , ? , ?  )");
            $stmt->execute( [$uid, strval( GPIO_TYPE), strval($cols) , strval($crc) , $datetime ,$ip, $mac, $hdmi] );
            unset($stmt);
           
            for($i=0 ; $i< sizeof($arr); $i++)
            {
               if($i==2)
               {
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  GPIO_TYPE , "'".$arr[$i]."'" , "'". $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i]."'" , $datetime ] );
                   unset($stmt);
               }
               if($i==7)
               {
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  GPIO_TYPE , "'".$arr[$i]."'" , "'". $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i]."'" , $datetime ] );
                    unset($stmt);
               }
               if($i==12)
               {
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  GPIO_TYPE , "'".$arr[$i]."'" , "'". $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i]."'" , $datetime ] );
                    unset($stmt);
               }
               if($i==17)
               {
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  GPIO_TYPE , "'".$arr[$i]."'" , "'". $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i]."'" , $datetime ] );
                    unset($stmt); break;
               }
            }
        break;
        case RELAY_TYPE:
            $d9= $val;
            unset($val);
            //09020000000000010000000007
            $crc = substr($d9, strlen($d9)-2, strlen($d9));  	
            $d9= trim(str_replace($crc, "", $d9));  //remover o crc   030400e30c000001e30c000002e30c000003e30c0000
            $arr = (utf8_str_split($d9,2));
            $cols = $arr[1];

            $stmt  = $conn -> prepare("INSERT INTO  parseddatasets (uid, tipo, qtde_colunas, crc, datahora, rmc_ip, rmc_mac, hdmistatus) VALUES ( ? , ? , ? , ? , ?  , ?  , ? , ?  )");
            $stmt->execute( [$uid, strval( RELAY_TYPE), strval($cols) , strval($crc) , $datetime , $ip, $mac, $hdmi] );
            unset($stmt);

            $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
            $stmt->execute( [$uid,  RELAY_TYPE , "'".$arr[2]."'" , "'". $arr[3].$arr[4].$arr[5].$arr[6]."'" , $datetime ] );
            unset($stmt);

            $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
            $stmt->execute( [$uid,  RELAY_TYPE , "'".$arr[7]."'" , "'". $arr[8].$arr[9].$arr[10].$arr[11]."'" , $datetime ] );
            unset($stmt);
        break;
        case VOLTAGE_TYPE:
            $d10= $val;
            unset($val);
            $crc = substr($d10, strlen($d10)-2, strlen( $d10));  	
            $d10= trim(str_replace($crc, "",  $d10));  //remover o crc   030400e30c000001e30c000002e30c000003e30c0000
            $arr = (utf8_str_split($d10,2));

            $cols = $arr[1];
            $stmt  = $conn -> prepare("INSERT INTO  parseddatasets (uid, tipo, qtde_colunas, crc, datahora, rmc_ip, rmc_mac, hdmistatus) VALUES ( ? , ? , ? , ? , ?  , ?  , ? , ?  )");
            $stmt->execute( [$uid, strval(hexdec(VOLTAGE_TYPE)), strval($cols) , strval($crc) , $datetime , $ip, $mac, $hdmi] ) ;
            unset($stmt);           
            sleep(1);
            //0a0300000000000100000000020000000010
            for($i = 0; $i< sizeof($arr); $i++)
            {
                if($i==0)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];  
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(VOLTAGE_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==5)
                {
                    $col2 = $arr[$i]; 
                    $valor2 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];  
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(VOLTAGE_TYPE)) , "'".$col2."'" , "'".$valor2."'" , $datetime ] );
                    unset($stmt);
                    unset($col2); unset($valor2);
                }
                if($i==10)
                {
                    $col3 = $arr[$i]; 
                    $valor3 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];  
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(VOLTAGE_TYPE)) , "'".$col3."'" , "'".$valor3."'" , $datetime ] );
                    unset($stmt);
                    unset($col3); unset($valor3); break;
                }
            }
        break; 
        case NOISE_TYPE:
            $d11= $val;
            unset($val);
            $SQL= "INSERT INTO toparser VALUES ( ? , ? , ?)"; //$uid, $unk, $datetime
            $stmt = $conn ->prepare($SQL);
            $stmt->execute( [$uid, $d11, $datetime] );
            unset($stmt);
        break; 
        case SMOK_TYPE:
            $d12= $val;
            unset($val);
            $SQL= "INSERT INTO toparser VALUES ( ? , ? , ?)"; //$uid, $unk, $datetime
            $stmt = $conn ->prepare($SQL);
            $stmt->execute( [$uid, $d12, $datetime] );
            unset($stmt);
        break; 
        case ELECTRIC_METER_TYPE:
            $d13= $val;
            unset($val);
            $crc = substr($d13, strlen($d13)-2, strlen($d13));  
            $arr = (utf8_str_split($d13,2));
            $cols ='6';
            $stmt  = $conn -> prepare("INSERT INTO  parseddatasets (uid, tipo, qtde_colunas, crc, datahora, rmc_ip, rmc_mac, hdmistatus) VALUES ( ? , ? , ? , ? , ?  , ?  , ? , ?  )");
            $stmt->execute( [$uid, strval(hexdec(ELECTRIC_METER_TYPE)), strval($cols) , strval($crc) , $datetime , $ip, $mac, $hdmi] ) ;
            unset($stmt);
            sleep(1);
            for($i = 0; $i< sizeof($arr); $i++)
            {
                if($i==2)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];  
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(ELECTRIC_METER_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==7)
                {
                    $col2 = $arr[$i]; 
                    $valor2 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];  
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(ELECTRIC_METER_TYPE)) , "'".$col2."'" , "'".$valor2."'" , $datetime ] );
                    unset($stmt);
                    unset($col2); unset($valor2);
                }
                if($i==12)
                {
                    $col3 = $arr[$i]; 
                    $valor3 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];  
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(ELECTRIC_METER_TYPE)) , "'".$col3."'" , "'".$valor3."'" , $datetime ] );
                    unset($stmt);
                    unset($col3); unset($valor3);  
                }
                if($i==17)
                {
                    $col4 = $arr[$i]; 
                    $valor4 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];  
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(ELECTRIC_METER_TYPE)) , "'".$col4."'" , "'".$valor4."'" , $datetime ] );
                    unset($stmt);
                    unset($col4); unset($valor4); 
                }
                if($i==22)
                {
                    $col5= $arr[$i]; 
                    $valor5 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];  
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(ELECTRIC_METER_TYPE)) , "'".$col5."'" , "'".$valor5."'" , $datetime ] );
                    unset($stmt);
                    unset($col5); unset($valor5); 
                }
                if($i==27)
                {
                    $col6= $arr[$i]; 
                    $valor6 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];  
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(ELECTRIC_METER_TYPE)) , "'".$col6."'" , "'".$valor6."'" , $datetime ] );
                    unset($stmt);
                    unset($col6); unset($valor6); 
                }
            }
        break; 
        case EXT_TEMP_TYPE:
            $d14= $val;
            unset($val);;
            $crc = substr($d14, strlen($d14)-2, strlen($d14));  
            $arr = (utf8_str_split($d14,2));
            $cols = hexdec($arr[2]);
            $stmt  = $conn -> prepare("INSERT INTO  parseddatasets (uid, tipo, qtde_colunas, crc, datahora, rmc_ip, rmc_mac, hdmistatus) VALUES ( ? , ? , ? , ? , ?  , ?  , ? , ?  )");
            $stmt->execute( [$uid, strval(hexdec(ELECTRIC_METER_TYPE)), strval($cols) , strval($crc) , $datetime ,$ip, $mac, $hdmi] ) ;
            unset($stmt);
            sleep(1);
            for($i = 0; $i< sizeof($arr); $i++)
            {
                if($i==2)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_TEMP_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==7)
                {
                    $col2 = $arr[$i]; 
                    $valor2 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_TEMP_TYPE)) , "'".$col2."'" , "'".$valor2."'" , $datetime ] );
                    unset($stmt);
                    unset($col2); unset($valor2);
                }
                if($i==12)
                {
                    $col3 = $arr[$i]; 
                    $valor3 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_TEMP_TYPE)) , "'".$col3."'" , "'".$valor3."'" , $datetime ] );
                    unset($stmt);
                    unset($col3); unset($valor3);
                }
                if($i==17)
                {
                    $col4 = $arr[$i]; 
                    $valor4 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_TEMP_TYPE)) , "'".$col4."'" , "'".$valor4."'" , $datetime ] );
                    unset($stmt);
                    unset($col4); unset($valor4);
                }
                if($i==22)
                {
                    $col5 = $arr[$i]; 
                    $valor5 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_TEMP_TYPE)) , "'".$col5."'" , "'".$valor5."'" , $datetime ] );
                    unset($stmt);
                    unset($col5); unset($valor5);
                }
                if($i==27)
                {
                    $col6 = $arr[$i]; 
                    $valor6 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_TEMP_TYPE)) , "'".$col6."'" , "'".$valor6."'" , $datetime ] );
                    unset($stmt);
                    unset($col6); unset($valor6);
                }
                if($i==32)
                {
                    $col7 = $arr[$i]; 
                    $valor7 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_TEMP_TYPE)) , "'".$col7."'" , "'".$valor7."'" , $datetime ] );
                    unset($stmt);
                    unset($col7); unset($valor7);
                }
                if($i==37)
                {
                    $col8 = $arr[$i]; 
                    $valor8 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_TEMP_TYPE)) , "'".$col8."'" , "'".$valor8."'" , $datetime ] );
                    unset($stmt);
                    unset($col8); unset($valor8);
                }
                if($i==42)
                {
                    $col9 = $arr[$i]; 
                    $valor9 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_TEMP_TYPE)) , "'".$col9."'" , "'".$valor9."'" , $datetime ] );
                    unset($stmt);
                    unset($col9); unset($valor9);
                }
                if($i==47)
                {
                    $col9 = $arr[$i]; 
                    $valor9 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_TEMP_TYPE)) , "'".$col9."'" , "'".$valor9."'" , $datetime ] );
                    unset($stmt);
                    unset($col9); unset($valor9);
                }
                if($i==52)
                {
                    $col9 = $arr[$i]; 
                    $valor9 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_TEMP_TYPE)) , "'".$col9."'" , "'".$valor9."'" , $datetime ] );
                    unset($stmt);
                    unset($col9); unset($valor9);
                }
                if($i==57)
                {
                    $col9 = $arr[$i]; 
                    $valor9 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_TEMP_TYPE)) , "'".$col9."'" , "'".$valor9."'" , $datetime ] );
                    unset($stmt);
                    unset($col9); unset($valor9);
                }
                if($i==62)
                {
                    $col9 = $arr[$i]; 
                    $valor9 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_TEMP_TYPE)) , "'".$col9."'" , "'".$valor9."'" , $datetime ] );
                    unset($stmt);
                    unset($col9); unset($valor9);
                }
                if($i==67)
                {
                    $col9 = $arr[$i]; 
                    $valor9 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_TEMP_TYPE)) , "'".$col9."'" , "'".$valor9."'" , $datetime ] );
                    unset($stmt);
                    unset($col9); unset($valor9);
                }
                if($i==72)
                {
                    $col9 = $arr[$i]; 
                    $valor9 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_TEMP_TYPE)) , "'".$col9."'" , "'".$valor9."'" , $datetime ] );
                    unset($stmt);
                    unset($col9); unset($valor9);
                }
                if($i==77)
                {
                    $col9 = $arr[$i]; 
                    $valor9 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_TEMP_TYPE)) , "'".$col9."'" , "'".$valor9."'" , $datetime ] );
                    unset($stmt);
                    unset($col9); unset($valor9);
                }
            }
        break;
        case EXT_RAP_TYPE:
            $d15=$val;
            unset($val);
            $crc = substr($d15, strlen($d15)-2, strlen($d15));  
            $arr = (utf8_str_split($d15,2));
            $cols = $arr[1];
            $stmt  = $conn -> prepare("INSERT INTO  parseddatasets (uid, tipo, qtde_colunas, crc, datahora, rmc_ip, rmc_mac, hdmistatus) VALUES ( ? , ? , ? , ? , ?  , ?  , ? , ?  )");
            $stmt->execute( [$uid, strval(hexdec(EXT_RAP_TYPE)), strval($cols) , strval($crc) , $datetime ,$ip, $mac, $hdmi] ) ;
            unset($stmt);           
            sleep(1);
            for($i = 0; $i< sizeof($arr); $i++)
            {
                if($i==2)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_RAP_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==7)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_RAP_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==12)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_RAP_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==17)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_RAP_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==22)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_RAP_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==27)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_RAP_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==32)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_RAP_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==37)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_RAP_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==42)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_RAP_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==47)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_RAP_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==52)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_RAP_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==57)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_RAP_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==62)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_RAP_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==67)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_RAP_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==72)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_RAP_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==77)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_RAP_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==82)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_RAP_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==87)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_RAP_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==92)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_RAP_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==97)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_RAP_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==102)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_RAP_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==107)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_RAP_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==112)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_RAP_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==117)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(EXT_RAP_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
            }
        break;
        case RTC_TYPE:
            $d16=$val;
            unset($val);
            //1001002200000001040000000223000000030600000004190000000541000000064500000028
            // $SQL= "INSERT INTO toparser VALUES ( ? , ? , ?)"; //$uid, $unk, $datetime
            // $stmt = $conn ->prepare($SQL);
            // $stmt->execute( [$uid, $d16, $datetime] );
            // unset($stmt);
            $crc = substr($d16, strlen($d16)-2, strlen($d16));  
            $arr = (utf8_str_split($d16,2));
            $cols = $arr[1];
            $stmt  = $conn -> prepare("INSERT INTO  parseddatasets (uid, tipo, qtde_colunas, crc, datahora, rmc_ip, rmc_mac, hdmistatus) VALUES ( ? , ? , ? , ? , ?  , ?  , ? , ?  )");
            $stmt->execute( [$uid, strval(hexdec(RTC_TYPE)), strval($cols) , strval($crc) , $datetime ,$ip, $mac, $hdmi] ) ;
            unset($stmt);           
            sleep(1);
            for($i = 0; $i< sizeof($arr); $i++)
            {
                if($i==2)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(RTC_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==7)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(RTC_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==12)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(RTC_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==17)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(RTC_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==22)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(RTC_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==27)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(RTC_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
                if($i==32)
                {
                    $col1 = $arr[$i]; 
                    $valor1 = $arr[1+$i].$arr[2+$i].$arr[3+$i].$arr[4+$i];
                    $stmt = $conn ->prepare("INSERT INTO parseddatasets_x_types (uid, tipo, coluna, coluna_valor, datahora) VALUES ( ? , ? , ? , ? , ? )");
                    $stmt->execute( [$uid,  strval(hexdec(RTC_TYPE)) , "'".$col1."'" , "'".$valor1."'" , $datetime ] );
                    unset($stmt);
                    unset($col1); unset($valor1);
                }
            }
        break;
        default:
            $unk =$val;
            unset($val);
            $SQL= "INSERT INTO unknowdatatype VALUES ( ? , ? , ?)"; //$uid, $unk, $datetime
            $stmt = $conn ->prepare($SQL);
            $stmt->execute( [$uid, $unk, $datetime] );
            unset($stmt);
        break;
    }
});

exit;
?>
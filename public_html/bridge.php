<?php
  ob_start();
  
  ini_set('display_errors', 'Off');
  
  $api_version = '1.0';
  $mysql_hostname = '';
  $mysql_user = '';
  $mysql_password = '';
  $mysql_databas = '';
  $client_secret = '';
  
// Connect to database
  $mysqli = new mysqli($mysql_hostname, $mysql_user, $mysql_password, $mysql_database);
  
  if ($mysqli->connect_errno) trigger_error('Connection to MySQL database failed: ' . $mysqli->connect_error, E_USER_ERROR);
    
  switch($_GET['action']) {
    
  // Return list of orders
    case 'list':
    
      $output = array();
      
      if ($result = $mysqli->query("SELECT * FROM tblOrders WHERE order_status = 'x';")) {
        
        while ($row = $result->fetch_assoc()) {
          
          $output[] = array(
            'reference'     => $row['order_id'],
            'name'          => $row['name'],
            'destination'   => $row['country_code'].'-'.$row['postcode'].' '.$row['city'],
            'total_value'   => $row['order_total'],
            'currency_code' => $row['currency_code'],
            'total_weight'  => $row['total_weight'],
            'weight_class'  => $row['weight_class'],
            'custom'        => '',
            'date'          => date('Y-m-d H:i:s', strtotime($row['date_created'])),
          );
        }
        
        $result->close();
      }
      
      break;
      
  // Return an order
    case 'get':
    
      $output = array();
      
      if ($result = $mysqli->query("SELECT * FROM tblOrders WHERE order_id = ". $mysqli->real_escape_string($_GET['reference']) ." LIMIT 1")) {
        
        while ($row = $result->fetch_assoc()) {
          
          $output = array(
            'reference' => $row['order_id'],
            //'consigner' => array(
            //  'type'         => 'company',
            //  'name'         => '...',
            //  'address1'     => '...',
            //  'city'         => '...',
            //  'postcode'     => '...',
            //  'country_code' => '...',
            //  'contact'      => '...',
            //  'phone'        => '...',
            //),
            'consignee' => array(
              'type'         => !empty($row['company']) ? 'company' : 'individual',
              'name'         => !empty($row['company']) ? $row['customer_company'] : $row['customer_firstname'] .' '. $row['customer_lastname'],
              'address1'     => $row['customer_address1'],
              'city'         => $row['customer_city'],
              'postcode'     => $row['customer_postcode'],
              'country_code' => $row['customer_country_code'],
              'contact'      => $row['customer_firstname'].' '. $row['customer_lastname'],
              'phone'        => $row['customer_phone'].' '. $row['customer_phone'],
            ),
            'consignee' => array(
            ),
            'consignment' => array(
              'value' => (float)$row['order_total'],
              'currency_code' => (float)$row['order_total'],
              'shipments' => array(
                array('weight' => 0, 'weight_class' => 'kg', 'length' => 0, 'width' => 0, 'height' => 0, 'length_class' => 'cm'),
                array('weight' => 0, 'weight_class' => 'kg', 'length' => 0, 'width' => 0, 'height' => 0, 'length_class' => 'cm'),
                array('weight' => 0, 'weight_class' => 'kg', 'length' => 0, 'width' => 0, 'height' => 0, 'length_class' => 'cm'),
              ),
            ),
          );
        }
        
        $result->close();
      }
      
      break;
  }
  
  if (version_compare(PHP_VERSION, '5.4', '>=')) {
    $output = json_encode($output, JSON_PRETTY_PRINT);
  } else {
    $output = json_encode($output);
  }
  
  if ($buffer = ob_get_clean()) {
    header('HTTP/1.1 500 Internal Server Error');
    die($buffer);
  }
  
  header('Version: '.$api_version);
  header('Checksum: '.sha1($client_secret . $output));
  header('Content-Type: application/json; charset=utf-8');
  header('Content-Length: '.strlen($output));
  die($output);
  
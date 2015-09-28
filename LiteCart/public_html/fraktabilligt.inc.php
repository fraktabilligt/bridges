<?php
  
  ob_start();
  
  include('includes/config.inc.php');
  
// Configuration
  $name = 'Fraktabilligt Generic Bridge';
  $version = '1.0';
  $username = 'foo';
  $password = 'bar';
  $secret_key = '123456';
  $mysql_hostname = DB_SERVER;
  $mysql_user = DB_USERNAME;
  $mysql_password = DB_PASSWORD;
  $mysql_database = DB_DATABASE;
  
// Set timezone (PHP 5.3+)
  if (!ini_get('date.timezone')) ini_set('date.timezone', 'Europe/Stockholm');
  
// Initiate HTTP Auth Digest Protection
  if (empty($_SERVER['PHP_AUTH_DIGEST'])) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Digest realm="'. $name .'", qop="auth", nonce="'.uniqid().'", opaque="'.md5($name).'"');
    die('Authorization Required');
  }
  
// Get the digest string
  $digest = null;
  if (isset($_SERVER['PHP_AUTH_DIGEST'])) {
    $digest = $_SERVER['PHP_AUTH_DIGEST'];
  } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    if (strpos(strtolower($_SERVER['HTTP_AUTHORIZATION']), 'digest') === 0) $digest = substr($_SERVER['HTTP_AUTHORIZATION'], 7);
  }
  
// Parse the digest_string
  $parameters = array('nonce' => 1, 'nc' => 1, 'cnonce' => 1, 'qop' => 1, 'username' => 1, 'uri' => 1, 'response' => 1);
  
  preg_match_all('@('.implode('|', array_keys($parameters)).')=(?:([\'"])([^\2]+?)\2|([^\s,]+))@', $digest, $matches, PREG_SET_ORDER);
  
  $data = array();
  foreach ($matches as $match) {
    $data[$match[1]] = $match[3] ? $match[3] : $match[4];
    unset($parameters[$match[1]]);
  }
  $data = $parameters ? false : $data;
  
// Generate the valid checksum
  $checksum = md5(md5($username.':'.$name.':'.$password).':'.$data['nonce'].':'.$data['nc'].':'.$data['cnonce'].':'.$data['qop'].':'.md5($_SERVER['REQUEST_METHOD'].':'.$data['uri']));
  
  if ($data['response'] != $checksum) {
    header('HTTP/1.1 401 Unauthorized');
    die('Authorization Failed');
  }
  
// Connect to database
  $mysqli = new mysqli($mysql_hostname, $mysql_user, $mysql_password, $mysql_database);
  
  if ($mysqli->connect_errno) trigger_error('Connection to MySQL database failed: ' . $mysqli->connect_error, E_USER_ERROR);
  
  switch(@$_GET['action']) {
    
  // Return list of orders
    case 'list':
    
      $output = array();
      
      $sql = (
        "SELECT * FROM ". DB_TABLE_ORDERS ."
        ORDER BY date_created DESC
        LIMIT 100"
      );
      
      if ($result = $mysqli->query($sql) or trigger_error($mysqli->error, E_USER_ERROR)) {
        
        while ($row = $result->fetch_assoc()) {
          
          $output[] = array(
            'reference'     => $row['id'],
            'name'          => $row['shipping_company'] ? $row['shipping_company'] : $row['shipping_firstname'].' '.$row['shipping_lastname'],
            'destination'   => $row['shipping_country_code'].'-'.$row['shipping_postcode'].' '.$row['shipping_city'],
            'total_value'   => $row['payment_due'],
            'currency_code' => $row['currency_code'],
            'total_weight'  => $row['weight_total'],
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
      
      $sql = (
        "SELECT * FROM ". DB_TABLE_ORDERS ."
        WHERE id = '". $mysqli->real_escape_string($_GET['reference']) ."'
        LIMIT 1"
      );
      
      if ($result = $mysqli->query($sql) or trigger_error($mysqli->error, E_USER_ERROR)) {
        
        while ($row = $result->fetch_assoc()) {
          
          $output = array(
            'reference' => $row['id'],
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
              'type'         => !empty($row['shipping_company']) ? 'company' : 'individual',
              'name'         => !empty($row['shipping_company']) ? $row['shipping_company'] : $row['shipping_firstname'].' '.$row['shipping_lastname'],
              'address1'     => $row['shipping_address1'],
              'city'         => $row['shipping_city'],
              'postcode'     => $row['shipping_postcode'],
              'country_code' => $row['shipping_country_code'],
              'contact'      => $row['shipping_firstname'].' '.$row['shipping_lastname'],
              'phone'        => $row['customer_phone'],
            ),
            'consignment' => array(
              'value' => (float)$row['payment_due'],
              'currency_code' => $row['currency_code'],
              'shipments' => array(
                array('weight' => 0, 'weight_class' => 'kg', 'length' => 0, 'width' => 0, 'height' => 0, 'length_class' => 'cm'),
              ),
            ),
          );
        }
        
        $result->close();
      }
      
      break;
    
  // Mark order as booked for shipping
    case 'update':
      
      // Not implemented yet
      // ...
      
      break;
      
    default:
      header('HTTP/1.1 400 Bad Request');
      die('No action');
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
  
  header('HTTP/1.1 200 OK');
  header('Version: '.$version);
  header('Checksum: '.sha1($secret_key . $output));
  header('Content-Type: application/json; charset=utf-8');
  header('Content-Length: '.strlen($output));
  die($output);
  
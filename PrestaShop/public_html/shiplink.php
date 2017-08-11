<?php
  ini_set('display_errors', 'On');
  require_once('config/settings.inc.php');
  
// Configuration
  $name = 'Shiplink Generic Bridge';
  $version = '1.0';
  $username = 'foo';
  $password = 'bar';
  $secret_key = '0123456789abcdef0123456789abcdef';
  $mysql_hostname = _DB_SERVER_;
  $mysql_user = _DB_USER_;
  $mysql_password = _DB_PASSWD_;
  $mysql_database = _DB_NAME_;
  
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
  $mysqli->set_charset('utf8');
  
  if ($mysqli->connect_errno) trigger_error('Connection to MySQL database failed: ' . $mysqli->connect_error, E_USER_ERROR);
  
  switch(@$_GET['action']) {
    
  // Return list of orders
    case 'list':
      
      $output = array();
      
      $sql = (
        "SELECT
          o.id_order as order_id,
          o.total_paid_tax_incl as order_total,
          c1.iso_code as currency_code,
          a.company as shipping_company, 
          a.firstname as shipping_firstname, 
          a.lastname as shipping_lastname, 
          a.postcode as shipping_postcode, 
          a.city as shipping_city,
          c2.iso_code as shipping_country_code,
          o.date_add as order_date
        FROM ". _DB_PREFIX_ ."orders o
        LEFT JOIN ". _DB_PREFIX_ ."currency c1 ON (c1.id_currency = c1.id_currency)
        LEFT JOIN ". _DB_PREFIX_ ."address a ON (a.id_address = o.id_address_delivery)
        LEFT JOIN ". _DB_PREFIX_ ."country c2 ON (a.id_country = c2.id_country)
        ORDER BY o.date_add DESC
        LIMIT 100;"
      );
      
      if ($result = $mysqli->query($sql) or trigger_error($mysqli->error, E_USER_ERROR)) {
        
        while ($row = $result->fetch_assoc()) {
          
          $output[] = array(
            'reference'     => $row['order_id'],
            'name'          => $row['shipping_company'] ? $row['shipping_company'] : $row['shipping_firstname'].' '.$row['shipping_lastname'],
            'destination'   => $row['shipping_country_code'].'-'.$row['shipping_postcode'].' '.$row['shipping_city'],
            'total_value'   => $row['order_total'],
            'currency_code' => $row['currency_code'],
            'total_weight'  => null,
            'weight_class'  => null,
            'custom'        => '',
            'date'          => date('Y-m-d H:i:s', strtotime($row['order_date'])),
          );
        }
        
        $result->close();
      }
      
      break;
      
  // Return an order
    case 'get':
    
      $output = array();
      
      $sql = (
        "SELECT
          o.id_order as order_id,
          o.total_paid_tax_incl as order_total,
          c1.iso_code as currency_code,
          a.company as shipping_company, 
          a.firstname as shipping_firstname, 
          a.lastname as shipping_lastname, 
          a.address1 as shipping_address1, 
          a.postcode as shipping_postcode, 
          a.city as shipping_city,
          c2.iso_code as shipping_country_code,
          a.phone as shipping_phone,
          o.date_add as order_date
        FROM ". _DB_PREFIX_ ."orders o
        LEFT JOIN ". _DB_PREFIX_ ."currency c1 ON (c1.id_currency = c1.id_currency)
        LEFT JOIN ". _DB_PREFIX_ ."address a ON (a.id_address = o.id_address_delivery)
        LEFT JOIN ". _DB_PREFIX_ ."country c2 ON (a.id_country = c2.id_country)
        WHERE o.id_order = '". $mysqli->real_escape_string($_GET['reference']) ."'
        LIMIT 1"
      );
      
      if ($result = $mysqli->query($sql) or trigger_error($mysqli->error, E_USER_ERROR)) {
        
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
              'type'         => !empty($row['shipping_company']) ? 'company' : 'individual',
              'name'         => !empty($row['shipping_company']) ? $row['shipping_company'] :  $row['shipping_firstname'].' '.$row['shipping_lastname'],
              'address1'     => $row['shipping_address1'],
              'city'         => $row['shipping_city'],
              'postcode'     => $row['shipping_postcode'],
              'country_code' => $row['shipping_country_code'],
              'contact'      =>  $row['shipping_firstname'].' '.$row['shipping_lastname'],
              'phone'        => $row['shipping_phone'],
            ),
            'consignment' => array(
              'value' => (float)$row['order_total'],
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
  
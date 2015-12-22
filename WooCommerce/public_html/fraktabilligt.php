<?php
  ini_set('display_errors', 'On');
  
// WordPress corrupts the HTTP Auth Digest protection so we cannot include() the configuration.
  $config = file_get_contents('wp-config.php');
  $matches = array();
  preg_match('#define\(\'DB_HOST\',\s+\'(.*)\'\);#m', $config, $matches['DB_HOST']);
  preg_match('#define\(\'DB_USER\',\s+\'(.*)\'\);#m', $config, $matches['DB_USER']);
  preg_match('#define\(\'DB_PASSWORD\',\s+\'(.*)\'\);#m', $config, $matches['DB_PASSWORD']);
  preg_match('#define\(\'DB_NAME\',\s+\'(.*)\'\);#m', $config, $matches['DB_NAME']);
  preg_match('#\$table_prefix\s+=\s+\'(.*)\';#m', $config, $matches['DB_TABLE_PREFIX']);
  
// Configuration
  $name = 'Fraktabilligt Generic Bridge';
  $version = '1.0';
  $username = 'foo';
  $password = 'bar';
  $secret_key = '0123456789abcdef0123456789abcdef';
  $mysql_hostname = $matches['DB_HOST'][1];
  $mysql_user = $matches['DB_USER'][1];
  $mysql_password = $matches['DB_PASSWORD'][1];
  $mysql_database = $matches['DB_NAME'][1];
  $mysql_table_prefix = $matches['DB_TABLE_PREFIX'][1];
  
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
      $delimiter = '/**,**/';
      
      $sql = (
        "SELECT
          p.ID as order_id,
          p.post_date as order_date,
          pm.`keys`, 
          pm.`values`
        FROM ". $mysqli->real_escape_string($mysql_table_prefix) ."posts p
        LEFT JOIN (
          SELECT post_id, group_concat(meta_key SEPARATOR '". $delimiter ."') as `keys`, group_concat(meta_value SEPARATOR '". $delimiter ."') as `values`
          FROM ". $mysqli->real_escape_string($mysql_table_prefix) ."postmeta
          GROUP BY post_id
        ) pm ON (pm.post_id = p.ID)
        WHERE post_type = 'shop_order'
        ORDER BY p.post_date DESC
        LIMIT 100;"
      );
      
      if ($result = $mysqli->query($sql) or trigger_error($mysqli->error, E_USER_ERROR)) {
        
        while ($row = $result->fetch_assoc()) {
          if (count(explode($delimiter, $row['keys'])) != count(explode($delimiter, $row['values']))) {
            trigger_error('Inconsistent amount of meta titles and values for order '. $row['order_id'], E_USER_WARNING);
            continue;
          }
          
          $row = array_merge($row, array_combine(explode($delimiter, $row['keys']), explode($delimiter, $row['values'])));
          
          $output[] = array(
            'reference'     => $row['order_id'],
            'name'          => $row['_shipping_company'] ? $row['_shipping_company'] : $row['_shipping_first_name'].' '.$row['_shipping_last_name'],
            'destination'   => (!empty($row['_shipping_country']) ? $row['_shipping_country'].'-' : '') . $row['_shipping_postcode'].' '.$row['_shipping_city'],
            'total_value'   => $row['_order_total'],
            'currency_code' => $row['_order_currency'],
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
      $delimiter = '/**,**/';
      
      $sql = (
        "SELECT
          p.ID as order_id,
          p.post_date as order_date,
          pm.`keys`, 
          pm.`values`
        FROM ". $mysqli->real_escape_string($mysql_table_prefix) ."posts p
        LEFT JOIN (
          SELECT post_id, group_concat(meta_key SEPARATOR '". $delimiter ."') as `keys`, group_concat(meta_value SEPARATOR '". $delimiter ."') as `values`
          FROM ". $mysqli->real_escape_string($mysql_table_prefix) ."postmeta
          GROUP BY post_id
        ) pm ON (pm.post_id = p.ID)
        WHERE p.ID = '". $mysqli->real_escape_string($_GET['reference']) ."'
        AND post_type = 'shop_order'
        LIMIT 1"
      );
      
      if ($result = $mysqli->query($sql) or trigger_error($mysqli->error, E_USER_ERROR)) {
        
        while ($row = $result->fetch_assoc()) {
          $row = array_merge($row, array_combine(explode($delimiter, $row['keys']), explode($delimiter, $row['values'])));
          
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
              'type'         => !empty($row['_shipping_company']) ? 'company' : 'individual',
              'name'         => !empty($row['_shipping_company']) ? $row['_shipping_company'] :  $row['_shipping_first_name'].' '.$row['_shipping_last_name'],
              'address1'     => $row['_shipping_address1'],
              'city'         => $row['_shipping_city'],
              'postcode'     => $row['_shipping_postcode'],
              'country_code' => $row['_shipping_country'],
              'contact'      => $row['_shipping_first_name'].' '.$row['_shipping_last_name'],
              'phone'        => '', // Or use $row['_billing_phone']
            ),
            'consignment' => array(
              'value' => (float)$row['_order_total'],
              'currency_code' => $row['_order_currency'],
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
  
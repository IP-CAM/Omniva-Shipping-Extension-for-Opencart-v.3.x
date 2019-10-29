<?php

/**
 * Omnivalt shipping extension general controller
 * for settings enable/disable/install module
 * @version 2.0.0
 * @author mijora.lt
 */
class ControllerExtensionShippingOmnivalt extends Controller
{
  private $error = array();
  private $defaulCodename = 'Omnivalt Mod Default';
  private $version = '1.0.3';

  public function install()
  {
    // Add aditional columns into order table
    $sql = "
      ALTER TABLE " . DB_PREFIX . "order 
      ADD `labelsCount` INT NOT NULL DEFAULT '1',
      ADD `omnivaWeight` FLOAT NOT NULL DEFAULT '1',
      ADD `cod_amount` FLOAT DEFAULT 0;
      ";
    $this->db->query($sql);
    // Add order_omniva table to database
    $sql2 = "
      CREATE TABLE " . DB_PREFIX . "order_omniva (
        id int NOT NULL AUTO_INCREMENT, 
        tracking TEXT, 
        manifest int, 
        labels text, 
        id_order int, 
        PRIMARY KEY (id), 
        UNIQUE (id_order)
        );
      ";
    $this->db->query($sql2);

    // Set generated manifest counter(?)
    $this->load->model('setting/setting');
    $this->model_setting_setting->editSetting('omniva', array('omniva_manifest' => 0));

    // Prepare for hooking into event system
    $this->load->model('setting/event');
    $this->model_setting_event->deleteEventByCode($this->defaulCodename);

    // Admin Events
    $this->model_setting_event->addEvent(
      $this->defaulCodename,
      'admin/view/common/column_left/before',
      'extension/shipping/omnivalt/events/menu'
    );
    $this->model_setting_event->addEvent(
      $this->defaulCodename,
      'admin/view/sale/order_list/before',
      'extension/shipping/omnivalt/events/orderList'
    );
    $this->model_setting_event->addEvent(
      $this->defaulCodename,
      'admin/view/sale/order_info/before',
      'extension/shipping/omnivalt/events/orderInfo'
    );
    // Front Events
    // fix tracking omniva terminals loaded into session
    $this->model_setting_event->addEvent(
      $this->defaulCodename,
      'catalog/controller/checkout/checkout/save/before',
      'extension/module/omnivalt/default/fixSession'
    );

    // load omniva data into view $data array
    $this->model_setting_event->addEvent(
      $this->defaulCodename,
      'catalog/view/checkout/shipping_method/before',
      'extension/module/omnivalt/default/shippingMethodsView'
    );
    // use our modified template
    $this->model_setting_event->addEvent(
      $this->defaulCodename,
      'catalog/view/checkout/shipping_method/before',
      'extension/module/omnivalt/default/changeTemplate'
    );

    // Install modificationif needed
    $this->installModification();
  }

  public function uninstall()
  {
    // Remove modification to order table
    $sql = "
      ALTER TABLE " . DB_PREFIX . "order
      DROP COLUMN labelsCount,
      DROP COLUMN omnivaWeight,
      DROP COLUMN cod_amount;
      ";
    $this->db->query($sql);

    // Remove order_omniva table (all unsaved information will be lost)
    $sql2 = "DROP TABLE " . DB_PREFIX . "order_omniva";
    $this->db->query($sql2);

    // Remove modification from database
    $data = $this->loadModificationXML();
    $existing = $this->getModificationByCode($data['code']);
    if ($existing) {
      $this->removeModification($existing['modification_id']);
    }

    // Remove event hooks
    $this->load->model('setting/event');
    $this->model_setting_event->deleteEventByCode($this->defaulCodename);
  }

  // Settings controller
  public function index()
  {
    $this->document->addScript('view/javascript/omnivalt/settings.js');
    $this->load->model('setting/setting');
    $this->load->language('extension/shipping/omnivalt');
    $this->document->setTitle($this->language->get('heading_title'));

    // TODO: Enabling countries
    /* $data['countries'] = array();
    $data['countries'][] = array('code' => 'LT', 'text' => 'Lithuania');
    $data['countries'][] = array('code' => 'LV', 'text' => 'Latvia');
    $data['countries'][] = array('code' => 'EE', 'text' => 'Estonia'); */

    // Saving and validation
    if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate() && $this->validateSettings()) {
      $this->model_setting_setting->editSetting('shipping_omnivalt', $this->request->post);
      if (empty($this->request->post['download'])) {
        if (!empty($this->request->post['save_exit'])) {
          $this->response->redirect($this->url->link('marketplace/extension', 'type=shipping&user_token=' . $this->session->data['user_token'], 'SSL'));
        }
        $this->session->data['omnivalt_saved'] = true;
        $this->response->redirect($this->url->link('extension/shipping/omnivalt', '&user_token=' . $this->session->data['user_token'], 'SSL'));
      }
      $data['terminal_update'] = $this->fetchUpdates();
    }

    // Header data
    $data['version'] = $this->version;
    $data['breadcrumbs'] = array();
    $data['breadcrumbs'][] = array(
      'text' => $this->language->get('text_home'),
      'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
    );
    $data['breadcrumbs'][] = array(
      'text' => $this->language->get('text_extension'),
      'href' => $this->url->link('marketplace/extension', 'type=shipping&user_token=' . $this->session->data['user_token'], 'SSL')
    );
    $data['breadcrumbs'][] = array(
      'text' => $this->language->get('heading_title'),
      'href' => $this->url->link('extension/shipping/omnivalt', 'user_token=' . $this->session->data['user_token'], true),
    );
    $data['action'] = $this->url->link('extension/shipping/omnivalt', 'user_token=' . $this->session->data['user_token'], true);
    $data['cancel'] = $this->url->link('marketplace/extension', 'type=shipping&user_token=' . $this->session->data['user_token'], 'SSL');
    // End of Header data

    $data['settings_saved'] = false;
    if (isset($this->session->data['omnivalt_saved'])) {
      $data['settings_saved'] = $this->language->get('settings_saved');
      unset($this->session->data['omnivalt_saved']);
    }

    // Load translation strings
    foreach (array(
      'cron_url', 'heading_title', 'text_edit', 'text_enabled', 'text_disabled', 'text_yes', 'text_no', 'text_none', 'text_parcel_terminal',
      'text_courier', 'text_sorting_center', 'entry_url', 'entry_user', 'entry_password', 'entry_service', 'entry_pickup_type', 'entry_company',
      'entry_bankaccount', 'entry_pickupstart', 'entry_pickupfinish', 'entry_cod', 'entry_status', 'entry_sort_order', 'entry_parcel_terminal_price',
      'entry_courier_price', 'entry_terminals', 'button_save', 'button_save_exit', 'button_cancel', 'button_download', 'entry_sender_name',
      'entry_sender_address', 'entry_sender_city', 'entry_sender_postcode', 'entry_sender_phone', 'entry_sender_country_code', 'button_update_terminals',
      'button_save_exit', 'webservice_header', 'sender_header', 'services_header', 'prices_header', 'cod_header', 'pickup_header', 'terminals_header',
      'option_lt', 'option_lv', 'option_ee'
    ) as $key) {
      $data[$key] = $this->language->get($key);
    }

    $data['errors_found'] = false;
    if ($this->error) {
      $data['errors_found'] = $this->language->get('errors_found');
    }

    // Check user credentials / url errors
    foreach (array('warning', 'url', 'user', 'password') as $key) {
      $data['error_' . $key] = isset($this->error[$key]) ? $this->error[$key] : '';
    }

    $sender_array = array(
      // Sender Info
      'sender_name', 'sender_address', 'sender_phone',
      'sender_postcode', 'sender_city', 'sender_country_code',
      'sender_phone',
      // Parcel, Courier prices per country
      'parcel_terminal_price', 'parcel_terminal_pricelv', 'parcel_terminal_priceee',
      'courier_price', 'courier_pricelv', 'courier_priceee',
      // COD
      'company', 'bankaccount'
    );

    foreach ($sender_array as $key) {
      $data['error_' . $key] = isset($this->error[$key]) ? $this->error[$key] : '';
    }

    foreach ($sender_array as $key) {
      if (isset($this->request->post['shipping_omnivalt_' . $key])) {
        $data['shipping_omnivalt_' . $key] = $this->request->post['shipping_omnivalt_' . $key];
        continue;
      }
      $data['shipping_omnivalt_' . $key] = $this->config->get('shipping_omnivalt_' . $key);
    }

    $settings_fields = array(
      // Omniva WebService credentials
      'url', 'user', 'password',
      'service',
      // LT price
      'parcel_terminal_price', 'courier_price',
      // LV price
      'parcel_terminal_pricelv', 'courier_pricelv',
      // EE price
      'parcel_terminal_priceee', 'courier_priceee',
      // COD (required info if enabled)
      'cod', 'company', 'bankaccount',
      // Pickup time and type
      'pickupstart', 'pickupfinish', 'pickup_type',
      // Extension status (enabled/disabled)
      'status',
      // Place in carriers list
      'sort_order'
    );

    foreach ($settings_fields as $key) {
      if (isset($this->request->post['shipping_omnivalt_' . $key])) {
        $data['shipping_omnivalt_' . $key] = $this->request->post['shipping_omnivalt_' . $key];
        continue;
      }
      $data['shipping_omnivalt_' . $key] = $this->config->get('shipping_omnivalt_' . $key);
    }
    // Default hardcoded values if settings not found
    if ($data['shipping_omnivalt_url'] == '') {
      $data['shipping_omnivalt_url'] = 'https://edixml.post.ee';
    }

    if ($data['shipping_omnivalt_service'] == NULL) {
      $data['shipping_omnivalt_service'] = array();
    }

    if ($data['shipping_omnivalt_pickupstart'] == '') {
      $data['shipping_omnivalt_pickupstart'] = "8:00";
    }

    if ($data['shipping_omnivalt_pickupfinish'] == '') {
      $data['shipping_omnivalt_pickupfinish'] = "17:00";
    }

    // Prep possible services
    $data['services'] = array();
    foreach (array('courier', 'parcel_terminal') as $key) {
      $data['services'][] = array(
        'text' => $this->language->get('text_' . $key),
        'value' => $key,
      );
    }

    $data['shipping_omnivalt_terminals'] = $this->loadTerminals();
    $data['terminal_count'] = $this->language->get('terminal_count');
    if (isset($data['shipping_omnivalt_terminals'])) {
      $data['terminal_count'] = count($data['shipping_omnivalt_terminals']);
    }

    $data['cron_link'] = HTTPS_CATALOG . 'index.php?route=extension/module/omnivalt/update_terminals';

    $data['header'] = $this->load->controller('common/header');
    $data['column_left'] = $this->load->controller('common/column_left');
    $data['footer'] = $this->load->controller('common/footer');

    $this->response->setOutput($this->load->view('extension/shipping/omnivalt', $data));
  }

  protected function isEnabled()
  {
    if (!isset($this->request->post['shipping_omnivalt_status'])) {
      return false;
    }
    return (int) $this->request->post['shipping_omnivalt_status'];
  }

  protected function validateSettings()
  {
    // If extension disabled dont check if required fields are filled
    if (!$this->isEnabled()) {
      return !$this->error;
    }

    foreach (array('url', 'user', 'password') as $key) {
      if (!$this->request->post['shipping_omnivalt_' . $key]) {
        $this->error[$key] = $this->language->get('error_' . $key);
      }
    }

    // Check for required fields
    $required_fields = array(
      'sender_name', 'sender_address', 'sender_phone', 'sender_postcode', 'sender_city', 'sender_country_code', 'sender_phone',
      'parcel_terminal_price', 'parcel_terminal_pricelv', 'parcel_terminal_priceee',
      'courier_price', 'courier_pricelv', 'courier_priceee'
    );
    foreach ($required_fields as $key) {
      if (!$this->request->post['shipping_omnivalt_' . $key]) {
        $this->error[$key] = $this->language->get('error_required');
      }
    }

    if ($this->request->post['shipping_omnivalt_cod']) {
      foreach (array('company', 'bankaccount') as $key) {
        if (!$this->request->post['shipping_omnivalt_' . $key]) {
          $this->error[$key] = $this->language->get('error_required');
        }
      }
    }
    return !$this->error;
  }

  protected function validate()
  {
    if (!$this->user->hasPermission('modify', 'extension/shipping/omnivalt')) {
      $this->error['warning'] = $this->language->get('error_permission');
    }
    return !$this->error;
  }

  private function loadTerminals()
  {
    $terminals_json_file_dir = DIR_DOWNLOAD."omniva_terminals.json";
    if (!file_exists($terminals_json_file_dir))
      return false;
    $terminals_file = fopen($terminals_json_file_dir, "r");
    if (!$terminals_file)
      return false;
    $terminals = fread($terminals_file, filesize($terminals_json_file_dir) + 10);
    fclose($terminals_file);
    $terminals = json_decode($terminals, true);
    return $terminals;
  }

  private function fetchUpdates()
  {
    $csv = $this->fetchURL('https://www.omniva.ee/locations.csv');
    if (isset($csv['failed'])) {
      return ['failed' => $csv['failed']];
    }
    if (empty($csv)) {
      return ['failed' => 'Requested terminal list was empty, aborting terminal list update']; // TODO: translate
    }
    $countries = array();
    $countries['LT'] = 1;
    $countries['LV'] = 2;
    $countries['EE'] = 3;
    $terminals = $this->parseCSV($csv, $countries);
    if (isset($terminals['failed'])) {
      return ['failed' => $terminals['failed']];
    }
    $terminals = $terminals ? $terminals : array();
    $fp = fopen(DIR_DOWNLOAD."omniva_terminals.json", "w");
    fwrite($fp, json_encode($terminals));
    fclose($fp);

    $this->csvTerminal();
    return ['success' => 'Terminals updated']; // TODO: translate
  }

  private function fetchURL($url)
  {
    $ch = curl_init(trim($url));
    if (!$ch) {
      return ['failed' => 'Cant create curl']; // TODO: translate
    }
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_VERBOSE, 0);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $out = curl_exec($ch);
    if (!$out) {
      return ['failed' => curl_error($ch)];
    }
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
      return ['failed' => 'Cannot fetch update from ' . curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) . ': ' . curl_getinfo($ch, CURLINFO_HTTP_CODE)]; // TODO: translate
    }

    curl_close($ch);
    return $out;
  }

  public function csvTerminal()
  {

    $url = 'https://www.omniva.ee/locations.json';
    $fp = fopen(DIR_DOWNLOAD . "locations.json", "w");
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_FILE, $fp);
    curl_setopt($curl, CURLOPT_TIMEOUT, 60);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    $data = curl_exec($curl);
    curl_close($curl);
    fclose($fp);
  }

  private function parseCSV($csv, $countries = array())
  {
    $cabins = array();
    if (empty($csv)) {
      return $cabins;
    }
    if (mb_detect_encoding($csv, 'UTF-8, ISO-8859-1') == 'ISO-8859-1') {
      $csv = utf8_encode($csv);
    }
    $rows = str_getcsv($csv, "\n"); #parse the rows, remove first
    if (strpos($rows[0], 'ZIP;') === false) {
      return ['failed' => 'Terminal CSV file is in wrong format']; // TODO: need translation
    }
    $newformat = count(str_getcsv($rows[0], ';')) > 10 ? 1 : 0;
    array_shift($rows);
    foreach ($rows as $row) {
      $cabin = str_getcsv($row, ';');
      # there are lines with all fields empty in estonian file, workaround
      if (!count(array_filter($cabin))) {
        continue;
      }
      if ($newformat) {
        if (empty($countries[strtoupper(trim($cabin[3]))])) {
          continue;
        }
        # closed ? exists on EE only
        if (intval($cabin[2])) {
          continue;
        }
        $cabin = array($cabin[1], $cabin[4], trim($cabin[5] . ' ' . ($cabin[8] != 'NULL' ? $cabin[8] : '') . ' ' . ($cabin[10] != 'NULL' ? $cabin[10] : '')), $cabin[0], $cabin[20], $cabin[3]);
      }
      if ($cabin) {
        $cabins[] = $cabin;
      }
    }
    return $cabins;
  }

  protected function addModification($data)
  {
    $this->db->query(
      "
      INSERT INTO " . DB_PREFIX . "modification 
      SET code = '" . $this->db->escape($data['code']) . "', name = '" . $this->db->escape($data['name']) . "', 
      author = '" . $this->db->escape($data['author']) . "', version = '" . $this->db->escape($data['version']) . "', 
      link = '" . $this->db->escape($data['link']) . "', xml = '" . $this->db->escape($data['xml']) . "', 
      status = '" . (int) $data['status'] . "', date_added = NOW()
      "
    );
  }

  protected function updateModification($data)
  {
    $this->db->query(
      "
      UPDATE " . DB_PREFIX . "modification 
      SET code = '" . $this->db->escape($data['code']) . "', name = '" . $this->db->escape($data['name']) . "', 
      author = '" . $this->db->escape($data['author']) . "', version = '" . $this->db->escape($data['version']) . "', 
      link = '" . $this->db->escape($data['link']) . "', xml = '" . $this->db->escape($data['xml']) . "', 
      status = '" . (int) $data['status'] . "', date_added = NOW() 
      WHERE modification_id = " . $data['modification_id']
    );
  }

  protected function removeModification($id)
  {
    $this->db->query(
      "
      REMOVE FROM " . DB_PREFIX . "modification 
      WHERE modification_id = " . $id
    );
  }

  protected function loadModificationXML()
  {
    $file =  DIR_SYSTEM . 'library/omnivalt_lib/base_install.xml';
    $xml = file_get_contents($file);

    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->loadXml($xml);

    $code = $dom->getElementsByTagName('code')->item(0)->nodeValue;
    $name = $dom->getElementsByTagName('name')->item(0)->nodeValue;
    $version = $dom->getElementsByTagName('version')->item(0)->nodeValue;
    $author = $dom->getElementsByTagName('author')->item(0)->nodeValue;
    $link = $dom->getElementsByTagName('link')->item(0)->nodeValue;
    $status = '1';

    return compact('code', 'name', 'version', 'author', 'link', 'status', 'xml');
  }

  // Install modification xml if this was uploaded instead of installed using module manager
  public function installModification()
  {
    $data = $this->loadModificationXML();
    $existing = $this->getModificationByCode($data['code']);
    if ($existing) {
      if (version_compare($existing["version"], $data['version'], ">=")) {
        return false; // no need to update databse
      }
      // we are installing newer version
      $data['modification_id'] = $existing['modification_id'];
      $this->updateModification($data);
      return true;
    }
    // doesnt have our modification
    $this->addModification($data);
    return true;
  }

  protected function getModificationByCode($code)
  {
    $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "modification WHERE code = '" . $this->db->escape($code) . "'");

    return $query->row;
  }
}

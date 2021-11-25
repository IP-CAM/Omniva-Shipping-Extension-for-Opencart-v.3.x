<?php

/**
 * Omnivalt shipping extension general controller
 * for settings enable/disable/install module
 * @version 1.1.0
 * @author mijora.lt
 */
class ControllerExtensionShippingOmnivalt extends Controller
{
  private $error = array();
  private $defaulCodename = 'Omnivalt Mod Default'; // used in older versions with opencart events
  private $version = '1.1.2';

  public function install()
  {
    // Add aditional columns into order table
    $sql = "
      ALTER TABLE `" . DB_PREFIX . "order` 
      ADD `labelsCount` INT NOT NULL DEFAULT '1',
      ADD `omnivaWeight` FLOAT NOT NULL DEFAULT '1',
      ADD `cod_amount` FLOAT DEFAULT 0;
      ";
    $this->db->query($sql);
    // Add order_omniva table to database
    $sql2 = "
      CREATE TABLE `" . DB_PREFIX . "order_omniva` (
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

    // Set generated manifest counter
    $this->load->model('setting/setting');
    $this->model_setting_setting->editSetting('omniva', array('omniva_manifest' => 0));

    // Install modification file
    $this->updateXMLFile();
  }

  public function uninstall()
  {
    // Remove modification to order table
    $sql = "
      ALTER TABLE `" . DB_PREFIX . "order`
      DROP COLUMN labelsCount,
      DROP COLUMN omnivaWeight,
      DROP COLUMN cod_amount;
      ";
    $this->db->query($sql);

    // Remove order_omniva table (all not exported information will be lost)
    $sql2 = "DROP TABLE `" . DB_PREFIX . "order_omniva`";
    $this->db->query($sql2);

    // Remove modification file
    $this->removeModificationXML(DIR_SYSTEM . 'omnivalt_base.ocmod.xml');

    // Remove event hooks (in case module is uninstalled after update but before opening its settings)
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
    $data = array();

    // remove old events
    $data['old_events_removed_msg'] = $this->removeOldEvents() ? $this->language->get('old_events_removed_msg') : false;

    if (isset($this->request->get['fixdb']) && $this->validate()) {
      $this->fixDBTables();
      $this->response->redirect($this->url->link('extension/shipping/omnivalt', 'user_token=' . $this->session->data['user_token'], true));
    }

    if (isset($this->request->get['fixxml']) && $this->validate()) {
      $this->removeXMLFromDB();
      $this->updateXMLFile();
      $this->session->data['success'] = $this->language->get('xml_updated');
      $this->response->redirect($this->url->link('marketplace/modification', 'user_token=' . $this->session->data['user_token'], true));
    }

    // Saving and validation
    if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate() && $this->validateSettings()) {
      $this->model_setting_setting->editSetting('shipping_omnivalt', $this->request->post);
      $this->session->data['success'] = $this->language->get('settings_saved');
      if (empty($this->request->post['download'])) {
        if (!empty($this->request->post['save_exit'])) {
          $this->response->redirect($this->url->link('marketplace/extension', 'type=shipping&user_token=' . $this->session->data['user_token'], 'SSL'));
        }
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

    $data['success'] = false;
    if (isset($this->session->data['success'])) {
      $data['success'] = $this->session->data['success'];
      unset($this->session->data['success']);
    }

    // Load translation strings
    foreach (array(
      'cron_url', 'heading_title', 'text_edit', 'text_enabled', 'text_disabled', 'text_yes', 'text_no', 'text_none', 'text_parcel_terminal',
      'text_courier', 'text_sorting_center', 'entry_url', 'entry_user', 'entry_password', 'entry_service', 'entry_pickup_type', 'entry_company',
      'entry_bankaccount', 'entry_pickupstart', 'entry_pickupfinish', 'entry_cod', 'entry_status', 'entry_sort_order', 'entry_parcel_terminal_price',
      'entry_courier_price', 'entry_terminals', 'button_save', 'button_save_exit', 'button_cancel', 'button_download', 'entry_sender_name',
      'entry_sender_address', 'entry_sender_city', 'entry_sender_postcode', 'entry_sender_phone', 'entry_sender_country_code', 'button_update_terminals',
      'button_save_exit', 'webservice_header', 'sender_header', 'services_header', 'prices_header', 'cod_header', 'pickup_header', 'terminals_header',
      'option_lt', 'option_lv', 'option_ee', 'entry_tax_class', 'db_fix_notify', 'button_fix_db', 'xml_fix_notify', 'button_fix_xml',
      'entry_label_print_type', 'option_label_print_type_1', 'option_label_print_type_2'
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
      'label_print_type',
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
      'sort_order',
      // Tax class ID: 0 to disable
      'tax_class_id'
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
    if (!$data['shipping_omnivalt_terminals']) {
      $data['shipping_omnivalt_terminals'] = array();
    }
    $data['terminal_count'] = $this->language->get('terminal_count');
    if (isset($data['shipping_omnivalt_terminals'])) {
      $data['terminal_count'] = count($data['shipping_omnivalt_terminals']);
    }

    $data['cron_link'] = HTTPS_CATALOG . 'index.php?route=extension/module/omnivalt/update_terminals';

    // DB check
    $data['db_check'] = $this->checkDBTables();
    $data['db_fix_url'] = $this->url->link('extension/shipping/omnivalt', 'user_token=' . $this->session->data['user_token'] . '&fixdb', true);
    // XML check
    $data['xml_check'] = $this->checkModificationVersion();
    $data['xml_fix_url'] = $this->url->link('extension/shipping/omnivalt', 'user_token=' . $this->session->data['user_token'] . '&fixxml', true);

    // Get all tax classes information
    $this->load->model('localisation/tax_class');
    $data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();

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

  protected function checkModificationVersion()
  {
    $source_xml = DIR_SYSTEM . 'library/omnivalt_lib/omnivalt_base.ocmod.xml';
    $xml = DIR_SYSTEM . 'omnivalt_base.ocmod.xml';

    return version_compare($this->getModXMLVersion($source_xml), $this->getModXMLVersion($xml), '>');
  }

  protected function getModXMLVersion($file)
  {
    if (!is_file($file)) {
      return null;
    }
    $xml = file_get_contents($file);

    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->loadXml($xml);

    $version = $dom->getElementsByTagName('version')->item(0)->nodeValue;

    return $version;
  }

  protected function updateXMLFile()
  {
    $this->copyModificationXML(
      DIR_SYSTEM . 'library/omnivalt_lib/omnivalt_base.ocmod.xml',
      DIR_SYSTEM . 'omnivalt_base.ocmod.xml'
    );
  }

  protected function copyModificationXML($src_file, $destination_file)
  {
    $this->removeModificationXML($destination_file);

    if(!copy($src_file, $destination_file)) {
      file_put_contents(DIR_LOGS . 'omniva.log', date('Y-m-d H:i:s') . ' - Failed to copy modification file. Check error.log');
    }
  }

  protected function removeXMLFromDB()
  {
    $query = $this->db->query("SELECT modification_id FROM `" . DB_PREFIX . "modification` WHERE code = 'omnivaltshipping'");
    if ($result = $query->row) {
      $this->db->query("DELETE FROM `" . DB_PREFIX . "modification` WHERE modification_id = " . $result['modification_id']);
    }
  }

  protected function removeModificationXML($target_file = false)
  {
    if (is_file($target_file)) {
      unlink($target_file);
    }
  }

  protected function checkDBTables()
  {
    $result = array();
    if (version_compare(VERSION, '3.0.0', '>=')) {
      $session_table = $this->db->query("DESCRIBE `" . DB_PREFIX . "session`")->rows;
      foreach ($session_table as $col) {
        if (strtolower($col['Field']) != 'data') {
          continue;
        }
        if (strtolower($col['Type']) == 'text') {
          // needs to be MEDIUMTEXT or LONGTEXT
          $result['session'] = array(
            'field' => $col['Field'],
            'fix' => 'MEDIUMTEXT'
          );
        }
        break;
      }
    }

    return $result;
  }

  protected function fixDBTables()
  {
    $db_check = $this->checkDBTables();
    if (!$db_check) {
      return; // nothing to fix
    }

    foreach ($db_check as $table => $data) {
      $this->db->query("ALTER TABLE `" . DB_PREFIX . $table . "` MODIFY `" . $data['field'] . "` " . $data['fix'] . ";");
    }
  }

  // removes old events, as those were moved back into ocmod.xml file
  protected function removeOldEvents()
  {
    if (!$this->config->get('omnivalt_events_removed')) {
      $this->load->model('setting/event');
      $this->model_setting_event->deleteEventByCode($this->defaulCodename);
      $this->model_setting_setting->editSetting('omnivalt_events', array('omnivalt_events_removed' => 1));
      return true;
    }
    return false;
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
    $terminals_json_file_dir = DIR_DOWNLOAD . "omniva_terminals.json";
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
      return ['failed' => 'Requested terminal list was empty, aborting terminal list update'];
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
    $fp = fopen(DIR_DOWNLOAD . "omniva_terminals.json", "w");
    fwrite($fp, json_encode($terminals));
    fclose($fp);

    $this->csvTerminal();
    return ['success' => 'Terminals updated'];
  }

  private function fetchURL($url)
  {
    $ch = curl_init(trim($url));
    if (!$ch) {
      return ['failed' => 'Cant create curl'];
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
      return ['failed' => 'Cannot fetch update from ' . curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) . ': ' . curl_getinfo($ch, CURLINFO_HTTP_CODE)];
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
      return ['failed' => 'Terminal CSV file is in wrong format'];
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
        // skip post offices (TYPE=1)
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
}

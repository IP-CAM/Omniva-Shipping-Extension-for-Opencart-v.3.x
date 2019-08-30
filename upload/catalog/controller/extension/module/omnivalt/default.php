<?php
class ControllerExtensionModuleOmnivaltDefault extends Controller
{
  /**
   * 
   * FUNCTIONS FOR EVENTS
   * 
   */

  // targetting checkout save function, after shipping coutry is changed updates omniva terminal list in session
  // fixes issue where checkout expects to find terminal thats not loaded into session.
  // preferably we should keep only one country worth of terminals inside session at all times.
  public function fixSession($route, $data)
  {
    if (isset($this->request->post['shipping_country_id'])) {
      $country_id = intval($this->request->post['shipping_country_id']);
      if (isset($this->session->data['omniva_country_loaded'])) {
        $omniva_loaded_country = $this->session->data['omniva_country_loaded'];
      } else {
        $omniva_loaded_country = '';
      }
      if ($omniva_loaded_country != $country_id) {
        // get country information
        $this->load->model('localisation/country');
        $country_info = $this->model_localisation_country->getCountry($country_id);
        // update terminal selection in session
        $this->load->model('extension/shipping/omnivalt');
        $quote = $this->model_extension_shipping_omnivalt->getQuote($country_info);
        $this->session->data['shipping_methods']['omnivalt'] = $quote;
      }
    }
  }

  public function changeTemplate(&$route, &$data)
  {
    $route = str_replace(
      'checkout/shipping_method',
      'extension/module/omnivalt/default/shipping_method',
      $route
    );
  }

  public function shippingMethodsView($route, &$data)
  {
    // prepare translation for omniva-map
    $this->load->language('extension/shipping/omnivalt');
    $data['omniva_map_translation'] = [
      'modal_header' => $this->language->get('text_omniva_map_head'),
      'terminal_addresses' => $this->language->get('text_omniva_terminal_address'),
      'text_select_terminal' => $this->language->get('text_select_omn_terminal'),
      'text_search_placeholder' => $this->language->get('text_omniva_search'),
      'not_found' => $this->language->get('text_omniva_not_found'),
      'text_enter_address' => $this->language->get('text_omniva_search'),
      'text_show_in_map' => $this->language->get('text_omniva_show_map'),
      'text_show_more' => $this->language->get('text_omniva_show_more'),
      'text_back_to_list' => $this->language->get('text_omniva_back_to_list')
    ];

    // some checkouts messes up selected shipping method
    if (isset($this->session->data['shipping_method'])) {
      $data['session_shipping_code'] = $this->session->data['shipping_method']['code'];
    } else {
      $data['session_shipping_code'] = "00000"; // nonexistant code
    }

    // get shipping methods country
    $data['omniva_country'] = $this->session->data['shipping_address']['iso_code_2'];

    // load terminal array
    $this->load->model('extension/shipping/omnivalt');
    $data['omniva_locations'] = $this->model_extension_shipping_omnivalt->getTerminalForMap(
      $this->session->data['shipping_address']['iso_code_2']
    );

    // index shipping methods (some themes/modules removes string keys)
    foreach ($this->session->data['shipping_methods'] as $key => $value) {
      $data['methods_mapping'][] = $key;
      $data['methods_mapping'][$key] = $key; // for default
    }
  }
}

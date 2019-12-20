<?php
class ControllerExtensionModuleOmnivaltDefault extends Controller
{
  /**
   * 
   * FUNCTIONS FOR EVENTS
   * 
   */

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
      'search_bar_title' => $this->language->get('text_omniva_terminal_address'),
      'select_terminal' => $this->language->get('text_select_omn_terminal'),
      'search_bar_placeholder' => $this->language->get('text_omniva_search'),
      'place_not_found' => $this->language->get('text_omniva_not_found'),
      'show_on_map' => $this->language->get('text_omniva_show_map'),
      'show_more' => $this->language->get('text_omniva_show_more'),
      'search_back_to_list' => $this->language->get('text_omniva_back_to_list'),
      'terminal_not_selected' => $this->language->get('text_omniva_no_terminal'),
    ];

    // some checkouts messes up selected shipping method
    $data['session_shipping_code'] = "00000"; // nonexistant code
    if (isset($this->session->data['shipping_method'])) {
      $data['session_shipping_code'] = $this->session->data['shipping_method']['code'];
    }

    // get shipping methods country
    if (isset($this->session->data['shipping_address']['iso_code_2']))
      $data['omniva_country'] = $this->session->data['shipping_address']['iso_code_2'];
    else
      $data['omniva_country'] = 'LT';

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

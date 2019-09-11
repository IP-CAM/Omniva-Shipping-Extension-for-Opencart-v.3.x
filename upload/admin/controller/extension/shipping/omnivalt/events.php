<?php
/*
 * Handles admin side events
 * @ProxyController
 */
class ControllerExtensionShippingOmnivaltEvents extends Controller
{
  public function index()
  { }
  
  public function menu($eventRoute, &$data, &$output)
  {
    $omniva = array();
    $this->load->language('extension/shipping/omnivalt');
    if ($this->user->hasPermission('access', 'extension/shipping/omnivalt/manifest')) {
      $omniva[] = array(
        'name'     => $this->language->get('menu_manifest'),
        'href'     => $this->url->link('extension/shipping/omnivalt/manifest', 'user_token=' . $this->session->data['user_token'], true),
        'children' => array()
      );
    }
    if ($this->user->hasPermission('access', 'extension/shipping/omnivalt')) {
      $omniva[] = array(
        'name'     => $this->language->get('menu_settings'),
        'href'     => $this->url->link('extension/shipping/omnivalt', 'user_token=' . $this->session->data['user_token'], true),
        'children' => array()
      );
    }
    if ($this->user->hasPermission('access', 'extension/shipping/omnivalt')) {
      for ($i = 0; $i < count($data['menus']); $i++) {
        if ($data['menus'][$i]['id'] == 'menu-extension') {
          $data['menus'][$i]['children'][] = array(
            'name'     => $this->language->get('menu_head'),
            'href'     => '',
            'children' => $omniva
          );
          break;
        }
      }
    }
  }

  public function orderList($eventRoute, &$data, &$output)
  {
    $data['omnivalt_label'] = $this->url->link('extension/shipping/omnivalt/prints/labels', 'user_token=' . $this->session->data['user_token'], true);
    $this->load->language('extension/shipping/omnivalt');
    $data['generate_labels']   = $this->language->get('generate_labels');
    $data['text_manifest']   = $this->language->get('print_manifest');
    $data['omnivalt_manifest'] = $this->url->link('extension/shipping/omnivalt/prints/manifest', 'user_token=' . $this->session->data['user_token'], true);
  }

  public function orderInfo($eventRoute, &$data, &$output)
  {
    $data['omnivalt_label'] = $this->url->link('extension/shipping/omnivalt/prints/labels', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . (int) $this->request->get['order_id'], true);
    $data['omnivalt_label_print'] = $this->url->link('extension/shipping/omnivalt/prints/labelsprint', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . (int) $this->request->get['order_id'], true);
  }
}

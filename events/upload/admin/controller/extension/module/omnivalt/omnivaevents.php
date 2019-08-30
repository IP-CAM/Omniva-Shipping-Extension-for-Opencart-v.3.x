<?php
class ControllerExtensionModuleOmnivaltOmnivaEvents extends Controller
{

  private $codename = 'OmnivaTest';

  public function install()
  {
    $this->load->model('setting/event');
    $this->model_setting_event->deleteEventByCode($this->codename);
    $this->model_setting_event->addEvent(
      $this->codename,
      'admin/view/common/column_left/before',
      'extension/module/omnivalt/omnivaevents/menu');
  }

  public function uninstall()
  {
    $this->load->model('setting/event');
    $this->model_setting_event->deleteEventByCode($this->codename);
  }

  public function menu($eventRoute, &$data, &$output)
  {
    $omniva = array();
    $this->load->language('extension/shipping/omnivalt');
    if ($this->user->hasPermission('access', 'extension/extension/omnivalt_manifest')) {
      $omniva[] = array(
        'name'     => $this->language->get('menu_manifest'),
        'href'     => $this->url->link('extension/extension/omnivalt_manifest', 'user_token=' . $this->session->data['user_token'], true),
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

  public function index() {
    $data = [];
    $this->response->setOutput($this->load->view('extension/omnivalt/omnivalt', $data));
  }
}

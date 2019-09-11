window.addEventListener("DOMContentLoaded", function(e) {
  var $statusInput = $('#input-status');
  var $mainPanel = $('#settings-panel');
  var $codInput = $('#input-cod');
  var $codPanel = $('#cod-settings');
  //$mainPanel.collapse();
  
  $statusInput.on('change', function(e) {
    if ($statusInput.val() == 1) {
      $mainPanel.collapse('show');
      return;
    }
    $mainPanel.collapse('hide');
  });

  $codInput.on('change', function(e) {
    if ($codInput.val() == 1) {
      $codPanel.collapse('show');
      return;
    }
    $codPanel.collapse('hide');
  });

  $statusInput.trigger('change');
  $codInput.trigger('change');
});
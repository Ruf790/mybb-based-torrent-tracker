<?

  define ('L_VERSION', '0.2');
  require_once 'global.php';
  gzip ();

  include_once INC_PATH . '/functions_security.php';
  $lang->load ('links');
  stdhead ($lang->links['head']);
  echo $lang->links['info'];
  stdfoot ();
?>

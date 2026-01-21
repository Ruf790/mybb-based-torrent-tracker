<?



  require_once 'global.php';
  gzip ();
 
  $lang->load ('formats');
  stdhead ($lang->formats['head']);
  echo $lang->formats['info'];
  stdfoot ();
?>

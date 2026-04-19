<?php
  $config['database']['type'] = 'mysqli';
  $config['database']['database'] = 'novtracker';
  $config['database']['table_prefix'] = '';
  $config['database']['hostname'] = 'localhost';
  $config['database']['username'] = 'root';
  $config['database']['password'] = 'megarave1995';
  $config['database']['encoding'] = 'utf8';
  $config['cache_store'] = 'files';
  $config['super_admins'] = '1';
  $config['log_pruning'] = array(
    'admin_logs' => 365,
    'mod_logs' => 365,
    'task_logs' => 30,
    'mail_logs' => 180,
    'user_mail_logs' => 180,
    'promotion_logs' => 180
  );

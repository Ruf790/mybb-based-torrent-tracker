<?php
/**
 * Database configuration
 *
 * Please see the MyBB Docs for advanced
 * database configuration for larger installations
 * https://docs.mybb.com/
 */

  $config['database']['type'] = 'mysqli';
  $config['database']['database'] = '';
  $config['database']['table_prefix'] = '';

  $config['database']['hostname'] = '';
  $config['database']['username'] = '';
  $config['database']['password'] = '';
  $config['database']['encoding'] = 'utf8';
  $config['cache_store'] = 'files';
  
  $config['super_admins'] = '1';
  
  $config['log_pruning'] = array(
	'admin_logs' => 365, // Administrator logs
	'mod_logs' => 365, // Moderator logs
	'task_logs' => 30, // Scheduled task logs
	'mail_logs' => 180, // Mail error logs
	'user_mail_logs' => 180, // User mail logs
	'promotion_logs' => 180 // Promotion logs
  );
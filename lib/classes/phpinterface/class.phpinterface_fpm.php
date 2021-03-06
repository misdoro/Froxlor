<?php

/**
 * This file is part of the Froxlor project.
 * Copyright (c) 2010 the Froxlor Team (see authors).
 *
 * For the full copyright and license information, please view the COPYING
 * file that was distributed with this source code. You can also view the
 * COPYING file online at http://files.froxlor.org/misc/COPYING.txt
 *
 * @copyright  (c) the authors
 * @author     Michael Kaufmann <mkaufmann@nutime.de>
 * @author     Froxlor team <team@froxlor.org> (2010-)
 * @license    GPLv2 http://files.froxlor.org/misc/COPYING.txt
 * @package    Cron
 *
 * @link       http://www.nutime.de/
 * @since      0.9.16
 *
 */

class phpinterface_fpm
{
	/**
	 * Database handler
	 * @var object
	 */
	private $_db = false;

	/**
	 * Settings array
	 * @var array
	 */
	private $_settings = array();

	/**
	 * Domain-Data array
	 * @var array
	 */
	private $_domain = array();

	/**
	 * Admin-Date cache array
	 * @var array
	 */
	private $_admin_cache = array();

	/**
	 * defines what can be used for pool-config from php.ini
	 * @var array
	 */
	private $_ini = array(
		'php_value' => array(
			'error_reporting',
			'max_execution_time',
			'include_path',
			'upload_max_filesize',
			'log_errors_max_len'
		),
		'php_flag' => array(
			'short_open_tag',
			'asp_tags',
			'display_errors',
			'display_startup_errors',
			'log_errors',
			'track_errors',
			'html_errors',
			'magic_quotes_gpc',
			'magic_quotes_runtime',
			'magic_quotes_sybase'
		),
		'php_admin_value' => array(
			'precision',
			'output_buffering',
			'disable_functions',
			'max_input_time',
			'memory_limit',
			'post_max_size',
			'variables_order',
			'gpc_order',
			'date.timezone'
		),
		'php_admin_flag' => array(
			'allow_call_time_pass_reference',
			'allow_url_fopen',
			'cgi.force_redirect',
			'enable_dl',
			'expose_php',
			'ignore_repeated_errors',
			'ignore_repeated_source',
			'report_memleaks',
			'register_argc_argv',
			'file_uploads',
			'allow_url_fopen'
		)
	);

	/**
	 * main constructor
	 */
	public function __construct($db, $settings, $domain)
	{
		$this->_db = $db;
		$this->_settings = $settings;
		$this->_domain = $domain;
	}

	public function createConfig($phpconfig)
	{
		$fh = @fopen($this->getConfigFile(), 'w');
		if($fh)
		{
			$fpm_pm = $this->_settings['phpfpm']['pm'];
			$fpm_children = (int)$this->_settings['phpfpm']['max_children'];
			$fpm_start_servers = (int)$this->_settings['phpfpm']['start_servers'];
			$fpm_min_spare_servers = (int)$this->_settings['phpfpm']['min_spare_servers'];
			$fpm_max_spare_servers = (int)$this->_settings['phpfpm']['max_spare_servers'];
			$fpm_requests = (int)$this->_settings['phpfpm']['max_requests'];

			if($fpm_children == 0) {
				$fpm_children = 1;
			}

			$fpm_config = ';PHP-FPM configuration for "'.$this->_domain['domain'].'" created on ' . date("Y.m.d H:i:s") . "\n";
			$fpm_config.= '['.$this->_domain['domain'].']'."\n";
			$fpm_config.= 'listen = '.$this->getSocketFile()."\n";
			if($this->_domain['loginname'] == 'froxlor.panel')
			{
				$fpm_config.= 'listen.owner = '.$this->_domain['guid']."\n";
				$fpm_config.= 'listen.group = '.$this->_domain['guid']."\n";
			}
			else
			{
				$fpm_config.= 'listen.owner = '.$this->_domain['loginname']."\n";
				$fpm_config.= 'listen.group = '.$this->_domain['loginname']."\n";
			}
			$fpm_config.= 'listen.mode = 0666'."\n";

			if($this->_domain['loginname'] == 'froxlor.panel')
			{
				$fpm_config.= 'user = '.$this->_domain['guid']."\n";
				$fpm_config.= 'group = '.$this->_domain['guid']."\n";
			}
			else
			{
				$fpm_config.= 'user = '.$this->_domain['loginname']."\n";
				$fpm_config.= 'group = '.$this->_domain['loginname']."\n";
			}

			$fpm_config.= 'pm = '.$fpm_pm."\n";
			$fpm_config.= 'pm.max_children = '.$fpm_children."\n";
			if($fpm_pm == 'dynamic' || $fpm_pm == 'ondemand') {
				// failsafe, refs #955
				if ($fpm_start_servers < $fpm_min_spare_servers) {
					$fpm_start_servers = $fpm_min_spare_servers;
				}
				if ($fpm_start_servers > $fpm_max_spare_servers) {
					$fpm_start_servers = $fpm_start_servers - (($fpm_start_servers - $fpm_max_spare_servers) + 1);
				}
				$fpm_config.= 'pm.start_servers = '.$fpm_start_servers."\n";
				$fpm_config.= 'pm.min_spare_servers = '.$fpm_min_spare_servers."\n";
				$fpm_config.= 'pm.max_spare_servers = '.$fpm_max_spare_servers."\n";
			}
			$fpm_config.= 'pm.max_requests = '.$fpm_requests."\n";

			$fpm_config.= ';chroot = '.makeCorrectDir($this->_domain['documentroot'])."\n";

			$tmpdir = makeCorrectDir($this->_settings['phpfpm']['tmpdir'] . '/' . $this->_domain['loginname'] . '/');
			if(!is_dir($tmpdir))
			{
				$this->getTempDir();
			}
			//$slowlog = makeCorrectFile($this->_settings['system']['logfiles_directory'] . $this->_domain['loginname'] . '/php-fpm_slow.log');

			$fpm_config.= 'env[TMP] = '.$tmpdir."\n";
			$fpm_config.= 'env[TMPDIR] = '.$tmpdir."\n";
			$fpm_config.= 'env[TEMP] = '.$tmpdir."\n";

			$fpm_config.= 'php_admin_value[sendmail_path] = /usr/sbin/sendmail -t -i -f '.$this->_domain['email']."\n";
			if($this->_domain['loginname'] != 'froxlor.panel')
			{
				if($this->_domain['openbasedir'] == '1')
				{
					$openbasedir = '';
					$_phpappendopenbasedir = '';
					$_custom_openbasedir = explode(':', $this->_settings['phpfpm']['peardir']);
					foreach($_custom_openbasedir as $cobd)
					{
						$_phpappendopenbasedir .= appendOpenBasedirPath($cobd);
					}

					$_custom_openbasedir = explode(':', $this->_settings['system']['phpappendopenbasedir']);
					foreach($_custom_openbasedir as $cobd)
					{
						$_phpappendopenbasedir .= appendOpenBasedirPath($cobd);
					}

					if($this->_domain['openbasedir_path'] == '0' && strstr($this->_domain['documentroot'], ":") === false)
					{
						$openbasedir = appendOpenBasedirPath($this->_domain['documentroot'], true);
					}
					else
					{
						$openbasedir = appendOpenBasedirPath($this->_domain['customerroot'], true);
					}

					$openbasedir .= appendOpenBasedirPath($this->getTempDir());
					$openbasedir .= $_phpappendopenbasedir;

					$openbasedir = explode(':', $openbasedir);
					$clean_openbasedir = array();
					foreach($openbasedir as $number => $path)
					{
						if(trim($path) != '/')
						{
							$clean_openbasedir[] = makeCorrectDir($path);
						}
					}
					$openbasedir = implode(':', $clean_openbasedir);

					$fpm_config.= 'php_admin_value[open_basedir] = ' . $openbasedir . "\n";
				}
			}
			$fpm_config.= 'php_admin_value[session.save_path] = ' . makeCorrectDir($this->_settings['phpfpm']['tmpdir'] . '/' . $this->_domain['loginname'] . '/') . "\n";
			$fpm_config.= 'php_admin_value[upload_tmp_dir] = ' . makeCorrectDir($this->_settings['phpfpm']['tmpdir'] . '/' . $this->_domain['loginname'] . '/') . "\n";

			$admin = $this->_getAdminData($this->_domain['adminid']);
			$php_ini_variables = array(
					'SAFE_MODE' => 'Off', // keep this for compatibility, just in case
					'PEAR_DIR' => $this->_settings['system']['mod_fcgid_peardir'],
					'TMP_DIR' => $this->getTempDir(),
					'CUSTOMER_EMAIL' => $this->_domain['email'],
					'ADMIN_EMAIL' => $admin['email'],
					'DOMAIN' => $this->_domain['domain'],
					'CUSTOMER' => $this->_domain['loginname'],
					'ADMIN' => $admin['loginname']
			);

			$phpini = replace_variables($phpconfig['phpsettings'], $php_ini_variables);
			$phpini_array = explode("\n", $phpini);

			$fpm_config.= "\n\n";
			foreach ($phpini_array as $inisection) {
				$is = explode("=", $inisection);
				foreach ($this->_ini as $sec => $possibles) {
					if (in_array(trim($is[0]), $possibles)) {
						$fpm_config.= $sec.'['.trim($is[0]).'] = ' . trim($is[1]) . "\n";
					}
				}
			}

			fwrite($fh, $fpm_config, strlen($fpm_config));
			fclose($fh);
		}
	}

	/**
	 * this is done via createConfig as php-fpm defines
	 * the ini-values/flags in its pool-config
	 *
	 * @param string $phpconfig
	 */
	public function createIniFile($phpconfig)
	{
		return;
	}

	/**
	 * fpm-config file
	 *
	 * @param boolean $createifnotexists create the directory if it does not exist
	 *
	 * @return string the full path to the file
	 */
	public function getConfigFile($createifnotexists = true)
	{
		$configdir = makeCorrectDir($this->_settings['phpfpm']['configdir']);
		$config = makeCorrectFile($configdir.'/'.$this->_domain['domain'].'.conf');

		if(!is_dir($configdir) && $createifnotexists)
		{
			safe_exec('mkdir -p ' . escapeshellarg($configdir));
		}

		return $config;
	}

	/**
	 * return path of fpm-socket file
	 *
	 * @param boolean $createifnotexists create the directory if it does not exist
	 *
	 * @return string the full path to the socket
	 */
	public function getSocketFile($createifnotexists = true)
	{
		$socketdir = makeCorrectDir('/var/run/'.$this->_settings['system']['webserver'].'/');
		$socket = makeCorrectFile($socketdir.'/'.$this->_domain['loginname'].'-'.$this->_domain['domain'].'-php-fpm.socket');

		if(!is_dir($socketdir) && $createifnotexists)
		{
			safe_exec('mkdir -p '.escapeshellarg($socketdir));
			safe_exec('chown -R '.$this->_settings['system']['httpuser'].':'.$this->_settings['system']['httpgroup'].' '.escapeshellarg($socketdir));
		}

		return $socket;
	}

	/**
	 * fpm-temp directory
	 *
	 * @param boolean $createifnotexists create the directory if it does not exist
	 *
	 * @return string the directory
	 */
	public function getTempDir($createifnotexists = true)
	{
		$tmpdir = makeCorrectDir($this->_settings['phpfpm']['tmpdir'] . '/' . $this->_domain['loginname'] . '/');

		if(!is_dir($tmpdir) && $createifnotexists)
		{
			safe_exec('mkdir -p ' . escapeshellarg($tmpdir));
			safe_exec('chown -R ' . $this->_domain['guid'] . ':' . $this->_domain['guid'] . ' ' . escapeshellarg($tmpdir));
			safe_exec('chmod 0750 ' . escapeshellarg($tmpdir));
		}

		return $tmpdir;
	}

	/**
 	 * fastcgi-fakedirectory directory
 	 *
 	 * @param boolean $createifnotexists create the directory if it does not exist
 	 *
 	 * @return string the directory
 	 */
 	public function getAliasConfigDir($createifnotexists = true)
 	{
	    // ensure default...
	    if (!isset($this->_settings['phpfpm']['aliasconfigdir'])) {
	      $this->_settings['phpfpm']['aliasconfigdir'] = '/var/www/php-fpm';
	    }

 		$configdir = makeCorrectDir($this->_settings['phpfpm']['aliasconfigdir'] . '/' . $this->_domain['loginname'] . '/' . $this->_domain['domain'] . '/');
 		if(!is_dir($configdir) && $createifnotexists)
 		{
 			safe_exec('mkdir -p ' . escapeshellarg($configdir));
 			safe_exec('chown ' . $this->_domain['guid'] . ':' . $this->_domain['guid'] . ' ' . escapeshellarg($configdir));
 		}

 		return $configdir;
 	}

	/**
	 * return the admin-data of a specific admin
	 *
	 * @param int $adminid id of the admin-user
	 *
	 * @return array
	 */
	private function _getAdminData($adminid) {
		$adminid = intval($adminid);

		if (!isset($this->_admin_cache[$adminid])) {
			$this->_admin_cache[$adminid] = $this->_db->query_first(
				"SELECT `email`, `loginname` FROM `" . TABLE_PANEL_ADMINS . "`
				WHERE `adminid` = " . (int)$adminid
			);
		}

		return $this->_admin_cache[$adminid];
	}
}

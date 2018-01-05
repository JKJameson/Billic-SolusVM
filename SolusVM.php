<?php
class SolusVM {
	public $settings = array(
		'orderform_vars' => array(
			'domain',
			'type',
			'nodegroup',
			'template',
			'ips',
			'plan',
			'custommemory',
			'customdiskspace',
			'custombandwidth',
			'customcpu',
			'issuelicense',
			'internalip'
		) ,
		'description' => 'Automate the provisioning of VPS through SolusVM.',
	);
	public $ch; // curl handle
	function user_cp($array) {
		global $billic, $db;
		if (get_config('solusvm_force_ssl_validation') == 1) {
			$url = 'https://' . get_config('solusvm_master_ip') . ':5656';
		} else {
			$url = 'http://' . get_config('solusvm_master_ip') . ':5353';
		}
		if ($_GET['Action'] == 'LoginToSolusVM') {
			$a = $this->curl('client-key-login', array(
				'vserverid' => $array['service']['username'],
				'returnurl' => $_SERVER['HTTP_REFERER'],
				'forward' => 1,
			));
			if ($this->api_errors($a)) {
				err('client-key-login: ' . $a['statusmsg']);
			}
			$billic->redirect($url . '/auth.php?_a=' . urlencode($a['hasha']) . '&_b=' . urlencode($a['hashb']));
		}
		if ($_GET['Action'] == 'ChangeHostname') {
			if (isset($_POST['update'])) {
				if (empty($_POST['newhostname'])) {
					$billic->error('A new hostname is required');
				}
				if (empty($billic->errors)) {
					$a = $this->curl('vserver-hostname', array(
						'vserverid' => $array['service']['username'],
						'hostname' => $_POST['newhostname'],
					));
					if ($this->api_errors($a)) {
						err('vserver-hostname: ' . $a['statusmsg']);
					}
					$billic->status = 'updated';
				}
			}
			echo '<h2>Change Hostname</h2>';
			$billic->show_errors();
			echo '<form class="form-inline" method="POST"><div class="form-group"><label for="newhostname">New Hostname:</label><input type="text" name="newhostname" id="newhostname" class="form-control" placeholder="' . safe($array['service']['domain']) . '"></div><input type="submit" name="update" class="btn btn-success" value="Change &raquo;"></form>';
			return;
		}
		if ($_GET['Action'] == 'ChangeRootPass') {
			if (isset($_POST['update'])) {
				if (empty($_POST['newpass'])) {
					$billic->error('A new password is required');
				}
				if (empty($billic->errors)) {
					$a = $this->curl('vserver-rootpassword', array(
						'vserverid' => $array['service']['username'],
						'rootpassword' => $_POST['newpass'],
					));
					if ($this->api_errors($a)) {
						err('vserver-rootpassword: ' . $a['statusmsg']);
					}
					$db->q('UPDATE `services` SET `password` = ? WHERE `id` = ?', $billic->encrypt($_POST['newpass']) , $array['service']['id']);
					$billic->status = 'updated';
				}
			}
			echo '<h2>Change Root Password</h2>';
			$billic->show_errors();
			echo '<form class="form-inline" method="POST"><div class="form-group"><label for="newpass">New Password:</label><input type="password" name="newpass" id="newpass" class="form-control"></div><input type="submit" name="update" class="btn btn-success" value="Change &raquo;"></form>';
			return;
		}
		if ($_GET['Action'] == 'Console') {
			echo '<h2>Serial Console</h2>';
			$a = $this->curl('vserver-console', array(
				'vserverid' => $array['service']['username'],
				'access' => 'enable',
				'time' => 2,
			));
			if ($this->api_errors($a)) {
				err('vserver-console: ' . $a['statusmsg']);
			}
			echo '<table class="table table-striped">';
			echo '<tr><td width="150">IP:</td><td>' . safe($a['consoleip']) . '</td></tr>';
			echo '<tr><td>Port:</td><td>' . safe($a['consoleport']) . '</td></tr>';
			echo '<tr><td>Username:</td><td>' . safe($a['consoleusername']) . '</td></tr>';
			echo '<tr><td>Password:</td><td><kbd>' . safe($a['consolepassword']) . '</kbd></td></tr>';
			echo '<tr><td>Time Remaining:</td><td>' . safe($a['sessionexpire']) . ' seconds</td></tr>';
			echo '</table>';
			return;
		}
		if ($_GET['Action'] == 'Shutdown') {
			echo '<h2>Shutdown</h2>';
			$a = $this->curl('vserver-shutdown', array(
				'vserverid' => $array['service']['username'],
			));
			if ($this->api_errors($a)) {
				err('vserver-shutdown: ' . $a['statusmsg']);
			}
			echo '<div class="alert alert-success" role="alert">Shutdown command successful.</div>';
			return;
		}
		if ($_GET['Action'] == 'Boot') {
			echo '<h2>Boot</h2>';
			$a = $this->curl('vserver-boot', array(
				'vserverid' => $array['service']['username'],
			));
			if ($this->api_errors($a)) {
				err('vserver-boot: ' . $a['statusmsg']);
			}
			echo '<div class="alert alert-success" role="alert">Boot command successful.</div>';
			return;
		}
		if ($_GET['Action'] == 'Reboot') {
			echo '<h2>Reboot</h2>';
			$a = $this->curl('vserver-reboot', array(
				'vserverid' => $array['service']['username'],
			));
			if ($this->api_errors($a)) {
				err('vserver-reboot: ' . $a['statusmsg']);
			}
			echo '<div class="alert alert-success" role="alert">Reboot command successful.</div>';
			return;
		}
		if ($_GET['Action'] == 'Reinstall') {
			if (isset($_POST['reinstall'])) {
				if (empty($_POST['template'])) {
					$billic->error('A template is required');
				}
				if (empty($billic->errors)) {
					$a = $this->curl('vserver-rebuild', array(
						'vserverid' => $array['service']['username'],
						'template' => $_POST['template'],
					));
					if ($this->api_errors($a)) {
						err('vserver-rebuild: ' . $a['statusmsg']);
					}
					echo '<div class="alert alert-success" role="alert">Reinstall is now in progress. Please allow 10 mins for the installation to complete.</div>';
					return;
				}
			}
			echo '<h2>Reinstall Operating System</h2>';
			$billic->show_errors();
			echo '<div class="alert alert-danger" role="alert"><b>Warning:</b> A reinstall will delete ALL DATA inside your VPS!</div>';
			$info = $this->curl('vserver-info', array(
				'vserverid' => $array['service']['username'],
			));
			if ($this->api_errors($info)) {
				err('vserver-info: ' . $info['statusmsg']);
			}
			$a = $this->curl('listtemplates', array(
				'type' => $info['type'],
				'listpipefriendly' => 'true',
			));
			echo '<form class="form-inline" method="POST"><div class="form-group"><label for="template">Template:</label><select name="template" id="template" class="form-control">';
			$templates = explode(',', $a['templates']);
			foreach ($templates as $template) {
				if ($template == '--none--') {
					continue;
				}
				$template = explode('|', $template);
				echo '<option value="' . safe($template[0]) . '">' . safe($template[1]) . '</option>';
			}
			echo '</select></div><input type="submit" name="reinstall" class="btn btn-danger" value="Change &raquo;"></form>';
			return;
		}
		$info = $this->curl('vserver-info', array(
			'vserverid' => $array['service']['username'],
		));
		if ($this->api_errors($info)) {
			err('vserver-info: ' . $info['statusmsg']);
		}
		$infoall = $this->curl('vserver-infoall', array(
			'vserverid' => $array['service']['username'],
		));
		if ($this->api_errors($infoall)) {
			err('vserver-state: ' . $infoall['statusmsg']);
		}
		$hdd = explode(',', $infoall['hdd']);
		$hdd_percent = ceil($hdd[3]);
		if ($hdd_percent > 50) {
			$hdd_color = 'warning';
		} else if ($hdd_percent > 75) {
			$hdd_color = 'danger';
		} else {
			$hdd_color = 'success';
		}
		$memory = explode(',', $infoall['memory']);
		$memory_percent = ceil($memory[3]);
		if ($memory_percent > 50) {
			$memory_color = 'warning';
		} else if ($memory_percent > 75) {
			$memory_color = 'danger';
		} else {
			$memory_color = 'success';
		}
		$bandwidth = explode(',', $infoall['bandwidth']);
		$bandwidth_percent = ceil($bandwidth[3]);
		if ($bandwidth_percent > 50) {
			$bandwidth_color = 'warning';
		} else if ($bandwidth_percent > 75) {
			$bandwidth_color = 'danger';
		} else {
			$bandwidth_color = 'success';
		}
		echo '<div class="row">
   <div class="col-md-12">
      <div class="panel panel-default">
         <div class="panel-heading">
            <h3 class="panel-title">Dashboard</h3>
            <div class="btn-group pull-right">
               <a href="Action/LoginToSolusVM/" class="btn btn-success btn-xs">Login to SolusVM <i class="icon-arrow-right"></i></a>
            </div>
         </div>
         <div class="panel-body">
            <div class="row">
               <div class="col-md-6">
                  <table class="table table-bordered table-striped">
				     <tr>
					 	<td><b>Type:</b> ' . $info['type'] . '</td>
					 </tr>
                     <tr>
                        <td><b>Hostname:</b> ' . $info['hostname'] . ' <a href="Action/ChangeHostname/" class="btn btn-default btn-xs"><i class="icon-edit-write"></i> Change</a></td>
                     </tr>
                     <tr>
                        <td><b>IP Addresses:</b> ';
		$out = '';
		$ips = explode(', ', $infoall['ipaddresses']);
		foreach ($ips as $ip) {
			if ($ip == $infoall['mainipaddress']) {
				$out.= '<b>';
			}
			$out.= $ip;
			if ($ip == $infoall['mainipaddress']) {
				$out.= '</b>';
			}
			$out.= ', ';
		}
		echo substr($out, 0, -2);
		echo '</td>
					 </tr>	
                     <tr>
                        <td><b>Root Password:</b> <kbd>' . $billic->decrypt($array['service']['password']) . '</kbd> <a href="Action/ChangeRootPass/" class="btn btn-default btn-xs"><i class="icon-edit-write"></i> Change</a></td>
                     </tr>
                     <tr>
                        <td><b>OS Template:</b> ' . $info['template'] . '</td>
                     </tr>
                     <tr>
                        <td><b>Status:</b> <span class="label label-';
		if ($infoall['state'] == 'online') {
			echo 'success"><b>Online';
		} else {
			echo 'danger"><b>' . ucwords($infoall['state']);
		}
		echo '</b></span></td>
                     </tr>
                  </table>
                  <a href="Action/Console/" class="btn btn-primary" onClick="return confirm(\'Are you sure you want to enable SERIAL CONSOLE access?\')"><i class="icon-screen-desktop"></i> Console</a> ';
		if ($infoall['state'] == 'online') {
			echo '<a href="Action/Shutdown/" class="btn btn-warning" onClick="return confirm(\'Are you sure you want to SHUTDOWN?\')"><i class="icon-power-off"></i> Shutdown</a> ';
			echo '<a href="Action/Reboot/" class="btn btn-warning" onClick="return confirm(\'Are you sure you want to REBOOT?\')"><i class="icon-refresh"></i> Reboot</a> ';
		} else {
			echo '<a href="Action/Boot/" class="btn btn-success"><i class="icon-power-off"></i> Boot</a> ';
		}
		echo '<a href="Action/Reinstall/" class="btn btn-danger"><i class="icon-trash-bin"></i> Reinstall</a>	  
               </div>
               <div class="col-md-6">
                  <table class="table table-bordered table-striped">
                     <tr>
                        <td>CPU: <span class="label label-info">' . $info['cpus'] . ' Core' . ($info['cpus'] > 1 ? 's' : '') . '</span></td>
                     </tr>
                     <tr>
                        <td>
                           Disk Usage <span class="pull-right">' . round(($hdd[1] / 1024 / 1024 / 1024) , 2) . ' of ' . round(($hdd[0] / 1024 / 1024 / 1024) , 2) . ' GB</span>
                           <div class="progress">
                              <div class="progress-bar progress-bar-' . $hdd_color . '" role="progressbar" aria-valuenow="' . ceil($hdd_percent) . '" aria-valuemin="0" aria-valuemax="100" style="width: ' . ceil($hdd_percent) . '%;">
                                 ' . $hdd_percent . '%
                              </div>
                           </div>
                        </td>
                     </tr>
                     <tr>
                        <td>
                           Memory Usage <span class="pull-right">' . round(($memory[1] / 1024 / 1024 / 1024) , 2) . ' of ' . round(($memory[0] / 1024 / 1024 / 1024) , 2) . ' GB</span>   
                           <div class="progress">
                              <div class="progress-bar progress-bar-' . $memory_color . '" role="progressbar" aria-valuenow="' . $memory_percent . '" aria-valuemin="0" aria-valuemax="100" style="width: ' . $memory_percent . '%;">
                                 ' . $memory_percent . '%
                              </div>
                           </div>
                        </td>
                     </tr>
                     <tr>
                        <td>
                           Bandwidth <span class="pull-right">' . round(($bandwidth[1] / 1024 / 1024 / 1024) , 2) . ' of ' . round(($bandwidth[0] / 1024 / 1024 / 1024) , 2) . ' GB</span>  
                           <div class="progress">
                              <div class="progress-bar progress-bar-' . $bandwidth_color . '" role="progressbar" aria-valuenow="' . $bandwidth_percent . '" aria-valuemin="0" aria-valuemax="100" style="width: ' . $bandwidth_percent . '%;">
                                 ' . $bandwidth_percent . '%
                              </div>
                           </div>
                        </td>
                     </tr>
                  </table>
               </div>
            </div>
         </div>
      </div>
   </div>
</div>';
		echo '<div align="center">';
		echo '<img id="SolusVM-Graph-Traffic" src="' . $url . '/' . $infoall['trafficgraph'] . '" style="padding:2px">';
		echo '<img id="SolusVM-Graph-Load" src="' . $url . '/' . $infoall['loadgraph'] . '" style="padding:2px"><br>';
		echo '<img id="SolusVM-Graph-Memory" src="' . $url . '/' . $infoall['memorygraph'] . '" style="padding:2px">';
		echo '</div>';
?>
<script>
addLoadEvent(function() {
	$("#SolusVM-Graph-Traffic").error(function () { 
		$(this).hide(); 
	});
	$("#SolusVM-Graph-Load").error(function () { 
		$(this).hide(); 
	});
	$("#SolusVM-Graph-Memory").error(function () { 
		$(this).hide(); 
	});
});
</script>
<?php
	}
	function suspend($array) {
		global $billic, $db;
		$a = $this->curl('vserver-suspend', array(
			'vserverid' => $array['service']['username'],
		));
		if ($this->api_errors($a)) {
			return $a['statusmsg'];
		}
		return true;
	}
	function unsuspend($array) {
		global $billic, $db;
		$a = $this->curl('vserver-unsuspend', array(
			'vserverid' => $array['service']['username'],
		));
		if ($this->api_errors($a)) {
			return $a['statusmsg'];
		}
		return true;
	}
	function terminate($array) {
		global $billic, $db;
		$a = $this->curl('vserver-terminate', array(
			'vserverid' => $array['service']['username'],
		));
		if ($this->api_errors($a)) {
			return $a['statusmsg'];
		}
		return true;
	}
	function create($array) {
		global $billic, $db;
		$password = $array['service']['password'];
		if (empty($password)) {
			$password = strtolower($billic->rand_str(10));
		} else {
			$password = $billic->decrypt($service['password']);
		}
		$db->q('UPDATE `services` SET `password` = ? WHERE `id` = ?', $billic->encrypt($password) , $array['service']['id']);
		$a = $this->curl('client-create', array(
			'username' => $array['user']['id'],
			'password' => $password,
			'email' => $array['user']['email'],
			'firstname' => $array['user']['firstname'],
			'lastname' => $array['user']['lastname'],
			'company' => $array['user']['company'],
		));
		if ($a['status'] != 'success' && $a['statusmsg'] != 'Client already exists') {
			return $a['statusmsg'];
		}
		$post = array(
			'type' => $array['vars']['type'],
			'nodegroup' => $array['vars']['nodegroup'],
			'hostname' => $array['service']['domain'],
			'password' => $password,
			'username' => $array['user']['id'],
			'plan' => $array['vars']['plan'],
			'template' => $array['vars']['template'],
			'ips' => $array['vars']['ips'],
			//'hvmt' => $array['vars']['hvmt'], // No idea what this does - documentation lacking
			'issuelicense' => $array['vars']['issuelicense'],
			'internalip' => $array['vars']['internalip'],
		);
		$custom_vars = array(
			'custommemory',
			'customdiskspace',
			'custombandwidth',
			'customcpu'
		);
		foreach ($custom_vars as $k) {
			if (!empty($array['vars'][$k])) {
				$post[$k] = $array['vars'][$k];
			}
		}
		// Make sure burstable RAM is specified if openvz
		if ($post['type'] == 'openvz' && array_key_exists('custommemory', $post) && strpos($post['custommemory'], ':') === false) {
			$post['custommemory'] = $post['custommemory'] . ':' . $post['custommemory'];
		}
		if ($post['issuelicense'] == 'Yes') {
			$post['issuelicense'] = 1;
		} else if ($post['issuelicense'] == 0 || $post['issuelicense'] == 'No') {
			unset($post['issuelicense']);
		}
		$a = $this->curl('vserver-create', $post);
		if ($this->api_errors($a)) {
			return $a['statusmsg'];
		}
		$db->q('UPDATE `services` SET `username` = ? WHERE `id` = ?', $a['vserverid'], $array['service']['id']);
		//$this->getips($service);
		return true;
	}
	function ordercheck($array) {
		global $billic, $db;
		$vars = $array['vars'];
		if (!(preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $vars['domain']) // valid chars check
		 && preg_match("/^.{1,253}$/", $vars['domain']) // overall length check
		 && preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $vars['domain']) // length of each label
		)) {
			$billic->error('Invalid Domain. It should be something like vps1.your-domain.com', 'domain');
		}
		return $vars['domain']; // return the domain for the service to be called
		
	}
	// $this->curl('action', array('a' => 1, 'b' => 2, ...));
	function curl($action, $postfields) {
		if ($this->ch === null) {
			$this->ch = curl_init();
			curl_setopt_array($this->ch, array(
				CURLOPT_URL => 'https://' . get_config('solusvm_master_ip') . ':5656/api/admin/command.php',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER => false,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_USERAGENT => 'Curl/Billic',
				CURLOPT_AUTOREFERER => true,
				CURLOPT_CONNECTTIMEOUT => 10,
				CURLOPT_TIMEOUT => 120,
				CURLOPT_MAXREDIRS => 5,
				CURLOPT_POST => true,
				CURLOPT_HTTPHEADER => array(
					'Expect:'
				) ,
				CURLOPT_FRESH_CONNECT => true,
				CURLOPT_SSL_VERIFYPEER => false,
			));
			if (get_config('solusvm_force_ssl_validation') == 1) {
				curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, true);
			} else {
				curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, false);
			}
		}
		//var_dump($postfields);
		$postfields['action'] = $action;
		$postfields['id'] = get_config('solusvm_api_id');
		$postfields['key'] = get_config('solusvm_api_key');
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, $postfields);
		$data = curl_exec($this->ch);
		if (curl_errno($this->ch) > 0) {
			return array(
				'status' => 'error',
				'statusmsg' => 'Curl error: ' . curl_error($this->ch)
			);
		}
		$data = trim($data);
		preg_match_all('/<(.*?)>([^<]+)<\/\\1>/i', $data, $match);
		$array = array();
		foreach ($match[1] as $x => $y) {
			$array[$y] = $match[2][$x];
		}
		return $array;
	}
	function api_errors(&$array) {
		global $billic, $db;
		if (!is_array($array)) {
			$array = array(
				'status' => 'error',
				'statusmsg' => 'Invalid data returned from API'
			);
			return true;
		}
		if ($array['status'] == 'error') {
			return true;
		}
		return false;
	}
	function settings($array) {
		global $billic, $db;
		if (empty($_POST['update'])) {
			echo '<form method="POST"><input type="hidden" name="billic_ajax_module" value="SolusVM"><table class="table table-striped">';
			echo '<tr><th>Setting</th><th>Value</th></tr>';
			echo '<tr><td>Master IP Address</td><td><input type="text" class="form-control" name="solusvm_master_ip" value="' . safe(get_config('solusvm_master_ip')) . '"></td></tr>';
			echo '<tr><td>Force SSL Validation</td><td><input type="checkbox" name="solusvm_force_ssl_validation" value="1"' . ((get_config('solusvm_force_ssl_validation') == 1) ? ' checked' : '') . '"> Tick this only if you have a signed SSL certificate. If it is self-signed leave this unchecked or you will get a cURL SSL error.</td></tr>';
			echo '<tr><td>API ID</td><td><input type="text" class="form-control" name="solusvm_api_id" value="' . safe(get_config('solusvm_api_id')) . '"></td></tr>';
			echo '<tr><td>API Key</td><td><input type="text" class="form-control" name="solusvm_api_key" value="' . safe(get_config('solusvm_api_key')) . '"></td></tr>';
			echo '<tr><td colspan="2" align="center"><input type="submit" class="btn btn-success" name="update" value="Update &raquo;"></td></tr>';
			echo '</table></form>';
		} else {
			if (empty($billic->errors)) {
				set_config('solusvm_master_ip', $_POST['solusvm_master_ip']);
				set_config('solusvm_force_ssl_validation', $_POST['solusvm_force_ssl_validation']);
				set_config('solusvm_api_id', $_POST['solusvm_api_id']);
				set_config('solusvm_api_key', $_POST['solusvm_api_key']);
				$billic->status = 'updated';
			}
		}
	}
}

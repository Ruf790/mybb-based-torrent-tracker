<?php
/*********************/
/*                   */
/*  Version : 5.1.0  */
/*  Author  : RM     */
/*  Comment : 071223 */
/*                   */
/*********************/



function get_ca_bundle_path()
{
	if($path = ini_get('openssl.cafile'))
	{
		return $path;
	}
	if($path = ini_get('curl.cainfo'))
	{
		return $path;
	}

	return false;
}

function get_ip_by_hostname($hostname)
{
	$addresses = @gethostbynamel($hostname);

	if(!$addresses)
	{
		$result_set = @dns_get_record($hostname, DNS_A | DNS_AAAA);

		if($result_set)
		{
			$addresses = array_column($result_set, 'ip');
		}
		else
		{
			return false;
		}
	}

	return $addresses;
}




$disallowed_remote_hosts = array(
	'localhost',
);

/**
 * Disallowed Remote Addresses
 *  List of IPv4 addresses the fetch_remote_file() function
 *  will not perform requests to.
 *  It is recommended that you enter addresses resolving to
 *  the forum server here to prevent Server Side Request
 *  Forgery attacks.
 *  Removing all values disables resolving hosts in that
 *  function.
 */

$disallowed_remote_addresses = array(
	'127.0.0.1',
	'10.0.0.0/8',
	'172.16.0.0/12',
	'192.168.0.0/16',
);



function TS_Fetch_Data($url, $post_data=array(), $max_redirects=20)
{
	global $mybb, $config, $disallowed_remote_hosts, $disallowed_remote_addresses;

	if(!my_validate_url($url, true))
	{
		return false;
	}

	$url_components = @parse_url($url);

	if(!isset($url_components['scheme']))
	{
		$url_components['scheme'] = 'https';
	}
	if(!isset($url_components['port']))
	{
		$url_components['port'] = $url_components['scheme'] == 'https' ? 443 : 80;
	}

	if(
		!$url_components ||
		empty($url_components['host']) ||
		(!empty($url_components['scheme']) && !in_array($url_components['scheme'], array('http', 'https'))) ||
		(!in_array($url_components['port'], array(80, 8080, 443))) ||
		(!empty($disallowed_remote_hosts) && in_array($url_components['host'], $disallowed_remote_hosts))
	)
	{
		return false;
	}

	$addresses = get_ip_by_hostname($url_components['host']);
	$destination_address = $addresses[0];

	if(!empty($disallowed_remote_addresses))
	{
		foreach($disallowed_remote_addresses as $disallowed_address)
		{
			$ip_range = fetch_ip_range($disallowed_address);

			$packed_address = my_inet_pton($destination_address);

			if(is_array($ip_range))
			{
				if(strcmp($ip_range[0], $packed_address) <= 0 && strcmp($ip_range[1], $packed_address) >= 0)
				{
					return false;
				}
			}
			elseif($destination_address == $disallowed_address)
			{
				return false;
			}
		}
	}

	$post_body = '';
	if(!empty($post_data))
	{
		foreach($post_data as $key => $val)
		{
			$post_body .= '&'.urlencode($key).'='.urlencode($val);
		}
		$post_body = ltrim($post_body, '&');
	}

	if(function_exists("curl_init"))
	{
		$fetch_header = $max_redirects > 0;

		$ch = curl_init();

		$curlopt = array(
			CURLOPT_URL => $url,
			CURLOPT_HEADER => $fetch_header,
			CURLOPT_TIMEOUT => 10,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_FOLLOWLOCATION => 0,
		);

		if($ca_bundle_path = get_ca_bundle_path())
		{
			$curlopt[CURLOPT_SSL_VERIFYPEER] = 1;
			$curlopt[CURLOPT_CAINFO] = $ca_bundle_path;
		}
		else
		{
			$curlopt[CURLOPT_SSL_VERIFYPEER] = 0;
		}

		$curl_version_info = curl_version();
		$curl_version = $curl_version_info['version'];

		if(version_compare(PHP_VERSION, '7.0.7', '>=') && version_compare($curl_version, '7.49', '>='))
		{
			// CURLOPT_CONNECT_TO
			$curlopt[10243] = array(
				$url_components['host'].':'.$url_components['port'].':'.$destination_address
			);
		}
		elseif(version_compare(PHP_VERSION, '5.5', '>=') && version_compare($curl_version, '7.21.3', '>='))
		{
			// CURLOPT_RESOLVE
			$curlopt[10203] = array(
				$url_components['host'].':'.$url_components['port'].':'.$destination_address
			);
		}

		if(!empty($post_body))
		{
			$curlopt[CURLOPT_POST] = 1;
			$curlopt[CURLOPT_POSTFIELDS] = $post_body;
		}

		curl_setopt_array($ch, $curlopt);

		$response = curl_exec($ch);

		if($fetch_header)
		{
			$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
			$header = substr($response, 0, $header_size);
			$body = substr($response, $header_size);

			if(in_array(curl_getinfo($ch, CURLINFO_HTTP_CODE), array(301, 302)))
			{
				preg_match('/^Location:(.*?)(?:\n|$)/im', $header, $matches);

				if($matches)
				{
					$data = TS_Fetch_Data(trim(array_pop($matches)), $post_data, --$max_redirects);
				}
			}
			else
			{
				$data = $body;
			}
		}
		else
		{
			$data = $response;
		}

		curl_close($ch);
		return $data;
	}
	else if(function_exists("fsockopen"))
	{
		if(!isset($url_components['path']))
		{
			$url_components['path'] = "/";
		}
		if(isset($url_components['query']))
		{
			$url_components['path'] .= "?{$url_components['query']}";
		}

		$scheme = '';

		if($url_components['scheme'] == 'https')
		{
			$scheme = 'ssl://';
			if($url_components['port'] == 80)
			{
				$url_components['port'] = 443;
			}
		}

		if(function_exists('stream_context_create'))
		{
			if($url_components['scheme'] == 'https' && $ca_bundle_path = get_ca_bundle_path())
			{
				$context = stream_context_create(array(
					'ssl' => array(
						'verify_peer' => true,
						'verify_peer_name' => true,
						'peer_name' => $url_components['host'],
						'cafile' => $ca_bundle_path,
					),
				));
			}
			else
			{
				$context = stream_context_create(array(
					'ssl' => array(
						'verify_peer' => false,
						'verify_peer_name' => false,
						'peer_name' => $url_components['host'],
					),
				));
			}

			$fp = @stream_socket_client($scheme.$destination_address.':'.(int)$url_components['port'], $error_no, $error, 10, STREAM_CLIENT_CONNECT, $context);
		}
		else
		{
			$fp = @fsockopen($scheme.$url_components['host'], (int)$url_components['port'], $error_no, $error, 10);
		}

		if(!$fp)
		{
			return false;
		}
		@stream_set_timeout($fp, 10);
		$headers = array();
		if(!empty($post_body))
		{
			$headers[] = "POST {$url_components['path']} HTTP/1.0";
			$headers[] = "Content-Length: ".strlen($post_body);
			$headers[] = "Content-Type: application/x-www-form-urlencoded";
		}
		else
		{
			$headers[] = "GET {$url_components['path']} HTTP/1.0";
		}

		$headers[] = "Host: {$url_components['host']}";
		$headers[] = "Connection: Close";
		$headers[] = '';

		if(!empty($post_body))
		{
			$headers[] = $post_body;
		}
		else
		{
			// If we have no post body, we need to add an empty element to make sure we've got \r\n\r\n before the (non-existent) body starts
			$headers[] = '';
		}

		$headers = implode("\r\n", $headers);
		if(!@fwrite($fp, $headers))
		{
			return false;
		}

		$data = null;

		while(!feof($fp))
		{
			$data .= fgets($fp, 12800);
		}
		fclose($fp);

		$data = explode("\r\n\r\n", $data, 2);

		$header = $data[0];
		$status_line = current(explode("\n\n", $header, 1));
		$body = $data[1];

		if($max_redirects > 0 && (strstr($status_line, ' 301 ') || strstr($status_line, ' 302 ')))
		{
			preg_match('/^Location:(.*?)(?:\n|$)/im', $header, $matches);

			if($matches)
			{
				$data = TS_Fetch_Data(trim(array_pop($matches)), $post_data, --$max_redirects);
			}
		}
		else
		{
			$data = $body;
		}

		return $data;
	}
	else
	{
		return false;
	}
}


if ( !defined( "IN_TRACKER" ) )
{
    exit( "<font face='verdana' size='2' color='darkred'><b>Error!</b> Direct initialization of this file is not allowed.</font>" );
}
?>

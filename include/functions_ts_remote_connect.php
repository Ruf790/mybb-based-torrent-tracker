<?php
declare(strict_types=1);


if (!defined('IN_TRACKER')) {
    exit("<font face='verdana' size='2' color='darkred'><b>Error!</b> Direct initialization of this file is not allowed.</font>");
}

$disallowed_remote_hosts = [
    'localhost',
];

$disallowed_remote_addresses = [
    '0.0.0.0',
    '10.0.0.0/8',
    '100.64.0.0/10',
    '127.0.0.0/8',
    '169.254.0.0/16',
    '172.16.0.0/12',
    '192.168.0.0/16',
    '255.255.255.255',
    '::',
    '::1',
    '::ffff:0:0/96',
    'fc00::/7',
    'fe80::/10',
    'ff00::/8',
];

function get_ca_bundle_path(): string|false
{
    return ini_get('openssl.cafile') ?: ini_get('curl.cainfo') ?: false;
}

function get_ip_by_hostname(string $hostname): array|false
{
    $addresses = @gethostbynamel($hostname);
    if ($addresses) {
        return $addresses;
    }

    $result_set = @dns_get_record($hostname, DNS_A | DNS_AAAA);
    if ($result_set) {
        return array_column($result_set, 'ip');
    }

    return false;
}



function TS_Fetch_Data(string $url, array|false $post_data = [], int $max_redirects = 20): string|false
{
    global $disallowed_remote_hosts, $disallowed_remote_addresses;
	
	$post_data = $post_data ?: [];

    if (!my_validate_url($url, true)) {
        return false;
    }

    $url_components = parse_url($url);

    if (!$url_components) {
        return false;
    }

    $url_components['scheme'] ??= 'https';
    $url_components['port']   ??= $url_components['scheme'] === 'https' ? 443 : 80;

    if (
        empty($url_components['host']) ||
        !in_array($url_components['scheme'], ['http', 'https'], true) ||
        !in_array($url_components['port'], [80, 8080, 443], true) ||
        (!empty($disallowed_remote_hosts) && in_array($url_components['host'], $disallowed_remote_hosts, true))
    ) {
        return false;
    }

    $addresses = get_ip_by_hostname($url_components['host']);
    if (!$addresses) {
        error_log('TS_Fetch_Data Error: Cannot resolve host — ' . $url_components['host']);
        return false;
    }

    $destination_address = $addresses[0];

    if (!empty($disallowed_remote_addresses)) {
        $packed_address = my_inet_pton($destination_address);
        if ($packed_address === false) {
            error_log('TS_Fetch_Data Error: Invalid IP address — ' . $destination_address);
            return false;
        }

        foreach ($disallowed_remote_addresses as $disallowed) {
            $ip_range = fetch_ip_range($disallowed);
            if (is_array($ip_range)) {
                if (strcmp($ip_range[0], $packed_address) <= 0 && strcmp($ip_range[1], $packed_address) >= 0) {
                    return false;
                }
            } elseif ($destination_address === $disallowed) {
                return false;
            }
        }
    }

    // Build POST body
    $post_body = '';
    if (!empty($post_data)) {
        $post_body = http_build_query($post_data);
    }

    // cURL
    if (function_exists('curl_init')) {
        return ts_fetch_via_curl($url, $url_components, $destination_address, $post_body, $post_data, $max_redirects);
    }

    // fsockopen fallback
    if (function_exists('fsockopen')) {
        return ts_fetch_via_socket($url, $url_components, $destination_address, $post_body, $post_data, $max_redirects);
    }

    return false;
}

function ts_fetch_via_curl(string $url, array $url_components, string $destination_address, string $post_body, array $post_data, int $max_redirects): string|false
{
    $fetch_header = $max_redirects > 0;
    $ch           = curl_init();
    $ca_bundle    = get_ca_bundle_path();

    $curlopt = [
        CURLOPT_URL            => $url,
        CURLOPT_HEADER         => $fetch_header,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_FOLLOWLOCATION => 0,
        CURLOPT_SSL_VERIFYPEER => (bool) $ca_bundle,
    ];

    if ($ca_bundle) {
        $curlopt[CURLOPT_CAINFO] = $ca_bundle;
    }

    // Pin destination IP to prevent DNS rebinding
    $curl_version = curl_version()['version'];
    $host_port    = $url_components['host'] . ':' . $url_components['port'] . ':' . $destination_address;

    if (version_compare($curl_version, '7.49', '>=')) {
        $curlopt[CURLOPT_CONNECT_TO] = [$host_port];
    } elseif (version_compare($curl_version, '7.21.3', '>=')) {
        $curlopt[CURLOPT_RESOLVE] = [$host_port];
    }

    if ($post_body !== '') {
        $curlopt[CURLOPT_POST]       = 1;
        $curlopt[CURLOPT_POSTFIELDS] = $post_body;
    }

    curl_setopt_array($ch, $curlopt);
    $response = curl_exec($ch);
    $data     = false;

    if ($fetch_header && is_string($response)) {
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header      = substr($response, 0, $header_size);
        $body        = substr($response, $header_size);

        if (in_array(curl_getinfo($ch, CURLINFO_HTTP_CODE), [301, 302], true)) {
            preg_match('/^Location:(.*?)(?:\n|$)/im', $header, $matches);
            if ($matches) {
                $data = TS_Fetch_Data(trim(array_pop($matches)), $post_data, $max_redirects - 1);
            }
        } else {
            $data = $body;
        }
    } else {
        $data = $response;
    }

    return $data;
}

function ts_fetch_via_socket(string $url, array $url_components, string $destination_address, string $post_body, array $post_data, int $max_redirects): string|false
{
    $url_components['path']  ??= '/';
    if (isset($url_components['query'])) {
        $url_components['path'] .= '?' . $url_components['query'];
    }

    $scheme = '';
    if ($url_components['scheme'] === 'https') {
        $scheme = 'ssl://';
        if ($url_components['port'] === 80) {
            $url_components['port'] = 443;
        }
    }

    $ca_bundle = get_ca_bundle_path();
    $ssl_opts  = $url_components['scheme'] === 'https' && $ca_bundle
        ? ['verify_peer' => true,  'verify_peer_name' => true,  'peer_name' => $url_components['host'], 'cafile' => $ca_bundle]
        : ['verify_peer' => false, 'verify_peer_name' => false, 'peer_name' => $url_components['host']];

    $context = stream_context_create(['ssl' => $ssl_opts]);
    $fp      = @stream_socket_client(
        $scheme . $destination_address . ':' . (int) $url_components['port'],
        $error_no, $error, 10, STREAM_CLIENT_CONNECT, $context
    );

    if (!$fp) {
        return false;
    }

    @stream_set_timeout($fp, 10);

    $headers   = [];
    $headers[] = ($post_body !== '' ? 'POST' : 'GET') . " {$url_components['path']} HTTP/1.0";
    if ($post_body !== '') {
        $headers[] = 'Content-Length: ' . strlen($post_body);
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    $headers[] = 'Host: ' . $url_components['host'];
    $headers[] = 'Connection: Close';
    $headers[] = '';
    if ($post_body !== '') {
        $headers[] = $post_body;
    } else {
        $headers[] = '';
    }

    if (!@fwrite($fp, implode("\r\n", $headers))) {
        fclose($fp);
        return false;
    }

    $raw = '';
    while (!feof($fp)) {
        $raw .= fgets($fp, 12800);
    }
    fclose($fp);

    [$header, $body] = array_pad(explode("\r\n\r\n", $raw, 2), 2, '');
    $status_line     = explode("\n", $header)[0] ?? '';

    if ($max_redirects > 0 && (str_contains($status_line, ' 301 ') || str_contains($status_line, ' 302 '))) {
        preg_match('/^Location:(.*?)(?:\n|$)/im', $header, $matches);
        if ($matches) {
            return TS_Fetch_Data(trim(array_pop($matches)), $post_data, $max_redirects - 1);
        }
    }

    return $body;
}
<?php

namespace Wide\Controller;

class Staticfile extends \Controller {

	/**
	 * Output static file
	 *
	 * @throws \ExitException
	 */
	public function index() {
		$uri = $this->input->server('REQUEST_URI');
		$path = substr($uri, strlen('/static/'));
		$query = strpos($path, '?');

		//remove query string
		if ($query !== false) {
			$path = substr($path, 0, $query);
		}

		//security
		if (strpos($path, '..') !== false) {
			nvHeader('HTTP/1.1 400 Bad Request');
			nvExit('<h1>400 Bad Request</h1>');
		}

		$file = RUN_DIR.'/static/'.$path;

		self::sendfile($file);
	}

	/**
	 * Send a file to client
	 *
	 * @param string $file
	 * @throws \ExitException
	 */
	private static function sendfile($file) {
		if (!is_file($file)) {
			nv404();
		}

		$time = time();
		$headers = array('Date' => date('D, d M Y H:i:s', $time) . ' GMT');
		$NV =& getInstance();

		//�ļ�������Ϣ
		$pathInfo = pathinfo($file);

		if (isset($pathInfo['extension'])) {
			$mimeType = $NV->config->get($pathInfo['extension'], 'mime_types');
			$mimeType && $headers['Content-Type'] = $mimeType;
		}

		//�ļ��Ƿ��л�����Ч��
		if (isset($pathInfo['extension'])) {
			$expires = $NV->config->get($pathInfo['extension'], 'static_cache');

			if ($expires) {
				switch (substr($expires, -1)) {
					case 'm': $expires = substr($expires, 0, -1) * 60; break;
					case 'h': $expires = substr($expires, 0, -1) * 3600; break;
					case 'd': $expires = substr($expires, 0, -1) * 86400; break;
				}

				$headers['Expires'] = date('D, d M Y H:i:s', $expires + $time).' GMT';
				$headers['Cache-Control'] = $expires;
			}
		}

		//��ȡ�ļ���Ϣ
		$info = stat($file);

		$modifiedTime = $info ? date('D, d M Y H:i:s', $info['mtime']) . ' GMT' : '';
		$modifiedTime && $headers['Last-Modified'] = $modifiedTime;

		$connection = $NV->input->connection();

		//�ͻ��˴����ļ�ʱ��δ�仯��ֱ�� 304
		if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']) && $modifiedTime === $_SERVER['HTTP_IF_MODIFIED_SINCE']) {
			self::sendHeaders($connection, 304, $headers);
			throw new \ExitException('RAW_OUTPUT_BREAK', 5);
		}

		$headers['Content-Length'] = $info['size'];

		self::sendHeaders($connection, 200, $headers);

		$connection->pause = false;
		$connection->fp = fopen($file, 'rb');

		$send = function() use ($connection) {
			while (!$connection->pause) {
				$buffer = fread($connection->fp, 8192);

				// ����������˵���ļ�����ĩβ��
				if ($buffer === '' || $buffer === false) {
					$connection->onBufferDrain = $connection->onBufferFull = null;
					$connection->pause = true;
					fclose($connection->fp);
					return;
				}

				$connection->send($buffer, true);
			}
		};

		//��������ʱ����ͣ����
		$connection->onBufferFull = function($connection) {
			$connection->pause = true;
		};

		//�������ճ�ʱ�����ָ�����
		$connection->onBufferDrain = function($connection) use ($send) {
			$connection->pause = false;
			$send();
		};

		//��ʼ�����ļ�����
		$send();

		//Tell output that has been break by raw data, and stop global output
		throw new \ExitException('RAW_OUTPUT_BREAK', 5);
	}

	/**
	 * Sort and send headers
	 *
	 * @param \Workerman\Connection\TcpConnection $connection
	 * @param int $statusCode
	 * @param array $headers
	 */
	private static function sendHeaders($connection, $statusCode, array $headers) {
		switch ($statusCode) {
			case 200: $out = 'HTTP/1.1 200 OK'; break;
			case 304: $out = 'HTTP/1.1 304 Not Modified'; break;
			default: $out = 'HTTP/1.1 400 Bad Request';
		}

		$sort = array('Date', 'Content-Type', 'Content-Length', 'Connection', 'Last-Modified', 'Expires', 'Cache-Control');

		if (!isset($headers['Date'])) {
			$headers['Date'] = date('D, d M Y H:i:s') . ' GMT';
		}

		if (!isset($headers['Connection'])) {
			$headers['Connection'] = 'keep-alive';
		}

		//header ����
		foreach ($sort as $name) {
			if (isset($headers[$name])) {
				$out .= "\r\n$name: {$headers[$name]}";
				unset($headers[$name]);
			}
		}

		//���� header
		foreach ($headers as $name => $value) {
			$out .= "\r\n$name: $value";
		}

		//add server tag
		$out .= "\r\nServer: Workerman/3.0\r\n\r\n";

		$connection->send($out, true);
	}

}

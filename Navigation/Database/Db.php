<?php

namespace Navigation\Database;

class Db {

	public $queryCount = 0;
	public $queryRecords = array();
	public $lastQuery = '';

	protected $config;
	protected $setNoPrefix = false;

	/**
	 * Adapters List
	 *
	 * @var array
	 */
	protected $allowAdapters = array('mysql', 'mysqli', 'pdo');

	/**
	 * @var string
	 */
	protected $adapterName;

	/**
	 * @var string
	 */
	protected $resultClass;

	/**
	 * @var AdapterInterface
	 */
	protected $adapter;

	protected $query;

	/**
	 * Initialize config
	 *
	 * @param string|array $conf
	 * @param bool $useDsn Unsupport yet
	 */
	public function __construct($conf='default', $useDsn=false) {
		if (!is_array($conf)) {
			$NV =& getInstance();
			$config = $NV->config->get($conf, 'database');

			if (!$config) {
				nvCallError("Undefined database config '$conf'");
			}

			$conf = $config;
		}

		$this->config = $conf;

		//Check adapter
		if (!isset($conf['adapter'])) {
			$conf['adapter'] = 'pdo';
		}

		if (!in_array($conf['adapter'], $this->allowAdapters)) {
			nvCallError("Unsupport db adapter '{$conf['adapter']}'");
		}

		$this->adapterName = $conf['adapter'];

		$this->connect($conf);
	}

	/**
	 * Connect to database
	 *
	 * @param $conf
	 */
	public function connect($conf) {
		$adapterClass = '\\Navigation\\Database\\Adapter'.ucfirst($this->adapterName);
		$this->adapter = new $adapterClass();

		if ($this->adapter->connect($conf) === false) {
			$message = $this->adapter->embedded ? "Failed to open {$this->adapter->dbType} database"
				: "Failed to connect to {$this->adapter->dbType} server";

			Util::error($message, $this->adapter->errorCode(), $this->adapter->errorMessage());
		}

		//set result adapter name
		$this->resultClass = '\\Navigation\\Database\\Adapter'.ucfirst($this->adapterName).'Result';
	}

	public function ping() {
		return $this->adapter->ping();
	}

	public function query($sql, $resultMode=true) {
		//if debug is open, log queries and time used
		if (!empty($this->config['debug'])) {
			$timeStart = microtime(true);
		}

		$this->query = $this->adapter->query($sql);

		if (!$this->query) {
			Util::error('Query error', $this->adapter->errorCode(), $this->adapter->errorMessage(), $sql);
		}

		++$this->queryCount;
		$this->lastQuery = $sql;

		//save debug info
		if (isset($timeStart)) {
			$timeEnd = microtime(true);
			$queryTime = round(($timeEnd - $timeStart), 6);
			$this->queryRecords[] = array('sql' => $sql, 'used' => $queryTime);
		}

		return $resultMode ? new $this->resultClass($this->query) : $this->query;
	}


	/**
	 * ִ��һ�� SQL ���
	 *
	 * @param string $sql
	 * @return \mysqli_result|\PDOStatement
	 */
	public function exec($sql) {
		return $this->query($sql, false);
	}

	/**
	 * Fetch an row from query result
	 *
	 * @return array
	 */
	public function fetch() {
		return $this->adapter->fetch($this->query);
	}

	//ȡ�ô�����ǰ׺�ı���
	public function prefix($table) { return $this->config['tbprefix'].$table; }

	/**
	 * ȡ����һ������������ ID
	 *
	 * @return int
	 */
	public function lastId() { return $this->adapter->lastId(); }

	/**
	 * ȡ��ǰһ�� MySQL ������Ӱ��ļ�¼����
	 *
	 * @return int
	 */
	public function affectedRows() {}

	/**
	 * ת���ַ������ڲ�ѯ
	 *
	 * @param string $str
	 * @param bool $quote
	 * @return string
	 */
	public function escapeStr($str, $quote=false) {
		$str = addcslashes($str, "\x00\x1a\n\r\\'\"");
		$quote && $str = "'$str'";
		return $str;
	}

	/**
	 * ��ʼһ������
	 *
	 * @return bool
	 */
	public function begin() { return $this->adapter->begin(); }

	/**
	 * �ύ����
	 *
	 * @return bool
	 */
	public function commit() { return $this->adapter->commit(); }

	/**
	 * �ع�����
	 *
	 * @return bool
	 */
	public function rollback() { return $this->adapter->rollback(); }

	/**
	 * ȡ�����ݿ����Ӷ�����ʶ
	 *
	 * @return AdapterInterface
	 */
	public function getConnection() { return $this->adapter->link; }

	//��ȡ MySQL ����˰汾��Ϣ
	public function getServerVersion() { return $this->adapter->getServerVersion(); }

	//��ȡ MySQL �ͻ��˰汾��Ϣ
	public function getClientVersion() { return $this->adapter->getClientVersion(); }

}

class Util {

	public static function error($message, $errno, $error, $sql='') {
		nvExit("<h4>Database Error</h4><p><b>Message:</b> {$message} [$errno]<br /><b>Error:</b> $error<br />$sql</p>");
	}

}

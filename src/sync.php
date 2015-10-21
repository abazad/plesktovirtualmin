<?php

/*
 *
 * Plesk to Virtualmin sync.
 * Plesk'den virtualmine senkronizasyon..
 *
 * Ahmet Orhan
 * ahmetariforhan@gmail.com
 *
 *
 *
 * Virtualmin ayarı :
 * System Settings -> Virtualmin Conf.-> Defaults for new domains ->Include domain name in usernames : Only to avoid a clash
 * System Settings -> Server Templates-> Defaults Setttings ->Mail For Domain -> Format for usernames that include domain : username@domain
 *
 *
 *
 */
set_time_limit(0);

class Virtualmin {

    /**
     * @var url https://1.2.3.4:10000/virtual-server/remote.cgi;
     */
    private $url;

    /**
     * @var webmin username : root
     */
    private $username;

    /**
     * @var webmin password
     */
    private $password;

    /**
     * @var webmin host ip
     */
    private $host;

    /**
     * @var webmin api output şekli
     */
    private $output = 'xml=';

    /**
     * @var domain ftp bilgileri [0] username [1] Password
     */
    private $ftp = array();

    /**
     * @var Plesk sunucu ipsi..
     */
    public $sourceIp;

    /**
     * @var bool ftp aktarımı
     */
    public $ftpPut = false;

    public function __construct($username, $password, $host, $port='10000') {

	try {
	    if (empty($username) || empty($password) || empty($host))
		throw new Exception('Hata! Bilgiler Eksik');
	    else {

		$this->username = $username;

		$this->password = $password;

		$this->host = $host;

		$this->url = 'https://' . $host . ':' . $port . '/virtual-server/remote.cgi';
	    }
	} catch (Exception $e) {
	    echo 'Message: ' . $e->getMessage();
	}
    }

    private function hazirla($program, $params=array(), $mesaj='') {

	$tempUrl = '';

	foreach ($params as $key => $value) {

	    $tempUrl.=$key . '=' . urlencode($value) . '&';
	}

	$tempUrl = rtrim($tempUrl, '&');

	$nUrl = $this->url . '?program=' . $program . '&' . $tempUrl . '&' . $this->output;



	return ($this->calistir($nUrl, $mesaj));
    }

    private function calistir($url, $mesaj) {

	$ch = curl_init($url);

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

	if (isset($this->username) && isset($this->password)) {
	    curl_setopt($ch, CURLOPT_USERPWD, $this->username . ":" . $this->password);
	    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
	}
	$data = curl_exec($ch);

	$xml = simplexml_load_string($data);


	if ($xml['status'] == 'failure') {
	    $sonuc = array('status' => $xml['status'], 'mesaj' => $mesaj . ">" . $xml['error'], 'detail' => $xml);
	} else if ($xml['status'] == 'success') {
	    $sonuc = array('status' => $xml['status'], 'mesaj' => $mesaj, 'detail' => $xml);
	}else
	    $sonuc = array('status' => 'info', 'mesaj' => '', 'detail' => $xml);


	return $sonuc;
    }

    /**
     * Yeni domain  oluştur.
     *
     * @param $domainName string domain.com
     * @param $domainUser string  Domain administrator user
     * @param $domainPass string  Domain admin pass
     * @param $params array  virtualmin params['mysql','web','mail'..]
     *
     * @return array
     */
    public function createDomain($domainName, $domainUser, $domainPass, $params=array()) {


	//webmin _ kabul etmiyor. _ karakterini . ile değiştir.
	if (strpos($domainUser, '_') !== false)
	    $domainUser = str_replace('_', '.', $domainUser);


	$this->ftp = array($domainUser, $domainPass);


	//domain var mı?
	$list = $this->ctrlDomain($domainName);


	//Parolayı update et.
	if ($list['status'] == 'success') {

	    $mesaj = "Update Domain Password [User : " . $domainUser . "]";

	    $program = 'list-users';

	    $paramsList['domain-user'] = $domainUser;

	    $sonuc = $this->hazirla($program, $paramsList, $mesaj);

	    //eğer user yoksa user ekle.. varsa sadece şifreyi değiştir.
	    if ($sonuc['status'] != 'success') {
		$mesaj = "Update Domain User and Password [User :  " . $domainUser . "]";
		$params['user'] = $domainUser;
	    }


	    $params['domain'] = $domainName;

	    $params['pass'] = $domainPass;

	    $program = 'modify-domain';

	    return($this->hazirla($program, $params, $mesaj));
	} else {

	    //params yoksa default olarak kullanılacak değerler..
	    if (count($params) == 0) {
		$params = array('web' => '', 'mail' => '', 'dns' => '', 'unix' => '', 'dir' => '', 'mysql' => '');
	    }

	    if (empty($domainName) || empty($domainPass))
		throw new Exception('Domain Bilgileri Eksik');
	    else {

		$mesaj = "Create Domain";

		$program = 'create-domain';

		$params['domain'] = $domainName;

		$params['pass'] = $domainPass;

		$params['user'] = $domainUser;

		return($this->hazirla($program, $params, $mesaj));
	    }
	}
    }

    /**
     * Yeni mail kullanıcısı oluştur.
     *
     * @param $domainName string domain.com
     * @param $user string Mail adresi sadece kullanıcı adı
     * @param $pass string  Mail pass
     * @param $params array  virtualmin params[]
     *
     * @return array status,mesaj,detail
     */
    public function createUser($domainName, $user, $pass, $params=array()) {

	if (empty($domainName) || empty($user) || empty($pass))
	    throw new Exception('Bilgiler Eksik');
	else {

	    $userCtrl = $this->ctrlUser($user);

	    if (count($userCtrl['detail']) != 0) {

		$program = 'modify-user';

		$paramsList['domain'] = $domainName;

		$paramsList['pass'] = $pass;

		$paramsList['user'] = $user . '@' . $domainName;

		$mesaj = "Update Mail Address [" . $user . "@" . $domainName . "]";

		return($this->hazirla($program, $paramsList, $mesaj));
	    } else {


		$program = 'create-user';

		$params['domain'] = $domainName;

		$params['pass'] = $pass;

		$params['user'] = $user . '@' . $domainName;

		$mesaj = "Create Mail Address [" . $user . "@" . $domainName . "]";

		$sonuc = $this->hazirla($program, $params, $mesaj);

		$programM = 'modify-user';
		$paramsM['domain'] = $domainName;
		$paramsM['user'] = $user;
		$paramsM['remove-email'] = $user . '@' . $domainName . '@' . $domainName;
		$this->hazirla($programM, $paramsM, '');

		return($sonuc);
	    }
	}
    }

    /**
     * Yeni Db oluştur
     *
     * @param $domainName string domain.com
     * @param $dbName string Database adı
     * @param $reCreate bool Yeniden oluşturma mesajı
     *
     * $this->sourceIp : Local sunucunun ip adresi veya rdns kaydı.
     * Bu kayıt webmindeki mysql database'e eklenir ve uzak mysql bağlantı için yetkilendirme yapılmış olur.
     *
     *
     * @return array status,mesaj,detail
     */
    public function createDb($domainName, $dbName, $reCreate=false) {

	if (empty($domainName) || empty($dbName))
	    throw new Exception('Bilgiler Eksik');
	else {

	    $sonuc = $this->ctrlDatabse($domainName, $dbName);
	    if ($sonuc) {
		return( array('status' => 'Pass', 'mesaj' => 'Database Already Exists [' . $dbName . ']', 'detail' => ''));
	    } else {

		$program = 'create-database';

		$params['domain'] = $domainName;

		$params['name'] = $dbName;

		$params['type'] = 'mysql';

		if ($reCreate)
		    $mesaj = "Import Database : Recreate Db [" . $dbName . "]";
		else
		    $mesaj = "Create Database [" . $dbName . "]";

		$sonuc = $this->hazirla($program, $params, $mesaj);


		$program = 'modify-database-hosts';

		$paramsM['domain'] = $domainName;

		$paramsM['add-host'] = $this->sourceIp;

		$paramsM['type'] = 'mysql';

		//$mesaj = "Create Database [" . $dbName . "]";

		$this->hazirla($program, $paramsM, $mesaj);

		return $sonuc;
	    }
	}
    }

    /**
     * Yeni database kullanıcısı oluştur
     *
     * @param $domainName string domain.com
     * @param $user string Database kullanıcı adı
     * @param $pass string  Database kullanıcı parola
     * @param $dbName string  Yetkilendirilecek db adı..
     *
     * @return array status,mesaj,detail
     */
    public function createDbUser($domainName, $user, $pass, $dbName) {

	if (empty($domainName) || empty($user) || empty($pass) || empty($dbName))
	    throw new Exception('Bilgiler Eksik');
	else {


	    $userVar = false;
	    $program = 'list-users';
	    $paramsList['all-domains'] = '';
	    $sonuc = $this->hazirla($program, $paramsList);
	    foreach ($sonuc['detail'] as $val) {
		if ($user == $val['name']) {
		    $userVar = true;
		}
	    }

	    if ($userVar) {
		$mesaj = "Change Database User Password [" . $dbName . " : " . $user . "]";
		$program = 'modify-user';
		$paramsUp['user'] = $user;
		$paramsUp['pass'] = $pass;
		$paramsUp['domain'] = $domainName;
		return($this->hazirla($program, $paramsUp, $mesaj));
	    } else {

		$mesaj = "Create Database User [" . $dbName . " : " . $user . "]";
		$program = 'create-user';
		$params['domain'] = $domainName;
		$params['user'] = $user;
		$params['pass'] = $pass;
		$params['mysql'] = $dbName;
		$params['noemail'] = '';
		$params['no-creation-mail'] = '';

		return($this->hazirla($program, $params, $mesaj));
	    }
	}
    }

    /**
     * Yeni dns kaydı oluştur
     *
     * @param $domainName string domain.com
     * @param $record string  Dns kaydı subdomain.domain.com A 1.2.3.4
     *
     * @return array status,mesaj,detail
     */
    public function createDns($domainName, $record) {

	if (empty($domainName) || empty($record))
	    throw new Exception('Bilgiler Eksik');
	else {

	    $bak = $this->ctrlDns($domainName, $record);

	    $kayitvar = false;

	    foreach ($bak['detail'] as $key => $val) {

		foreach ($val as $v) {

		    $tip = $v->type;
		    $deger = $v->value;
		}

		$sonDns = $val['name'] . " " . $tip . " " . $deger;

		if ($sonDns == $record) {
		    $kayitvar = true;
		}
	    }

	    if ($kayitvar) {

		return( array('status' => 'Pass', 'mesaj' => 'Dns Kaydi Var [' . $record . ']', 'detail' => ''));
	    } else {


		$program = 'modify-dns';

		$params['domain'] = $domainName;

		//record : name type value
		$params['add-record'] = $record;

		$mesaj = "Create DNS Record";

		return($this->hazirla($program, $params, $mesaj));
	    }
	}
    }

    /**
     * Domain Listesi
     *
     * @param $domainName string domain.com
     *
     * @return array status,mesaj,detail -> domain listesi detail'de
     */
    public function ctrlDomain($domainName) {


	if (empty($domainName))
	    throw new Exception('Bilgiler Eksik');
	else {

	    $program = 'list-domains';

	    $params['domain'] = $domainName;

	    return($this->hazirla($program, $params));
	}
    }

    /**
     * Database Kontrol
     *
     * @param $domain string domain.com
     * @param $db string Database adı
     *
     * @return bool
     */
    public function ctrlDatabse($domain, $db) {


	if (empty($domain))
	    throw new Exception('Bilgiler Eksik');
	else {

	    $program = 'list-databases';

	    $params['domain'] = $domain;


	    $sonuc = $this->hazirla($program, $params);

	    foreach ($sonuc['detail'] as $val) {
		if ($val['name'] == $db)
		    return true;
	    }

	    return false;
	}
    }

    /**
     * Kullanıcı Listesi
     *
     * @param $userName string Kullanıcı Adı
     *
     * @return array status,mesaj,detail -> Kullanıcı listesi detail'de
     */
    public function ctrlUser($userName) {

	if (empty($userName))
	    throw new Exception('Bilgiler Eksik');
	else {

	    $program = 'list-users';

	    $params['all-domains'] = '';
	    $params['user'] = $userName;

	    return($this->hazirla($program, $params));
	}
    }

    /**
     * Domaine ait Dns Listesi
     *
     * @param $domain string domain.com
     * @param $record string
     *
     * @return array status,mesaj,detail -> Dns listesi detail'de
     */
    public function ctrlDns($domain, $record) {

	if (empty($domain))
	    throw new Exception('Bilgiler Eksik');
	else {

	    $program = 'get-dns';

	    $params['domain'] = $domain;
	    $params['multiline'] = '';


	    return($this->hazirla($program, $params));
	}
    }

    /**
     * Ftp Dosya Aktarımı
     *
     * @param $local string Yerel Dizin
     * @param $remote string Hedef Dizin
     *
     * @return array status,mesaj,detail
     */
    public function ftp($local, $remote) {

	try {
	    $ftp = new FtpClient\FtpClient();

	    $ftp->connect($this->host);

	    $ftp->login($this->ftp[0], $this->ftp[1]);

	    $this->messages(array('status' => 'info', 'mesaj' => 'File transfer started..'));

	    $ftp->putAll($local, $remote, FTP_BINARY);
	} catch (\Exception $e) {

	    return(array('status' => 'failure', 'mesaj' => 'Ftp : ' . $e->getMessage()));
	}

	return(array('status' => 'info', 'mesaj' => 'File transfer finished'));
    }

    /**
     * Mesajları yaz
     *
     * @param $val array =>('status','mesaj','detail')
     *
     */
    public function messages($val) {
	if ($val['status'] == 'success')
	    $renk = '32m';
	else if ($val['status'] == 'failure')
	    $renk = '31m';
	else if ($val['status'] == 'info')
	    $renk = '33m';
	else
	    $renk = '37m';
	echo "\033[" . $renk . " [" . $val['status'] . "] \033[0m : " . $val['mesaj'] . "\n";
    }

    /**
     * Database Dosyasını import et
     * Database varsa siler ve yeniden oluşturur. mysqldump ile alınan yedeği import eder..
     *
     * @param $domainName string domain.com
     * @param $database array : database ve kullanıcı bilgileri
     *
     * @return array status,mesaj,detail
     */
    public function dbImport($domainName, $database) {

	$msg = '';
	$mesaj = '';

	if (count($database) > 0) {

	    foreach ($database as $dbler) {

		// data dizini altında domain.com oluştur..

		if (!file_exists(__DIR__ . '/data/' . $domainName)) {
		    mkdir(__DIR__ . '/data/' . $domainName);
		}

		//sql dosyası path
		$file = __DIR__ . '/data/' . $domainName . '/' . $dbler['db'] . '.sql';

		//db varsa devam et.

		if (isset($dbler['dbler'][0][0]) && isset($dbler['dbler'][0][1])) {

		    $sonuc = $this->ctrlDatabse($domainName, $dbler['db']);
		    if ($sonuc) {

			$program = 'delete-database';

			$paramsM['domain'] = $domainName;

			$paramsM['name'] = $dbler['db'];

			$paramsM['type'] = 'mysql';

			$deleteDb = $this->hazirla($program, $paramsM, $mesaj);

			if ($deleteDb['status'] == 'success') {
			    $create = $this->createDb($domainName, $dbler['db'], true);
			    $this->messages($create);
			}else
			    $this->messages($deleteDb);
		    }

		    foreach ($dbler['dbler'] as $db) {
			$mesaj = "Modify Database User [" . $dbler['db'] . "]";
			$program = 'modify-user';
			$params['domain'] = $domainName;
			$params['user'] = $db['0'];
			$params['add-mysql'] = $dbler['db'];
			$modify = $this->hazirla($program, $params, $mesaj);
			$this->messages($modify);
		    }



		    if (strncasecmp(PHP_OS, 'WIN', 3) == 0)
			exec('c:\wamp\bin\mysql\mysql5.5.16\bin\mysqldump -h localhost -u ' . $db[0] . ' -p' . str_replace('$', '\$', $db[1]) . ' ' . $dbler['db'] . ' > ' . $file . ' 2>&1', $outdump);
		    else
			exec('mysqldump -h localhost -u ' . $db[0] . ' -p' . str_replace('$', '\$', $db[1]) . ' ' . $dbler['db'] . ' > ' . $file . ' 2>&1', $outdump);


		    //dosya oluşturulana kadar bekle. oluşturulmuşsa devam et..
		    for ($i = 0; $i <= 120; $i++) {

			if (file_exists(__DIR__ . '/data/' . $domainName . '/' . $dbler['db'] . '.sql')) {
			    $this->messages(array('status' => 'info', 'mesaj' => 'Sql dump file created, Importing database.. [' . $dbler['db'] . ']'));
			    break;
			}
			sleep(2);
		    }



		    foreach ($outdump as $val) {
			$this->messages(array('status' => 'info', 'mesaj' => 'Create sql dump file [' . $dbler['db'] . ']' . $val));
		    }


		    if (strncasecmp(PHP_OS, 'WIN', 3) == 0)
			exec('c:\wamp\bin\mysql\mysql5.5.16\bin\mysql -h ' . $this->host . ' -u ' . $db[0] . ' -p' . str_replace('$', '\$', $db[1]) . ' ' . $dbler['db'] . ' < ' . $file . ' 2>&1', $out);
		    else
			exec('mysql -h ' . $this->host . ' -u ' . $db[0] . ' -p' . str_replace('$', '\$', $db[1]) . ' ' . $dbler['db'] . ' < ' . $file . ' 2>&1', $out);


		    foreach ($out as $val) {
			$this->messages(array('status' => 'info', 'mesaj' => 'Mysql import [' . $dbler['db'] . ']' . $val));
		    }
		}
	    }
	}
    }

    /**
     * Plesk xml dosyadan bilgileri çek
     *
     * @param $domain string domain.com
     *
     * @return array
     */
    public function readXml($domain) {

	if (strncasecmp(PHP_OS, 'WIN', 3) != 0) {
	    if (!file_exists(__DIR__ . '/data/' . $domain)) {
		exec('mkdir ' . __DIR__ . '/data/' . $domain . ' 2>&1');
	    }

	    exec('rm -rf ' . __DIR__ . '/data/' . $domain . '/* 2>&1');

	    sleep(2);

	    exec('/usr/local/psa/bin/pleskbackup --domains-name ' . $domain . ' --output-file ' . __DIR__ . '/data/' . $domain . '/backup.tar -c -z 2>&1');

	    exec('tar -xvf ' . __DIR__ . '/data/' . $domain . '/backup.tar -C ' . __DIR__ . '/data/' . $domain . ' 2>&1');

	    exec('mv ' . __DIR__ . '/data/' . $domain . '/*.xml ' . __DIR__ . '/data/' . $domain . '/' . $domain . '.xml 2>&1');

	    for ($i = 0; $i <= 120; $i++) {

		if (file_exists(__DIR__ . '/data/' . $domain . '/' . $domain . '.xml')) {
		    $this->messages(array('status' => 'info', 'mesaj' => 'Xml file created'));
		    break;
		}
		sleep(2);
	    }
	}




	$file = __DIR__ . '/data/' . $domain . '/' . $domain . '.xml';
	if (!file_exists($file)) {
	    echo 'Xml File does not exists';
	    exit;
	}


	$xmlstr = file_get_contents($file);
	$xmlcont = new SimpleXMLElement($xmlstr);
	foreach ($xmlcont as $val) {



	    foreach ($val->phosting as $sys) {
		$data['domain'] = array((string) $val->attributes()->name, (string) $sys->preferences->sysuser->attributes()->name, (string) $sys->preferences->sysuser->password);
	    }

	    foreach ($val->properties as $dns) {

		foreach ($dns->{'dns-zone'}->{'dnsrec'} as $dnsrec) {

		    if (!$dnsrec->attributes()->status) {

			$data['dns'][] = array((string) $dnsrec->attributes()->src, (string) $dnsrec->attributes()->type, (string) $dnsrec->attributes()->dst);
		    }
		}
	    }

	    foreach ($val->mailsystem as $mailler) {

		if (count($mailler->mailusers->mailuser) > 0) {
		    foreach ($mailler->mailusers->mailuser as $k => $mail) {
			$data['mailuser'][] = array((string) $mail->attributes()->name, (string) $mail->properties->password);
		    }
		}
	    }

	    foreach ($val->databases as $db) {
		foreach ($db->database as $dbs) {



		    //plesk global db user check
		    if (isset($db->dbusers)) {
			foreach ($db->dbusers as $dbusers) {
			    foreach ($dbusers as $genel) {

				$anyUser = (string) $genel->attributes()->name;
				$anyPass = (string) $genel->password;
			    }
			}
		    }

		    $dbAdi = (string) $dbs->attributes()->name;
		    $data['database'][$dbAdi]['db'] = $dbAdi;

		    //database kullanıcısı varsa ekle, yoksa ana database kullanıcısı varsa onu ekle..
		    if ($dbs->dbuser) {
			foreach ($dbs->dbuser as $dbuser) {
			    $data['database'][$dbAdi]['dbler'][] = array((string) $dbuser->attributes()->name, (string) $dbuser->password);
			}
		    } else if ($anyUser && $anyPass) {
			$data['database'][$dbAdi]['dbler'][] = array($anyUser, $anyPass);
		    }
		}
	    }
	}
	return $data;
    }

    /**
     * İşlemleri başlat: Controller
     *
     * @param $data array plesk xml dosyadan gelen veriler..
     *
     */
    public function start($data) {

	foreach ($data as $key => $val) {
	    switch ($key) {
		case 'domain':
		    $domain = $val[0];
		    $sonuc = $this->createDomain($val[0], $val[1], $val[2]);
		    $this->messages($sonuc);
		    break;
		case 'mailuser':
		    foreach ($val as $mailuser) {
			$sonuc = $this->createUser($domain, $mailuser[0], $mailuser[1]);
			$this->messages($sonuc);
		    }
		    break;

		case 'database':
		    foreach ($val as $dblist) {
			$dblistesi = $dblist;
			$sonuc = $this->createDb($domain, $dblist['db']);
			$this->messages($sonuc);
			if (isset($dblist['dbler'])) {
			    foreach ($dblist['dbler'] as $dbkey => $dbuser) {
				$sonuc = $this->createDbUser($domain, $dbuser[0], $dbuser[1], $dblist['db']);
				$this->messages($sonuc);
			    }
			}
		    }
		    break;
		case 'dns':
		    foreach ($val as $dns) {

			$sonuc = $this->createDns($domain, $dns[0] . ' ' . $dns[1] . ' ' . $dns[2]);
			$this->messages($sonuc);
		    }

		    break;
	    }
	}
	if (isset($data['database'])) {
	    $sonuc = $this->dbImport($domain, $data['database']);
	    $this->messages($sonuc);
	}
	//eğer -ftp aktifse dosyaları gönder..
	if ($this->ftpPut) {
	    if (strncasecmp(PHP_OS, 'WIN', 3) == 0)
		$sonuc = $this->ftp('c:\test\\', '/public_html');
	    else
		$sonuc = $this->ftp('/var/www/vhosts/' . $domain . '/httpdocs', '/public_html');

	    $this->messages($sonuc);
	}
    }

}


<?php

/*
 *
 * # php main.php domain.com -ftp
 *
 */

try {
    require_once __DIR__ . '/src/sync.php';
    require_once __DIR__ . '/vendor/autoload.php';


    if ($argv[1]) {
	$v = new Virtualmin('root', 'pass', '1.2.3.4');

	$data = $v->readXml($argv[1]);

	//plesk sunucu ip adresi..
	$v->sourceIp = '140cy7o4u.ni.net.tr';

	//argv2 -ftp yazılırsa web dizininide ftp ile gönderir.
	if ($argv[2] == '-ftp')
	    $v->ftpPut = true;

	//başla bakalım..
	$v->start($data);
    }
} catch (Exception $e) {
    echo $e->getMessage();
}

?>

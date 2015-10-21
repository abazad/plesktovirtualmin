Tested Plesk 12 and Virtualmin 4.18 on Centos


Plesk server to Virtualmin server synchronization

    Domain user and password (Create or Update password)
    Domain mail addresses (Create or update mail password)
    Dns Records (Create)
    Database users (Create or update db user password)
    Database (Create and update content)
    Web Files (Transfer files)


Usage:

Virtualmin Setting:

    System Settings -> Virtualmin Conf.-> Defaults for new domains ->Include domain name in usernames : Only to avoid a clash

    System Settings -> Server Templates-> Defaults Setttings ->Mail For Domain -> Format for usernames that include domain : username@domain


Command Line:

php sync.php domain.com

or

php sync.php domain.com -ftp (-ftp : transfer all web files to remote(virtualmin) server)


sync.php file

        //Virtualmin Server Information
       	$v = new Virtualmin('root', 'pass', '2.2.2.2');

        //plesk server ip address for mysql import at the virtualmin.(dbuser@1.1.1.1)
        $v->sourceIp = '1.1.1.1';


tr

Plesk sunucudan virtualmin sunucuya senkronizasyon yapar.

    Domain admin ve parolası
    Domain mail adresleri
    Domain sonradan eklenen dns kayıtları
    Veritabanı kullanıcıları
    Veritabanları
        Veritabanı içeriği her seferinde plesk'den mysldump ile alınır ve Virtualmin'e aktarılır.
    Web dosyaları
        -ftp seçeneği yazılmışsa web dosyalarını da aktarır.

        (Kayıtlar virtualmin'de zaten mevcutsa o zaman sadece kullanıcı parolaları ve database içeriği güncellenir.)

Kullanımı:

İlk olarak virtualmin'de aşağıdaki ayarların yapılması gerekir.

    System Settings -> Virtualmin Conf.-> Defaults for new domains ->Include domain name in usernames : Only to avoid a clash

    System Settings -> Server Templates-> Defaults Setttings ->Mail For Domain -> Format for usernames that include domain : username@domain


Komut satırında

php sync.php domain.com

yada

php sync.php domain.com -ftp (-ftp yazılırsa tüm web dizini de aktarır.)




        //Virtualmin Server Bilgileri
       	$v = new Virtualmin('root', 'pass', '2.2.2.2');


        //plesk server ip adresi. virtualminde bu ip database host kısmına eklenir ve database import işlemi bu sayede yapılır.
        $v->sourceIp = '1.1.1.1';









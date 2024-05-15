# Turnierwebsite

### Datenbank auf Netcup
Einrichtung analog zu Trinkspielapp https://github.com/richardbendler/Trinkspielapp

### Lokale Entwicklungsumgebung
https://www.easyphp.org/documentation/devserver/getting-started.php


### Code auf Server laden
Erstmal dem blankiball-user Rechte für /var/www geben damit es mehr convenient ist, die Dateien mit WinSCP hochzuladen:
sudo apt-get install acl
sudo setfacl -R -m u:blankiball:rwx /var/www
(https://stackoverflow.com/questions/23251963/how-do-i-change-file-permissions-in-ubuntu)

### Alte Websites
sudo nano /etc/apache2/sites-available/...
TODO
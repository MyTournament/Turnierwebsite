# Turnierwebsite

### Datenbank auf Netcup
Einrichtung analog zu Trinkspielapp https://github.com/richardbendler/Trinkspielapp

### Lokale Entwicklungsumgebung
https://www.easyphp.org/documentation/devserver/getting-started.php
Um die Website letztendlich zu öffnen, muss die Index ausgewählt werden, nicht einfach auf REDACTED geklickt werden.


### Code auf Server laden
Erstmal dem REDACTED-user Rechte für /var/www geben damit es mehr convenient ist, die Dateien mit WinSCP hochzuladen:
sudo apt-get install acl
sudo setfacl -R -m u:REDACTED:rwx /var/www
(https://stackoverflow.com/questions/23251963/how-do-i-change-file-permissions-in-ubuntu)

### Alte Websites
sudo nano /etc/apache2/sites-available/...
TODO
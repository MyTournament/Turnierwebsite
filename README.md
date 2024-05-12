# Turnierwebsite

### Datenbank auf Netcup
Einrichtung analog zu Trinkspielapp https://github.com/richardbendler/Trinkspielapp

### Lokale Entwicklungsumgebung
https://www.easyphp.org/documentation/devserver/getting-started.php


### Code auf Server laden
Erstmal dem blankiballä-user Rechte für /var/www geben damit es mehr convenient ist, die Dateien mit WinSCP hochzuladen:
sudo apt-get install acl
sudo setfacl -R -m u:blankiball:rwx /var/www
(https://stackoverflow.com/questions/23251963/how-do-i-change-file-permissions-in-ubuntu)

### Von http auf https weiterleiten
Das Modul mod_rewrite aktivieren
TODO: Wie?

### Alte Websites
sudo nano /etc/apache2/sites-available/000-default.conf
-> Funktioniert aber irgendwie hier nicht (Das File ist schon fur http, aber klappt trotzdem nicht)
sudo systemctl restart apache2
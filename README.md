# Turnierwebsite

### Datenbank auf Netcup
Einrichtung analog zu Trinkspielapp

### Code auf Server laden
Erstmal dem REDACTEDä-user Rechte für /var/www geben damit es mehr convenient ist, die Dateien mit WinSCP hochzuladen:
sudo apt-get install acl
sudo setfacl -R -m u:REDACTED:rwx /var/www

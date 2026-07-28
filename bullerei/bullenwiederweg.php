<?php
// Alter "Bookmark"-Kurzlink - führt jetzt zur Login-Seite (bullerei/home.php) statt direkt (und ohne
// jede Rechteprüfung!) die Website wieder online zu schalten. Das eigentliche Online-Schalten
// passiert erst nach erfolgreichem Admin/Co-Admin-Login dort, siehe edit_website_bullerei.php.
header("Location: /bullerei/home.php");

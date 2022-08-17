<?php
function insert_traffic($conn, $websiteID, $bn, $kategorie, $text) {
    //Kategorien:
    //  1 = Button
    //  2 = Teilnahmeurkunde
    $sql = "INSERT INTO `System_Traffic` (`fk_who`, `fk_kategorie`, `text`, `fk_website`) VALUES (?, ?, ?, ?);";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $bn, $kategorie, $text, $websiteID); //This is called "argument unpacking", and is available since PHP 5.6
    $stmt->execute();
}
?>
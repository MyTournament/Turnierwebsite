<?php
//zum Testen des Interfaces einfach auf die Seite gehen: https://REDACTED.de/website_datachange/edit_interface_test.php
$name = 'ich';
$description = 'lalala';
$fk_random = '5';
$TurnierID = '1';

$bn = "richard";
include_once '../database/db_connection.php';
include_once 'edit_interface.php';

$sql = "INSERT INTO edit_interface_test (name, description, fk_random) VALUES (?, ?, ?)";
$insert_id = myDb_execute($conn, $TurnierID, $bn, "edit_interface_test.php",$sql, array($name, $description, $fk_random));
echo "_INSERT: $insert_id <br/>";
$sqlType = substr($sql, 0, 6);
echo "SQL-Type: $sqlType<br/>";

$sql = "UPDATE edit_interface_test SET fk_random = ? WHERE fk_random = ?";
$insert_id = myDb_execute($conn, $TurnierID, $bn, "edit_interface_test.php 2",$sql, array("4", "3"));
echo "_UPDATE: $insert_id <br/>"; //gibt es nur bei Insert
$sqlType = substr($sql, 0, 6);
echo "SQL-Type: $sqlType<br/>";

$sql = "DELETE FROM edit_interface_test WHERE fk_random = ?";
$insert_id = myDb_execute($conn, $TurnierID, $bn, "edit_interface_test.php 3",$sql, array("4"));
echo "_DELETE: $insert_id <br/>"; //gibt es nur bei Insert
$sqlType = substr($sql, 0, 6);
echo "SQL-Type: $sqlType<br/>";

//https://www.peterkropff.de/site/php/mysqli_stmt_daten.htm

//https://www.php.net/manual/de/security.database.sql-injection.php
/*Beispiel #5 Ein sicherer Weg, eine Abfrage zu erstellen

settype($offset, 'integer');
$query = "SELECT id, name FROM products ORDER BY name LIMIT 20 OFFSET $offset;";

// Beachten Sie %d im Formatstring, %s zu verwenden wäre sinnlos
$query = sprintf("SELECT id, name FROM products ORDER BY name LIMIT 20 OFFSET %d;",
                 $offset);
*/

$sql = "SELECT * FROM edit_interface_test WHERE name = ?";
$sqlType = substr($sql, 0, 6);
echo "SQL-Type: $sqlType<br/>";
/*$stmt = myDb_execute($conn, $TurnierID, $bn, "edit_interface_test.php", $sql, array("ich"));
while ($stmt->fetch()) {
    echo $name.'; ';
}*/
$stmt = $conn->prepare($sql);
$name = "ich";
$stmt->bind_param("s", $name);
$stmt->execute();
$stmt->bind_result($name);
//$stmt->get_result();
//while ($stmt->fetch()) {
while ($row = $stmt->fetch()){
    echo $name.'; ';
    $name = $row['fk_random'];
    echo "x";
    echo "$name";
    //https://qastack.com.de/programming/8321096/call-to-undefined-method-mysqli-stmtget-result
}
/*
$num_of_rows = $result->num_rows;
echo "$num_of_rows";

//FUNZT NICHT:
$result = $stmt->get_result();
//$result = mysqli_stmt_get_result($stmt);
while ($row = $result->fetch_assoc()){
    $name = $row['name'];
    echo "name: $name";
    echo "while";
}
echo "end";*/



/*
Für diejenigen, die nach einer Alternative suchen, $result = $stmt->get_result()habe ich diese Funktion entwickelt, mit der Sie $result->fetch_assoc()das stmt-Objekt nachahmen können, aber direkt verwenden:

function fetchAssocStatement($stmt)
{
    if($stmt->num_rows>0)
    {
        $result = array();
        $md = $stmt->result_metadata();
        $params = array();
        while($field = $md->fetch_field()) {
            $params[] = &$result[$field->name];
        }
        call_user_func_array(array($stmt, 'bind_result'), $params);
        if($stmt->fetch())
            return $result;
    }

    return null;
}
Wie Sie sehen, wird ein Array erstellt und mit den Zeilendaten abgerufen. Da es $stmt->fetch()intern verwendet wird, können Sie es so aufrufen, wie Sie es aufrufen würden mysqli_result::fetch_assoc(stellen Sie nur sicher, dass das $stmtObjekt geöffnet ist und das Ergebnis gespeichert ist):

//mysqliConnection is your mysqli connection object
if($stmt = $mysqli_connection->prepare($query))
{
    $stmt->execute();
    $stmt->store_result();

    while($assoc_array = fetchAssocStatement($stmt))
    {
        //do your magic
    }

    $stmt->close();
}
*/

?>
<center></center>
<hr> -> horizontale Linie
SQL:
$sqlGruppe = 'SELECT * FROM Turnier_Gruppe WHERE id IN (SELECT fk_gruppe FROM Team WHERE fk_turnier = ' . $TurnierID . ') ORDER BY id';


$sql="SELECT * FROM USER WHERE id=".$_GET['id'];
$result=mysql_fetch_row(mysql_query($sql));


echo "<script>console.log('BegegnungsID: $begegnungID')</script>";


<!-- <input size='100' style='height: 30px;' type='submit' name='action' value='<?php echo $a?>:<?php echo $b?>'/> -->


<?php echo "<form action='website_datachange/changegame.php?id=$spielId' method='POST' onSubmit='return checkAGBchangeGame()''>"; 
//$gameID = $_GET['id'];?>

$num_of_rows = $result->num_rows;

<input type="text" name="bn" onkeypress="return /[a-z]/i.test(event.key)" class="Eingabe" placeholder="username" style="color: white" required>


echo " ".__DIR__." ";


include_once '/mnt/web508/d1/34/510124634/htdocs/Turnierwebsite/tourna-dev/database/db_update.php';
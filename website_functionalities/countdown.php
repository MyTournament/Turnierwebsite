<?php
/*echo"
<!--  Countdown -->
<!--Überschrift: ca. 'Anmeldung noch bis zum...'-->

<!-- <h2 id='demo' style='color: white'></h2> -->
<!-- Display the countdown timer in an element-->";*/

//Datum aus Datenbank abfragen
//include_once '../database/db_connection.php'; //Datenbanklogin    
?>
<script>
function countdown(countDownDate){
    // Set the date we're counting down to

    /*$sql = 'SELECT * FROM Turnier_Main WHERE fk_website = '. $websiteId .' ORDER BY order_on_website';
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $TurnierID = $row['id'];
        break; //nur erstes Turnier wird das aktuelle Turnier
    }*/
    /*var countDownDate = new Date("Sep 06, 2021 14:00:00").getTime();*/

    // Update the count down every 1 second
    var x = setInterval(function() {

        // Get todays date and time
        var now = new Date().getTime();

        // Find the distance between now an the count down date
        var distance = countDownDate - now;

        // Time calculations for days, hours, minutes and seconds
        var days = Math.floor(distance / (1000 * 60 * 60 * 24));
        var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        var seconds = Math.floor((distance % (1000 * 60)) / 1000);

        // Display the result in the element with id="demo"
        document.getElementById("demo").innerHTML = days + "d " + hours + "h "
        + minutes + "m " + seconds + "s ";

        // If the count down is finished, write some text 
        if (distance < 0) {
        clearInterval(x);
        document.getElementById("demo").innerHTML = "Mögen die Spiele beginnen!";
        }
    }, 1000);
}
</script>
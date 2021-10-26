<?php
    $test_turnier_id = 0;
    $test_turnier_id = $_GET["test_turnier_id"];
    if($test_turnier_id == NULL){
        $test_turnier_id = $_POST["test_turnier_id"];
    }
    if ($test_turnier_id != 0) {
        //TurnierID überschreiben weil ich in den Test-Modus möchte
        $TurnierID = $testTurniere[$test_turnier_id][1]; //kommt aus variables.php
        $TurnierName = $testTurniere[$test_turnier_id][2];
        echo "
        <table class='th-text-center'> <!-- class='withBorderCollapse'  -->
            <thead style='background-color:#7700FF;'>
                <tr>
                    <td style='color:white; text-align: center;'>Du befindest dich aktuell im Testmodus! ($TurnierName)</td> <!-- #7700FF -->
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style='text-align: center;'>
                        <form style='color:#00FF00;margin: 0 0 0 0;' method='post' action='/'>      
                            <button style='background-color:#7700FF;' class='button primary'>Testmodus verlassen</button>   
                        </form>
                    </td>
                </tr>
            </tbody>
        </table>
        "; 
        //echo "<script>console.log('TurnierID: $TurnierID')</script>";
        // button -> name='content'              
    }
?>
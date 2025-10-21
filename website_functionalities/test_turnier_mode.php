<?php
    $test_turnier_id = 0;
    if (isset($_GET["test_turnier_id"])) { $test_turnier_id = $_GET["test_turnier_id"]; }
    if($test_turnier_id == NULL && isset($_POST["test_turnier_id"])){
        $test_turnier_id = $_POST["test_turnier_id"];
    }
    $history_turnier_id = 0;
    if (isset($_GET["history_turnier_id"])) { $history_turnier_id = $_GET["history_turnier_id"]; }
    if($history_turnier_id == NULL && isset($_POST["history_turnier_id"])){
        $history_turnier_id = $_POST["history_turnier_id"];
    }
    if ($history_turnier_id != 0) {
        //TurnierID überschreiben weil ich in den Test-Modus möchte
        $TurnierID = $history[$history_turnier_id][1]; //kommt aus variables.php
        $TurnierName = $history[$history_turnier_id][2];
        echo "
        <div style='background-color:#7700FF;'>
        <!--<div style='color:white; text-align: right;'>-->
            <div style='text-align: center;position:fixed; top: 2px; right: 2px;'>
                <!--<form style='color:#00FF00;margin: 0 0 0 0;' method='post' action='/'> 
                    <button href='/' style='color:white; background-color:#7700FF;' class='button'><p>🚶 Leave 🚶‍♀️</p></button>   
                </form>-->
                <a href='/' class='button' style='color:white; background-color:#7700FF;'><p>🚶 Leave 🚶‍♀️</p></a>  
            </div>
        <!--</div>-->
        <div style='color:white; text-align: center;'>
            <h3>History</h3>
            <h2>$TurnierName</h2>
        </div> <!-- #7700FF -->
        </div>
        "; 
        //echo "<script>console.log('TurnierID: $TurnierID')</script>";
        // button -> name='content'              
    }else if ($test_turnier_id != 0) {
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

<?php
    $test_turnier_id = 0;
    $test_turnier_id = $_GET["test_turnier_id"];
    if($test_turnier_id == NULL){
        $test_turnier_id = $_POST["test_turnier_id"];
    }
    $history_turnier_id = 0;
    $history_turnier_id = $_GET["history_turnier_id"];
    if($history_turnier_id == NULL){
        $history_turnier_id = $_POST["history_turnier_id"];
    }
    if ($history_turnier_id != 0) {
        //TurnierID überschreiben weil ich in den Test-Modus möchte
        $TurnierID = $history[$history_turnier_id][1]; //kommt aus variables.php
        $TurnierName = $history[$history_turnier_id][2];
        echo "
        <div style='position: relative;top: 0'>
            <div style='position:relative'>
                <table class='th-text-center'> <!-- class='withBorderCollapse'  -->
                    <thead style='background-color:#7700FF;'>
                        <tr>
                            <td style='color:white; text-align: right;'>
                                <!--<div style='text-align: center;position:absolute; top: 2px; right: 2px;'>-->
                                    <form style='color:#00FF00;margin: 0 0 0 0;' method='post' action='/'>      
                                        <button style='color:white; background-color:#7700FF;' class='button'>🔙 History verlassen</button>   
                                    </form>
                                <!--</div>-->
                            </td> <!-- #7700FF -->
                        </tr>
                        <tr>
                            <td style='color:white; text-align: center;'>
                                <h3>History</p>
                                <h2>$TurnierName</h2>
                                <i> Du befindest dich in der History-Ansicht. 
                                Alle Informationen, die Teams und Spiele betreffen, wurden vom gewünschten Turnier geladen. 
                                Alle sonstigen Infos bleiben aber die vom aktuellen Turnier.
                            </td> <!-- #7700FF -->
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style='text-align: center;'>
                                
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
        </div>
        <!-- <div style='background-color:#7700FF; text-align: center;'>
                <h3>History</p>
                <h2>$TurnierName</h2>
                <i> Du befindest dich in der History-Ansicht. 
                Alle Informationen, die Teams und Spiele betreffen, wurden vom gewünschten Turnier geladen. 
                Alle sonstigen Infos bleiben aber die vom aktuellen Turnier.
                <br/><br/>
                <form style='color:#00FF00;margin: 0 0 0 0;' method='post' action='/'>      
                    <button style='color:white; background-color:#5600b8;' class='button'>History verlassen</button>   
                </form>
                <br/>
            </div>-->
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
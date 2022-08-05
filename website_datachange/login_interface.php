<?php
function get_rights_of_user($conn, $TurnierID, $bn, $pw, $begegnungId){
  //LOGIN
  //$bn = $_POST['bn'];
  //$pw = $_POST['pw'];
  $successfulLogin = 0; //false

  //FALL: Team-Login -> Bearbeitungsrechte nur für eigene Begegnungen
  $sqlLogin = "SELECT * FROM `Turnier_Team` WHERE kuerzel = '$bn' AND `password` = '$pw' ORDER BY ID";
  $resultLogin = $conn->query($sqlLogin);
  $spielGehoertZuTeam = 0; //false
  $teamBearbeitungsrecht = 0;
  while ( !empty( $rowLogin = $resultLogin->fetch_assoc() ) ){
      $successfulLogin = 1;
      $teamBearbeitungsrecht = $rowLogin["bearbeitungsrechte"];
      //echo "<script>console.log('Du bist eingeloggt als Team.')</script>";
      //checken ob Begegnung zu Team-Kürzel passt, das sich eingeloggt hat
      $sql = "SELECT * FROM Turnier_Team, Turnier_Begegnung WHERE Turnier_Begegnung.id = '$begegnungId' AND ((Turnier_Begegnung.fk_heimteam IN (SELECT id FROM Turnier_Team WHERE kuerzel = '$bn' AND `password` = '$pw')) OR (Turnier_Begegnung.fk_auswaertsteam IN (SELECT id FROM Turnier_Team WHERE kuerzel = '$bn' AND `password` = '$pw')))"; // AND fk_turnier = '$TurnierID'
      $result = $conn->query($sql);
      while ( !empty( $row = $result->fetch_assoc() ) ){
        $spielGehoertZuTeam = 1;
        //echo "<script>console.log('Das Spiel gehört zu deinem Team. Das ist gut.')</script>";
      }
  }
  $team_rights = [
    "successfulLogin" => $successfulLogin,
    "teamBearbeitungsrecht" => $teamBearbeitungsrecht,
    "spielGehoertZuTeam" => $spielGehoertZuTeam,
  ];

  
  //FALL: Account-Login -> Bearbeitungsrechte für alle Begegnungen
  $rechte_neue_admins = 0;
  $rechte_neue_co_admins = 0;
  $rechte_restliche_rollen_vergeben = 0;
  $rechte_turnier_settings = 0;
  $rechte_cms = 0;
  $rechte_teams = 0;
  $rechte_backstage = 0;
  $rechte_alle_spiele = 0;
  $sqlLoginAccount = "SELECT * FROM `System_Benutzer_in`, `System_Benutzer_in_Relation_Rolle`, `System_Benutzer_in_Rolle` WHERE System_Benutzer_in.id = System_Benutzer_in_Relation_Rolle.fk_benutzer_in AND System_Benutzer_in_Rolle.id = System_Benutzer_in_Relation_Rolle.fk_rolle AND `Benutzername` = '$bn' AND `Passwort` = '$pw'"; //AND fk_rechte <= 20 
  $resultLoginAccount = $conn->query($sqlLoginAccount);
  while ( !empty( $rowLoginAccount = $resultLoginAccount->fetch_assoc() ) ){
    if($rowLoginAccount['rechte_neue_admins'] == 1){ $rechte_neue_admins = 1; }
    if($rowLoginAccount['rechte_neue_co_admins'] == 1){ $rechte_neue_co_admins = 1; }
    if($rowLoginAccount['rechte_restliche_rollen_vergeben'] == 1){ $rechte_restliche_rollen_vergeben = 1; }
    if($rowLoginAccount['rechte_turnier_settings'] == 1){ $rechte_turnier_settings = 1; }
    if($rowLoginAccount['rechte_cms'] == 1){ $rechte_cms = 1; }
    if($rowLoginAccount['rechte_teams'] == 1){ $rechte_teams = 1; }
    if($rowLoginAccount['rechte_backstage'] == 1){ $rechte_backstage = 1; }
    if($rowLoginAccount['rechte_alle_spiele'] == 1){ $rechte_alle_spiele = 1; }
    
    $account_rights = [
      "rechte_neue_admins" => $rechte_neue_admins,
      "rechte_neue_co_admins" => $rechte_neue_co_admins,
      "rechte_restliche_rollen_vergeben" => $rechte_restliche_rollen_vergeben,
      "rechte_turnier_settings" => $rechte_turnier_settings,
      "rechte_cms" => $rechte_cms,
      "rechte_teams" => $rechte_teams,
      "rechte_backstage" => $rechte_backstage,
      "rechte_alle_spiele" => $rechte_alle_spiele,
    ];
    //$successfulLogin = 1;
    //$teamBearbeitungsrecht = 1;
    //echo "<script>console.log('Du bist eingeloggt mit deinem Account und hast damit volle Bearbeitungsrechte.')</script>";
  }
  $rights = [
    "account_rights" => $account_rights,
    "team_rights" => $team_rights,
  ];
  return $rights;
  
}

?>
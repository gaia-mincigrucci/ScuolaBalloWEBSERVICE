<?php
header("Content-Type: application/json; charset=UTF-8");

$host = 'localhost';
$nome_db = 'db_scuolaballo';
$utente = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$nome_db;charset=utf8", $utente, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["stato" => "errore", "msg" => "Database non raggiungibile"]);
    exit;
}

$azione = isset($_GET['azione']) ? $_GET['azione'] : '';

if ($azione == 'catalogo') {
    
    $query = $pdo->query("SELECT * FROM corsi WHERE posti_disponibili > 0");
    $elenco_corsi = $query->fetchAll(PDO::FETCH_ASSOC);
    
    http_response_code(200);
    echo json_encode(["stato" => "successo", "dati" => $elenco_corsi]);

} elseif ($azione == 'prenota') {
    
    $dati_ricevuti = json_decode(file_get_contents("php://input"));
    
    if (!empty($dati_ricevuti->id_corso) && !empty($dati_ricevuti->email)) {
        
        $check = $pdo->prepare("SELECT posti_disponibili FROM corsi WHERE id = ?");
        $check->execute([$dati_ricevuti->id_corso]);
        $corso = $check->fetch(PDO::FETCH_ASSOC);
        
        if ($corso && $corso['posti_disponibili'] > 0) {
            
            $pdo->beginTransaction();
            
            try {
                $sql_ins = $pdo->prepare("INSERT INTO prenotazioni (corso_id, email_allievo) VALUES (?, ?)");
                $sql_ins->execute([$dati_ricevuti->id_corso, $dati_ricevuti->email]);
                
                $sql_upd = $pdo->prepare("UPDATE corsi SET posti_disponibili = posti_disponibili - 1 WHERE id = ?");
                $sql_upd->execute([$dati_ricevuti->id_corso]);
                
                $pdo->commit();
                
                http_response_code(201);
                echo json_encode(["stato" => "ok", "msg" => "Iscrizione completata con successo!"]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(["stato" => "errore", "msg" => "Errore tecnico durante il salvataggio"]);
            }
            
        } else {
            http_response_code(400);
            echo json_encode(["stato" => "errore", "msg" => "Spiacenti, i posti per questo corso sono terminati"]);
        }
        
    } else {
        http_response_code(400);
        echo json_encode(["stato" => "errore", "msg" => "Dati mancanti (id_corso o email)"]);
    }

} else {
    http_response_code(404);
    echo json_encode(["stato" => "errore", "msg" => "Servizio non trovato. Usa ?azione=catalogo"]);
}
?>

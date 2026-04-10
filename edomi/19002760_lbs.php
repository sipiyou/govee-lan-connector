###[DEF]###
[name           = Govee LAN :: Connector v1.01 ]

[e#1 trigger    = (Re)Start/Stopp ]
[e#2 important  = Poll-Intervall in Sek.#init=0 ]
[e#3 trigger    = Poll jetzt ]
[e#4            = Verify-TTL in Sek.#init=30 ]
[e#5            = Max. Verify-Versuche#init=6 ]

[e#8 important  = Admin-Interface#init=1 ]
[e#9            = DEBUG#init=0 ]

###[/DEF]###

###[HELP]###
<a class="cmdButton" href="../Govee/govee_admin.php" target="_goveeAdmin">Administration</a>

Dieser LBS steuert Govee-Lampen über das lokale Netzwerk (LAN Control API).

Der Baustein darf nur einmal im Projekt verwendet werden!

<b>Hinweis:</b> Nach Änderungen in der Administration (Geräte, KO-Zuordnungen) muss der LBS neu gestartet werden (E1 = 0, dann E1 = 1).

<b>Voraussetzung:</b> "LAN Control" in der Govee App unter Geräte &rsaquo; Einstellungen aktivieren.

E1 : Betriebsmodus
     0 = LBS stoppen
     1 = LBS starten

E2 : Poll-Intervall in Sekunden (Status abfragen)
     0 = kein Polling
     Default = 0 (kein Polling)

E3 : Poll-Trigger – löst sofort einen Statusabruf aus
     (z.B. nach dem Einschalten einer Steckdose)

E4 : Verify-TTL in Sekunden — maximale Gesamtdauer für Command-Verify-Versuche
     (0 = unbegrenzt)
     Default = 30

E5 : Maximale Anzahl Wiederholversuche bei fehlgeschlagenem Command-Verify
     Default = 3

E8 : Admin-Interface aktivieren (=1) oder deaktivieren (=0)
E9 : Debug: [0..3], 0=Kritisch, 1=Info, 2=Debug, 3=Zugewiesen (writeGA)

<h2>Command-Verify</h2>

Jeder Steuerbefehl (setPower, setBrightness usw.) wird nach dem Senden automatisch
verifiziert. Der LBS fragt dazu 2 Sekunden nach dem Befehl den Gerätestatus ab und
vergleicht den gemeldeten Wert mit dem gesendeten.

<b>Ablauf bei nicht erreichbarem Gerät (z.B. Steckdose gerade eingeschaltet):</b>

  1. Befehl wird sofort per UDP gesendet (kann verloren gehen, Gerät noch offline)
  2. Nach 2s: Statusabfrage — kommt keine Antwort, gilt das Gerät als offline
  3. Solange der Verify aussteht, wird alle 2s erneut gepollt bis das Gerät antwortet
  4. Sobald das Gerät antwortet: Status wird geprüft
       &rarr; Wert stimmt    → fertig
       &rarr; Wert stimmt nicht → Befehl wird erneut gesendet (Versuch 1 von E5)
  5. Erneuter Verify-Poll nach 2s, usw.

Offline-Timeouts (Gerät antwortet nicht) zählen <b>nicht</b> gegen E5 — nur tatsächliche
Mismatches nach erfolgreicher Antwort (z.B. UDP-Paketverlust).
Nach Ablauf von E4 Sekunden wird der ausstehende Verify verworfen.

Der LBS installiert folgende Dateien nach

EDOMI_ROOT/www/Govee:
 govee_admin.php, ko_picker.php, ko_picker.css, ko_picker.js

EDOMI_ROOT/main/include/php/Govee:
 GoveeLanClient.php

<h2>Unterstützte KO-Typen je Gerät</h2>
<pre>
  setPower       : bool/int  Lampe ein (1) oder aus (0)
  setBrightness  : int       Helligkeit 0..100 (%)
  setColor       : string    Farbe als RRGGBB (z.B. FF8000) oder #RRGGBB
  setColorTemp   : int       Farbtemperatur in Kelvin (2000..6535)
  setHSV         : string    Farbe+Helligkeit als HHSSVV (je 00..FF)
                             H=Farbton 0-FF (0°..360°), S=Sättigung 0-FF, V=Helligkeit 0-FF
                             Setzt Farbe und Helligkeit in einem KO (ideal für Visu-Farbwähler)

  getOnline      : int       Erreichbarkeit: 1=online, 0=offline
  getPower       : int       Status: 1=an, 0=aus
  getBrightness  : int       Status Helligkeit 0..100
  getColor       : string    Status Farbe als RRGGBB
  getColorTemp   : int       Status Farbtemperatur in Kelvin
  getHSV         : string    Status Farbe+Helligkeit als HHSSVV (H/S aus RGB, V aus Helligkeit)
</pre>

<h2>Disclaimer</h2>
<b>(c),(w) 2026 by Nima Ghassemi Nejad (ngn928@web.de)

The code provided in this release is the intellectual property of Nima Ghassemi Nejad, and is protected by copyright laws. By accessing and using this code, you agree to the following terms and conditions:

Permission: This code is made available for personal, educational, and non-commercial use only. No part of this code may be used, copied, reproduced, modified, distributed, or transmitted in any form or by any means without the prior written permission from the author.

Commercial Projects: The use of this code in commercial projects or for commercial purposes is strictly prohibited without obtaining explicit written permission from the author. This includes, but is not limited to, using the code in products, services, or applications that are intended for sale, licensing, or any form of commercial distribution.

Modifications: You are not allowed to modify or alter this code without obtaining explicit written permission from the author. Any modifications made to the code without proper authorization may infringe upon the intellectual property rights of the autor.

No Warranty: This code is provided "as is" without any warranty, express or implied. The author makes no representations or warranties regarding the accuracy, functionality, or suitability of this code for any particular purpose. You acknowledge that the use of this code is at your own risk.

Limitation of Liability: In no event shall the author be liable for any direct, indirect, incidental, special, exemplary, or consequential damages arising out of the use or inability to use this code, even if the author has been advised of the possibility of such damages.

Legal Compliance: You agree to comply with all applicable laws, regulations, and third-party rights when using this code. You are solely responsible for ensuring that your use of this code is in compliance with all relevant legal obligations.

If you have any questions or require further information regarding the use of this code, please contact Nima at ngn928@web.de.

By using this code, you acknowledge that you have read, understood, and agreed to the terms and conditions outlined in this disclaimer.    
</b>
###[/HELP]###

###[LBS]###
<?

/*
Changelog:
==========
v1.00  03.04.2026 NG initial release
 1.01  10.04.2026 NG bugfix für E1=0/2 beim Systemstart und dann E1=1
*/

function LB_LBSID_debug($debugLevel, $thisTxtDbgLevel, $str) {
    if ($thisTxtDbgLevel <= $debugLevel) {
        $dbgTxts = array("Kritisch", "Info", "Debug", "Zugewiesen");
        writeToCustomLog("LBS_GOVEE_LBSID", $dbgTxts[$thisTxtDbgLevel], $str);
    }
}

function LB_LBSID_installAdmin($wwwDir, $debugLevel) {
    if (!is_dir($wwwDir)) {
        mkdir($wwwDir, 0755, true);
    }

    $adminFile = $wwwDir . "/govee_admin.php";
    $data = gzuncompress(base64_decode("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"));
    $data = str_replace("__INSERT_EDOMI_PATH__", MAIN_PATH, $data);
    if (!file_put_contents($adminFile, $data)) {
        LB_LBSID_debug($debugLevel, 0, "Admin ($adminFile) konnte nicht erstellt werden!");
    } else {
        LB_LBSID_debug($debugLevel, 1, "Admin ($adminFile) aktiviert.");
    }

    $staticFiles = array(
        "ko_picker.php" => "eNrlV9tu20YQfc9XjAM1SwJ0aiAvQV3aaBM5FZw0hiKgCASBWJMrayOKSy1XSQTHQL+rn9Mv6eyNF1mWLechDxVgmTs7Mztz5sws9etpOSufAJ9CcMCriqmgl7zpj8aEpoqLgkxC+PYNDniRUCnpenM3gjGZi5FkjESATx8YlenMPr9h6kzkGZPoJIRrkEytZHEMNzBjFMUBeSUKxQp1OFqX7BegZZnzlGrHP3+q0Ht4bOLaOBLiOAZ/qPbbm5pTBhnEsKBfg6MIAl6o0Bv6bTQ9PYUXRyH6dTYVmownuJRMP/YW62qZ88OT5YrJdfD0Q/9t/9UIeBZBQRcMzobv3wHLxIJfSPGJpeo5y7gaCqGegPv89Ud/2IeSSsyLZ3ET229/vjZOssuYaKtzQWqj98PX/SH8/hEqIZU/7alPH2ML4cuM50wvdJgoOTyZMpXOElpVIg0QX5/ReKJzIjwjEJ9YIHrSrLFYRDs2G1pmFhOdPldssR8WEVxR/afW5R2wnIsNUGyAHVDqxB+TsAnapgt75IvSK2qVKyV5cYVEwU2UGX4QEhoNTKzr0YrQfFLTZ1BMBR5frPL84bh5buxgkwOsDVUbHHj2DO4lgottvB8yPriuSS3VZGHpTIDuz4QVqchY4Dussu7cAp2ZAlmheUSR3Wwr6jgnoZ4KLK/Yjn53o8V0/BIzIz8ReN7ALRnNE1altGSJrWuA3wvvbVlXN0QrtMWC0Tw/e8QY2KeEGx2/L8WbCMed+tkef3QJv6/fo7qTH9r5Zni+HZz3gfSWBLseHTXr22PQ6b8bjODlURez6/tRu/5xowFhVjOzl8zFBU/nTCaXK55nFygPan2PnzZp1Tg0s+VGt8LtHttspgf1THMFG1jm4s5LUm/ZLO39KJHDRvnF0XHnhrXDzpxp3J2ggfa9k0X3EqY18bTX/aedDm0Lvhoj46hRi20OOug7R9kgc8wwKGBdaZGySgk348aTBn2d+/93kGi++5zTldRbOgaPIko/8wr5mjklH6nX1bX1zwdxTTsUt145nUJUO7NN7hamzb3OcedNtpW1V9Cvopd4V8xd8N3XpeaoFibbvLRfnlp5b1dtIPN03BKfzVyHty2umojbw3LG7ahcZQyEiWSfUY8FRorUsdJSlLXk3lbwq1vNYDzYfrhpGJ/mAs9DacbNv+mqMLNp62Ds+YbtjMOoM4VMyRFJVd3JN/6j+LabbtcO7lVRzfhUBTaLaDexwv2J5aaRY7euhv3FBXxR5rqkBP79+x/AH2c2AlOy/wBPemia",
        "ko_picker.css" => "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",
        "ko_picker.js"  => "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",
    );
    foreach ($staticFiles as $filename => $encoded) {
        $dest = $wwwDir . "/" . $filename;
        $fileData = gzuncompress(base64_decode($encoded));
        if (!file_put_contents($dest, $fileData)) {
            LB_LBSID_debug($debugLevel, 0, "$filename konnte nicht erstellt werden!");
        }
    }
}

function LB_LBSID_installLib($libDir, $debugLevel) {
    if (!is_dir($libDir)) {
        mkdir($libDir, 0755, true);
    }
    $libFile = $libDir . "/GoveeLanClient.php";
    $data = gzuncompress(base64_decode("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"));
    if (!file_put_contents($libFile, $data)) {
        LB_LBSID_debug($debugLevel, 0, "Lib ($libFile) konnte nicht erstellt werden!");
    } else {
        LB_LBSID_debug($debugLevel, 1, "Lib ($libFile) installiert.");
    }
}

function LB_LBSID($id) {
    $ADMIN_WWW = MAIN_PATH . "/www/Govee";
    $LIB_DIR   = MAIN_PATH . "/main/include/php/Govee";

    if ($E = logic_getInputs($id)) {
        $vars    = logic_getVars($id);
        $running = (int)($vars[1] ?? 0) === 1;

        // E1=0 nur weiterleiten wenn EXEC gerade läuft — sonst verwerfen (z.B. Systemstart-Sequenz)
        if (!($E[1]['refresh'] && (int)$E[1]['value'] === 0 && !$running)) {
            logic_setInputsQueued($id, $E);
        }

        if ($E[8]['refresh']) {
            switch ($E[8]['value']) {
            case 0:
                $adminFile = $ADMIN_WWW . "/govee_admin.php";
                if (file_exists($adminFile)) unlink($adminFile);
                LB_LBSID_debug($E[9]['value'], 1, "Admin deaktiviert.");
                break;
            case 1:
                LB_LBSID_installAdmin($ADMIN_WWW, $E[9]['value']);
                break;
            }
        }

        if (($E[1]['refresh'] == 1) && ($E[1]['value'] == 1) && !$running) {
            LB_LBSID_installLib($LIB_DIR, $E[9]['value']);
            if ($E[8]['value'] == 1) {
                LB_LBSID_installAdmin($ADMIN_WWW, $E[9]['value']);
            }
            logic_setVar($id, 1, 1);
            logic_callExec(LBSID, $id, false);
        }
    }
}
 ?>

###[/LBS]###

###[EXEC]###
<?php
 //require('wrapper.php');
 require(dirname(__FILE__)."/../../../../main/include/php/incl_lbsexec.php");

 $GOVEE_LIB = MAIN_PATH . "/main/include/php/Govee/GoveeLanClient.php";

 if (!file_exists($GOVEE_LIB)) {
     exec_debug(0, "GoveeLanClient nicht vorhanden. Bitte LBS neu starten (E1=1).");
     sql_disconnect();
     die();
 }

 include_once $GOVEE_LIB;

 sql_connect();
 set_time_limit(0);
 set_error_handler("LB_LBSID_execErrorHandler");

 $E = logic_getInputs($id);
 if (isset($hasWrapper)) $E = W_logic_getInputs($id);

 $debugLevel   = (int)$E[9]['value'];
 $pollInterval = (int)$E[2]['value'];
 $cmdTTL       = (int)$E[4]['value']; // Verify-TTL in Sekunden, 0 = unbegrenzt
 $maxRetries   = (int)$E[5]['value']; // Max. Verify-Versuche bei Mismatch
 $lbsID        = LBSID;

 $dbgTxts = array("Kritisch", "Info", "Debug", "Zugewiesen");

 $govee = new GoveeLanClient();
 $govee->debug = ($debugLevel >= 2);
 $govee->openRecvSocket();

 // DB-Mapping laden: deviceID => [ip, koMap[koType=>koID]]
 $devices = array();
 // setKos: dynEingang# => [deviceID, ip, koType, koID]
 $setKos  = array();

 exec_debug(1, "Lade Gerätekonfiguration aus DB...");

 $res = $mysqli_govee = mysqli_connect("localhost", "root", "", "");
 $sql = "SELECT d.id, d.deviceName, d.deviceIP, k.koType, k.koID
         FROM edomiProject.goveeDevice d
         LEFT JOIN edomiProject.goveeKoMap k ON d.id = k.deviceID
         ORDER BY d.id, k.koType";

 $result = mysqli_query($mysqli_govee, $sql);
 if ($result) {
     while ($row = mysqli_fetch_assoc($result)) {
         $devID = (int)$row['id'];
         if (!isset($devices[$devID])) {
             $devices[$devID] = array(
                 'name'  => $row['deviceName'],
                 'ip'    => $row['deviceIP'],
                 'koMap' => array(),
             );
         }
         if (!empty($row['koType']) && (int)$row['koID'] > 0) {
             $devices[$devID]['koMap'][$row['koType']] = (int)$row['koID'];
         }
     }
 }

 exec_debug(1, "Geräte geladen: " . count($devices));

 // Schneller Lookup ip => devID für den Empfangspfad
 $deviceByIp = array();
 foreach ($devices as $devID => $dev) {
     $deviceByIp[$dev['ip']] = $devID;
 }

 // Alte dynamische Eingänge löschen
 sql_call("DELETE FROM edomiLive.RAMlogicLink WHERE eingang >= 10 AND elementid=" . (int)$id);

 // Dynamische Eingänge für set-KOs registrieren
 $dynIdx = 10;
 $setTypes = array('setPower', 'setBrightness', 'setColor', 'setColorTemp', 'setHSV');
 foreach ($devices as $devID => $dev) {
     foreach ($setTypes as $koType) {
         if (isset($dev['koMap'][$koType]) && $dev['koMap'][$koType] > 0) {
             $koID = $dev['koMap'][$koType];
             $setKos[$dynIdx] = array(
                 'devID'  => $devID,
                 'ip'     => $dev['ip'],
                 'koType' => $koType,
                 'koID'   => $koID,
             );
             $sql = "INSERT INTO edomiLive.RAMlogicLink "
                  . "(elementid, functionid, eingang, linktyp, linkid, ausgang, init, refresh, value) "
                  . "VALUES ($id, $lbsID, $dynIdx, 0, $koID, NULL, 2, 0, '0')";
             sql_call($sql);
             exec_debug(2, "Dyn-Eingang E$dynIdx → $koType (KO $koID) Gerät: " . $dev['name']);
             $dynIdx++;
         }
     }
 }

 if (empty($devices)) {
     exec_debug(1, "Keine Geräte konfiguriert. Bitte Admin aufrufen.");
 }

 $lastPoll     = 0;
 $lastStatus   = array(); // [koID => letzter geschriebener Wert]
 $initialPoll  = true;    // einmaliger Status-Abruf beim Start
 $pendingPoll  = 0;       // Zeitstempel für verzögerten Poll nach sendCmd (0 = inaktiv)
 $pendingIps   = array(); // [ip => true] — Requests gesendet, Antwort ausstehend
 $pollDeadline = 0;       // Zeitstempel ab dem nicht antwortende Geräte als offline gelten
 $pendingVerify    = array(); // [devID => ['cmds'=>[koType=>val], 'attempts'=>int, 'expiry'=>float]]
 exec_debug(1, "LBS gestartet. Poll-Intervall: {$pollInterval}s");

 do {
     // Status-Polling
     $now = microtime(true);
     $doPoll = !empty($devices) && (
         $initialPoll ||
         ($pendingPoll > 0 && $now >= $pendingPoll) ||
         ($pollInterval > 0 && ($now - $lastPoll) >= $pollInterval)
     );
     // Poll-Requests senden (wenn fällig) — non-blocking, Antworten kommen asynchron
     if ($doPoll) {
         $initialPoll  = false;
         $pendingPoll  = 0;
         $lastPoll     = $now;
         $ips = array_column($devices, 'ip');
         $govee->sendStatusRequests($ips);
         $pendingIps   = array_fill_keys($ips, true);
         $pollDeadline = $now + GoveeLanClient::CMD_TIMEOUT;
     }

     // Antworten verarbeiten — läuft jede Iteration, kehrt sofort zurück wenn nichts anliegt
     foreach ($govee->receiveAvailable() as $ip => $status) {
         if (!isset($deviceByIp[$ip])) continue;
         unset($pendingIps[$ip]);
         $devID = $deviceByIp[$ip];
         $dev   = $devices[$devID];
         $km    = $dev['koMap'];

         LB_LBSID_writeGA($km['getOnline'] ?? 0, 1, $lastStatus, $debugLevel);
         if (isset($km['getPower']) && $km['getPower'] > 0) {
             LB_LBSID_writeGA($km['getPower'], (int)($status['onOff'] ?? 0), $lastStatus, $debugLevel);
         }
         if (isset($km['getBrightness']) && $km['getBrightness'] > 0) {
             LB_LBSID_writeGA($km['getBrightness'], (int)($status['brightness'] ?? 0), $lastStatus, $debugLevel);
         }
         if (isset($km['getColor']) && $km['getColor'] > 0 && isset($status['color'])) {
             $c = $status['color'];
             LB_LBSID_writeGA($km['getColor'], sprintf("%02X%02X%02X", (int)$c['r'], (int)$c['g'], (int)$c['b']), $lastStatus, $debugLevel);
         }
         if (isset($km['getColorTemp']) && $km['getColorTemp'] > 0 && isset($status['colorTemInKelvin'])) {
             LB_LBSID_writeGA($km['getColorTemp'], (int)$status['colorTemInKelvin'], $lastStatus, $debugLevel);
         }
         if (isset($km['getHSV']) && $km['getHSV'] > 0 && isset($status['color'])) {
             $c   = $status['color'];
             $hsv = LB_LBSID_convertRGBtoHSV((int)$c['r'], (int)$c['g'], (int)$c['b']);
             // V aus Gerätehelligkeit (0-100 → 0-255), nicht aus RGB-Berechnung
             $hsv[2] = (int)round((int)($status['brightness'] ?? 0) / 100.0 * 255);
             LB_LBSID_writeGA($km['getHSV'], sprintf("%02X%02X%02X", $hsv[0], $hsv[1], $hsv[2]), $lastStatus, $debugLevel);
         }
         if ($debugLevel >= 2) exec_debug(2, "Status " . $dev['name'] . ": " . json_encode($status));

         // Command-Verify: gesendete Befehle gegen aktuellen Status prüfen
         if (isset($pendingVerify[$devID])) {
             $pv = &$pendingVerify[$devID];
             $failedCmds = array();
             foreach ($pv['cmds'] as $koType => $expected) {
                 if (!LB_LBSID_verifyCmd($koType, $expected, $status)) {
                     $failedCmds[$koType] = $expected;
                 }
             }
             if (empty($failedCmds)) {
                 exec_debug(2, "Verify OK: " . $dev['name']);
                 unset($pendingVerify[$devID]);
             } elseif ($pv['attempts'] < $maxRetries) {
                 $pv['attempts']++;
                 $pv['cmds'] = $failedCmds;
                 exec_debug(1, "Verify fehlgeschlagen (" . $pv['attempts'] . "/$maxRetries) " . $dev['name'] . ": " . implode(', ', array_keys($failedCmds)));
                 foreach ($failedCmds as $koType => $val) {
                     LB_LBSID_sendCmd($govee, $dev['ip'], $koType, $val, $debugLevel);
                 }
                 $pendingPoll = $now + 2.0;
             } else {
                 exec_debug(0, $dev['name'] . ": Max. Verify-Versuche erreicht, Befehle verworfen.");
                 unset($pendingVerify[$devID]);
             }
             unset($pv);
         }
     }

     // Timeout: Geräte die nicht geantwortet haben → offline markieren
     if ($pollDeadline > 0 && $now >= $pollDeadline) {
         foreach ($pendingIps as $ip => $_) {
             if (!isset($deviceByIp[$ip])) continue;
             $devID = $deviceByIp[$ip];
             $dev   = $devices[$devID];
             exec_debug(1, "Kein Status von " . $dev['name'] . " ($ip)");
             LB_LBSID_writeGA($dev['koMap']['getOnline'] ?? 0, 0, $lastStatus, $debugLevel);
         }
         $pendingIps   = array();
         $pollDeadline = 0;

         // Abgelaufene Verify-Einträge verwerfen
         foreach ($pendingVerify as $devID => $pv) {
             if ($now >= $pv['expiry']) {
                 exec_debug(1, "Verify-TTL abgelaufen: " . $devices[$devID]['name'] . ", Befehle verworfen.");
                 unset($pendingVerify[$devID]);
             }
         }

         // Noch ausstehende Verifies → erneut pollen (Gerät offline oder Mismatch)
         if (!empty($pendingVerify) && $pendingPoll == 0) {
             $pendingPoll = $now + 2.0;
         }
     }

     // Queued Inputs verarbeiten
     if ($E = logic_getInputsQueued($id)) {
         if (isset($hasWrapper)) $E = W_logic_getInputsQueued($id);

         if (isset($E[1]['refresh']) && $E[1]['refresh'] && ((int)$E[1]['value'] !== 1)) {
             exec_debug(1, "E1=" . (int)$E[1]['value'] . ": LBS wird beendet.");
             break;
         }

         if (isset($E[4]['refresh']) && $E[4]['refresh']) {
             $cmdTTL = (int)$E[4]['value'];
             exec_debug(2, "E4: Verify-TTL aktualisiert: {$cmdTTL}s");
         }

         if (isset($E[5]['refresh']) && $E[5]['refresh']) {
             $maxRetries = (int)$E[5]['value'];
             exec_debug(2, "E5: Max. Verify-Versuche aktualisiert: {$maxRetries}");
         }

         if (isset($E[3]['refresh']) && $E[3]['refresh']) {
             exec_debug(2, "E3: Poll-Trigger empfangen.");
             $pendingPoll = $now;
         }

         foreach ($E as $eNum => $eVal) {
             if ($eNum < 10) continue;
             if (!$eVal['refresh']) continue;
             if (!isset($setKos[$eNum])) continue;

             $info   = $setKos[$eNum];
             $rawVal = getGADataFromID($info['koID'], 0, "value");
             $val    = isset($rawVal['value']) ? $rawVal['value'] : 0;

             if ($debugLevel >= 3) exec_debug(3, "sendCmd " . $devices[$info['devID']]['name'] . " (" . $info['ip'] . ") " . $info['koType'] . "=$val");

             LB_LBSID_sendCmd($govee, $info['ip'], $info['koType'], $val, $debugLevel);
             // pendingVerify anlegen/aktualisieren (neuester Wert gewinnt je koType)
             if (!isset($pendingVerify[$info['devID']])) {
                 $pendingVerify[$info['devID']] = array(
                     'cmds'     => array(),
                     'attempts' => 0,
                     'expiry'   => $now + ($cmdTTL > 0 ? (float)$cmdTTL : PHP_INT_MAX),
                 );
             }
             $pendingVerify[$info['devID']]['cmds'][$info['koType']] = $val;
             $pendingPoll = $now + 2.0;
         }
     }

     usleep(250000); // 250ms

 } while (getSysInfo(1) >= 1);

 exec_debug(1, "LBS beendet.");
 $govee->closeRecvSocket();
 mysqli_close($mysqli_govee);
 logic_setVar($id, 1, 0);
 sql_disconnect();

 // ---------------------------------------------------------------

 function LB_LBSID_sendCmd($govee, $ip, $koType, $val, $debugLevel) {
     switch ($koType) {
     case 'setPower':
         $govee->setPower($ip, (bool)(int)$val);
         break;
     case 'setBrightness':
         $govee->setBrightness($ip, (int)$val);
         break;
     case 'setColor':
         // RRGGBB oder #RRGGBB
         $hex = ltrim(trim((string)$val), '#');
         if (strlen($hex) === 6 && ctype_xdigit($hex)) {
             $r = hexdec(substr($hex, 0, 2));
             $g = hexdec(substr($hex, 2, 2));
             $b = hexdec(substr($hex, 4, 2));
             $govee->setColor($ip, $r, $g, $b);
         } else {
             exec_debug($debugLevel, 1, "setColor: Ungültiger Wert '$val' (erwartet RRGGBB)");
         }
         break;
     case 'setColorTemp':
         $govee->setColorTemp($ip, (int)$val);
         break;
     case 'setHSV':
         // HHSSVV oder #HHSSVV  (H/S/V je 0-FF = 0-255)
         $hex = ltrim(trim((string)$val), '#');
         if (strlen($hex) === 6 && ctype_xdigit($hex)) {
             $h = hexdec(substr($hex, 0, 2));
             $s = hexdec(substr($hex, 2, 2));
             $v = hexdec(substr($hex, 4, 2));
             list($r, $g, $b) = LB_LBSID_hsvToRgb($h, $s, 255); // Farbe bei voller Helligkeit
             $govee->setColor($ip, $r, $g, $b);
             $govee->setBrightness($ip, (int)round($v / 255.0 * 100));
         } else {
             exec_debug($debugLevel, 1, "setHSV: Ungültiger Wert '$val' (erwartet HHSSVV)");
         }
         break;
     }
 }

 // RGB (0-255 je) → HSV (0-255 je) — identische Logik wie HUE-Connector
 function LB_LBSID_convertRGBtoHSV($r, $g, $b) {
     $r /= 255; $g /= 255; $b /= 255;
     $max = max($r, $g, $b);
     $min = min($r, $g, $b);
     $v = $max;
     $d = $max - $min;
     $s = ($max == 0) ? 0 : $d / $max;
     if ($max == $min) {
         $h = 0;
     } else {
         if ($max == $r) $h = ($g - $b) / $d + ($g < $b ? 6 : 0);
         if ($max == $g) $h = ($b - $r) / $d + 2;
         if ($max == $b) $h = ($r - $g) / $d + 4;
         $h /= 6;
     }
     if ($h >= 0 && $h <= 1 && $s >= 0 && $s <= 1 && $v >= 0 && $v <= 1) {
         return array((int)($h * 255), (int)($s * 255), (int)($v * 255));
     }
     return array(0, 0, 0);
 }

 // HSV (0-255 je) → RGB (0-255 je) — identische Logik wie HUE-Connector
 function LB_LBSID_hsvToRgb($h, $s, $v) {
     $h /= 255; $s /= 255; $v /= 255;
     if ($s == 0) {
         $c = (int)round($v * 255);
         return array($c, $c, $c);
     }
     $h *= 6;
     $i = (int)floor($h);
     $f = $h - $i;
     $p = $v * (1 - $s);
     $q = $v * (1 - $s * $f);
     $t = $v * (1 - $s * (1 - $f));
     switch ($i) {
         case 0: list($r,$g,$b) = array($v,$t,$p); break;
         case 1: list($r,$g,$b) = array($q,$v,$p); break;
         case 2: list($r,$g,$b) = array($p,$v,$t); break;
         case 3: list($r,$g,$b) = array($p,$q,$v); break;
         case 4: list($r,$g,$b) = array($t,$p,$v); break;
         default: list($r,$g,$b) = array($v,$p,$q); break;
     }
     return array((int)round($r*255), (int)round($g*255), (int)round($b*255));
 }

 function LB_LBSID_verifyCmd($koType, $expected, $status) {
     switch ($koType) {
     case 'setPower':
         return isset($status['onOff']) && ((int)$status['onOff'] === ((int)(bool)(int)$expected));
     case 'setBrightness':
         return isset($status['brightness']) && ((int)$status['brightness'] === (int)$expected);
     case 'setColor':
         if (!isset($status['color'])) return false;
         $c = $status['color'];
         $actual = strtoupper(sprintf("%02X%02X%02X", (int)$c['r'], (int)$c['g'], (int)$c['b']));
         return $actual === strtoupper(ltrim(trim((string)$expected), '#'));
     case 'setColorTemp':
         return isset($status['colorTemInKelvin']) && ((int)$status['colorTemInKelvin'] === (int)$expected);
     case 'setHSV':
         // HSV-Verify mit Toleranz ±2 wegen RGB↔HSV-Rundungsfehlern
         if (!isset($status['color'])) return false;
         $hex = ltrim(trim((string)$expected), '#');
         if (strlen($hex) !== 6 || !ctype_xdigit($hex)) return true;
         $eh = hexdec(substr($hex, 0, 2));
         $es = hexdec(substr($hex, 2, 2));
         $ev = hexdec(substr($hex, 4, 2));
         $c   = $status['color'];
         $hsv = LB_LBSID_convertRGBtoHSV((int)$c['r'], (int)$c['g'], (int)$c['b']);
         $hsv[2] = (int)round((int)($status['brightness'] ?? 0) / 100.0 * 255);
         return abs($hsv[0] - $eh) <= 2 && abs($hsv[1] - $es) <= 2 && abs($hsv[2] - $ev) <= 2;
     }
     return true;
 }

 function LB_LBSID_writeGA($koID, $val, &$cache, $debugLevel) {
     if ($koID <= 0) return;
     $key = (string)$koID;
     if (isset($cache[$key]) && (string)$cache[$key] === (string)$val) return;
     $cache[$key] = $val;
     if ($debugLevel >= 3) exec_debug(3, "writeGA KO=$koID val=$val");
     writeGA($koID, $val);
 }

 function exec_debug($thisTxtDbgLevel, $str) {
     global $debugLevel, $dbgTxts, $hasWrapper;
     if ($thisTxtDbgLevel <= $debugLevel) {
         if (isset($hasWrapper)) {
             W_writeToCustomLog("LBS_LBSID", $dbgTxts[$thisTxtDbgLevel], $str);
         } else {
             writeToCustomLog("LBS_GOVEE_LBSID", $dbgTxts[$thisTxtDbgLevel], $str);
         }
     }
 }

 function LB_LBSID_execErrorHandler($errCode, $errText, $errFile, $errRow) {
     global $dbgTxts;
     if (0 == error_reporting()) return;
     writeToCustomLog("LBS_GOVEE_LBSID", $dbgTxts[0],
         'Datei: ' . $errFile . ' | Code: ' . $errCode . ' | Zeile: ' . $errRow . ' | ' . $errText);
 }
?>
###[/EXEC]###

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
<b>__INSERT_DISCLAIMER__</b>
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
    $data = gzuncompress(base64_decode("__govee_admin.txt__"));
    $data = str_replace("__INSERT_EDOMI_PATH__", MAIN_PATH, $data);
    if (!file_put_contents($adminFile, $data)) {
        LB_LBSID_debug($debugLevel, 0, "Admin ($adminFile) konnte nicht erstellt werden!");
    } else {
        LB_LBSID_debug($debugLevel, 1, "Admin ($adminFile) aktiviert.");
    }

    $staticFiles = array(
        "ko_picker.php" => "__ko_picker.php.txt__",
        "ko_picker.css" => "__ko_picker.css.txt__",
        "ko_picker.js"  => "__ko_picker.js.txt__",
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
    $data = gzuncompress(base64_decode("__GoveeLanClient.txt__"));
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

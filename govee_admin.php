<?php
define('MAIN_PATH', '__INSERT_EDOMI_PATH__');

$mysqli = mysqli_connect("localhost", "root", "", "");
require_once dirname(__FILE__) . '/ko_picker.php';

$goveeLib = MAIN_PATH . '/main/include/php/Govee/GoveeLanClient.php';
if (file_exists($goveeLib)) {
    include_once $goveeLib;
}

/*
  (w)(c) 2026 Nima Ghassemi Nejad (ngn928@web.de)
  v 1.00  03.04.2026 - initial release
*/

$KO_DEFS = [
    'setPower'      => ['label' => 'Ein/Aus setzen (0/1)',          'dir' => 'set'],
    'setBrightness' => ['label' => 'Helligkeit setzen (0&ndash;100)', 'dir' => 'set'],
    'setColor'      => ['label' => 'Farbe setzen (RRGGBB)',          'dir' => 'set'],
    'setColorTemp'  => ['label' => 'Farbtemp. setzen (Kelvin)',      'dir' => 'set'],
    'setHSV'        => ['label' => 'Farbe+Helligkeit setzen (HHSSVV)', 'dir' => 'set'],
    'getOnline'     => ['label' => 'Status Online/Offline (1/0)',    'dir' => 'get'],
    'getPower'      => ['label' => 'Status Ein/Aus',                 'dir' => 'get'],
    'getBrightness' => ['label' => 'Status Helligkeit',              'dir' => 'get'],
    'getColor'      => ['label' => 'Status Farbe (RRGGBB)',          'dir' => 'get'],
    'getColorTemp'  => ['label' => 'Status Farbtemp. (Kelvin)',      'dir' => 'get'],
    'getHSV'        => ['label' => 'Status Farbe+Helligkeit (HHSSVV)', 'dir' => 'get'],
];

class GoveeAdmin {
    private $mysqli;
    private $REQUEST;
    private $KO_DEFS;
    private $adminVersion = "1.00";

    private $headerHTML;
    private $footerHTML = "</div></body></html>";

    public function __construct($mysqli, $request, $koDefs) {
        $this->mysqli  = $mysqli;
        $this->REQUEST = $request;
        $this->KO_DEFS = $koDefs;
        $this->checkDB();
        $baseHref = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
        $this->headerHTML = <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
<base href="$baseHref">
<meta charset="UTF-8">
<title>Edomi Govee KO-Setup</title>
<link rel="stylesheet" href="ko_picker.css">
<style>
* { box-sizing: border-box; }
body { font-family: Arial, sans-serif; font-size: 12px; background: #343434; color: #000000; margin: 0; padding: 10px; }
input, select { font-family: inherit; font-size: inherit; color: #000000; background: #ffffff; }
.page-wrap {
    display: inline-block; border-radius: 3px;
    box-shadow: 3px 10px 40px #303030;
    background: -webkit-linear-gradient(top, #ffffff 0px, #f0f0e9 74px, #ffffff 74px);
    background: linear-gradient(to bottom, #ffffff 0px, #f0f0e9 74px, #ffffff 74px);
    background-repeat: no-repeat; background-color: #ffffff;
    text-align: left; width: 100%; padding: 10px 12px 15px;
}
h1 { font-size: 15px; font-weight: bold; color: #343434; margin: 0 0 10px; }
h2 { font-size: 13px; color: #343434; margin: 15px 0 5px; padding-top: 15px; border-bottom: 1px dotted #a0a0a0; padding-bottom: 3px; }
.toolbar { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.cmdButton {
    display: inline-block; font-family: inherit; font-size: inherit;
    padding: 5px; text-align: center; color: #000000;
    background: -webkit-linear-gradient(top, #d9d9d9 0%, #f0f0f0 100%);
    background: linear-gradient(to bottom, #d9d9d9 0%, #f0f0f0 100%);
    cursor: pointer; line-height: 15px; border: 1px solid #c0c0c0;
    border-radius: 3px; min-width: 62px; height: 27px; text-decoration: none;
}
.cmdButton:hover { background: #f0f0f0; }
table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
thead { position: sticky; top: 0; z-index: 5; }
thead th {
    background: -webkit-linear-gradient(top, #d9d9d9 0%, #f0f0f0 100%);
    background: linear-gradient(to bottom, #d9d9d9 0%, #f0f0f0 100%);
    border: 1px solid #c0c0c0; padding: 5px 6px; text-align: left;
    font-weight: bold; color: #000000;
}
tbody tr:nth-child(even) { background: #f5f5f0; }
tbody tr:hover { background: #e0e0e0; }
td { padding: 4px 6px; vertical-align: middle; border-bottom: 1px solid #e8e8e8; }
.ko-cell {
    display: flex; align-items: center; gap: 4px; min-height: 20px;
    cursor: pointer; padding: 2px 4px; border: 1px solid #d9d9d9; background: #ffffff;
    transition: border-color 0.1s;
}
.ko-cell:hover { border-color: #808080; background: #f0f0e9; }
.ko-cell-empty { color: #707070; font-style: italic; flex: 1; }
.ko-cell-name  { flex: 1; color: #00a000; }
.btn-save {
    display: inline-block; font-family: inherit; font-size: inherit;
    padding: 5px; text-align: center; color: #000000;
    background: -webkit-linear-gradient(top, #80e020 0%, #50b000 100%);
    background: linear-gradient(to bottom, #80e020 0%, #50b000 100%);
    cursor: pointer; line-height: 15px; border: 1px solid #409000;
    border-radius: 3px; height: 27px; min-width: 62px;
}
.btn-save:hover { background: #80e000; }
a { color: #c00000; text-decoration: none; }
a:hover { text-decoration: underline; }
.msg-info { background: #e8f4e8; border: 1px solid #80c080; padding: 6px 8px; margin-bottom: 8px; border-radius: 3px; }
.msg-warn { background: #f4ece8; border: 1px solid #c08080; padding: 6px 8px; margin-bottom: 8px; border-radius: 3px; }
label.dir-set { color: #0050a0; }
label.dir-get { color: #007000; }
</style>
<script src="ko_picker.js"></script>
<script>
function goveePickerOpen(inputId) {
    var currentId = parseInt(document.getElementById(inputId).value) || 0;
    koPickerOpen({
        currentKoId: currentId,
        ajaxUrl: 'govee_admin.php?',
        onConfirm: function(id, name) {
            document.getElementById(inputId).value = id;
            var sp = document.getElementById('disp_' + inputId);
            sp.className = 'ko-cell-name';
            sp.textContent = name + ' [' + id + ']';
        },
        onReset: function() {
            document.getElementById(inputId).value = '';
            var sp = document.getElementById('disp_' + inputId);
            sp.className = 'ko-cell-empty';
            sp.textContent = 'KO ausw\u00e4hlen';
        }
    });
}
</script>
</head>
<body>
<div class="page-wrap">
<h1>Govee LAN &mdash; Admin</h1>
<p class="msg-warn">Nach &Auml;nderungen an Ger&auml;ten oder KO-Zuordnungen muss der LBS neu gestartet werden (E1 = 0, dann E1 = 1).</p>
HTML;
    }

    private function checkDB() {
        $this->mysqli->query(
            "CREATE TABLE IF NOT EXISTS edomiProject.goveeDevice (
                id       bigint unsigned NOT NULL AUTO_INCREMENT,
                deviceName varchar(100) NOT NULL DEFAULT '',
                deviceIP   varchar(15)  NOT NULL DEFAULT '',
                deviceSku  varchar(20)  NOT NULL DEFAULT '',
                deviceMac  varchar(60)  NOT NULL DEFAULT '',
                PRIMARY KEY (id)
            ) ENGINE=MyISAM"
        );
        $this->mysqli->query(
            "CREATE TABLE IF NOT EXISTS edomiProject.goveeKoMap (
                id       bigint unsigned NOT NULL AUTO_INCREMENT,
                deviceID bigint unsigned NOT NULL,
                koType   varchar(20)     NOT NULL,
                koID     bigint unsigned NOT NULL DEFAULT 0,
                PRIMARY KEY (id)
            ) ENGINE=MyISAM"
        );
    }

    private function getKoName($koID) {
        if (empty($koID)) return '';
        $r = $this->mysqli->query("SELECT name FROM edomiProject.editKo WHERE id=" . (int)$koID);
        if ($r && $row = $r->fetch_assoc()) return $row['name'];
        return '';
    }

    private function renderKoCell($inputId, $koID) {
        $koName  = $this->getKoName($koID);
        $display = $koName ? htmlspecialchars($koName) . ' [' . $koID . ']' : 'KO ausw&auml;hlen';
        $spanCls = $koName ? 'ko-cell-name' : 'ko-cell-empty';
        return "<input type='hidden' name='ko_$inputId' id='$inputId' value='" . (int)$koID . "'>"
             . "<div class='ko-cell' onclick=\"goveePickerOpen('$inputId')\">"
             . "<span id='disp_$inputId' class='$spanCls'>$display</span>"
             . "</div>";
    }

    private function loadKoMap($deviceID) {
        $map = [];
        $stmt = $this->mysqli->prepare(
            "SELECT koType, koID FROM edomiProject.goveeKoMap WHERE deviceID=?"
        );
        if ($stmt) {
            $stmt->bind_param('i', $deviceID);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $map[$row['koType']] = (int)$row['koID'];
            }
        }
        return $map;
    }

    private function saveDevice() {
        $req = $this->REQUEST;
        $devID = isset($req['deviceID']) ? (int)$req['deviceID'] : 0;
        $name  = isset($req['deviceName']) ? trim($req['deviceName']) : '';
        $ip    = isset($req['deviceIP'])   ? trim($req['deviceIP'])   : '';
        $sku   = isset($req['deviceSku'])  ? trim($req['deviceSku'])  : '';
        $mac   = isset($req['deviceMac'])  ? trim($req['deviceMac'])  : '';

        if ($devID === 0) {
            // INSERT
            $stmt = $this->mysqli->prepare(
                "INSERT INTO edomiProject.goveeDevice (deviceName,deviceIP,deviceSku,deviceMac) VALUES (?,?,?,?)"
            );
            if ($stmt) {
                $stmt->bind_param('ssss', $name, $ip, $sku, $mac);
                $stmt->execute();
                $devID = (int)$stmt->insert_id;
            }
        } else {
            // UPDATE
            $stmt = $this->mysqli->prepare(
                "UPDATE edomiProject.goveeDevice SET deviceName=?,deviceIP=?,deviceSku=?,deviceMac=? WHERE id=?"
            );
            if ($stmt) {
                $stmt->bind_param('ssssi', $name, $ip, $sku, $mac, $devID);
                $stmt->execute();
            }
            // Alte KO-Map löschen
            $this->mysqli->query("DELETE FROM edomiProject.goveeKoMap WHERE deviceID=$devID");
        }

        // KO-Map speichern
        foreach ($this->KO_DEFS as $koType => $def) {
            $koID = (int)($req['ko_ko_' . $koType] ?? 0);
            if ($koID > 0) {
                $stmt = $this->mysqli->prepare(
                    "INSERT INTO edomiProject.goveeKoMap (deviceID,koType,koID) VALUES (?,?,?)"
                );
                if ($stmt) {
                    $stmt->bind_param('isi', $devID, $koType, $koID);
                    $stmt->execute();
                }
            }
        }
    }

    private function deleteDevice() {
        $id = (int)$this->REQUEST['delete'];
        $this->mysqli->query("DELETE FROM edomiProject.goveeKoMap WHERE deviceID=$id");
        $this->mysqli->query("DELETE FROM edomiProject.goveeDevice WHERE id=$id");
    }

    private function deviceForm($name, $ip, $sku, $mac, $devID, $koMap) {
        $title  = $devID ? "Ger&auml;t bearbeiten" : "Ger&auml;t hinzuf&uuml;gen";
        $html   = "<h2>$title</h2>";
        $html  .= "<form method='post' action='govee_admin.php'>";
        $html  .= "<input type='hidden' name='saveDevice' value='1'>";
        $html  .= "<input type='hidden' name='deviceID'  value='$devID'>";
        $html  .= "<input type='hidden' name='deviceSku' value='" . htmlspecialchars($sku) . "'>";
        $html  .= "<input type='hidden' name='deviceMac' value='" . htmlspecialchars($mac) . "'>";

        $html  .= "<table style='width:100%'><tbody>";

        // Name
        $html .= "<tr><td style='width:220px'><label>Name</label></td>"
               . "<td><input type='text' name='deviceName' value='" . htmlspecialchars($name) . "' style='width:100%;padding:2px 4px;border:1px solid #c0c0c0;'></td></tr>";
        // IP
        $html .= "<tr><td><label>IP-Adresse</label></td>"
               . "<td><input type='text' name='deviceIP' value='" . htmlspecialchars($ip) . "' style='width:160px;padding:2px 4px;border:1px solid #c0c0c0;'>"
               . "&nbsp;<span style='color:#808080'>SKU: " . htmlspecialchars($sku) . "&nbsp;&nbsp;MAC: " . htmlspecialchars($mac) . "</span></td></tr>";

        $html .= "<tr><td colspan='2' style='padding-top:10px'><b>KO-Verkn&uuml;pfungen:</b></td></tr>";

        // KO-Zeilen
        $lastDir = '';
        foreach ($this->KO_DEFS as $koType => $def) {
            $dir = $def['dir'];
            if ($dir !== $lastDir) {
                $sectionLabel = $dir === 'set' ? '&#8594; Edomi &rarr; Lampe (Setzen)' : '&#8592; Lampe &rarr; Edomi (Status)';
                $html .= "<tr><td colspan='2' style='padding-top:8px; font-size:11px; color:#606060'>$sectionLabel</td></tr>";
                $lastDir = $dir;
            }
            $inputId = 'ko_' . $koType;
            $koID    = $koMap[$koType] ?? 0;
            $dirCls  = $dir === 'set' ? 'dir-set' : 'dir-get';
            $html .= "<tr>"
                   . "<td><label class='$dirCls'>" . $def['label'] . "</label></td>"
                   . "<td>" . $this->renderKoCell($inputId, $koID) . "</td>"
                   . "</tr>";
        }

        $html .= "</tbody></table>";
        $html .= "<div style='text-align:right; margin-top:8px'>";
        $html .= "<a href='govee_admin.php' class='cmdButton' style='margin-right:6px'>Abbrechen</a>";
        $html .= "<input type='submit' class='btn-save' value='Speichern'>";
        $html .= "</div></form>";
        echo $html;
    }

    private function showDeviceList() {
        global $goveeLib;

        // Toolbar
        echo "<div class='toolbar'>";
        if (class_exists('GoveeLanClient')) {
            echo "<a href='govee_admin.php?discover=1' class='cmdButton'>Discovery</a>";
        } else {
            echo "<span class='msg-warn'>GoveeLanClient nicht installiert &ndash; LBS einmal starten (E1=1), dann Discovery verf&uuml;gbar.</span>";
        }
        echo "</div>";

        // Tabelle aller Geräte
        $html = "<table><thead><tr>"
              . "<th>Name</th><th>IP</th><th>SKU</th><th>MAC</th>"
              . "<th style='width:70px'>KOs</th>"
              . "<th style='width:60px'>Bearbeiten</th>"
              . "<th style='width:50px'>L&ouml;schen</th>"
              . "</tr></thead><tbody>";

        $stmt = $this->mysqli->prepare("SELECT * FROM edomiProject.goveeDevice ORDER BY deviceName");
        if ($stmt && $stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                // KO count
                $r2 = $this->mysqli->query(
                    "SELECT COUNT(*) as cnt FROM edomiProject.goveeKoMap WHERE deviceID=" . (int)$row['id'] . " AND koID>0"
                );
                $cnt = ($r2 && $c = $r2->fetch_assoc()) ? $c['cnt'] : 0;
                $html .= "<tr>"
                       . "<td>" . htmlspecialchars($row['deviceName']) . "</td>"
                       . "<td>" . htmlspecialchars($row['deviceIP'])   . "</td>"
                       . "<td>" . htmlspecialchars($row['deviceSku'])  . "</td>"
                       . "<td style='font-size:11px;color:#606060'>" . htmlspecialchars($row['deviceMac']) . "</td>"
                       . "<td style='text-align:center'>$cnt</td>"
                       . "<td style='text-align:center'><a href='govee_admin.php?edit=" . (int)$row['id'] . "' title='Bearbeiten'>&#9998;</a></td>"
                       . "<td style='text-align:center'><a href='govee_admin.php?delete=" . (int)$row['id'] . "' style='color:#c00000' title='L&ouml;schen' onclick=\"return confirm('Ger\\u00e4t l\\u00f6schen?')\">&#x2715;</a></td>"
                       . "</tr>";
            }
        }

        $html .= "</tbody></table>";
        echo $html;
    }

    private function showDiscovery() {
        if (!class_exists('GoveeLanClient')) {
            echo "<div class='msg-warn'>GoveeLanClient nicht verf&uuml;gbar. LBS einmal starten.</div>";
            echo "<a href='govee_admin.php' class='cmdButton'>Zur&uuml;ck</a>";
            return;
        }

        echo "<h2>Discovery &ndash; Govee-Ger&auml;te im Netzwerk</h2>";

        $govee = new GoveeLanClient();
        $found = $govee->discover(3);

        // Bekannte MACs laden
        $knownMacs = [];
        $res = $this->mysqli->query("SELECT deviceMac, id FROM edomiProject.goveeDevice");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $knownMacs[$row['deviceMac']] = (int)$row['id'];
            }
        }

        echo "<div class='toolbar'><a href='govee_admin.php' class='cmdButton'>Zur&uuml;ck</a></div>";

        if (empty($found)) {
            echo "<div class='msg-warn'>Keine Ger&auml;te gefunden. Pr&uuml;fe ob &quot;LAN Control&quot; in der Govee App aktiv ist.</div>";
            return;
        }

        $html = "<table><thead><tr>"
              . "<th>IP</th><th>SKU</th><th>MAC / Device-ID</th><th style='width:100px'>Aktion</th>"
              . "</tr></thead><tbody>";

        foreach ($found as $dev) {
            $ip  = htmlspecialchars($dev['ip']     ?? '');
            $sku = htmlspecialchars($dev['sku']    ?? '');
            $mac = htmlspecialchars($dev['device'] ?? '');
            $rawMac = $dev['device'] ?? '';

            if (isset($knownMacs[$rawMac])) {
                $action = "<span style='color:#007000'>&#10003; konfiguriert</span>";
            } else {
                $action = "<a href='govee_admin.php?add=1&ip=" . urlencode($dev['ip'] ?? '') . "&sku=" . urlencode($dev['sku'] ?? '') . "&mac=" . urlencode($rawMac) . "' class='cmdButton'>Hinzuf&uuml;gen</a>";
            }

            $html .= "<tr><td>$ip</td><td>$sku</td><td style='font-size:11px;color:#606060'>$mac</td><td>$action</td></tr>";
        }

        $html .= "</tbody></table>";
        echo $html;
    }

    private function addDeviceForm() {
        $ip  = $this->REQUEST['ip']  ?? '';
        $sku = $this->REQUEST['sku'] ?? '';
        $mac = $this->REQUEST['mac'] ?? '';
        $this->deviceForm($sku, $ip, $sku, $mac, 0, []);
    }

    private function editDeviceForm() {
        $id   = (int)$this->REQUEST['edit'];
        $stmt = $this->mysqli->prepare("SELECT * FROM edomiProject.goveeDevice WHERE id=?");
        if (!$stmt) return;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) return;
        $koMap = $this->loadKoMap($id);
        $this->deviceForm($row['deviceName'], $row['deviceIP'], $row['deviceSku'], $row['deviceMac'], $id, $koMap);
    }

    public function run() {
        echo $this->headerHTML;

        // POST-Aktionen zuerst
        if (isset($this->REQUEST['saveDevice'])) {
            $this->saveDevice();
        } elseif (isset($this->REQUEST['delete'])) {
            $this->deleteDevice();
        }

        // GET-Aktionen / Ansicht
        if (isset($this->REQUEST['edit'])) {
            $this->editDeviceForm();
        } elseif (isset($this->REQUEST['discover'])) {
            $this->showDiscovery();
        } elseif (isset($this->REQUEST['add'])) {
            $this->addDeviceForm();
        } else {
            $this->showDeviceList();
        }

        echo "<div style='text-align:right; font-size:11px; color:#808080; margin-top:8px'>Govee-Admin v{$this->adminVersion}</div>";
        echo $this->footerHTML;
    }
}

$admin = new GoveeAdmin($mysqli, $_REQUEST, $KO_DEFS);
$admin->run();
$mysqli->close();
?>

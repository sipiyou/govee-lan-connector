<?php
/**
 * Govee LAN – Lokaler Testscript
 * Aufruf: php test.php [discover|probe|power|brightness|color|colortemp|status]
 *
 * Beispiele:
 *   php test.php discover
 *   php test.php probe      192.168.1.50          ← direkt eine bekannte IP prüfen
 *   php test.php power      192.168.1.50 on
 *   php test.php brightness 192.168.1.50 75
 *   php test.php color      192.168.1.50 255 128 0
 *   php test.php colortemp  192.168.1.50 4000
 *   php test.php status     192.168.1.50
 */

require_once __DIR__ . '/GoveeLanClient.php';

$client = new GoveeLanClient();
$client->debug = true;

$cmd = $argv[1] ?? 'discover';

switch ($cmd) {

    case 'discover':
        echo "Suche Govee-Geräte im Netzwerk (Multicast + Broadcast)...\n";
        $devices = $client->discover();
        if (empty($devices)) {
            echo "Keine Geräte gefunden.\n";
            echo "Tipps:\n";
            echo "  - 'LAN Control' in der Govee App aktivieren\n";
            echo "  - Bekannte IP direkt testen: php test.php probe <ip>\n";
        } else {
            echo count($devices) . " Gerät(e) gefunden:\n";
            foreach ($devices as $dev) {
                printf("  IP: %-16s  Device: %s  SKU: %s\n",
                    $dev['ip'] ?? '?',
                    $dev['device'] ?? '?',
                    $dev['sku'] ?? '?'
                );
            }
        }
        break;

    case 'probe':
        $ip = $argv[2] ?? die("IP fehlt\n");
        echo "Frage Gerät direkt an: $ip\n";
        $dev = $client->probeDevice($ip);
        if ($dev === false) {
            echo "Kein Gerät auf $ip erreichbar.\n";
            echo "Prüfe: Firewall? LAN Control aktiv? Richtige IP?\n";
        } else {
            echo "Gerät gefunden!\n";
            print_r($dev);
        }
        break;

    case 'power':
        $ip = $argv[2] ?? die("IP fehlt\n");
        $on = strtolower($argv[3] ?? 'on') !== 'off';
        echo $client->setPower($ip, $on) ? "OK\n" : "Fehler\n";
        break;

    case 'brightness':
        $ip  = $argv[2] ?? die("IP fehlt\n");
        $pct = (int)($argv[3] ?? 100);
        echo $client->setBrightness($ip, $pct) ? "OK\n" : "Fehler\n";
        break;

    case 'color':
        $ip = $argv[2] ?? die("IP fehlt\n");
        $r  = (int)($argv[3] ?? 255);
        $g  = (int)($argv[4] ?? 255);
        $b  = (int)($argv[5] ?? 255);
        echo $client->setColor($ip, $r, $g, $b) ? "OK\n" : "Fehler\n";
        break;

    case 'colortemp':
        $ip     = $argv[2] ?? die("IP fehlt\n");
        $kelvin = (int)($argv[3] ?? 4000);
        echo $client->setColorTemp($ip, $kelvin) ? "OK\n" : "Fehler\n";
        break;

    case 'status':
        $ip = $argv[2] ?? die("IP fehlt\n");
        $status = $client->getStatus($ip);
        if ($status === false) {
            echo "Keine Antwort vom Gerät.\n";
        } else {
            echo "Status:\n";
            print_r($status);
        }
        break;

    default:
        echo "Unbekannter Befehl: $cmd\n";
        echo "Gültige Befehle: discover, power, brightness, color, colortemp, status\n";
}

<?php
/**
 * Govee LAN API Client
 * Kommunikation mit Govee-Lampen im lokalen Netzwerk via UDP.
 *
 * Voraussetzung: "LAN Control" in der Govee App unter "Geräte > Einstellungen" aktivieren.
 *
 * Protokoll:
 *   Discovery:  UDP Multicast an 239.255.255.250:4001  (Fallback: Subnet-Broadcast)
 *   Steuerung:  UDP Unicast an <device-ip>:4003
 *   Antworten:  werden auf Port 4002 empfangen
 */
class GoveeLanClient {

    const MULTICAST_ADDR    = '239.255.255.250';
    const DISCOVERY_PORT    = 4001;
    const LISTEN_PORT       = 4002;
    const DEVICE_PORT       = 4003;
    const DISCOVERY_TIMEOUT = 3;   // Sekunden auf Antworten warten
    const CMD_TIMEOUT       = 2;   // Sekunden auf Geräte-Antwort warten

    /** @var bool Debug-Ausgaben aktivieren */
    public $debug = false;

    /** @var resource|\Socket|null Persistenter Empfangs-Socket (Port 4002) für die Main-Loop */
    private $recvSock = null;

    /**
     * Netzwerk-Interface das nach außen geht (z.B. 'enp1s0', 'eth0').
     * Wenn leer, wird es automatisch ermittelt.
     * @var string
     */
    public $interface = '';

    // ------------------------------------------------------------------
    // Discovery
    // ------------------------------------------------------------------

    /**
     * Sendet einen Discovery-Broadcast und gibt alle gefundenen Geräte zurück.
     * Versucht zuerst Multicast, dann Subnet-Broadcast als Fallback.
     *
     * @return array  Array von ['ip', 'device', 'sku', ...]
     */
    public function discover(int $timeout = self::DISCOVERY_TIMEOUT): array {
        $localIp  = $this->getLocalIp();
        $ifIndex  = $this->getIfIndex();
        $this->log("Lokale IP: $localIp, Interface-Index: $ifIndex");

        $msg = json_encode(['msg' => ['cmd' => 'scan', 'data' => ['account_topic' => 'reserve']]]);

        // Empfangs-Socket auf Port 4002 vorbereiten
        $recvSock = $this->createUdpSocket();
        socket_bind($recvSock, '0.0.0.0', self::LISTEN_PORT);
        socket_set_option($recvSock, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $timeout, 'usec' => 0]);

        // Multicast beitreten (Antworten kommen als Unicast auf Port 4002 an,
        // aber manches Equipment nutzt Multicast-Rückantwort)
        @socket_set_option($recvSock, IPPROTO_IP, MCAST_JOIN_GROUP, [
            'group'     => self::MULTICAST_ADDR,
            'interface' => $ifIndex,
        ]);

        // Sende-Socket: Multicast-Interface explizit setzen
        $sendSock = $this->createUdpSocket();
        socket_set_option($sendSock, SOL_SOCKET, SO_BROADCAST, 1);
        // TTL für Multicast erhöhen (Standard ist 1 = nur lokales Segment)
        socket_set_option($sendSock, IPPROTO_IP, IP_MULTICAST_TTL, 4);
        // Multicast-Interface: Interface-Name bevorzugt, IP als Fallback (@ unterdrückt PHP-Warning)
        $iface = $this->interface !== '' ? $this->interface : $this->getIfName();
        if ($iface !== '') {
            @socket_set_option($sendSock, IPPROTO_IP, IP_MULTICAST_IF, $iface);
        }

        // 1) Multicast-Discovery
        socket_sendto($sendSock, $msg, strlen($msg), 0, self::MULTICAST_ADDR, self::DISCOVERY_PORT);
        $this->log("Multicast-Discovery gesendet an " . self::MULTICAST_ADDR . ":" . self::DISCOVERY_PORT);

        // 2) Subnet-Broadcast als Fallback (funktioniert auch ohne Multicast-Routing)
        $broadcast = $this->getSubnetBroadcast($localIp);
        if ($broadcast) {
            socket_sendto($sendSock, $msg, strlen($msg), 0, $broadcast, self::DISCOVERY_PORT);
            $this->log("Broadcast-Discovery gesendet an $broadcast:" . self::DISCOVERY_PORT);
        }

        socket_close($sendSock);

        // Antworten einsammeln
        $devices  = [];
        $seen     = [];
        $deadline = time() + $timeout;
        while (time() < $deadline) {
            $buf  = '';
            $from = '';
            $port = 0;
            $bytes = @socket_recvfrom($recvSock, $buf, 4096, 0, $from, $port);
            if ($bytes === false) break;

            $data = json_decode($buf, true);
            if ($data && isset($data['msg']['cmd']) && $data['msg']['cmd'] === 'scan') {
                if (isset($seen[$from])) continue;
                $seen[$from] = true;
                $dev = $data['msg']['data'];
                $dev['ip'] = $from;
                $devices[] = $dev;
                $this->log("Gerät gefunden: {$from} – " . ($dev['sku'] ?? '?'));
            }
        }

        socket_close($recvSock);
        return $devices;
    }

    /**
     * Fragt ein bekanntes Gerät direkt per IP an (kein Discovery nötig).
     * Gibt ['ip', 'device', 'sku', ...] zurück oder false wenn kein Gerät antwortet.
     *
     * @return array|false
     */
    public function probeDevice(string $ip) {
        $msg = json_encode(['msg' => ['cmd' => 'scan', 'data' => ['account_topic' => 'reserve']]]);

        $recvSock = $this->createUdpSocket();
        socket_bind($recvSock, '0.0.0.0', self::LISTEN_PORT);
        socket_set_option($recvSock, SOL_SOCKET, SO_RCVTIMEO, ['sec' => self::CMD_TIMEOUT, 'usec' => 0]);

        $sendSock = $this->createUdpSocket();
        socket_sendto($sendSock, $msg, strlen($msg), 0, $ip, self::DISCOVERY_PORT);
        socket_close($sendSock);
        $this->log("Probe gesendet an $ip:" . self::DISCOVERY_PORT);

        $buf  = '';
        $from = '';
        $port = 0;
        $bytes = @socket_recvfrom($recvSock, $buf, 4096, 0, $from, $port);
        socket_close($recvSock);

        if ($bytes === false) {
            $this->log("Keine Antwort von $ip");
            return false;
        }
        $data = json_decode($buf, true);
        if ($data && isset($data['msg']['data'])) {
            $dev = $data['msg']['data'];
            $dev['ip'] = $from;
            return $dev;
        }
        return false;
    }

    // ------------------------------------------------------------------
    // Persistenter Socket für non-blocking Main-Loop
    // ------------------------------------------------------------------

    /**
     * Öffnet den persistenten Empfangs-Socket auf Port 4002.
     * Muss einmalig beim Start der Main-Loop aufgerufen werden.
     */
    public function openRecvSocket(): void {
        if ($this->recvSock !== null) return;
        $this->recvSock = $this->createUdpSocket();
        socket_bind($this->recvSock, '0.0.0.0', self::LISTEN_PORT);
        $this->log("Empfangs-Socket geöffnet auf Port " . self::LISTEN_PORT);
    }

    /**
     * Schließt den persistenten Empfangs-Socket.
     */
    public function closeRecvSocket(): void {
        if ($this->recvSock === null) return;
        socket_close($this->recvSock);
        $this->recvSock = null;
    }

    /**
     * Sendet devStatus-Requests an alle angegebenen IPs (Fire-and-forget).
     * Blockiert nicht — kein Warten auf Antworten.
     * Antworten kommen asynchron über receiveAvailable() rein.
     *
     * @param string[] $ips
     */
    public function sendStatusRequests(array $ips): void {
        if ($this->recvSock === null) {
            throw new \RuntimeException('openRecvSocket() muss zuerst aufgerufen werden.');
        }
        $msg = json_encode(['msg' => ['cmd' => 'devStatus', 'data' => []]]);
        foreach ($ips as $ip) {
            socket_sendto($this->recvSock, $msg, strlen($msg), 0, $ip, self::DEVICE_PORT);
            $this->log("devStatus gesendet an $ip");
        }
    }

    /**
     * Liest alle aktuell verfügbaren UDP-Antworten (non-blocking, sofortige Rückkehr).
     * Gibt [ip => data-Array] zurück für alle Pakete die in diesem Aufruf bereit lagen.
     * Leeres Array wenn nichts anliegt.
     *
     * @return array [ip => array]
     */
    public function receiveAvailable(): array {
        if ($this->recvSock === null) return [];
        $results = [];
        while (true) {
            $read  = [$this->recvSock];
            $write = null;
            $ex    = null;
            $n = socket_select($read, $write, $ex, 0, 0);
            if ($n === false || $n === 0) break;
            $buf  = ''; $from = ''; $port = 0;
            @socket_recvfrom($this->recvSock, $buf, 4096, 0, $from, $port);
            $data = json_decode($buf, true);
            if ($data && isset($data['msg']['data'])) {
                $results[$from] = $data['msg']['data'];
            }
        }
        return $results;
    }

    // ------------------------------------------------------------------
    // Steuerung
    // ------------------------------------------------------------------

    /**
     * Schaltet das Gerät ein oder aus.
     *
     * @param string $ip      IP-Adresse des Geräts
     * @param bool   $on      true = an, false = aus
     */
    public function setPower(string $ip, bool $on): bool {
        $cmd = ['cmd' => 'turn', 'data' => ['value' => $on ? 1 : 0]];
        return $this->sendCmd($ip, $cmd);
    }

    /**
     * Setzt die Helligkeit (0–100 %).
     *
     * @param string $ip    IP-Adresse des Geräts
     * @param int    $pct   Helligkeit 0–100
     */
    public function setBrightness(string $ip, int $pct): bool {
        $pct = max(0, min(100, $pct));
        $cmd = ['cmd' => 'brightness', 'data' => ['value' => $pct]];
        return $this->sendCmd($ip, $cmd);
    }

    /**
     * Setzt eine RGB-Farbe.
     *
     * @param string $ip   IP-Adresse des Geräts
     * @param int    $r    Rot   0–255
     * @param int    $g    Grün  0–255
     * @param int    $b    Blau  0–255
     */
    public function setColor(string $ip, int $r, int $g, int $b): bool {
        $r = max(0, min(255, $r));
        $g = max(0, min(255, $g));
        $b = max(0, min(255, $b));
        $cmd = ['cmd' => 'colorwc', 'data' => [
            'color'     => ['r' => $r, 'g' => $g, 'b' => $b],
            'colorTemInKelvin' => 0,
        ]];
        return $this->sendCmd($ip, $cmd);
    }

    /**
     * Setzt die Farbtemperatur in Kelvin (typisch 2000–6535 K).
     *
     * @param string $ip       IP-Adresse des Geräts
     * @param int    $kelvin   Farbtemperatur in Kelvin
     */
    public function setColorTemp(string $ip, int $kelvin): bool {
        $cmd = ['cmd' => 'colorwc', 'data' => [
            'color'            => ['r' => 0, 'g' => 0, 'b' => 0],
            'colorTemInKelvin' => $kelvin,
        ]];
        return $this->sendCmd($ip, $cmd);
    }

    /**
     * Liest den aktuellen Status eines einzelnen Geräts.
     * Gibt das 'data'-Array der Antwort zurück, oder false bei Fehler.
     *
     * @return array|false
     */
    public function getStatus(string $ip) {
        $result = $this->getStatusBatch([$ip]);
        return $result[$ip];
    }

    /**
     * Liest den Status mehrerer Geräte gleichzeitig (non-blocking).
     * Alle Requests werden sofort gesendet, Antworten werden bis CMD_TIMEOUT gesammelt.
     * Gibt ein Array [ip => data|false] zurück.
     *
     * @param  string[] $ips
     * @return array    [ip => array|false]
     */
    public function getStatusBatch(array $ips): array {
        if (empty($ips)) return [];

        $sock = $this->createUdpSocket();
        socket_bind($sock, '0.0.0.0', self::LISTEN_PORT);

        $msg = json_encode(['msg' => ['cmd' => 'devStatus', 'data' => []]]);
        foreach ($ips as $ip) {
            socket_sendto($sock, $msg, strlen($msg), 0, $ip, self::DEVICE_PORT);
            $this->log("devStatus gesendet an $ip");
        }

        $results  = array_fill_keys($ips, false);
        $received = [];
        $deadline = microtime(true) + self::CMD_TIMEOUT;

        while (count($received) < count($ips)) {
            $remaining_us = (int)(($deadline - microtime(true)) * 1000000);
            if ($remaining_us <= 0) break;
            $read  = [$sock];
            $write = null;
            $ex    = null;
            $n = socket_select($read, $write, $ex, (int)($remaining_us / 1000000), $remaining_us % 1000000);
            if ($n === false || $n === 0) break;

            $buf = ''; $from = ''; $port = 0;
            @socket_recvfrom($sock, $buf, 4096, 0, $from, $port);
            if (!array_key_exists($from, $results) || isset($received[$from])) continue;

            $data = json_decode($buf, true);
            if ($data && isset($data['msg']['data'])) {
                $results[$from] = $data['msg']['data'];
            }
            $received[$from] = true;
        }

        socket_close($sock);
        return $results;
    }

    // ------------------------------------------------------------------
    // Intern
    // ------------------------------------------------------------------

    private function sendCmd(string $ip, array $cmd): bool {
        $sock = $this->createUdpSocket();
        $msg  = json_encode(['msg' => $cmd]);
        $bytes = socket_sendto($sock, $msg, strlen($msg), 0, $ip, self::DEVICE_PORT);
        socket_close($sock);

        $ok = ($bytes !== false);
        $this->log("CMD [{$cmd['cmd']}] an $ip – " . ($ok ? 'OK' : 'FEHLER'));
        return $ok;
    }

    /** Ermittelt die lokale IP-Adresse des ausgehenden Interfaces. */
    private function getLocalIp(): string {
        if ($this->interface !== '') {
            // Interface-Name angegeben: IP via ip-Befehl ermitteln
            $out = shell_exec("ip -4 addr show " . escapeshellarg($this->interface) . " 2>/dev/null");
            if (preg_match('/inet (\d+\.\d+\.\d+\.\d+)/', $out ?? '', $m)) {
                return $m[1];
            }
        }
        // Automatisch: UDP-Trick ohne echte Verbindung
        $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_connect($sock, '8.8.8.8', 80);
        socket_getsockname($sock, $addr);
        socket_close($sock);
        return $addr ?? '0.0.0.0';
    }

    /** Ermittelt den Interface-Index des ausgehenden Interfaces. */
    private function getIfIndex(): int {
        $iface = $this->getIfName();
        if ($iface !== '' && function_exists('socket_if_nametoindex')) {
            $idx = socket_if_nametoindex($iface);
            if ($idx !== false) return (int)$idx;
        }
        return 0;
    }

    /** Ermittelt den Interface-Namen des ausgehenden Interfaces. */
    private function getIfName(): string {
        if ($this->interface !== '') return $this->interface;
        $out = shell_exec("ip route get 8.8.8.8 2>/dev/null");
        if (preg_match('/dev\s+(\S+)/', $out ?? '', $m)) {
            return $m[1];
        }
        return '';
    }

    /** Berechnet die Broadcast-Adresse aus einer lokalen IP (nimmt /24 an, prüft Route). */
    private function getSubnetBroadcast(string $localIp): ?string {
        $out = shell_exec("ip route show src " . escapeshellarg($localIp) . " 2>/dev/null");
        // z.B. "192.168.1.0/24 dev enp1s0 ..."
        if (preg_match('/(\d+\.\d+\.\d+\.\d+)\/(\d+)/', $out ?? '', $m)) {
            $net    = ip2long($m[1]);
            $prefix = (int)$m[2];
            $mask   = ~((1 << (32 - $prefix)) - 1) & 0xFFFFFFFF;
            $bcast  = long2ip(($net & $mask) | ~$mask & 0xFFFFFFFF);
            return $bcast;
        }
        // Fallback: letztes Oktett auf 255
        $parts    = explode('.', $localIp);
        $parts[3] = '255';
        return implode('.', $parts);
    }

    /** @return resource|\Socket */
    private function createUdpSocket() {
        $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($sock === false) {
            throw new \RuntimeException('socket_create fehlgeschlagen: ' . socket_strerror(socket_last_error()));
        }
        socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_option($sock, SOL_SOCKET, SO_REUSEPORT, 1);
        return $sock;
    }

    private function log(string $msg): void {
        if ($this->debug) {
            echo '[Govee] ' . $msg . PHP_EOL;
        }
    }
}

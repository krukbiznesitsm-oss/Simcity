<?php
/**
 * ROZBUDOWANA KONFIGURACJA I SYSTEM INTEGRACJI - II KRÓLESTWO BARIDAS
 * Plik zawiera klasy autoryzacji, zarządzanie stanem oraz definicje globalne.
 */

// Dane Bazy Danych
$db_host = 'mysql2.mydevil.net';
$db_user = 'm14005_baridas';
$db_pass = 'dp2T#vS&4Un%!J76hu$kMbCvq';
$db_name = 'm14005_baridas';

// Konfiguracja Aplikacji Sarmackiej
$config = [
    'appId'     => 'S00122',
    'appSecret' => '6e0702304ab794fa',
    'appName'   => 'II Królestwo Baridas',
    'adress'    => 'http://' . $_SERVER['HTTP_HOST'] . explode('?', $_SERVER['REQUEST_URI'])[0],
    'options'   => ['email', 'gotowka']
];

// Uprawnienia Wicekróla
const ADMIN_PASSPORTS = ['12345', 'AG003', 'AG006'];

/**
 * KLASA INTEGRACJI Z SYSTEMAMI KSIĘSTWA SARMACJI
 */
class Integracja {
    private $conf;

    public function setConfiguration($c) { $this->conf = $c; }

    public function loginURL() {
        $params = [
            'appId' => $this->conf['appId'],
            'redirect' => $this->conf['adress'],
            'options' => implode(',', $this->conf['options'])
        ];
        return "https://sarmacja.org/logowanie?" . http_build_query($params);
    }

    public function getUser() {
        if (isset($_GET['token'])) {
            $url = "https://sarmacja.org/api/user_data?appId=" . $this->conf['appId'] . "&token=" . $_GET['token'] . "&secret=" . $this->conf['appSecret'];
            $ctx = stream_context_create(['http' => ['timeout' => 5]]);
            $response = @file_get_contents($url, false, $ctx);
            if ($response) {
                $data = json_decode($response, true);
                if ($data && isset($data['paszport'])) {
                    $_SESSION['user'] = $data;
                    header("Location: " . $this->conf['adress']);
                    exit;
                }
            }
        }
        return $_SESSION['user'] ?? null;
    }
}

/**
 * FUNKCJE POMOCNICZE
 */
function isViceroy($pid) {
    return in_array($pid, ADMIN_PASSPORTS);
}

function formatConstructionTime($seconds) {
    if ($seconds <= 0) return "Gotowe";
    if ($seconds < 60) return "$seconds s";
    $min = floor($seconds / 60);
    if ($min < 60) return $min . " min";
    $h = floor($min / 60);
    return $h . " h " . ($min % 60) . " m";
}

/**
 * ZAPIS STANU GRACZA - GWARANTUJE SYNCHRONIZACJĘ LBR
 * Zapisuje wszystkie statystyki z sesji do bazy MySQL.
 */
function savePlayerState($db, &$player, $pid) {
    if (!$pid || !$db) return;

    $upd = $db->prepare("UPDATE players SET 
        lbr = ?, health = ?, energy = ?, level = ?, exp = ?, 
        skill_pts = ?, attr_pts = ?, population = ?, 
        last_update = ?, fief_name = ?, `rank` = ?, office = ?,
        skills = ?, attributes = ?
        WHERE paszport = ?");

    $skills_json = json_encode($player['skills'] ?? []);
    $attributes_json = json_encode($player['attributes'] ?? []);
    $now = time();

    $upd->bind_param("diiiiiiiissssss", 
        $player['lbr'], $player['health'], $player['energy'], 
        $player['level'], $player['exp'], $player['skill_pts'], 
        $player['attr_pts'], $player['population'], $now, 
        $player['fief_name'], $player['rank'], $player['office'], 
        $skills_json, $attributes_json, $pid
    );
    $upd->execute();
}

/**
 * AKTUALIZACJA STANU GRY
 */
function updateGameState(&$player) {
    $player['health'] = $player['health'] ?? 100;
    $player['energy'] = $player['energy'] ?? 50;
    $player['lbr'] = (float)($player['lbr'] ?? 0);
    $player['level'] = $player['level'] ?? 1;
    $player['exp'] = $player['exp'] ?? 0;
    $player['exp_max'] = $player['level'] * 1000;
    
    $total_pop = 0;
    if (isset($player['villages']) && is_array($player['villages'])) {
        foreach ($player['villages'] as $v) {
            if (isset($v['buildings']) && is_array($v['buildings'])) {
                foreach ($v['buildings'] as $b) {
                    if ($b['name'] === 'Chata Osadnicza') $total_pop += 5;
                    if ($b['name'] === 'Domostwo Mieszczańskie') $total_pop += 20;
                }
            }
        }
    }
    $player['population'] = $total_pop;
}
?>
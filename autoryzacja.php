<?php

//UPRASZA SIĘ O NIE ZMIENIANIE NICZEGO W TYM PLIKU

class Integracja {

    private $appId;
    private $appSecret;
    private $appName;
    private $user = null; //tu przetrzymujemy podstawowe dane usera
    private $wynik;
    private $accessToken = null;
    private $adress;
    private $options;
    private $config;
    private $sarmaUrl = 'https://www.sarmacja.org/integracja/';
    private $iv = null;

    public function setConfiguration($config) {
        if (!session_id()) {
            session_start();
        }

        if (isset($config) && !empty($config)) {
            if (isset($config['appId']) && isset($config['appSecret']) && isset($config['adress']) && isset($config['appName'])) {

                $this->appId = $config['appId'];
                $this->appSecret = $config['appSecret'];
                $this->adress = $config['adress'];

                if (!isset($config['options']))
                    $config['options'] = array();
                $this->options = base64_encode(json_encode($config['options']));

                $this->appName = $config['appName'];

                if (isset($_SESSION['at']))
                    $this->accessToken = $_SESSION['at'];

                if (isset($_SESSION['user']))
                    $this->user = $_SESSION['user'];

                $this->config = $config;
                $this->iv = substr(hash('sha256', $this->appSecret), 0, 16);
                $this->wynik['error'] = 200;
            }
            else {
                exit('Plik konfiguracyjny niepełny. Aplikacja została automatycznie wyłączona.');
            }
        } else {
            exit('Brak pliku konfiguracyjnego. Aplikacja została automatycznie wyłączona.');
        }
    }

    //wywolywanie akcji wymagajacych autoryzacj aplikacji i urzytkownika
    //dostepne zmienne funkcji beda podane na forum
    public function action($parm, $action) {
        if ($this->appSecret == '') {
            $this->wynik['error'] = 500;
            $this->wynik['errorD'] = 'Niemożliwe wykonanie akcji. Brak podstawowych parametrów.';
        } else {
            $parm['appS'] = $this->appSecret;
            $parm['userToken'] = $this->accessToken;
            $dane['dane'] = $this->encrypt(json_encode($parm));
            $dane['appId'] = $this->appId;
            $this->wynik = json_decode($this->request($dane, $action), true);
        }
    }

    public function przelew($parm) {
        if ($this->appSecret == '') {
            $this->wynik['error'] = 500;
            $this->wynik['errorD'] = 'Niemożliwe wykonanie akcji. Brak podstawowych parametrów.';
        } else {
            $parm['appS'] = $this->appSecret;
            $parm['userToken'] = $this->accessToken;
            $parm['przelewId'] = rand(1000, 9999);
            $dane['dane'] = $this->encrypt(json_encode($parm));
            $dane['appId'] = $this->appId;
            $this->wynik = json_decode($this->request($dane, 'przelew'), true);

            if ($this->wynik['error'] == 200 && $this->decrypt($this->wynik['body']) != $parm['przelewId']) {
                $this->wynik['error'] = 700;
                $this->wynik['errorD'] = 'Ktoś stara się oszukać system.';
            }
        }
    }

    //integracja do danych niewymagajacych zalogowanego uzytkownika i zautoryzowanej aplikacji
    //dostepne zmienne funkcji beda podane na forum
    public function ogolnaIntegracja($parm, $action) {
        $this->wynik = json_decode($this->request($parm, $action), true);
    }

    private function autorization() {
        if (isset($_POST['at'])) {
            $parm = array();
            $parm['aT'] = $_POST['at'];
            $parm['upr'] = $this->options;
            $parm['appS'] = $this->appSecret;
            $parm['appI'] = $this->appId;
            $parm['userId'] = $_POST['paszport'];
            $this->action($parm, 'login');

            if ($this->wynik['error'] == 200) {
                $this->user = $this->wynik['body'];
                $this->accessToken = $_POST['at'];
                $_SESSION['at'] = $this->accessToken;
                $_SESSION['user'] = $this->user;
            }
        }
    }

    private function userUpdate() {
        $parm['userId'] = $this->user['paszport'];
        $this->wynik = json_decode($this->ogolnaIntegracja($parm, 'commonUserData'), true);
if (is_array($this->wynik) && isset($this->wynik['error']) && $this->wynik['error'] == 200) {
            $this->user = $this->wynik['body'];
            $_SESSION['user'] = $this->user;
        }
    }

    private function request($data, $method) {
        $hand = curl_init();
        curl_setopt($hand, CURLOPT_URL, $this->sarmaUrl . '+' . $method . '/');
        curl_setopt($hand, CURLOPT_POST, 1);
        curl_setopt($hand, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($hand, CURLOPT_POSTFIELDS, $this->getUrl($data, 0));
        $wynik = curl_exec($hand);
        curl_close($hand);
        return $wynik;
    }

    public function loginURL() {
        return $this->sarmaUrl . 'auth2/?options=' . $this->options . '&redirect=' . base64_encode($this->adress) . '&appName=' . base64_encode($this->appName) . '&appId=' . $this->appId;
    }

    //zwraca tablice asocjacyjną: paszport, nick, gotówka, email
    public function getUser() {
        //jezeli user nie jest zalogowany i zautoryzowany to autoryzacja
        if (empty($this->user) || is_null($this->user)) {
            $this->autorization();
        } else { //w przeciwnym wypadku zarzadaj odswieżenia danych
            $this->userUpdate();
        }
        return $this->user;
    }

    public function getWynik() {
        return $this->wynik;
    }

    private function getUrl($parm, $opt = 1) {
        $query = http_build_query($parm, null, '&');
        if ($opt) {
            return $this->sarmaUrl . '?' . $query;
        } else {
            return $query;
        }
    }
    
    public function getAppPass(){
        return $this->appSecret;
    }

    private function encrypt($string) {
        return base64_encode(openssl_encrypt($string, 'aes-128-cbc', $this->appSecret, 0, $this->iv));
    }

    public function decrypt ($string) {
        return openssl_decrypt(base64_decode($string), 'aes-128-cbc', $this->appSecret, 0, $this->iv);
    }

}

?>
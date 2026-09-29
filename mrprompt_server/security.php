<?php
// Ochrona przed atakami z innych stron (CSRF / "drive-by" na localhost).
//
// Bez tego dowolna strona otwarta w przeglądarce mogła wysłać formularz na
// http://localhost/.../system_handler.php (run_cmd) lub file_handler.php i wykonać
// polecenie albo zapisać plik na komputerze. Teraz każde żądanie zmieniające stan wymaga:
//   1) zgodnego nagłówka Origin / Sec-Fetch-Site (tylko ta sama witryna),
//   2) tokenu sesji (nagłówek X-MRP-Token lub pole _token), a sesja ma ciasteczko SameSite=Strict.

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('MRPSRV'); // własna nazwa, żeby nie kolidować z sesjami innych aplikacji w htdocs
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    @session_start();
}
if (empty($_SESSION['mrp_token'])) {
    $_SESSION['mrp_token'] = bin2hex(random_bytes(24));
}

session_write_close(); // token już odczytany – nie blokuj równoległych żądań

function mrp_token()
{
    return $_SESSION['mrp_token'];
}

function mrp_deny($msg = 'Odrzucono żądanie (brak autoryzacji).')
{
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'msg' => $msg]);
    exit;
}

function mrp_same_site_request()
{
    $fetchSite = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
    if ($fetchSite !== '' && !in_array($fetchSite, ['same-origin', 'none'], true)) return false;
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && $origin !== 'null') {
        $oh = parse_url($origin, PHP_URL_HOST);
        $op = parse_url($origin, PHP_URL_PORT);
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $hostOnly = preg_replace('/:\d+$/', '', $host);
        if (strcasecmp((string)$oh, $hostOnly) !== 0) return false;
        if ($op && preg_match('/:(\d+)$/', $host, $m) && (int)$m[1] !== (int)$op) return false;
    } elseif ($origin === 'null') {
        return false;
    }
    return true;
}

// Wywołaj na początku każdego skryptu, który zmienia stan lub uruchamia polecenia.
function mrp_guard()
{
    if (!mrp_same_site_request()) mrp_deny('Żądanie z innej witryny zostało zablokowane.');
    $given = $_SERVER['HTTP_X_MRP_TOKEN'] ?? ($_POST['_token'] ?? '');
    if (!is_string($given) || !hash_equals(mrp_token(), $given)) {
        mrp_deny('Nieprawidłowy token. Odśwież stronę aplikacji (Ctrl+F5).');
    }
}

// Fragment <script> do wstawienia w <head> stron aplikacji: dokleja token do każdego fetch()
// na ten sam adres oraz udostępnia window.MRP_TOKEN (dla formularzy tworzonych w JS).
function mrp_client_script()
{
    $t = json_encode(mrp_token());
    return "<script>window.MRP_TOKEN=$t;(function(){var f=window.fetch;window.fetch=function(u,o){o=o||{};try{var url=new URL(typeof u==='string'?u:(u&&u.url)||'',location.href);if(url.origin===location.origin){var h=new Headers(o.headers||(u&&u.headers)||{});h.set('X-MRP-Token',window.MRP_TOKEN);o.headers=h;}}catch(e){}return f.call(this,u,o);};})();</script>";
}

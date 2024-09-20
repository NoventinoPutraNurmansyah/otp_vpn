<?php
$conn = pg_connect("host=<letak-database-anda> dbname=<nama-database> user=<user-db> password=<password-db>");
if (!$conn) {
    die("Koneksi gagal: " . pg_last_error($conn));
}

$client_ip = $_SERVER['REMOTE_ADDR'];
$message = '';
$info = '';
$vpn_user = '';
$no_hp = '';
$connection_time = date('Y-m-d H:i:s');

require('routeros_api.class.php');
$API = new RouterosAPI();

function getVPNUserDetails($API, $client_ip) {
    $vpn_user = '';
    if ($API->connect('<ip-mikrotik>', '<user-mikrotik>', '<password-mikrotik>')) {
        $active_connections = $API->comm("/ppp/active/print");

        foreach ($active_connections as $connection) {
            if ($connection['address'] == $client_ip) {
                $vpn_user = $connection['name'];
                break;
            }
        }
        $API->disconnect();
    }
    return $vpn_user;
}

$vpn_user = getVPNUserDetails($API, $client_ip);

if (empty($vpn_user)) {
    echo "<script>alert('Username VPN tidak ditemukan.');</script>";
}

function convertToInternationalFormat($no_hp) {
    if (substr($no_hp, 0, 2) === '08') {
        return '628' . substr($no_hp, 2);
    }
    return $no_hp;
}

$query = "SELECT no_hp FROM data_user_gi WHERE email = '$vpn_user' LIMIT 1";  // Ganti 'email' sesuai kolom sebenarnya
$result = pg_query($conn, $query);

if (!$result) {
    $message = "Query gagal: " . pg_last_error($conn);
} else if (pg_num_rows($result) > 0) {
    $row = pg_fetch_assoc($result);
    $no_hp = $row['no_hp'];

    if (!empty($no_hp)) {
        $no_hp_international = convertToInternationalFormat($no_hp);

        if (isset($_POST['request_otp'])) {
            $otp = rand(100000, 999999);
            $url = '<url-untuk-mengirim-otp>';
            $data = [
                'app_token_id' => '<API-key-anda>',
                'service' => 'whatsapp',
                'penerima' => $no_hp_international,
                'konten' => $otp
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);

            if ($response === FALSE) {
                echo "<script>alert('Gagal mengirim OTP.');</script>";
            } else {
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $sql = "INSERT INTO data_user_connect (auth_username, user_token, created_at, ip_address) VALUES ('$vpn_user', '$otp', NOW(), '$ip_address')";
                pg_query($conn, $sql);
                echo "<script>alert('OTP berhasil dikirim!');</script>";
            }
        }
    } else {
        $message = "Nomor HP tidak ditemukan untuk pengguna VPN ini.";
        echo "<script>alert('$message');</script>";
    }
} else {
    $message = "Pengguna VPN tidak ditemukan di tabel.";
    echo "<script>alert('$message');</script>";
}

if (isset($_POST['connect'])) {
    $otp = trim($_POST['otp']);

    $query_connect = "SELECT * FROM data_user_connect WHERE auth_username = '$vpn_user' AND user_token = '$otp'";
    $result_connect = pg_query($conn, $query_connect);

    $query_gi_vpn = "SELECT * FROM data_user_gi_vpn WHERE auth_token = '$otp'";
    $result_gi_vpn = pg_query($conn, $query_gi_vpn);

    if (pg_num_rows($result_connect) > 0 || pg_num_rows($result_gi_vpn) > 0) {

        if ($API->connect('<ip-mikrotik>', '<user-mikrotik>', '<password-mikrotik>')) {
            $API->comm("/ip/firewall/address-list/add", array(
                "list"     => "bolehinet",
                "address"  => $_SERVER["REMOTE_ADDR"],
                "disabled" => "no",
            ));
            $API->disconnect();

            if (pg_num_rows($result_connect) > 0) {
                $update_query = "UPDATE data_user_connect SET used = true WHERE auth_username = '$vpn_user' AND user_token = '$otp'";
            } else {
                $update_query = "UPDATE data_user_gi_vpn SET used = true WHERE auth_token = '$otp'";
            }
            pg_query($conn, $update_query);

            $message = "Koneksi berhasil dan address list telah ditambahkan.";
            $info = "Waktu koneksi: $connection_time<br>";
            $info .= "IP Address: $client_ip<br>";
            $info .= "Username VPN: $vpn_user";
        } else {
            $message = "Koneksi ke Mikrotik gagal.";
        }

    } else {
        $message = "OTP tidak valid atau sudah digunakan.";
    }
}

pg_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masukkan OTP</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f7f7f7;
            color: #333;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            padding: 30px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            border: 2px solid #ff0000;
        }
        h2 {
            color: #ff0000;
            margin-bottom: 20px;
        }
        form {
            margin-top: 20px;
        }
        label {
            font-size: 16px;
            color: #333;
        }
        input[type="text"] {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }
        button {
            background-color: #ff0000;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #cc0000;
        }
        .btn-otp {
            background-color: #000;
            color: #fff;
            margin-top: 10px;
        }
        .btn-otp:hover {
            background-color: #333;
        }
        .info-box {
            margin-top: 20px;
            background-color: #ffe6e6;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #ff0000;
        }
        .info-box h3 {
            color: #ff0000;
            margin-bottom: 10px;
        }
        .info-box p {
            color: #333;
            font-size: 16px;
        }
    </style>
    <script type="text/javascript">
        function showAlert(message) {
            if (message !== '') {
                alert(message);
            }
        }
    </script>
</head>
<body onload="showAlert('<?php echo htmlspecialchars($message); ?>')">
    <div class="container">
        <h2>Masukkan OTP</h2>
        <p>Haii <?= htmlspecialchars($vpn_user) ?>, kode OTP telah dikirimkan ke nomor WA <?= htmlspecialchars($no_hp) ?>. Silahkan masukkan OTP.</p>
        <form method="post" action="">
            <label for="otp">OTP:</label>
            <input type="text" name="otp" id="otp" maxlength="6" placeholder="Masukkan OTP">
            <button type="submit" name="connect">Connect</button>
        </form>

        <p>Belum menerima OTP?</p>
        <form method="post" action="">
            <button type="submit" name="request_otp" class="btn-otp">Kirim Ulang OTP</button>
        </form>

        <?php if (!empty($info)): ?>
            <div class="info-box">
                <h3>Informasi Koneksi</h3>
                <p><?php echo $info; ?></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
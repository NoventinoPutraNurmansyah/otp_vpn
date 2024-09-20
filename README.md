Saya akan memberikan cara menggunakan kode agar sesuai dengan kebutuhan anda

$conn = pg_connect("host=localhost dbname=admin_vpn user=user-db password=password-db"); pada kode diatas sesuaikan dbname, user, password sesuai dengan yang anda gunakan.

if ($API->connect('ip-mikrotik', 'user-mikrotik', 'password-mikrotik')) untuk kode diatas terdapat didalam file tabel.php lakukan hal sama yaitu sesuaikan dengan mikrotik anda.

 if (isset($_POST['request_otp'])) {
            $otp = rand(100000, 999999);
            $url = '<url-untuk-mengirim-otp>';
            $data = [
                'app_token_id' => '<API-key-anda>',
                'service' => 'whatsapp',
                'penerima' => $no_hp_international,
                'konten' => $otp
            ];
dan untuk kode diatas pada bagian $url sesuaikan dengan url untuk mengirim otp, dan pada app_token_id sesuaikan dengan API key whatsapp anda

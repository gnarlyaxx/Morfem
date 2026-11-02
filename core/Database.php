<?php
class Database
{
    private static ?mysqli $connectionInstance = null;
    private static string $dbHost = 'localhost';
    private static string $dbUser = 'root';
    private static string $dbPassword = '';
    private static string $dbName = 'morfem';

    public static function getConnection(): mysqli
    {
        if (self::$connectionInstance === null) {
            mysqli_report(MYSQLI_REPORT_OFF);

            $host = getenv('DB_HOST') ?: self::$dbHost;
            $user = getenv('DB_USER') ?: self::$dbUser;
            $pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : self::$dbPassword;
            $name = getenv('DB_NAME') ?: self::$dbName;

            $activeConnection = @mysqli_connect($host, $user, $pass, $name);

            if (!$activeConnection) {
                die('<div style="font-family:monospace;padding:16px;background:#fee2e2;color:#991b1b;border:1px solid #f87171;margin:20px auto;max-width:600px;">
                    <strong>Database Connection Error:</strong> Pastikan MySQL aktif dan database <code>' . htmlspecialchars($name) . '</code> sudah di-import.<br>
                    Detail: ' . htmlspecialchars(mysqli_connect_error()) . '<br><br>
                    <small>Catatan untuk Hosting: Sesuaikan kredensial di <code>core/Database.php</code> atau atur Environment Variables (DB_HOST, DB_USER, DB_PASS, DB_NAME).</small>
                </div>');
            }

            mysqli_set_charset($activeConnection, 'utf8mb4');
            self::$connectionInstance = $activeConnection;
        }

        return self::$connectionInstance;
    }
}
